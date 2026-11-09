<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/registration_documents.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $config = burnout_database_config();
    $environment = getenv('BURNOUT_ENV') ?: (burnout_env_config()['default'] ?? 'local');
    if ($environment !== 'local' || !in_array($config['host'], ['127.0.0.1', 'localhost'], true) || (int) $config['port'] !== 3306) {
        throw new RuntimeException('Esta herramienta solo admite MySQL local de XAMPP.');
    }
    $pdo = burnout_pdo();
    $rows = $pdo->query('SELECT a.document, r.event_id FROM registration_attendees a INNER JOIN registrations r ON r.id = a.registration_id')->fetchAll();
    $seen = [];
    $duplicates = 0;
    foreach ($rows as $row) {
        $key = $row['event_id'] . ':' . burnout_normalize_document($row['document']);
        if (isset($seen[$key])) {
            $duplicates++;
        }
        $seen[$key] = true;
    }
    echo 'Asistentes revisados: ' . count($rows) . '; duplicados: ' . $duplicates . PHP_EOL;
    if ($duplicates > 0) {
        throw new RuntimeException('Resolver manualmente los duplicados antes de instalar la restricción. No se ha eliminado ningún registro.');
    }
    $indexes = $pdo->query('SHOW INDEX FROM registration_attendees')->fetchAll();
    foreach ($indexes as $index) {
        if ($index['Key_name'] === 'registration_attendees_event_document_unique') {
            echo "Restricción instalada.\n";
            exit(0);
        }
    }
    if (!in_array('--apply', $argv, true)) {
        echo "Migración pendiente. --apply crea copia e instala únicamente en local.\n";
        exit(0);
    }
    $backup = [];
    foreach (['events', 'registrations', 'registration_attendees'] as $table) {
        $backup[$table] = [
            'schema' => $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1],
            'rows' => $pdo->query('SELECT * FROM ' . $table)->fetchAll(),
        ];
    }
    $path = tempnam(sys_get_temp_dir(), 'burnout-registration-backup-');
    if ($path === false) {
        throw new RuntimeException('No se pudo crear la copia previa.');
    }
    $json = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json) !== strlen($json) || file_get_contents($path) !== $json) {
        throw new RuntimeException('No se pudo verificar la copia previa.');
    }
    echo 'Copia privada verificada: ' . $path . PHP_EOL;
    $sql = file_get_contents(__DIR__ . '/../database/migrations/2026_11_09_registration_document_unique.sql');
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer la migración.');
    }
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
    $registrationIndexes = $pdo->query('SHOW INDEX FROM registrations')->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('registrations_id_event_unique', $registrationIndexes, true)) {
        $pdo->exec($statements[0]);
    }
    $columns = $pdo->query('SHOW COLUMNS FROM registration_attendees')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('event_id', $columns, true)) {
        $pdo->exec($statements[1]);
    }
    $pdo->exec($statements[2]);
    $pdo->exec($statements[3]);
    echo "Migración instalada.\n";
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    fwrite(STDERR, "No se pudo completar la inspección o migración. Consulta el diagnóstico local; no se han borrado asistentes.\n");
    exit(1);
}
