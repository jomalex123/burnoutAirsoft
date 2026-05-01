<?php

return [
    'default' => 'pre',
    'connections' => [
        'local' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'bbdd',
            'username' => 'boutAdmin',
            'password' => 'REMOVED_SECRET',
            'charset' => 'utf8mb4',
        ],
        'pre' => [
            'host' => 'PMYSQL201.dns-servicio.com',
            'port' => 3306,
            'database' => 'bbddPRE',
            'username' => 'boutAdminPRE',
            'password' => 'REMOVED_SECRET',
            'charset' => 'utf8mb4',
        ],
        'pro' => [
            'host' => 'PMYSQL201.dns-servicio.com',
            'port' => 3306,
            'database' => 'bbdd',
            'username' => 'boutAdmin',
            'password' => 'REMOVED_SECRET',
            'charset' => 'utf8mb4',
        ],
    ],
];
