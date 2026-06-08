<?php

namespace app\jobs;

use app\models\SyncLog;
use app\models\SyncPair;
use app\services\SyncEngine;
use yii\base\BaseObject;
use yii\queue\JobInterface;
use yii\queue\RetryableJobInterface;

/**
 * SyncJob — queue payload
 *
 * Pushed onto the queue by SyncController or the cron command.
 * Picked up by the queue worker and executed asynchronously.
 *
 * Implements RetryableJobInterface so yii2-queue handles TTR
 * and auto-retry on timeout automatically.
 */
class SyncJob extends BaseObject implements JobInterface, RetryableJobInterface
{
    /** @var int Sync pair ID to process */
    public int $pairId;

    /** @var int Which attempt number this is (1-based) */
    public int $attempt = 1;

    // ----------------------------------------------------------------
    // RetryableJobInterface
    // ----------------------------------------------------------------

    /**
     * Time-to-reserve: if the job takes longer than this, the queue
     * considers it timed out and re-queues it.
     */
    public function getTtr(): int
    {
        return 5 * 60; // 5 minutes max per sync run
    }

    /**
     * Whether to retry after failure.
     * We handle retry logic ourselves inside execute() for fine control,
     * so we return false here to avoid double-retrying.
     */
    public function canRetry($attempt, $error): bool
    {
        return false;
    }

    // ----------------------------------------------------------------
    // JobInterface
    // ----------------------------------------------------------------

    public function execute($queue): void
    {
        $pair = SyncPair::find()
            ->with(['localConn', 'cloudConn'])
            ->where(['id' => $this->pairId])
            ->one();

        if (!$pair) {
            SyncLog::write(null, 'error', "Queue job failed — pair #{$this->pairId} not found.");
            return;
        }

        if ($pair->status === 'paused') {
            SyncLog::write($pair->id, 'info', "Pair paused — skipping queued job.");
            return;
        }

        $engine = new SyncEngine($pair);
        $job    = $engine->run($this->attempt);

        // If failed and under retry limit, push a delayed retry job
        if ($job->status === 'failed' && $pair->retry_count < \app\models\SyncJob::MAX_ATTEMPTS) {
            $backoffMinutes = $this->calculateBackoff($pair->retry_count);

            SyncLog::write($pair->id, 'warning',
                "Scheduling retry in {$backoffMinutes} min (attempt {$pair->retry_count}/" .
                \app\models\SyncJob::MAX_ATTEMPTS . ")");

            \Yii::$app->queue->delay($backoffMinutes * 60)->push(new self([
                'pairId'  => $this->pairId,
                'attempt' => $this->attempt + 1,
            ]));

            // Record the backoff duration
            $pair->last_backoff_minutes = $backoffMinutes;
            $pair->save(false);
        }
    }

    // ----------------------------------------------------------------
    // Exponential backoff: 2 → 10 → 30 minutes
    // ----------------------------------------------------------------
    private function calculateBackoff(int $failureCount): int
    {
        switch ($failureCount) {
            case 1:
                return 2;
            case 2:
                return 10;
            default:
                return 30;
        }
    }
}