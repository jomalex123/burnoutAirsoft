<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function burnout_hq_text(array $input, string $key, int $max): string
{
    $value = $input[$key] ?? '';
    $length = is_string($value) && strlen($value) <= $max * 4 ? preg_match_all('/./us', trim($value)) : false;
    if ($length === false || $length === 0 || $length > $max) {
        throw new BurnoutUserException('Revisa el campo ' . $key . ' (máximo ' . $max . ' caracteres).');
    }
    return trim($value);
}

function burnout_hq_id(array $input, string $key, bool $optional = false): int
{
    $value = $input[$key] ?? ($optional ? 0 : null);
    if ((!is_string($value) && !is_int($value)) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $optional ? 0 : 1, 'max_range' => 4294967295]]) === false) {
        throw new BurnoutUserException('Revisa el identificador de la solicitud.');
    }
    return (int) $value;
}

function burnout_hq_choice(array $input, string $key, array $choices): string
{
    $value = $input[$key] ?? null;
    if (!is_string($value) || !in_array($value, $choices, true)) {
        throw new BurnoutUserException('La opción seleccionada no es válida.');
    }
    return $value;
}

function burnout_hq_today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d');
}

function burnout_hq_available_events(): array
{
    $query = burnout_pdo()->prepare(
        "SELECT e.id AS event_id, o.id AS operation_id, e.title, e.event_date AS operation_date,
                e.time_slot, COALESCE(o.status, 'active') AS status
         FROM events e LEFT JOIN hq_operations o ON o.event_id = e.id
         WHERE e.event_date >= ?
         ORDER BY e.event_date ASC, FIELD(e.time_slot, 'M', 'T', 'N'), e.id ASC"
    );
    $query->execute([burnout_hq_today()]);
    return $query->fetchAll();
}

function burnout_hq_select_event(int $eventId): int
{
    $pdo = burnout_pdo();
    $query = $pdo->prepare('SELECT id, title, event_date FROM events WHERE id = ? AND event_date >= ?');
    $query->execute([$eventId, burnout_hq_today()]);
    $event = $query->fetch();
    if (!$event) {
        throw new BurnoutUserException('Selecciona un evento del calendario de hoy o de una fecha futura.');
    }
    // La clave única conserva una sola operación por evento, incluso entre dos administradores.
    $query = $pdo->prepare('INSERT INTO hq_operations (event_id, title, operation_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $query->execute([$eventId, $event['title'], $event['event_date']]);
    return (int) $pdo->lastInsertId();
}

function burnout_hq_operation(int $id, bool $lock = false): array
{
    $query = burnout_pdo()->prepare('SELECT o.id, o.event_id, e.title, e.event_date AS operation_date, e.time_slot, o.status FROM hq_operations o JOIN events e ON e.id = o.event_id WHERE o.id = ? AND e.event_date >= ?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id, burnout_hq_today()]);
    $row = $query->fetch();
    if (!$row) {
        http_response_code(410);
        throw new BurnoutUserException('La partida ya no está disponible en el calendario. Selecciona un evento de hoy o futuro.');
    }
    return $row;
}

function burnout_hq_team_access(array $session, int $operationId): ?string
{
    if ((int) ($session['operation_id'] ?? 0) !== $operationId || !in_array($session['team'] ?? '', ['alpha', 'bravo'], true)) {
        return null;
    }
    $query = burnout_pdo()->prepare('SELECT code_hash FROM hq_access WHERE operation_id = ? AND team = ?');
    $query->execute([$operationId, $session['team']]);
    $hash = $query->fetchColumn();
    return is_string($hash) && hash_equals($hash, (string) ($session['version'] ?? '')) ? $session['team'] : null;
}

function burnout_hq_state(int $operationId, ?string $team, string $channel, int $after): array
{
    $pdo = burnout_pdo();
    $operation = burnout_hq_operation($operationId);
    $fields = $team === null ? 'id, team, title, briefing, status, catalog_id, is_imported, acknowledged_at, updated_at' : 'id, team, title, briefing, status, acknowledged_at, updated_at';
    $sql = 'SELECT ' . $fields . ' FROM hq_missions WHERE operation_id = ?';
    $params = [$operationId];
    if ($team !== null) {
        $sql .= " AND team = ? AND status <> 'draft'";
        $params[] = $team;
    }
    $query = $pdo->prepare($sql . ' ORDER BY sort_order ASC, id DESC');
    $query->execute($params);
    $missions = $query->fetchAll();
    $channel = $team ?? $channel;
    if ($after > 0) {
        $query = $pdo->prepare('SELECT id, sender, body, created_at FROM hq_messages WHERE operation_id = ? AND team = ? AND id > ? ORDER BY id ASC LIMIT 100');
        $query->execute([$operationId, $channel, $after]);
        $messages = $query->fetchAll();
    } else {
        $query = $pdo->prepare('SELECT id, sender, body, created_at FROM hq_messages WHERE operation_id = ? AND team = ? ORDER BY id DESC LIMIT 50');
        $query->execute([$operationId, $channel]);
        $messages = array_reverse($query->fetchAll());
    }
    return ['operation' => $operation, 'missions' => $missions, 'messages' => $messages, 'channel' => $channel];
}

function burnout_hq_categories(): array
{
    return burnout_pdo()->query('SELECT name FROM hq_mission_categories ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
}

function burnout_hq_catalog(): array
{
    return burnout_pdo()->query('SELECT id, title, briefing, category, mission_type FROM hq_mission_catalog WHERE is_available = 1 ORDER BY title, id')->fetchAll();
}

function burnout_hq_catalog_item(int $id): array
{
    $query = burnout_pdo()->prepare('SELECT id, title, briefing, category, mission_type FROM hq_mission_catalog WHERE id = ? AND is_available = 1');
    $query->execute([$id]);
    $item = $query->fetch();
    if (!$item) {
        http_response_code(404);
        throw new BurnoutUserException('La misión ya no está disponible en el catálogo. Importa otra misión.');
    }
    return $item;
}

function burnout_hq_catalog_store(string $title, string $briefing): int
{
    $pdo = burnout_pdo();
    $query = $pdo->prepare('INSERT INTO hq_mission_catalog (title, briefing, content_hash) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), is_available = 1');
    $query->execute([$title, $briefing, hash('sha256', $title . "\n" . $briefing)]);
    return (int) $pdo->lastInsertId();
}
