<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

function burnout_public_event_time_to_label(string $timeSlot): string
{
    return [
        'M' => 'Mañana',
        'T' => 'Tarde',
        'N' => 'Noche',
    ][$timeSlot] ?? $timeSlot;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $statement = burnout_pdo()->query(
        'SELECT id, event_date, title, time_slot
         FROM events
         ORDER BY event_date ASC, FIELD(time_slot, "M", "T", "N"), id ASC'
    );

    $events = array_map(static function (array $event): array {
        return [
            'id' => (int) $event['id'],
            'date' => (string) $event['event_date'],
            'title' => (string) $event['title'],
            'time' => burnout_public_event_time_to_label((string) $event['time_slot']),
            'timeSlot' => (string) $event['time_slot'],
            'url' => 'registro.php',
        ];
    }, $statement->fetchAll());

    echo json_encode($events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo '[]';
}
