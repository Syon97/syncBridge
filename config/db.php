<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=syncbridge_db;charsert=utf8mb4',
    'username' => '',
    'password' => '',
    'charset' => 'utf8mb4',
    'on afterOpen' => function($event) {
        $event->sender->createCommand("SET time_zone = '+08:00'")->execute();
    },

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];

// return [
//     'class' => 'yii\db\Connection',
//     'dsn' => 'mysql:host=192.168.20.17;dbname=syncbridge_db;charsert=utf8mb4',
//     'username' => 'itadmin',
//     'password' => 'itadmin@2018', // or put your actual password if required
//     'charset' => 'utf8mb4',
//     'on afterOpen' => function($event) {
//         $event->sender->createCommand("SET time_zone = '+08:00'")->execute();
//     },

//     // Schema cache options (for production environment)
//     //'enableSchemaCache' => true,
//     //'schemaCacheDuration' => 60,
//     //'schemaCache' => 'cache',
// ];
