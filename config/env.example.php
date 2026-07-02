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

    'instagram' => [
        'enabled' => false,
        'graph_version' => 'v20.0',
        'user_id' => '',
        'access_token' => '',
        'app_id' => '',
        'app_secret' => '',
        'limit' => 12,
        'sync_limit' => 0,
        'sync_max_pages' => 50,
        'cache_keep_items' => 0,
        'max_image_bytes' => 2000000,
        'profile_url' => 'https://www.instagram.com/burnoutairsoft/',
        'ca_file' => '',
        'ssl_verify' => true,
        'exchange_enabled' => false,
        'exchange_url' => 'https://graph.facebook.com/{graph_version}/oauth/access_token',
        'exchange_token_param' => 'fb_exchange_token',
        'exchange_params' => [
            'grant_type' => 'fb_exchange_token',
        ],
        'refresh_enabled' => false,
        'refresh_url' => 'https://graph.instagram.com/refresh_access_token',
        'refresh_token_param' => 'access_token',
        'refresh_params' => [
            'grant_type' => 'ig_refresh_token',
        ],
        'refresh_interval_days' => 30,
    ],
];
