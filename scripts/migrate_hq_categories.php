<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';

function burnout_categories_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function burnout_categories_columns(PDO $pdo, string $table): array
{
    $query = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $query->execute([$table]);
    $columns = [];
    foreach ($query->fetchAll() as $column) $columns[$column['COLUMN_NAME']] = $column;
    return $columns;
}

function burnout_categories_backup_write($backup, string $text): void
{
    global $backupBytes;
    $written = gzwrite($backup, $text);
    if ($written !== strlen($text)) throw new RuntimeException('No se ha podido completar la copia de seguridad.');
    $backupBytes += $written;
}

function burnout_categories_table_snapshot(PDO $pdo, string $table, $backup = null): array
{
    $quoted = burnout_categories_identifier($table);
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
    $order = $primary ? ' ORDER BY ' . implode(', ', array_map('burnout_categories_identifier', $primary)) : '';
    if (is_resource($backup)) burnout_categories_backup_write($backup, "\n-- Table " . $quoted . "\n" . $create . ";\n");
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
            $names[] = burnout_categories_identifier($name);
            $value = $row[$name];
            if ($value === null) $values[] = 'NULL';
            elseif (preg_match('/blob|binary|bit/i', $column['Type'])) $values[] = "X'" . bin2hex((string) $value) . "'";
            else $values[] = $pdo->quote((string) $value);
        }
        burnout_categories_backup_write($backup, 'INSERT INTO ' . $quoted . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $values) . ");\n");
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

function burnout_categories_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo 'OK: ' . $message . PHP_EOL;
}

