<?php

namespace app\controllers;

use Yii;
use app\models\SyncLog;
use app\models\SyncPair;
use app\services\QueueManager;
use yii\web\Controller;
use yii\filters\VerbFilter;

/**
 * SyncController — REST API endpoints
 *
 * Phase 3 change: /sync/start now pushes to queue (async)
 * instead of running sync inline (blocking).
 *
 * POST /sync/start           — queue all due pairs
 * POST /sync/start?id=X      — queue a specific pair
 * GET  /sync/status          — pair statuses + queue depth
 * GET  /sync/status?id=X     — single pair status
 * GET  /sync/logs            — recent logs
 * GET  /sync/logs?id=X       — logs for one pair
 * GET  /sync/table/{name}    — logs filtered by table
 */
class SyncController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => ['start' => ['POST']],
            ],
            'contentNegotiator' => [
                'class'   => \yii\filters\ContentNegotiator::class,
                'formats' => ['application/json' => \yii\web\Response::FORMAT_JSON],
            ],
        ];
    }

    // ----------------------------------------------------------------
    // POST /sync/start  — push to queue (returns immediately)
    // ----------------------------------------------------------------
    public function actionStart(?int $id = null): array
    {
        if ($id) {
            $pair = SyncPair::find()
                ->with(['localConn', 'cloudConn'])
                ->where(['id' => $id])
                ->one();

            if (!$pair) {
                Yii::$app->response->statusCode = 404;
                return ['success' => false, 'error' => "Pair #{$id} not found."];
            }

            if ($pair->status === 'paused') {
                return ['success' => false, 'error' => "Pair is paused. Resume it first."];
            }

            if (QueueManager::hasPendingJob($pair->id)) {
                return [
                    'success'  => false,
                    'error'    => "Pair already has a pending job in the queue.",
                    'queued'   => 0,
                ];
            }

            $jobId = QueueManager::pushPair($pair);

            return [
                'success'   => true,
                'message'   => "Job queued for {$pair->localConn->label} → {$pair->cloudConn->label}",
                'job_id'    => $jobId,
                'queued'    => 1,
                'async'     => true,
            ];
        }

        // Push all due pairs
        $count = QueueManager::pushAllDue();

        return [
            'success'     => true,
            'queued'      => $count,
            'queue_depth' => QueueManager::getQueueDepth(),
            'message'     => $count > 0
                ? "{$count} pair(s) queued for sync."
                : "No pairs due. All up to date.",
            'async'       => true,
        ];
    }

    // ----------------------------------------------------------------
    // GET /sync/status
    // ----------------------------------------------------------------
    public function actionStatus(?int $id = null): array
    {
        $query = SyncPair::find()->with(['localConn', 'cloudConn']);
        if ($id) $query->where(['id' => $id]);
        $pairs = $query->all();

        $data = array_map(function (SyncPair $pair) {
            $lastJob = \app\models\SyncJob::find()
                ->where(['pair_id' => $pair->id])
                ->orderBy(['created_at' => SORT_DESC])
                ->one();

            return [
                'id'                  => $pair->id,
                'label'               => $pair->label,
                'local'               => $pair->localConn->label,
                'cloud'               => $pair->cloudConn->label,
                'status'              => $pair->status,
                'last_synced_at'      => $pair->last_synced_at,
                'next_sync_at'        => $pair->next_sync_at,
                'retry_count'         => $pair->retry_count,
                'last_backoff_minutes'=> $pair->last_backoff_minutes ?? 0,
                'last_error'          => $pair->last_error,
                'has_pending_job'     => QueueManager::hasPendingJob($pair->id),
                'last_job'            => $lastJob ? [
                    'id'              => $lastJob->id,
                    'status'          => $lastJob->status,
                    'attempt'         => $lastJob->attempt,
                    'records_pushed'  => $lastJob->records_pushed,
                    'records_deleted' => $lastJob->records_deleted,
                    'conflicts'       => $lastJob->conflicts,
                    'started_at'      => $lastJob->started_at,
                    'completed_at'    => $lastJob->completed_at,
                ] : null,
            ];
        }, $pairs);

        return [
            'pairs'       => $id ? ($data[0] ?? []) : $data,
            'total'       => count($data),
            'queue_depth' => QueueManager::getQueueDepth(),
            'running'     => QueueManager::getRunningCount(),
            'failed_jobs' => QueueManager::getFailedCount(),
        ];
    }

    // ----------------------------------------------------------------
    // GET /sync/logs
    // ----------------------------------------------------------------
    public function actionLogs(?int $id = null, int $limit = 50, string $level = null): array
    {
        $query = SyncLog::find()
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(min($limit, 200));

        if ($id)    $query->andWhere(['pair_id'   => $id]);
        if ($level) $query->andWhere(['level'     => $level]);

        $logs = $query->all();

        return [
            'logs' => array_map(fn(SyncLog $log) => [
                'id'               => $log->id,
                'pair_id'          => $log->pair_id,
                'level'            => $log->level,
                'message'          => $log->message,
                'table_name'       => $log->table_name,
                'records_affected' => $log->records_affected,
                'duration_ms'      => $log->duration_ms,
                'created_at'       => $log->created_at,
            ], $logs),
            'count' => count($logs),
        ];
    }

    // ----------------------------------------------------------------
    // GET /sync/table/{name}
    // ----------------------------------------------------------------
    public function actionTable(string $name, int $limit = 50): array
    {
        $logs = SyncLog::find()
            ->where(['table_name' => $name])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(min($limit, 200))
            ->all();

        return [
            'table' => $name,
            'logs'  => array_map(fn(SyncLog $log) => [
                'id'               => $log->id,
                'pair_id'          => $log->pair_id,
                'level'            => $log->level,
                'message'          => $log->message,
                'records_affected' => $log->records_affected,
                'duration_ms'      => $log->duration_ms,
                'created_at'       => $log->created_at,
            ], $logs),
            'count' => count($logs),
        ];
    }
}