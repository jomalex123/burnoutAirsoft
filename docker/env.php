<?php

declare(strict_types=1);

return [
    'default' => 'local',

    'connections' => [
        'local' => [
            'host' => 'db',
            'port' => 3306,
            'database' => '11364681_burnoutairsoft',
            'username' => 'boutAdmin',
            'password' => 'Asdqwe123',
            'charset' => 'utf8mb4',
        ],
    ],

    'mail' => [
        'local' => [
            'enabled' => false,
        ],
    ],
];
