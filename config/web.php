<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'defaultRoute' => 'dashboard/index',           // ← ADD: landing page
    'name' => 'SyncBridge',                        // ← ADD: app name
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => 'Ap0p2BsDrvp7n-yaledVl2P2YDfnM2QB',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [                          // ← ADD: uncommented + routes added
            'class' => 'yii\web\UrlManager',
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                ''                                  => 'dashboard/index',
                'dashboard'                         => 'dashboard/index',
                'connection'                        => 'connection/index',
                'connection/create'                 => 'connection/create',
                'connection/update/<id:\d+>'        => 'connection/update',
                'connection/delete/<id:\d+>'        => 'connection/delete',
                'connection/test/<id:\d+>'          => 'connection/test',
                'sync-pair'                         => 'sync-pair/index',
                'sync-pair/create'                  => 'sync-pair/create',
                'sync-pair/update/<id:\d+>'         => 'sync-pair/update',
                'sync-pair/delete/<id:\d+>'         => 'sync-pair/delete',
                'sync-pair/toggle/<id:\d+>'         => 'sync-pair/toggle',

                // Sync API endpoints
                'sync/start'              => 'sync/start',
                'sync/start/<id:\d+>'     => 'sync/start',
                'sync/status'             => 'sync/status',
                'sync/status/<id:\d+>'    => 'sync/status',
                'sync/logs'               => 'sync/logs',
                'sync/logs/<id:\d+>'      => 'sync/logs',
                'sync/table/<name:\w+>'   => 'sync/table',

                // Monitoring & maintenance
                'monitor'                          => 'monitor/index',
                'monitor/poll'                     => 'monitor/poll',
                'monitor/retry-job'                => 'monitor/retry-job',
                'monitor/logs-export'              => 'monitor/logs-export',
                'monitor/purge-queue'              => 'monitor/purge-queue',

                // User management
                'user'                           => 'user/index',
                'user/create'                    => 'user/create',
                'user/update/<id:\d+>'           => 'user/update',
                'user/delete/<id:\d+>'           => 'user/delete',
                'user/toggle-status/<id:\d+>'    => 'user/toggle-status',
                'user/generate-token/<id:\d+>'   => 'user/generate-token',
                'user/revoke-token'              => 'user/revoke-token',
            ],
        ],
        'queue' => [
            'class'   => \yii\queue\db\Queue::class,
            'db'      => 'db',                  // uses your existing DB connection
            'tableName' => 'queue_jobs',        // our custom table name
            'channel'   => 'sync',
            'mutex'     => \yii\mutex\MysqlMutex::class,
        ],
        'formatter' => [                           // ← ADD: timezone + locale
            'class' => 'yii\i18n\Formatter',
            'timeZone' => 'Asia/Kuala_Lumpur',
            'locale' => 'en-MY',
        ],
        'session' => [                             // ← ADD: explicit session (for flash messages)
            'class' => 'yii\web\Session',
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;