<?php

declare(strict_types=1);

return [
    'default' => 'local',

    'connections' => [
        'local' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'burnoutairsoft_local',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
        'pre' => [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'database_pre',
            'username' => 'user_pre',
            'password' => 'change_me',
            'charset' => 'utf8mb4',
        ],
        'pro' => [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'database_pro',
            'username' => 'user_pro',
            'password' => 'change_me',
            'charset' => 'utf8mb4',
        ],
    ],

    'mail' => [
        'local' => [
            'enabled' => false,
        ],
        'pre' => [
            'enabled' => true,
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp_pre@example.com',
            'password' => 'change_me',
            'from_email' => 'no-reply@burnoutairsoft.com',
            'from_name' => 'Burnout Airsoft',
            'reply_to' => null,
        ],
        'pro' => [
            'enabled' => true,
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp_pro@example.com',
            'password' => 'change_me',
            'from_email' => 'no-reply@burnoutairsoft.com',
            'from_name' => 'Burnout Airsoft',
            'reply_to' => null,
        ],
    ],
];
