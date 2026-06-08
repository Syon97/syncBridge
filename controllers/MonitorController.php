<?php

namespace app\controllers;

use app\models\SyncLog;
use app\models\SyncPair;
use app\services\QueueManager;
use Yii;
use yii\web\Controller;
use yii\web\Response;

class MonitorController extends Controller
{

    public function behaviors(): array 
    {
        return [
            'access' => [ // Require login for all monitor actions
                'class' => \yii\filters\AccessControl::class,
                'rules' => [[
                    'allow' => true,
                    'roles' => ['@'],
                ]],
            ],
        ];
    }

    // ----------------------------------------------------------------
    // GET /monitor — full monitoring dashboard
    // ----------------------------------------------------------------
    public function actionIndex()
    {
        $pairs      = SyncPair::find()->with(['localConn', 'cloudConn'])->all();
        $queueJobs  = QueueManager::getRecentJobs(60);
        $queueDepth = QueueManager::getQueueDepth();
        $running    = QueueManager::getRunningCount();
        $failed     = QueueManager::getFailedCount();

        // Per-pair stats for the last 24 hours
        $pairStats = [];
        foreach ($pairs as $pair) {
            $stats = Yii::$app->db->createCommand(
                "SELECT
                    SUM(records_affected) AS total_records,
                    COUNT(*)              AS total_ops,
                    SUM(CASE WHEN level = 'error'   THEN 1 ELSE 0 END) AS errors,
                    SUM(CASE WHEN level = 'warning' THEN 1 ELSE 0 END) AS warnings,
                    AVG(duration_ms)      AS avg_duration_ms
                 FROM sync_logs
                 WHERE pair_id = :pid
                   AND created_at >= :since",
                [':pid' => $pair->id, ':since' => date('Y-m-d H:i:s', strtotime('-24 hours'))]
            )->queryOne();

            $pairStats[$pair->id] = $stats;
        }

        // Hourly sync activity for the sparkline chart (last 24 hours)
        $hourlyActivity = Yii::$app->db->createCommand(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS hour,
                SUM(records_affected)                         AS records,
                COUNT(*)                                      AS ops
             FROM sync_logs
             WHERE level = 'success'
               AND created_at >= :since
             GROUP BY hour
             ORDER BY hour ASC",
            [':since' => date('Y-m-d H:i:s', strtotime('-24 hours'))]
        )->queryAll();

        return $this->render('index', compact(
            'pairs', 'queueJobs', 'queueDepth', 'running', 'failed',
            'pairStats', 'hourlyActivity'
        ));
    }

    // ----------------------------------------------------------------
    // GET /monitor/poll  — AJAX endpoint for live refresh
    // ----------------------------------------------------------------
    public function actionPoll(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Queue stats
        $queueStats = [
            'depth'   => QueueManager::getQueueDepth(),
            'running' => QueueManager::getRunningCount(),
            'failed'  => QueueManager::getFailedCount(),
        ];

        // Pair statuses
        $pairs = SyncPair::find()->with(['localConn', 'cloudConn'])->all();
        $pairData = array_map(fn(SyncPair $p) => [
            'id'             => $p->id,
            'status'         => $p->status,
            'last_synced_at' => $p->last_synced_at,
            'next_sync_at'   => $p->next_sync_at,
            'retry_count'    => $p->retry_count,
            'has_pending'    => QueueManager::hasPendingJob($p->id),
        ], $pairs);

        // Last 10 logs
        $logs = SyncLog::find()
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(10)
            ->all();

        $logData = array_map(fn(SyncLog $l) => [
            'id'         => $l->id,
            'pair_id'    => $l->pair_id,
            'level'      => $l->level,
            'message'    => $l->message,
            'created_at' => $l->created_at,
        ], $logs);

        return compact('queueStats', 'pairData', 'logData');
    }

    // ----------------------------------------------------------------
    // POST /monitor/retry-job?id=X  — re-queue a failed job's pair
    // ----------------------------------------------------------------
    public function actionRetryJob(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $job = Yii::$app->db->createCommand(
            "SELECT * FROM queue_jobs WHERE id = :id", [':id' => $id]
        )->queryOne();

        if (!$job || !$job['pair_id']) {
            Yii::$app->response->statusCode = 404;
            return ['success' => false, 'error' => 'Job not found.'];
        }

        $pair = SyncPair::find()
            ->with(['localConn', 'cloudConn'])
            ->where(['id' => $job['pair_id']])
            ->one();

        if (!$pair) {
            return ['success' => false, 'error' => 'Sync pair no longer exists.'];
        }

        // Reset retry counter and re-queue
        $pair->retry_count  = 0;
        $pair->status       = 'active';
        $pair->last_error   = null;
        $pair->save(false);

        $newJobId = QueueManager::pushPair($pair);

        SyncLog::write($pair->id, 'info', "Manual retry queued from monitor (job #{$newJobId})");

        return ['success' => true, 'new_job_id' => $newJobId];
    }

    // ----------------------------------------------------------------
    // GET /monitor/logs-export  — download logs as CSV
    // ----------------------------------------------------------------
    public function actionLogsExport(?int $pairId = null): Response
    {
        $where  = $pairId ? ['pair_id' => $pairId] : [];
        $logs   = SyncLog::find()
            ->where($where)
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(5000)
            ->all();

        $csv  = "id,pair_id,level,message,table_name,records_affected,duration_ms,created_at\n";
        foreach ($logs as $log) {
            $csv .= implode(',', [
                $log->id,
                $log->pair_id ?? '',
                $log->level,
                '"' . str_replace('"', '""', $log->message) . '"',
                $log->table_name ?? '',
                $log->records_affected,
                $log->duration_ms ?? '',
                $log->created_at,
            ]) . "\n";
        }

        return Yii::$app->response->sendContentAsFile(
            $csv,
            'syncbridge_logs_' . date('Ymd_His') . '.csv',
            ['mimeType' => 'text/csv']
        );
    }

    // ----------------------------------------------------------------
    // POST /monitor/purge-queue  — clear old completed jobs
    // ----------------------------------------------------------------
    public function actionPurgeQueue(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $deleted = QueueManager::purgeOldJobs(7);
        SyncLog::write(null, 'info', "Queue purged — {$deleted} old completed jobs removed.");
        return ['success' => true, 'deleted' => $deleted];
    }
}