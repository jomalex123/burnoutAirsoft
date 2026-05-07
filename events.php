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
        'SELECT
            e.id,
            e.event_date,
            e.title,
            e.time_slot,
            e.max_attendees,
            COALESCE(COUNT(ra.id), 0) AS attendee_count
         FROM events e
         LEFT JOIN registrations r ON r.event_id = e.id
         LEFT JOIN registration_attendees ra ON ra.registration_id = r.id
         GROUP BY e.id, e.event_date, e.title, e.time_slot, e.max_attendees
         ORDER BY e.event_date ASC, FIELD(e.time_slot, "M", "T", "N"), e.id ASC'
    );

    $events = array_map(static function (array $event): array {
        return [
            'id' => (int) $event['id'],
            'date' => (string) $event['event_date'],
            'title' => (string) $event['title'],
            'time' => burnout_public_event_time_to_label((string) $event['time_slot']),
            'timeSlot' => (string) $event['time_slot'],
            'attendeeCount' => (int) $event['attendee_count'],
            'maxAttendees' => max(1, (int) $event['max_attendees']),
            'url' => 'registro.php',
        ];
    }, $statement->fetchAll());

    echo json_encode($events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo '[]';
}
