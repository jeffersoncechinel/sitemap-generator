<?php

$params = array_merge(
    require(__DIR__ . '/params.default.php'),
    require(__DIR__ . '/params.php')
);
$db = array_merge(
    require(__DIR__ . '/db.default.php'),
    require(__DIR__ . '/db.php')
);

return [
    'id' => 'sitemap-generator',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'timezone' => 'America/New_York',
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
    ],
    'params' => $params,
];
