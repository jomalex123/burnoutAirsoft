<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    echo 'Archivo seleccionado: ' . burnout_env_file() . PHP_EOL;
    $config = burnout_database_config();
    echo 'Configuración: ' . burnout_env_file() . PHP_EOL;
    echo 'Entorno: ' . (getenv('BURNOUT_ENV') ?: (burnout_env_config()['default'] ?? 'local')) . PHP_EOL;
    echo 'Servidor: ' . $config['host'] . ':' . $config['port'] . PHP_EOL;
    echo 'Base de datos: ' . $config['database'] . PHP_EOL;
    $pdo = burnout_pdo();
    $pdo->query('SELECT 1');
    echo 'Conexión correcta.' . PHP_EOL;
    $server = $pdo->query('SELECT @@port AS port, @@datadir AS data_directory')->fetch();
    echo 'Puerto MySQL real: ' . $server['port'] . PHP_EOL;
    echo 'Directorio de datos MySQL: ' . $server['data_directory'] . PHP_EOL;
    foreach (['events', 'registrations', 'admin_users'] as $table) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $statement->execute(['table' => $table]);
        echo $table . ': ' . ($statement->fetchColumn() ? 'existe' : 'falta') . PHP_EOL;
    }
} catch (Throwable $exception) {
    echo 'Tipo de fallo: ' . get_class($exception) . PHP_EOL;
    if (($exception instanceof RuntimeException && !$exception instanceof PDOException) || $exception instanceof Error) {
        echo $exception->getMessage() . PHP_EOL;
    }
    $code = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
    echo 'Fallo de conexión. Código del controlador: ' . $code . PHP_EOL;
    echo match ($code) {
        1045 => 'El servidor ha rechazado las credenciales del usuario.',
        1049 => 'La base de datos configurada no existe en este servidor.',
        2002, 2003 => 'No se ha podido conectar al servidor configurado.',
        default => 'Revisar la configuración y las extensiones PHP.',
    } . PHP_EOL;
    exit(1);
}
