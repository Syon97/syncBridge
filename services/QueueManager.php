<?php

namespace app\services;

use app\jobs\SyncJob;
use app\models\SyncLog;
use app\models\SyncPair;
use Yii;
use yii\db\Expression;

/**
 * QueueManager
 *
 * Central service for pushing sync jobs and querying queue state.
 * All queue interactions go through here so the rest of the app
 * never touches Yii::$app->queue directly.
 */
class QueueManager
{
    // ----------------------------------------------------------------
    // Push jobs
    // ----------------------------------------------------------------

    /**
     * Push a single pair onto the queue immediately.
     */
    public static function pushPair(SyncPair $pair, int $attempt = 1): int
    {
        $jobId = Yii::$app->queue->push(new SyncJob([
            'pairId'  => $pair->id,
            'attempt' => $attempt,
        ]));

        // Update our meta columns on the queue_jobs row just inserted
        Yii::$app->db->createCommand()
            ->update('queue_jobs', [
                'pair_id' => $pair->id,
                'status'  => 'waiting',
            ], ['id' => $jobId])
            ->execute();

        SyncLog::write($pair->id, 'info',
            "Job #{$jobId} queued — {$pair->localConn->label} → {$pair->cloudConn->label}");

        return $jobId;
    }

    /**
     * Push all active pairs that are due.
     * Returns number of jobs pushed.
     */
    public static function pushAllDue(): int
    {
        $now = date('Y-m-d H:i:s');

        $pairs = SyncPair::find()
            ->with(['localConn', 'cloudConn'])
            ->where(['status' => 'active'])
            ->andWhere([
                'OR',
                ['next_sync_at' => null],
                ['<=', 'next_sync_at', $now],
            ])
            ->all();

        // Error pairs eligible for retry
        $retryPairs = SyncPair::find()
            ->with(['localConn', 'cloudConn'])
            ->where(['status' => 'error'])
            ->andWhere(['<', 'retry_count', \app\models\SyncJob::MAX_ATTEMPTS])
            ->andWhere([
                'OR',
                ['next_sync_at' => null],
                ['<=', 'next_sync_at', $now],
            ])
            ->all();

        $allPairs = array_merge($pairs, $retryPairs);
        $count    = 0;

        foreach ($allPairs as $pair) {
            // Skip if already has a waiting/reserved job
            if (self::hasPendingJob($pair->id)) continue;

            self::pushPair($pair, $pair->retry_count + 1);
            $count++;
        }

        return $count;
    }

    /**
     * Push a delayed retry for a failed pair.
     */
    public static function pushRetry(SyncPair $pair, int $delaySeconds): void
    {
        $jobId = Yii::$app->queue->delay($delaySeconds)->push(new SyncJob([
            'pairId'  => $pair->id,
            'attempt' => $pair->retry_count + 1,
        ]));

        Yii::$app->db->createCommand()
            ->update('queue_jobs', [
                'pair_id' => $pair->id,
                'status'  => 'waiting',
            ], ['id' => $jobId])
            ->execute();
    }

    // ----------------------------------------------------------------
    // Queue inspection
    // ----------------------------------------------------------------

    public static function hasPendingJob(int $pairId): bool
    {
        return (int) Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM queue_jobs
             WHERE pair_id = :pid AND status IN ('waiting','reserved') AND done_at IS NULL",
            [':pid' => $pairId]
        )->queryScalar() > 0;
    }

    public static function getQueueDepth(): int
    {
        return (int) Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM queue_jobs WHERE status = 'waiting' AND done_at IS NULL"
        )->queryScalar();
    }

    public static function getRunningCount(): int
    {
        return (int) Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM queue_jobs WHERE status = 'reserved' AND done_at IS NULL"
        )->queryScalar();
    }

    public static function getFailedCount(): int
    {
        return (int) Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM queue_jobs WHERE status = 'failed'"
        )->queryScalar();
    }

    /**
     * Recent queue_jobs rows with optional filters
     */
    public static function getRecentJobs(int $limit = 50, string $status = null, int $pairId = null): array
    {
        $where  = [];
        $params = [];

        if ($status) {
            $where[]  = 'status = :status';
            $params[':status'] = $status;
        }
        if ($pairId) {
            $where[]  = 'pair_id = :pid';
            $params[':pid'] = $pairId;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return Yii::$app->db->createCommand(
            "SELECT q.*, sp.label AS pair_label,
                    lc.label AS local_label, cc.label AS cloud_label,
                    FROM_UNIXTIME(q.pushed_at)   AS pushed_at_dt,
                    FROM_UNIXTIME(q.reserved_at) AS reserved_at_dt,
                    FROM_UNIXTIME(q.done_at)     AS done_at_dt
             FROM queue_jobs q
             LEFT JOIN sync_pairs sp   ON sp.id = q.pair_id
             LEFT JOIN db_connections lc ON lc.id = sp.local_conn_id
             LEFT JOIN db_connections cc ON cc.id = sp.cloud_conn_id
             {$whereClause}
             ORDER BY q.id DESC
             LIMIT {$limit}",
            $params
        )->queryAll();
    }

    /**
     * Purge completed jobs older than N days.
     */
    public static function purgeOldJobs(int $days = 7): int
    {
        $cutoff = time() - ($days * 86400);
        return Yii::$app->db->createCommand(
            "DELETE FROM queue_jobs WHERE status = 'done' AND done_at < :cutoff",
            [':cutoff' => $cutoff]
        )->execute();
    }
}