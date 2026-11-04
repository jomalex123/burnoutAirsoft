<?php
declare(strict_types=1);

// Uso CLI: sin argumentos inspecciona; --apply hace backup, migra y verifica PRO.
// --categories selecciona exclusivamente la migracion de categorias del 04/11/2026.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('BURNOUT_ENV_FILE=' . dirname(__DIR__) . '/config/env.php');
putenv('BURNOUT_ENV=pro');
require_once __DIR__ . '/../config/database.php';
if (in_array('--categories', $argv, true)) { require __DIR__ . '/migrate_hq_categories.php'; exit; }

function burnout_pro_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function burnout_pro_columns(PDO $pdo, string $table): array
{
    $query = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $query->execute([$table]);
    $columns = [];
    foreach ($query->fetchAll() as $column) $columns[$column['COLUMN_NAME']] = $column;
    return $columns;
}

function burnout_pro_backup_write($backup, string $text): void
{
    global $backupBytes;
    $written = gzwrite($backup, $text);
    if ($written !== strlen($text)) throw new RuntimeException('No se ha podido completar la copia de seguridad.');
    $backupBytes += $written;
}

function burnout_pro_table_snapshot(PDO $pdo, string $table, $backup = null): array
{
    $quoted = burnout_pro_identifier($table);
    $query = $pdo->query('SHOW CREATE TABLE ' . $quoted);
    $create = $query->fetch(PDO::FETCH_NUM)[1];
    $query->closeCursor();
    $query = $pdo->query('SHOW COLUMNS FROM ' . $quoted);
    $columns = $query->fetchAll();
    $query->closeCursor();
    $query = $pdo->query('SHOW INDEX FROM ' . $quoted);
    $indexes = $query->fetchAll();
    $query->closeCursor();
    $primary = [];
    foreach ($indexes as $index) {
        if ($index['Key_name'] === 'PRIMARY') $primary[(int) $index['Seq_in_index']] = $index['Column_name'];
    }
    ksort($primary);
    $order = $primary ? ' ORDER BY ' . implode(', ', array_map('burnout_pro_identifier', $primary)) : '';
    if (is_resource($backup)) burnout_pro_backup_write($backup, "\n-- Table " . $quoted . "\n" . $create . ";\n");
    $query = $pdo->query('SELECT * FROM ' . $quoted . $order);
    $hash = hash_init('sha256');
    $unordered = [];
    $rows = 0;
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $serialized = serialize($row);
        if ($primary) hash_update($hash, $serialized);
        else $unordered[] = hash('sha256', $serialized);
        $rows++;
        if (!is_resource($backup)) continue;
        $names = [];
        $values = [];
        foreach ($columns as $column) {
            if (preg_match('/(?:VIRTUAL|STORED|PERSISTENT) GENERATED/i', $column['Extra'] ?? '')) continue;
            $name = $column['Field'];
            $names[] = burnout_pro_identifier($name);
            $value = $row[$name];
            if ($value === null) $values[] = 'NULL';
            elseif (preg_match('/blob|binary|bit/i', $column['Type'])) $values[] = "X'" . bin2hex((string) $value) . "'";
            else $values[] = $pdo->quote((string) $value);
        }
        burnout_pro_backup_write($backup, 'INSERT INTO ' . $quoted . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $values) . ");\n");
    }
    $query->closeCursor();
    if (!$primary) {
        sort($unordered);
        foreach ($unordered as $digest) hash_update($hash, $digest);
    }
    // Los INSERT de prueba con rollback pueden avanzar el siguiente AUTO_INCREMENT.
    $schema = preg_replace('/\bAUTO_INCREMENT=\d+\b/', 'AUTO_INCREMENT=<counter>', $create);
    return ['schema' => hash('sha256', $schema), 'rows' => $rows, 'data' => hash_final($hash)];
}

function burnout_pro_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo 'OK: ' . $message . PHP_EOL;
}

