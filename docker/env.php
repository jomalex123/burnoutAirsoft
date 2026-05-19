<?php

declare(strict_types=1);

return [
    'default' => 'local',

    'connections' => [
        'local' => [
            'host' => 'db',
            'port' => 3306,
            'database' => 'burnoutairsoft_local',
            'username' => 'burnout',
            'password' => 'burnout',
            'charset' => 'utf8mb4',
        ],
    ],

    'mail' => [
        'local' => [
            'enabled' => false,
        ],
    ],
];
