<?php

declare(strict_types=1);

function burnout_env_file(): string
{
    $configuredFile = getenv('BURNOUT_ENV_FILE');
    $candidates = [];

    if (is_string($configuredFile) && trim($configuredFile) !== '') {
        $candidates[] = trim($configuredFile);
    }

    $projectRoot = dirname(__DIR__);
    $accountRoot = dirname($projectRoot);
    $projectDirectory = basename($projectRoot);

    if ($projectDirectory === 'httpdocs') {
        $candidates[] = $accountRoot . '/private/env.php';
    } elseif ($projectDirectory === 'burnoutairsoft.com') {
        $candidates[] = $accountRoot . '/private/burnoutairsoft/env.php';
    } else {
        $candidates[] = $accountRoot . '/private/burnoutairsoft/env.php';
        $candidates[] = $accountRoot . '/private/env.php';
    }

    $candidates[] = __DIR__ . '/env.php';
    $candidates[] = __DIR__ . '/env.example.php';

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('No se ha encontrado el fichero de configuracion env.php.');
}

function burnout_env_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $loadedConfig = require burnout_env_file();

    if (!is_array($loadedConfig)) {
        throw new RuntimeException('El fichero env.php debe devolver un array de configuracion.');
    }

    $config = $loadedConfig;

    return $config;
}