$stage = 'configuracion';
$backupPath = null;
$backup = null;
$backupBytes = 0;
try {
    $config = burnout_database_config();
    $all = burnout_env_config();
    foreach (['local', 'pre'] as $otherEnvironment) {
        $other = $all['connections'][$otherEnvironment] ?? [];
        if ([$config['host'], (int) $config['port'], $config['database']] === [$other['host'] ?? '', (int) ($other['port'] ?? 3306), $other['database'] ?? '']) {
            throw new RuntimeException('PRO coincide con otro entorno; no se continuara.');
        }
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], (int) $config['port'], $config['database'], $config['charset']);
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
    echo "PRO: conexion verificada.\n";
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $initial = array_fill_keys($tables, true);
    burnout_pro_check(isset($initial['events']), 'Existe el calendario previo');
    $operation = burnout_pro_columns($pdo, 'hq_operations');
    $missions = burnout_pro_columns($pdo, 'hq_missions');
    $catalog = burnout_pro_columns($pdo, 'hq_mission_catalog');
    $plan = [
        '2026_10_30_add_hq.sql' => count(array_intersect(['hq_operations', 'hq_access', 'hq_missions', 'hq_messages'], $tables)) !== 4,
        '2026_10_30_link_hq_events.sql' => !isset($operation['event_id']),
        '2026_11_02_add_hq_mission_catalog.sql' => !isset($initial['hq_mission_catalog']) && !isset($missions['catalog_id']),
        '2026_11_03_hq_deleted_missions.sql' => !isset($missions['status']) || !str_contains($missions['status']['COLUMN_TYPE'], "'deleted'"),
        '2026_11_03_add_hq_maps.sql' => !isset($initial['hq_maps']),
        '2026_11_03_hq_mission_order.sql' => !isset($missions['sort_order']),
        '2026_11_03_hq_mission_classification.sql' => !isset($catalog['category']) && !isset($catalog['mission_type']),
    ];
    if (isset($initial['hq_mission_catalog']) !== isset($missions['catalog_id']) || isset($catalog['category']) !== isset($catalog['mission_type'])) {
        throw new RuntimeException('Se ha detectado una migracion parcial; revisar antes de continuar.');
    }
    foreach ($plan as $file => $pending) echo ($pending ? 'PENDIENTE: ' : 'INSTALADA: ') . $file . PHP_EOL;
    if (!in_array('--apply', $argv, true)) {
        echo "Inspeccion terminada. No se han realizado cambios.\n";
        exit;
    }

    $stage = 'copia de seguridad';
    $engines = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    if (array_filter($engines, static fn ($engine) => strcasecmp((string) $engine, 'InnoDB') !== 0)) throw new RuntimeException('Hay tablas no transaccionales; revisar la copia consistente antes de continuar.');
    if (!function_exists('gzopen')) throw new RuntimeException('No esta disponible gzip para la copia.');
    $backupPath = tempnam(sys_get_temp_dir(), 'burnout-pro-before-hq-');
    if ($backupPath === false) throw new RuntimeException('No se ha podido preparar la copia.');
    $namedBackup = $backupPath . '.sql.gz';
    if (!rename($backupPath, $namedBackup)) throw new RuntimeException('No se ha podido nombrar la copia.');
    $backupPath = $namedBackup;
    @chmod($backupPath, 0600);
    $backup = gzopen($backupPath, 'wb6');
    if ($backup === false) throw new RuntimeException('No se ha podido abrir la copia.');
    burnout_pro_backup_write($backup, "-- PRO antes de migraciones HQ. Restaurar en una base vacia.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    $baseline = [];
    foreach ($tables as $table) $baseline[$table] = burnout_pro_table_snapshot($pdo, $table, $backup);
    $pdo->commit();
    burnout_pro_backup_write($backup, "SET FOREIGN_KEY_CHECKS=1;\n");
    if (!gzclose($backup)) throw new RuntimeException('No se ha podido cerrar la copia.');
    $backup = null;
    if (filesize($backupPath) < 50) throw new RuntimeException('La copia esta vacia.');
    $verify = gzopen($backupPath, 'rb');
    if ($verify === false) throw new RuntimeException('No se ha podido verificar la copia.');
    $readBytes = 0;
    while (!gzeof($verify)) {
        $chunk = @gzread($verify, 1048576);
        if ($chunk === false) { gzclose($verify); throw new RuntimeException('La copia gzip no se puede leer.'); }
        $readBytes += strlen($chunk);
    }
    gzclose($verify);
    burnout_pro_check($readBytes === $backupBytes, 'Copia gzip leida y validada antes de aplicar migraciones');
    echo 'Copia SQL gzip de ' . count($tables) . ' tablas: ' . $backupPath . PHP_EOL;
    foreach ($plan as $file => $pending) {
        if (!$pending) continue;
        $stage = $file;
        $sql = file_get_contents(__DIR__ . '/../database/migrations/' . $file);
        if ($sql === false) throw new RuntimeException('No se ha podido leer la migracion.');
        $pdo->exec($sql);
        echo 'APLICADA: ' . $file . PHP_EOL;
    }

    $stage = 'verificacion del esquema';
    $required = [
        'hq_operations' => ['id', 'event_id', 'title', 'operation_date', 'status'],
        'hq_access' => ['operation_id', 'team', 'code_hash'],
        'hq_missions' => ['id', 'operation_id', 'team', 'title', 'briefing', 'status', 'catalog_id', 'is_imported', 'sort_order', 'acknowledged_at'],
        'hq_messages' => ['id', 'operation_id', 'team', 'sender', 'body'],
        'hq_mission_catalog' => ['id', 'title', 'briefing', 'content_hash', 'is_available', 'category', 'mission_type'],
        'hq_maps' => ['operation_id', 'annotations', 'updated_at'],
    ];
    foreach ($required as $table => $fields) {
        burnout_pro_check(!array_diff($fields, array_keys(burnout_pro_columns($pdo, $table))), 'Columnas completas: ' . $table);
    }
    $missions = burnout_pro_columns($pdo, 'hq_missions');
    burnout_pro_check(str_contains($missions['status']['COLUMN_TYPE'], "'deleted'") && $missions['status']['COLUMN_DEFAULT'] === 'draft', 'Estados de mision y valor Pendiente correctos');
    burnout_pro_check(str_contains(burnout_pro_columns($pdo, 'hq_maps')['annotations']['COLUMN_TYPE'], 'mediumblob'), 'Anotaciones almacenadas en MEDIUMBLOB');
    $foreign = $pdo->query("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN ('hq_operations','hq_access','hq_missions','hq_messages','hq_maps')")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['hq_operations_event_fk' => 'SET NULL', 'hq_access_operation_fk' => 'CASCADE', 'hq_missions_operation_fk' => 'CASCADE', 'hq_missions_catalog_fk' => 'SET NULL', 'hq_messages_operation_fk' => 'CASCADE', 'hq_maps_operation_fk' => 'CASCADE'] as $name => $rule) {
        burnout_pro_check(($foreign[$name] ?? '') === $rule, 'Clave foranea correcta: ' . $name);
    }
    $indexes = $pdo->query("SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hq_operations'")->fetchAll();
    burnout_pro_check((bool) array_filter($indexes, static fn ($index) => $index['COLUMN_NAME'] === 'event_id' && (int) $index['NON_UNIQUE'] === 0), 'Una operacion unica por evento');
    $count = (int) $pdo->query('SELECT COUNT(*) FROM hq_mission_catalog WHERE is_available = 1')->fetchColumn();
    burnout_pro_check($count >= 45, 'Catalogo inicial disponible: ' . $count . ' misiones');
    burnout_pro_check((int) $pdo->query('SELECT COUNT(*) FROM hq_mission_catalog WHERE content_hash <> SHA2(CONCAT(title, CHAR(10), briefing), 256)')->fetchColumn() === 0, 'Hashes del catalogo coherentes con los guiones');

    $stage = 'comprobacion funcional con rollback';
    $pdo->beginTransaction();
    $date = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Madrid')))->format('Y-m-d');
    $pdo->prepare('INSERT INTO events (event_date, title, time_slot, max_attendees) VALUES (?, ?, ?, ?)')->execute([$date, 'HQ PRO VALIDACION TEMPORAL', 'M', 1]);
    $eventId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO hq_operations (event_id, title, operation_date) VALUES (?, ?, ?)')->execute([$eventId, 'HQ PRO VALIDACION TEMPORAL', $date]);
    $operationId = (int) $pdo->lastInsertId();
    $item = $pdo->query('SELECT id, title, briefing FROM hq_mission_catalog WHERE is_available = 1 ORDER BY id LIMIT 1')->fetch();
    $pdo->prepare('INSERT INTO hq_missions (operation_id, team, title, briefing, catalog_id, is_imported, sort_order) VALUES (?, ?, ?, ?, ?, 1, 0)')->execute([$operationId, 'alpha', $item['title'], $item['briefing'], $item['id']]);
    $missionId = (int) $pdo->lastInsertId();
    $query = $pdo->prepare('SELECT status FROM hq_missions WHERE id = ?');
    $query->execute([$missionId]);
    $storedStatus = $query->fetchColumn();
    $query->closeCursor();
    burnout_pro_check($storedStatus === 'draft', 'Crear mision deja el estado Pendiente');
    foreach (['active', 'completed', 'failed', 'deleted', 'draft'] as $status) {
        $pdo->prepare('UPDATE hq_missions SET status = ? WHERE id = ?')->execute([$status, $missionId]);
    }
    burnout_pro_check(true, 'Todos los estados del guion se pueden persistir');
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
    $pdo->prepare('INSERT INTO hq_maps (operation_id, annotations) VALUES (?, ?)')->execute([$operationId, $image]);
    $query = $pdo->prepare('SELECT annotations FROM hq_maps WHERE operation_id = ?');
    $query->execute([$operationId]);
    $storedImage = $query->fetchColumn();
    $query->closeCursor();
    burnout_pro_check($storedImage === $image, 'El mapa conserva sus datos binarios');
    foreach (['alpha', 'bravo'] as $team) {
        $pdo->prepare('INSERT INTO hq_access (operation_id, team, code_hash) VALUES (?, ?, ?)')->execute([$operationId, $team, password_hash(bin2hex(random_bytes(6)), PASSWORD_DEFAULT)]);
        $pdo->prepare('INSERT INTO hq_messages (operation_id, team, sender, body) VALUES (?, ?, ?, ?)')->execute([$operationId, $team, 'hq', 'Mensaje ficticio temporal']);
    }
    $query = $pdo->prepare('SELECT COUNT(*) FROM hq_messages WHERE operation_id = ? AND team = ?');
    $query->execute([$operationId, 'alpha']);
    $messageCount = (int) $query->fetchColumn();
    $query->closeCursor();
    burnout_pro_check($messageCount === 1, 'Los mensajes se consultan por equipo');
    $pdo->rollBack();
    $query = $pdo->prepare('SELECT COUNT(*) FROM events WHERE id = ?');
    $query->execute([$eventId]);
    $eventCount = (int) $query->fetchColumn();
    $query->closeCursor();
    burnout_pro_check($eventCount === 0, 'Rollback: no queda el evento ficticio');
    $query = $pdo->prepare('SELECT COUNT(*) FROM hq_operations WHERE id = ?');
    $query->execute([$operationId]);
    $operationCount = (int) $query->fetchColumn();
    $query->closeCursor();
    burnout_pro_check($operationCount === 0, 'Rollback: no queda la operacion ficticia');

    $stage = 'integridad de las tablas previas';
    foreach ($baseline as $table => $snapshot) {
        burnout_pro_check(burnout_pro_table_snapshot($pdo, $table) === $snapshot, 'Esquema y datos previos intactos: ' . $table);
    }
    echo "PRO: migraciones HQ y verificaciones completadas.\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (is_resource($backup)) gzclose($backup);
    $code = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
    fwrite(STDERR, 'FALLO en ' . $stage . '. Codigo del controlador: ' . $code . PHP_EOL);
    if (!$exception instanceof PDOException) fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    if (is_string($backupPath)) fwrite(STDERR, 'Copia local: ' . $backupPath . PHP_EOL);
    fwrite(STDERR, "No repetir las migraciones sin revisar el estado: el DDL puede haberse aplicado parcialmente.\n");
    exit(1);
}
