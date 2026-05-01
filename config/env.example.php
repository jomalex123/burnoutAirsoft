<?php

return [
    'default' => 'local',
    'connections' => [
        'local' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => '11364681_burnoutairsoft',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
        'pre' => [
            'host' => 'PMYSQL201.dns-servicio.com',
            'port' => 3306,
            'database' => '11364681_burnoutairsoftPRE',
            'username' => 'boutAdminPRE',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
        'pro' => [
            'host' => 'PMYSQL201.dns-servicio.com',
            'port' => 3306,
            'database' => '11364681_burnoutairsoft',
            'username' => 'boutAdmin',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
    ],
];
