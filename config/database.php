<?php

declare(strict_types=1);

function burnout_database_config(): array
{
    $configFile = __DIR__ . '/env.php';

    if (!is_file($configFile)) {
        $configFile = __DIR__ . '/env.example.php';
    }

    $config = require $configFile;
    $environment = getenv('BURNOUT_ENV') ?: ($config['default'] ?? 'local');

    if (!isset($config['connections'][$environment])) {
        throw new RuntimeException(sprintf('No existe configuracion de base de datos para el entorno "%s".', $environment));
    }

    return $config['connections'][$environment] + [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
    ];
}

function burnout_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = burnout_database_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        (int) $config['port'],
        $config['database'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
