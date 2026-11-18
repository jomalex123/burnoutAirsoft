<?php

declare(strict_types=1);

// Explicit remote tool; the local migration and tests keep their existing guards.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['environment:', 'apply', 'verify-backup:', 'player-roles', 'confirm-production']);
$environment = $options['environment'] ?? '';
if (!in_array($environment, ['pre', 'pro'], true)) {
    fwrite(STDERR, "Usar --environment=pre o --environment=pro; sin --apply solo inspecciona.\n");
    exit(1);
}
if (isset($options['player-roles'], $options['apply']) && $environment === 'pro' && !isset($options['confirm-production'])) {
    fwrite(STDERR, "Aplicar roles en PRO requiere autorización expresa y --confirm-production. Sin --apply solo inspecciona.\n");
    exit(1);
}
putenv('BURNOUT_ENV_FILE=' . dirname(__DIR__) . '/config/env.php');
putenv('BURNOUT_ENV=' . $environment);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/registration_documents.php';

function burnout_reg_remote_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function burnout_reg_remote_columns(PDO $pdo, string $table): array
{
    $query = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $query->execute([$table]);
    $columns = [];
    foreach ($query->fetchAll() as $column) $columns[$column['COLUMN_NAME']] = $column;
    return $columns;
}

function burnout_reg_remote_backup_write($backup, string $text): void
{
    global $backupBytes;
    $written = gzwrite($backup, $text);
    if ($written !== strlen($text)) throw new RuntimeException('No se ha podido completar la copia de seguridad.');
    $backupBytes += $written;
}

function burnout_reg_remote_table_snapshot(PDO $pdo, string $table, $backup = null, ?array $dataColumns = null): array
{
    $quoted = burnout_reg_remote_identifier($table);
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
    $order = $primary ? ' ORDER BY ' . implode(', ', array_map('burnout_reg_remote_identifier', $primary)) : '';
    if (is_resource($backup)) burnout_reg_remote_backup_write($backup, "\n-- Table " . $quoted . "\n" . $create . ";\n");
    $query = $pdo->query('SELECT * FROM ' . $quoted . $order);
    $hash = hash_init('sha256');
    $unordered = [];
    $rows = 0;
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $dataRow = $row;
        if ($dataColumns !== null) {
            $dataRow = [];
            foreach ($dataColumns as $name) $dataRow[$name] = $row[$name];
        }
        $serialized = serialize($dataRow);
        if ($primary) hash_update($hash, $serialized);
        else $unordered[] = hash('sha256', $serialized);
        $rows++;
        if (!is_resource($backup)) continue;
        $names = [];
        $values = [];
        foreach ($columns as $column) {
            if (preg_match('/(?:VIRTUAL|STORED|PERSISTENT) GENERATED/i', $column['Extra'] ?? '')) continue;
            $name = $column['Field'];
            $names[] = burnout_reg_remote_identifier($name);
            $value = $row[$name];
            if ($value === null) $values[] = 'NULL';
            elseif (preg_match('/blob|binary|bit/i', $column['Type'])) $values[] = "X'" . bin2hex((string) $value) . "'";
            else $values[] = $pdo->quote((string) $value);
        }
        burnout_reg_remote_backup_write($backup, 'INSERT INTO ' . $quoted . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $values) . ");\n");
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

function burnout_reg_remote_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo 'OK: ' . $message . PHP_EOL;
}

function burnout_reg_remote_index(PDO $pdo, string $table, string $name, array $expected): bool
{
    $found = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . burnout_reg_remote_identifier($table))->fetchAll() as $index) {
        if ($index['Key_name'] !== $name) continue;
        if ((int) $index['Non_unique'] !== 0 || $index['Sub_part'] !== null) throw new RuntimeException('Indice existente incompatible: ' . $name);
        $found[(int) $index['Seq_in_index']] = $index['Column_name'];
    }
    if (!$found) return false;
    ksort($found);
    if (array_values($found) !== $expected) throw new RuntimeException('Columnas de indice incompatibles: ' . $name);
    return true;
}