$stage = 'configuracion';
$backupPath = null;
$backup = null;
$backupBytes = 0;
try {
    $environment = getenv('BURNOUT_ENV');
    if (!in_array($environment, ['pre', 'pro'], true)) throw new RuntimeException('Usar migrate_hq_pre.php o migrate_hq_pro.php con --categories.');
    $config = burnout_database_config();
    foreach (['local', $environment === 'pre' ? 'pro' : 'pre'] as $otherEnvironment) {
        $other = burnout_env_config()['connections'][$otherEnvironment] ?? [];
        if ([$config['host'], (int) $config['port'], $config['database']] === [$other['host'] ?? '', (int) ($other['port'] ?? 3306), $other['database'] ?? '']) throw new RuntimeException('El destino coincide con otro entorno.');
    }
    $pdo = burnout_pdo();
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 15');
    $sql = file_get_contents(__DIR__ . '/../database/migrations/2026_11_04_hq_mission_categories.sql');
    preg_match_all("/UPDATE hq_mission_catalog SET category = '((?:''|[^'])*)' WHERE content_hash = '([a-f0-9]{64})';/u", $sql, $matches, PREG_SET_ORDER);
    $expected = [];
    foreach ($matches as $match) $expected[$match[2]] = str_replace("''", "'", $match[1]);
    if (count($expected) !== 45 || count(array_unique($expected)) !== 17) throw new RuntimeException('La migracion debe contener 45 misiones y 17 categorias.');
    $before = $pdo->query('SELECT * FROM hq_mission_catalog ORDER BY id')->fetchAll();
    $found = [];
    $pending = false;
    foreach ($before as $row) {
        $hash = $row['content_hash'];
        if (!isset($expected[$hash])) continue;
        if ($hash !== hash('sha256', $row['title'] . "\n" . $row['briefing'])) throw new RuntimeException('El contenido de una mision difiere de su hash.');
        $found[$hash] = true;
        if ($row['category'] !== $expected[$hash]) $pending = true;
    }
    if (count($found) !== 45) throw new RuntimeException('No coinciden las 45 misiones remotas. No se modifica nada.');
    $exists = (bool) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hq_mission_categories'")->fetchColumn();
    $oldCategories = $exists ? $pdo->query('SELECT name FROM hq_mission_categories ORDER BY name')->fetchAll(PDO::FETCH_COLUMN) : [];
    $needed = array_values(array_unique(array_merge(array_values($expected), array_filter(array_column($before, 'category')), $oldCategories)));
    $pending = $pending || !$exists || (bool) array_diff($needed, $oldCategories);
    echo strtoupper($environment) . ': 45 misiones verificadas; categorias ' . ($pending ? 'PENDIENTES' : 'INSTALADAS') . ".\n";
    if (!in_array('--apply', $argv, true) || !$pending) { echo "Inspeccion terminada sin cambios.\n"; exit; }

    $stage = 'copia previa';
    $engines = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    if (array_filter($engines, static fn ($engine) => strcasecmp((string) $engine, 'InnoDB') !== 0)) throw new RuntimeException('Hay tablas no transaccionales.');
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $backupPath = tempnam(sys_get_temp_dir(), 'burnout-' . $environment . '-categories-');
    if ($backupPath === false) throw new RuntimeException('No se pudo preparar la copia.');
    $named = $backupPath . '.sql.gz';
    if (!rename($backupPath, $named)) throw new RuntimeException('No se pudo nombrar la copia.');
    $backupPath = $named;
    @chmod($backupPath, 0600);
    $backup = gzopen($backupPath, 'wb6');
    if ($backup === false) throw new RuntimeException('No se pudo abrir la copia.');
    burnout_categories_backup_write($backup, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    $baseline = [];
    foreach ($tables as $table) $baseline[$table] = burnout_categories_table_snapshot($pdo, $table, $backup);
    $pdo->commit();
    burnout_categories_backup_write($backup, "SET FOREIGN_KEY_CHECKS=1;\n");
    if (!gzclose($backup)) throw new RuntimeException('No se pudo cerrar la copia.');
    $backup = null;
    $verify = gzopen($backupPath, 'rb');
    if ($verify === false) throw new RuntimeException('No se pudo leer la copia.');
    $bytes = 0;
    while (!gzeof($verify)) {
        $chunk = gzread($verify, 1048576);
        if ($chunk === false) throw new RuntimeException('Copia gzip ilegible.');
        $bytes += strlen($chunk);
    }
    gzclose($verify);
    burnout_categories_check($bytes === $backupBytes && $bytes > 50, 'Copia completa leida y validada');
    echo 'Copia SQL gzip: ' . $backupPath . PHP_EOL;

    $stage = 'migracion';
    $boundary = strpos($sql, '-- DATA');
    if ($boundary === false) throw new RuntimeException('Falta separador DDL/datos.');
    $pdo->exec(substr($sql, 0, $boundary));
    $pdo->beginTransaction();
    // Lock and reread before modifying; detect edits since the inspection and backup.
    $locked = $pdo->query('SELECT * FROM hq_mission_catalog ORDER BY id FOR UPDATE')->fetchAll();
    if ($locked !== $before) throw new RuntimeException('El catalogo cambio durante la copia. Revisar y volver a inspeccionar.');
    $pdo->exec(substr($sql, $boundary));
    $wanted = $before;
    foreach ($wanted as &$row) {
        if (isset($expected[$row['content_hash']])) $row['category'] = $expected[$row['content_hash']];
    }
    unset($row);
    $after = $pdo->query('SELECT * FROM hq_mission_catalog ORDER BY id')->fetchAll();
    // updated_at may change automatically when assigning a category.
    foreach ($after as $index => &$row) {
        if (isset($expected[$row['content_hash']]) && array_key_exists('updated_at', $row)) $row['updated_at'] = $wanted[$index]['updated_at'];
    }
    unset($row);
    burnout_categories_check($after === $wanted, 'Solo cambian las categorias de las 45 misiones previstas');
    $categories = $pdo->query('SELECT name FROM hq_mission_categories ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    burnout_categories_check(!array_diff($needed, $categories), '17 categorias iniciales y categorias previas conservadas');
    $pdo->commit();

    $stage = 'integridad posterior';
    foreach ($baseline as $table => $snapshot) {
        if (in_array($table, ['hq_mission_catalog', 'hq_mission_categories'], true)) continue;
        burnout_categories_check(burnout_categories_table_snapshot($pdo, $table) === $snapshot, 'Sin cambios en ' . $table);
    }
    burnout_categories_check(burnout_categories_table_snapshot($pdo, 'hq_mission_catalog')['schema'] === $baseline['hq_mission_catalog']['schema'], 'Esquema del catalogo conservado');
    echo strtoupper($environment) . ": migracion de categorias aplicada y verificada.\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (is_resource($backup)) gzclose($backup);
    fwrite(STDERR, 'FALLO en ' . $stage . '. ' . ($error instanceof PDOException ? 'Codigo SQL: ' . ($error->errorInfo[1] ?? 0) : $error->getMessage()) . PHP_EOL);
    if ($backupPath) fwrite(STDERR, 'Copia: ' . $backupPath . PHP_EOL);
    fwrite(STDERR, "Inspeccionar estado antes de reintentar; el DDL puede haberse aplicado.\n");
    exit(1);
}
