<?php

$params = require __DIR__ . '/../../config/params.php';
$db     = require __DIR__ . '/../../config/db.php';

return [
    'id'                  => 'basic-console',
    'basePath'            => dirname(dirname(__DIR__)),
    'bootstrap'           => ['log'],
    'controllerNamespace' => 'app\commands',

    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],

    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [[
                'class'  => 'yii\log\FileTarget',
                'levels' => ['error', 'warning'],
            ]],
        ],
        'db' => $db,

        // ── Queue (same table as web app) ────────────────────
        'queue' => [
            'class'     => \yii\queue\db\Queue::class,
            'db'        => 'db',
            'tableName' => 'queue_jobs',
            'channel'   => 'sync',
            'mutex'     => \yii\mutex\MysqlMutex::class,
        ],
    ],

    // ── Register console commands ────────────────────────────
    'controllerMap' => [
        'sync' => 'app\commands\SyncCommand',
    ],

    'params' => $params,
];