function burnout_reg_remote_foreign_key(PDO $pdo): bool
{
    $query = $pdo->query("SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE k
        INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
          ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'registration_attendees'
          AND k.CONSTRAINT_NAME = 'registration_attendees_registration_event_foreign'
        ORDER BY k.ORDINAL_POSITION");
    $rows = $query->fetchAll();
    if (!$rows) return false;
    if (array_column($rows, 'COLUMN_NAME') !== ['registration_id', 'event_id'] ||
        array_column($rows, 'REFERENCED_COLUMN_NAME') !== ['id', 'event_id']) {
        throw new RuntimeException('Clave foranea compuesta incompatible.');
    }
    foreach ($rows as $row) {
        if ($row['REFERENCED_TABLE_NAME'] !== 'registrations' || $row['UPDATE_RULE'] !== 'CASCADE' || $row['DELETE_RULE'] !== 'CASCADE') {
            throw new RuntimeException('Reglas de clave foranea incompatibles.');
        }
    }
    return true;
}

function burnout_reg_remote_verify_backup(PDO $pdo, string $path): void
{
    $stream = gzopen($path, 'rb');
    if ($stream === false) throw new RuntimeException('No se pudo abrir la copia para comparar los datos.');
    $tables = [];
    $table = '';
    $schema = '';
    $line = '';
    while (!gzeof($stream)) {
        $piece = gzgets($stream, 1048576);
        if ($piece === false) break;
        $line .= $piece;
        if (substr($line, -1) !== "\n") continue;
        if (preg_match('/^-- Table `([^`]+)`/', $line, $match)) {
            $table = $match[1];
            $tables[$table] = ['rows' => 0, 'hash' => hash_init('sha256'), 'columns' => []];
            $schema = '';
        } elseif (str_starts_with($line, 'CREATE TABLE') || $schema !== '') {
            $schema .= $line;
            if (str_ends_with(rtrim($line), ';')) {
                $tables[$table]['schema'] = rtrim(rtrim($schema), ';');
                $schema = '';
            }
        } elseif (preg_match('/^INSERT INTO `([^`]+)` \((.*?)\) VALUES /', $line, $match)) {
            if ($match[1] !== $table) throw new RuntimeException('Orden de tablas inesperado en la copia.');
            preg_match_all('/`([^`]+)`/', $match[2], $names);
            $tables[$table]['columns'] = $names[1];
            hash_update($tables[$table]['hash'], $line);
            $tables[$table]['rows']++;
        }
        $line = '';
    }
    gzclose($stream);
    if ($line !== '' || !$tables) throw new RuntimeException('La copia esta incompleta o no contiene tablas.');
    foreach ($tables as $name => $expected) {
        $quoted = burnout_reg_remote_identifier($name);
        $currentSchema = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM)[1];
        if (!in_array($name, ['registrations', 'registration_attendees'], true)) {
            $normalize = static fn (string $value): string => trim(preg_replace('/\bAUTO_INCREMENT=\d+\b/', 'AUTO_INCREMENT=<counter>', $value));
            burnout_reg_remote_check($normalize($expected['schema']) === $normalize($currentSchema), 'Esquema previo conservado: ' . $name);
        }
        $primary = [];
        foreach ($pdo->query('SHOW INDEX FROM ' . $quoted)->fetchAll() as $index) {
            if ($index['Key_name'] === 'PRIMARY') $primary[(int) $index['Seq_in_index']] = $index['Column_name'];
        }
        ksort($primary);
        if (!$primary) throw new RuntimeException('La verificacion de copia requiere clave primaria.');
        $columns = $expected['columns'];
        $select = $columns ? implode(', ', array_map('burnout_reg_remote_identifier', $columns)) : '*';
        $binaryTypes = [];
        foreach ($pdo->query('SHOW COLUMNS FROM ' . $quoted)->fetchAll() as $column) {
            $binaryTypes[$column['Field']] = (bool) preg_match('/blob|binary|bit/i', $column['Type']);
        }
        $query = $pdo->query('SELECT ' . $select . ' FROM ' . $quoted . ' ORDER BY ' . implode(', ', array_map('burnout_reg_remote_identifier', $primary)));
        $hash = hash_init('sha256');
        $count = 0;
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($row as $column => $value) {
                $values[] = $value === null ? 'NULL' : ($binaryTypes[$column] ? "X'" . bin2hex((string) $value) . "'" : $pdo->quote((string) $value));
            }
            $statement = 'INSERT INTO ' . $quoted . ' (' . implode(', ', array_map('burnout_reg_remote_identifier', array_keys($row))) . ') VALUES (' . implode(', ', $values) . ");\n";
            hash_update($hash, $statement);
            $count++;
        }
        burnout_reg_remote_check($count === $expected['rows'] && hash_final($hash) === hash_final($expected['hash']), 'Contenido original coincide con copia: ' . $name);
    }
}

