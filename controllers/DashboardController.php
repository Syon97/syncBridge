<?php

namespace app\controllers;

use app\models\DbConnection;
use app\models\SyncPair;
use app\models\SyncLog;
use yii\web\Controller;

class DashboardController extends Controller
{
    public function actionIndex()
    {
        // Metric counts
        $totalConns    = DbConnection::find()->count();
        $reachable     = DbConnection::find()->where(['is_reachable' => 1])->count();
        $activePairs   = SyncPair::find()->where(['status' => 'active'])->count();
        $errorPairs    = SyncPair::find()->where(['status' => 'error'])->count();

        // Records synced today (success logs only)
        $today = date('Y-m-d');
        $recordsToday = SyncLog::find()
            ->where(['level' => 'success'])
            ->andWhere(['>=', 'created_at', $today . ' 00:00:00'])
            ->sum('records_affected') ?? 0;

        // Errors in last 24 hours
        $errors24h = SyncLog::find()
            ->where(['level' => 'error'])
            ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-24 hours'))])
            ->count();

        // All pairs with connections eager-loaded
        $pairs = SyncPair::find()
            ->with(['localConn', 'cloudConn'])
            ->orderBy(['status' => SORT_ASC, 'updated_at' => SORT_DESC])
            ->all();

        // All connections
        $connections = DbConnection::find()
            ->orderBy(['type' => SORT_ASC, 'label' => SORT_ASC])
            ->all();

        // Recent logs (last 30)
        $logs = SyncLog::find()
            ->with('pair')
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(30)
            ->all();

        return $this->render('index', compact(
            'totalConns', 'reachable', 'activePairs', 'errorPairs',
            'recordsToday', 'errors24h', 'pairs', 'connections', 'logs'
        ));
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [[
                    'allow' => true,
                    'roles' => ['@'],
                ]],
            ],
        ];
    }
}