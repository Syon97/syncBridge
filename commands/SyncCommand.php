<?php

namespace app\commands;

use app\services\QueueManager;
use app\models\SyncLog;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * SyncCommand — Yii2 console command (Phase 3: queue-based)
 *
 * Usage:
 *   php yii sync/run              — push all due pairs onto the queue
 *   php yii sync/run --pairId=3   — push a specific pair
 *   php yii sync/worker           — start the queue worker
 *   php yii sync/status           — print queue depth to console
 *   php yii sync/purge            — remove completed jobs older than 7 days
 */
class SyncCommand extends Controller
{
    /** @var int|null Target a specific pair */
    public ?int $pairId = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['pairId']);
    }

    // ----------------------------------------------------------------
    // sync/run  — push due pairs onto the queue
    // ----------------------------------------------------------------
    public function actionRun(): int
    {
        $this->stdout("[SyncBridge] Checking pairs due for sync...\n");

        if ($this->pairId) {
            $pair = \app\models\SyncPair::find()
                ->with(['localConn', 'cloudConn'])
                ->where(['id' => $this->pairId])
                ->one();

            if (!$pair) {
                $this->stderr("Pair #{$this->pairId} not found.\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            if (QueueManager::hasPendingJob($pair->id)) {
                $this->stdout("Pair #{$this->pairId} already has a pending job — skipping.\n");
                return ExitCode::OK;
            }

            $jobId = QueueManager::pushPair($pair);
            $this->stdout("Queued job #{$jobId} for pair #{$this->pairId}.\n");
            return ExitCode::OK;
        }

        $count = QueueManager::pushAllDue();

        if ($count === 0) {
            $this->stdout("[SyncBridge] No pairs due. Queue depth: " . QueueManager::getQueueDepth() . "\n");
        } else {
            $this->stdout("[SyncBridge] Pushed {$count} job(s). Queue depth: " . QueueManager::getQueueDepth() . "\n");
        }

        return ExitCode::OK;
    }

    // ----------------------------------------------------------------
    // sync/worker  — start the queue worker
    // ----------------------------------------------------------------
    public function actionWorker(): int
    {
        $this->stdout("[SyncBridge] Queue worker started. Waiting for jobs...\n");
        \Yii::$app->queue->run(true);
        return ExitCode::OK;
    }

    // ----------------------------------------------------------------
    // sync/status  — print queue state to console
    // ----------------------------------------------------------------
    public function actionStatus(): int
    {
        $depth   = QueueManager::getQueueDepth();
        $running = QueueManager::getRunningCount();
        $failed  = QueueManager::getFailedCount();

        $this->stdout("[SyncBridge Queue Status]\n");
        $this->stdout("  Waiting : {$depth}\n");
        $this->stdout("  Running : {$running}\n");
        $this->stdout("  Failed  : {$failed}\n");

        $pairs = \app\models\SyncPair::find()->with(['localConn', 'cloudConn'])->all();
        $this->stdout("\n[Sync Pairs]\n");
        foreach ($pairs as $pair) {
            $pending = QueueManager::hasPendingJob($pair->id) ? ' [queued]' : '';
            $this->stdout(sprintf(
                "  #%-3d %-12s  %s → %s%s\n",
                $pair->id,
                "({$pair->status})",
                $pair->localConn->label,
                $pair->cloudConn->label,
                $pending
            ));
        }

        return ExitCode::OK;
    }

    // ----------------------------------------------------------------
    // sync/purge  — remove old completed jobs
    // ----------------------------------------------------------------
    public function actionPurge(int $days = 7): int
    {
        $deleted = QueueManager::purgeOldJobs($days);
        $this->stdout("[SyncBridge] Purged {$deleted} completed job(s) older than {$days} days.\n");
        SyncLog::write(null, 'info', "Queue purged via CLI — {$deleted} jobs removed.");
        return ExitCode::OK;
    }
}