function burnout_reg_remote_inspect(PDO $pdo, string $expression): array
{
    $columns = burnout_reg_remote_columns($pdo, 'registration_attendees');
    $parentIndex = burnout_reg_remote_index($pdo, 'registrations', 'registrations_id_event_unique', ['id', 'event_id']);
    $unique = burnout_reg_remote_index($pdo, 'registration_attendees', 'registration_attendees_event_document_unique', ['event_id', 'document_key']);
    $foreign = burnout_reg_remote_foreign_key($pdo);
    $generated = isset($columns['document_key']);
    if ($generated !== $unique || $unique !== $foreign || ($generated && !isset($columns['event_id']))) {
        throw new RuntimeException('El ultimo ALTER esta parcialmente instalado. Revisar manualmente.');
    }
    $duplicates = (int) $pdo->query('SELECT COUNT(*) FROM (
        SELECT registrations.event_id, ' . $expression . ' AS normalized_document
        FROM registration_attendees INNER JOIN registrations ON registrations.id = registration_attendees.registration_id
        GROUP BY registrations.event_id, normalized_document HAVING COUNT(*) > 1
    ) duplicate_groups')->fetchColumn();
    $orphans = (int) $pdo->query('SELECT COUNT(*) FROM registration_attendees LEFT JOIN registrations ON registrations.id = registration_attendees.registration_id WHERE registrations.id IS NULL')->fetchColumn();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM registration_attendees')->fetchColumn();
    echo 'Asistentes: ' . $count . '; grupos duplicados: ' . $duplicates . '; huerfanos: ' . $orphans . PHP_EOL;
    if ($duplicates) {
        $groups = $pdo->query('SELECT registrations.event_id,
            COUNT(DISTINCT registration_attendees.full_name) AS distinct_names,
            GROUP_CONCAT(registration_attendees.id ORDER BY registration_attendees.id) AS attendee_ids,
            GROUP_CONCAT(registrations.id ORDER BY registration_attendees.id) AS registration_ids
            FROM registration_attendees INNER JOIN registrations ON registrations.id = registration_attendees.registration_id
            GROUP BY registrations.event_id, ' . $expression . ' HAVING COUNT(*) > 1')->fetchAll();
        foreach ($groups as $group) {
            echo 'Duplicado en evento ' . $group['event_id'] . ': asistentes [' . $group['attendee_ids'] . '], inscripciones [' . $group['registration_ids'] . '], nombres distintos: ' . $group['distinct_names'] . '. Sin mostrar documentos ni nombres.' . PHP_EOL;
        }
    }
    if ($duplicates || $orphans) throw new RuntimeException('Resolver inconsistencias manualmente antes de migrar. No se eliminan asistentes.');
    if ($generated) {
        if (!$parentIndex || $columns['event_id']['IS_NULLABLE'] !== 'NO' || stripos($columns['event_id']['COLUMN_TYPE'], 'unsigned') === false) {
            throw new RuntimeException('El evento o indice padre no tiene la estructura esperada.');
        }
        $metadata = $pdo->query("SELECT GENERATION_EXPRESSION, EXTRA, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registration_attendees' AND COLUMN_NAME = 'document_key'")->fetch();
        if (!preg_match('/(?:STORED|PERSISTENT) GENERATED/i', $metadata['EXTRA']) || (int) $metadata['CHARACTER_MAXIMUM_LENGTH'] !== 40) {
            throw new RuntimeException('La clave normalizada no es una columna generada almacenada de longitud 40.');
        }
        // MySQL 8.4 may escape the quotes in INFORMATION_SCHEMA metadata.
        $generationExpression = str_replace("\\'", "'", $metadata['GENERATION_EXPRESSION']);
        $sample = $pdo->prepare('SELECT ' . $generationExpression . ' FROM (SELECT ? AS document) document_sample');
        foreach (["12345678z", "12345678-Z", "12345678 Z\t\r\n\v\f", 'X1234567l', 'ABC123456'] as $value) {
            $sample->execute([$value]);
            if ($sample->fetchColumn() !== burnout_normalize_document($value)) throw new RuntimeException('La expresion generada no normaliza correctamente.');
            $sample->closeCursor();
        }
        $invalid = (int) $pdo->query('SELECT COUNT(*) FROM registration_attendees INNER JOIN registrations ON registrations.id = registration_attendees.registration_id WHERE registration_attendees.event_id <> registrations.event_id OR registration_attendees.document_key <> ' . $expression)->fetchColumn();
        if ($invalid) throw new RuntimeException('Hay eventos o documentos normalizados incoherentes.');
    }
    echo 'Migracion de documentos: ' . ($generated ? 'INSTALADA' : 'PENDIENTE') . PHP_EOL;
    return ['installed' => $generated, 'parent_index' => $parentIndex, 'event_column' => isset($columns['event_id'])];
}

function burnout_reg_remote_player_pending(PDO $pdo): array
{
    $attendees = burnout_reg_remote_columns($pdo, 'registration_attendees');
    $registrations = burnout_reg_remote_columns($pdo, 'registrations');
    foreach (['event_id', 'document_key'] as $required) {
        if (!isset($attendees[$required])) throw new RuntimeException('Instalar primero documentos por partida.');
    }
    $pending = [];
    if (!isset($attendees['weapon_role'])) $pending[] = '2026_11_17_registration_weapon_role.sql';
    elseif ($attendees['weapon_role']['COLUMN_TYPE'] !== "enum('Fusilero','Tirador Selecto','Apoyo','Francotirador','Escudo')" || $attendees['weapon_role']['IS_NULLABLE'] !== 'YES') throw new RuntimeException('weapon_role existente incompatible.');
    if (!isset($registrations['hq_team'])) $pending[] = '2026_11_17_hq_players.sql';
    elseif ($registrations['hq_team']['COLUMN_TYPE'] !== "enum('alpha','bravo')" || $registrations['hq_team']['COLUMN_DEFAULT'] !== 'alpha' || $registrations['hq_team']['IS_NULLABLE'] !== 'NO') throw new RuntimeException('hq_team existente incompatible.');
    $levels = ["enum('TL','Ingeniero')" => 1, "enum('TL','Ingeniero','Radio Operator','Segundo TL')" => 2, "enum('TL','Ingeniero','Radio Operator','Segundo TL','Medico')" => 3];
    $level = 0;
    if (isset($attendees['tactical_role'])) {
        $level = $levels[$attendees['tactical_role']['COLUMN_TYPE']] ?? -1;
        if ($level < 0 || $attendees['tactical_role']['IS_NULLABLE'] !== 'YES') throw new RuntimeException('tactical_role existente incompatible.');
    }
    if ($level < 1) $pending[] = '2026_11_17_hq_player_tactical_role.sql';
    if ($level < 2) $pending[] = '2026_11_18_hq_player_functions.sql';
    if ($level < 3) $pending[] = '2026_11_18_hq_player_medic.sql';
    if (!burnout_reg_remote_index($pdo, 'registration_attendees', 'registration_attendees_event_document_unique', ['event_id', 'document_key']) || !burnout_reg_remote_foreign_key($pdo)) {
        throw new RuntimeException('Revisar la unicidad de documentos y la relación de asistentes con la partida.');
    }
    return $pending;
}

function burnout_reg_remote_migrate_player_roles(PDO $pdo, array $options): void
{
    global $backupPath, $backup, $backupBytes, $locked, $ddlStarted, $stage, $environment;
    $label = strtoupper($environment);
    $stage = 'inspeccion de roles en ' . $label;
    $pending = burnout_reg_remote_player_pending($pdo);
    foreach ($pending as $file) echo 'Pendiente: ' . $file . PHP_EOL;
    if (!$pending) { echo $label . ": las cinco migraciones están instaladas.\n"; return; }
    if (!isset($options['apply'])) { echo 'Inspección de ' . $label . " terminada sin cambios.\n"; return; }
    $tables = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll();
    foreach ($tables as $table) {
        if (strcasecmp($table['ENGINE'], 'InnoDB') !== 0) throw new RuntimeException('Se requiere InnoDB para la copia.');
    }
    $names = array_column($tables, 'TABLE_NAME');
    $lockSql = 'LOCK TABLES ' . implode(', ', array_map(static function (string $name): string {
        return burnout_reg_remote_identifier($name) . (in_array($name, ['events', 'registrations', 'registration_attendees'], true) ? ' WRITE' : ' READ');
    }, $names));
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('SET autocommit=0'); $pdo->exec($lockSql); $locked = true;
    $pending = burnout_reg_remote_player_pending($pdo);
    $stage = 'copia privada de ' . $label;
    $backupPath = tempnam(sys_get_temp_dir(), 'burnout-' . $environment . '-player-roles-');
    if ($backupPath === false) throw new RuntimeException('No se pudo crear la copia privada.');
    $named = $backupPath . '.sql.gz';
    if (!rename($backupPath, $named)) throw new RuntimeException('No se pudo nombrar la copia.');
    $backupPath = $named;
    @chmod($backupPath, 0600);
    $backup = gzopen($backupPath, 'wb6');
    if ($backup === false) throw new RuntimeException('No se pudo abrir la copia.');
    burnout_reg_remote_backup_write($backup, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;\nSET FOREIGN_KEY_CHECKS=0;\n");
    $baseline = []; $columns = [];
    foreach ($names as $name) {
        $columns[$name] = $pdo->query('SHOW COLUMNS FROM ' . burnout_reg_remote_identifier($name))->fetchAll(PDO::FETCH_COLUMN);
        $baseline[$name] = burnout_reg_remote_table_snapshot($pdo, $name, $backup);
    }
    burnout_reg_remote_backup_write($backup, "SET FOREIGN_KEY_CHECKS=1;\n");
    if (!gzclose($backup)) throw new RuntimeException('No se pudo cerrar la copia.');
    $backup = null;
    $read = gzopen($backupPath, 'rb');
    if ($read === false) throw new RuntimeException('No se pudo verificar la copia.');
    $bytes = 0; $hash = hash_init('sha256');
    while (!gzeof($read)) {
        $chunk = gzread($read, 1048576);
        if ($chunk === false) throw new RuntimeException('No se pudo leer la copia completa.');
        $bytes += strlen($chunk); hash_update($hash, $chunk);
    }
    gzclose($read);
    if ($bytes !== $backupBytes || $bytes < 50) throw new RuntimeException('Copia incompleta.');
    // Relee y compara las sentencias originales de la copia antes del primer DDL.
    burnout_reg_remote_verify_backup($pdo, $backupPath);
    $manifest = json_encode(['environment' => $environment, 'migrations' => $pending, 'baseline' => $baseline, 'columns' => $columns, 'uncompressed_sha256' => hash_final($hash)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (file_put_contents($backupPath . '.manifest.json', $manifest) !== strlen($manifest) || file_get_contents($backupPath . '.manifest.json') !== $manifest) throw new RuntimeException('Manifiesto no verificado.');
    echo 'Copia SQL gzip verificada de ' . count($names) . ' tablas: ' . $backupPath . PHP_EOL;
    echo 'Antes de migrar: ' . $baseline['registrations']['rows'] . ' inscripciones y ' . $baseline['registration_attendees']['rows'] . " asistentes.\n";
    $stage = 'migraciones de jugadores ' . $label; $ddlStarted = true;
    foreach ($pending as $file) {
        $sql = file_get_contents(__DIR__ . '/../database/migrations/' . $file);
        if ($sql === false) throw new RuntimeException('No se pudo leer la migración.');
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
            if (preg_match('/^ALTER\s/i', $statement)) $pdo->exec($lockSql);
        }
        echo 'Aplicada en ' . $label . ': ' . $file . PHP_EOL;
    }
    $stage = 'verificacion de jugadores ' . $label;
    if (burnout_reg_remote_player_pending($pdo)) throw new RuntimeException('Quedan migraciones pendientes.');
    foreach ($baseline as $name => $before) {
        $after = burnout_reg_remote_table_snapshot($pdo, $name, null, $columns[$name]);
        $ok = $before['rows'] === $after['rows'] && $before['data'] === $after['data'];
        if (!in_array($name, ['registrations', 'registration_attendees'], true)) $ok = $ok && $before['schema'] === $after['schema'];
        burnout_reg_remote_check($ok, 'Datos originales conservados: ' . $name);
    }
    burnout_reg_remote_verify_backup($pdo, $backupPath);
    $pdo->exec('COMMIT'); $pdo->exec('UNLOCK TABLES'); $locked = false; $pdo->exec('SET autocommit=1');
    echo $label . ': cinco migraciones verificadas; ' . $baseline['registrations']['rows'] . ' inscripciones y ' . $baseline['registration_attendees']['rows'] . " asistentes originales conservados, sin duplicarlos.\n";
}

$stage = 'configuracion';
$backupPath = null;
$backup = null;
$backupBytes = 0;
$locked = false;
$ddlStarted = false;
try {
    $config = burnout_database_config();
    foreach (['local', $environment === 'pre' ? 'pro' : 'pre'] as $otherEnvironment) {
        $other = burnout_env_config()['connections'][$otherEnvironment] ?? [];
        if ([$config['host'], (int) $config['port'], $config['database']] === [$other['host'] ?? '', (int) ($other['port'] ?? 3306), $other['database'] ?? '']) {
            throw new RuntimeException('El destino coincide con otro entorno.');
        }
    }
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], (int) $config['port'], $config['database'], $config['charset']), $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    echo strtoupper($environment) . ': conexion verificada; servidor ' . $pdo->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;
    $pdo->exec('SET SESSION lock_wait_timeout = 15');
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 15');
    if (isset($options['player-roles'])) {
        burnout_reg_remote_migrate_player_roles($pdo, $options);
        exit(0);
    }
    $sql = file_get_contents(__DIR__ . '/../database/migrations/2026_11_09_registration_document_unique.sql');
    if ($sql === false || !preg_match('/ADD COLUMN document_key VARCHAR\(40\) GENERATED ALWAYS AS \((.*)\) STORED,/', $sql, $match)) {
        throw new RuntimeException('No se reconoce la migracion SQL esperada.');
    }
    $expression = $match[1];
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
    if (count($statements) !== 4) throw new RuntimeException('La migracion debe contener cuatro sentencias.');
    $stage = 'inspeccion';
    $state = burnout_reg_remote_inspect($pdo, $expression);
    if (isset($options['verify-backup'])) {
        $stage = 'comparacion con copia previa';
        burnout_reg_remote_verify_backup($pdo, $options['verify-backup']);
    }
    if (!isset($options['apply']) || $state['installed']) {
        echo "Inspeccion terminada sin cambios.\n";
        exit(0);
    }
    $tables = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll();
    foreach ($tables as $table) {
        if (strcasecmp($table['ENGINE'], 'InnoDB') !== 0) throw new RuntimeException('Hay tablas no transaccionales.');
    }
    $tableNames = array_column($tables, 'TABLE_NAME');
    $stage = 'bloqueo y copia previa';
    // Freeze the existing tables during backup and DDL. Reacquire after each ALTER,
    // because a rebuilt table may lose its explicit lock. Never start a transaction
    // here: START TRANSACTION would release LOCK TABLES.
    $lockSql = 'LOCK TABLES ' . implode(', ', array_map(static function (string $table): string {
        return burnout_reg_remote_identifier($table) . (in_array($table, ['events', 'registrations', 'registration_attendees'], true) ? ' WRITE' : ' READ');
    }, $tableNames));
    $pdo->exec('SET autocommit=0');
    $pdo->exec($lockSql);
    $locked = true;
    $state = burnout_reg_remote_inspect($pdo, $expression);
    echo "Preparando copia privada...\n";
    $backupPath = tempnam(sys_get_temp_dir(), 'burnout-' . $environment . '-registration-');
    if ($backupPath === false) throw new RuntimeException('No se pudo preparar la copia.');
    $named = $backupPath . '.sql.gz';
    if (!rename($backupPath, $named)) throw new RuntimeException('No se pudo nombrar la copia.');
    $backupPath = $named;
    @chmod($backupPath, 0600);
    $backup = gzopen($backupPath, 'wb6');
    if ($backup === false) throw new RuntimeException('No se pudo abrir la copia.');
    echo "Copia privada abierta; leyendo tablas...\n";
    burnout_reg_remote_backup_write($backup, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    $baseline = [];
    $originalColumns = [];
    foreach ($tableNames as $table) {
        $originalColumns[$table] = $pdo->query('SHOW COLUMNS FROM ' . burnout_reg_remote_identifier($table))->fetchAll(PDO::FETCH_COLUMN);
        $baseline[$table] = burnout_reg_remote_table_snapshot($pdo, $table, $backup);
        echo 'Copiada: ' . $table . ' (' . $baseline[$table]['rows'] . ' filas)' . PHP_EOL;
    }
    $manifest = json_encode(['baseline' => $baseline, 'columns' => $originalColumns], JSON_THROW_ON_ERROR);
    if (file_put_contents($backupPath . '.manifest.json', $manifest) !== strlen($manifest) || file_get_contents($backupPath . '.manifest.json') !== $manifest) {
        throw new RuntimeException('No se pudo verificar el manifiesto privado de integridad.');
    }
    burnout_reg_remote_backup_write($backup, "SET FOREIGN_KEY_CHECKS=1;\n");
    if (!gzclose($backup)) throw new RuntimeException('No se pudo cerrar la copia.');
    $backup = null;
    $read = gzopen($backupPath, 'rb');
    if ($read === false) throw new RuntimeException('No se pudo abrir la copia para verificarla.');
    $bytes = 0;
    while (!gzeof($read)) {
        $chunk = gzread($read, 1048576);
        if ($chunk === false) throw new RuntimeException('No se pudo leer la copia gzip.');
        $bytes += strlen($chunk);
    }
    gzclose($read);
    burnout_reg_remote_check($bytes === $backupBytes && $bytes > 50, 'Copia SQL gzip completa leida y verificada antes del DDL');
    echo 'Copia de ' . count($tableNames) . ' tablas: ' . $backupPath . PHP_EOL;
    $stage = 'migracion';
    $ddlStarted = true;
    if (!$state['parent_index']) {
        $pdo->exec($statements[0]);
        $pdo->exec($lockSql);
    }
    if (!$state['event_column']) {
        $pdo->exec($statements[1]);
        $pdo->exec($lockSql);
    }
    // Avoid aliases while explicit table locks are held.
    $pdo->exec('UPDATE registration_attendees INNER JOIN registrations ON registrations.id = registration_attendees.registration_id SET registration_attendees.event_id = registrations.event_id WHERE registration_attendees.event_id IS NULL');
    $pdo->exec('COMMIT');
    $pdo->exec($statements[3]);
    $pdo->exec($lockSql);
    $stage = 'verificacion final';
    $final = burnout_reg_remote_inspect($pdo, $expression);
    burnout_reg_remote_check($final['installed'], 'Restriccion por evento/documento y clave foranea instaladas');
    foreach ($baseline as $table => $before) {
        $after = burnout_reg_remote_table_snapshot($pdo, $table, null, $originalColumns[$table]);
        $unchanged = $before['rows'] === $after['rows'] && $before['data'] === $after['data'];
        if (!in_array($table, ['registrations', 'registration_attendees'], true)) $unchanged = $unchanged && $before['schema'] === $after['schema'];
        burnout_reg_remote_check($unchanged, 'Datos previos conservados: ' . $table);
    }
    $pdo->exec('COMMIT');
    $pdo->exec('UNLOCK TABLES');
    $locked = false;
    $pdo->exec('SET autocommit=1');
    echo strtoupper($environment) . ": migracion completada y verificada.\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($locked && isset($pdo)) { try { $pdo->exec('UNLOCK TABLES'); } catch (Throwable $ignored) {} }
    if (is_resource($backup)) gzclose($backup);
    $code = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
    fwrite(STDERR, 'FALLO en ' . $stage . '. Codigo del controlador: ' . $code . PHP_EOL);
    if (!$exception instanceof PDOException) fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    if (is_string($backupPath)) fwrite(STDERR, 'Copia privada local: ' . $backupPath . PHP_EOL);
    fwrite(STDERR, $ddlStarted
        ? "Inspeccionar antes de reintentar: el DDL puede haberse aplicado parcialmente.\n"
        : "No se ha ejecutado DDL ni se han modificado asistentes.\n");
    exit(1);
}
