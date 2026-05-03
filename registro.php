<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

function registro_param(string $key, string $source = 'get'): string
{
    $data = $source === 'post' ? $_POST : $_GET;

    return trim((string) ($data[$key] ?? ''));
}

function registro_event_time_to_label(string $timeSlot): string
{
    return [
        'M' => 'Manana',
        'T' => 'Tarde',
        'N' => 'Noche',
    ][$timeSlot] ?? $timeSlot;
}

function registro_find_event(int $eventId): ?array
{
    $statement = burnout_pdo()->prepare(
        'SELECT id, event_date, title, time_slot FROM events WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $eventId]);
    $event = $statement->fetch();

    if (!$event) {
        return null;
    }

    return [
        'id' => (int) $event['id'],
        'date' => (string) $event['event_date'],
        'title' => (string) $event['title'],
        'turn' => registro_event_time_to_label((string) $event['time_slot']),
    ];
}

function registro_format_date(string $value): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return strtoupper($value);
    }

    $weekdays = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    return strtoupper(sprintf(
        '%s %d de %s de %d',
        $weekdays[(int) $date->format('w')],
        (int) $date->format('j'),
        $months[(int) $date->format('n') - 1],
        (int) $date->format('Y')
    ));
}

function registro_format_turn(string $value): string
{
    $turn = strtolower($value);

    if ($turn === 'maã±ana' || $turn === 'mañana') {
        $turn = 'manana';
    }

    return strtoupper($turn);
}

function registro_replace_between_id(string $html, string $id, string $value): string
{
    $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $pattern = sprintf('/(<[^>]+id="%s"[^>]*>)(.*?)(<\/[^>]+>)/s', preg_quote($id, '/'));

    return preg_replace($pattern, '$1' . $escaped . '$3', $html, 1) ?? $html;
}

function registro_set_input_value(string $html, string $id, string $value): string
{
    $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $pattern = sprintf('/(<input\b[^>]*id="%s"[^>]*)(>)/i', preg_quote($id, '/'));

    return preg_replace_callback($pattern, static function (array $matches) use ($escaped): string {
        $input = preg_replace('/\svalue="[^"]*"/i', '', $matches[1]) ?? $matches[1];

        return $input . ' value="' . $escaped . '"' . $matches[2];
    }, $html, 1) ?? $html;
}

function registro_set_message(string $html, string $message, bool $success): string
{
    if ($message === '') {
        return $html;
    }

    $class = $success ? 'registro-message registro-message--success' : 'registro-message registro-message--error';
    $escaped = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    return preg_replace(
        '/<div id="registroMessage" class="registro-message" hidden><\/div>/',
        '<div id="registroMessage" class="' . $class . '">' . $escaped . '</div>',
        $html,
        1
    ) ?? $html;
}

function registro_clean_list(array $values): array
{
    return array_map(static function ($value): string {
        return trim((string) $value);
    }, $values);
}

function registro_save_submission(): int
{
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    if ($eventId === false || $eventId === null) {
        throw new RuntimeException('No se ha recibido el ID de la partida.');
    }

    if (!registro_find_event((int) $eventId)) {
        throw new RuntimeException('La partida seleccionada no existe.');
    }

    $email = registro_param('email', 'post');
    $phone = registro_param('telefono', 'post');
    $team = registro_param('equipo', 'post');
    $acceptedRules = isset($_POST['normativaAceptada']);
    $attendeeNames = registro_clean_list(is_array($_POST['attendee_name'] ?? null) ? $_POST['attendee_name'] : []);
    $attendeeDocuments = registro_clean_list(is_array($_POST['attendee_document'] ?? null) ? $_POST['attendee_document'] : []);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Introduce una direccion electronica valida.');
    }

    if (!preg_match('/^\+?\d[\d\s-]{7,18}$/', $phone)) {
        throw new RuntimeException('Introduce un telefono de contacto valido.');
    }

    if (!$acceptedRules) {
        throw new RuntimeException('Debes aceptar la normativa para completar el registro.');
    }

    if (!$attendeeNames || count($attendeeNames) !== count($attendeeDocuments)) {
        throw new RuntimeException('Completa el nombre y documento de todos los asistentes.');
    }

    if (count($attendeeNames) > 10) {
        throw new RuntimeException('No se pueden registrar mas de 10 asistentes en el mismo formulario.');
    }

    foreach ($attendeeNames as $index => $name) {
        $document = $attendeeDocuments[$index] ?? '';

        if ($name === '' || $document === '') {
            throw new RuntimeException('El nombre completo y documento son obligatorios para cada asistente.');
        }
    }

    $pdo = burnout_pdo();
    $pdo->beginTransaction();

    try {
        $registration = $pdo->prepare(
            'INSERT INTO registrations (event_id, email, phone, team_name, accepted_rules)
             VALUES (:event_id, :email, :phone, :team_name, 1)'
        );
        $registration->execute([
            'event_id' => $eventId,
            'email' => $email,
            'phone' => $phone,
            'team_name' => $team !== '' ? $team : null,
        ]);

        $registrationId = (int) $pdo->lastInsertId();
        $attendee = $pdo->prepare(
            'INSERT INTO registration_attendees (registration_id, full_name, document)
             VALUES (:registration_id, :full_name, :document)'
        );

        foreach ($attendeeNames as $index => $name) {
            $attendee->execute([
                'registration_id' => $registrationId,
                'full_name' => $name,
                'document' => $attendeeDocuments[$index],
            ]);
        }

        $pdo->commit();

        return $registrationId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

$message = '';
$messageSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $registrationId = registro_save_submission();
        $message = sprintf('Registro guardado correctamente. Numero de registro: %d.', $registrationId);
        $messageSuccess = true;
        $_POST = [];
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
    }
}

$eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

if ($eventId === false || $eventId === null) {
    $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
}

if ($eventId === false || $eventId === null) {
    $eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
}

$event = $eventId ? registro_find_event((int) $eventId) : null;
$title = $event['title'] ?? (registro_param('titulo') ?: 'CURSO INICIACION 4 EDICION');
$date = $event['date'] ?? (registro_param('fecha') ?: '2026-05-16');
$turn = $event['turn'] ?? (registro_param('turno') ?: 'Tarde');

$html = file_get_contents(__DIR__ . '/registro.html');

if ($html === false) {
    http_response_code(500);
    echo 'No se ha podido cargar el formulario de registro.';
    exit;
}

$html = registro_replace_between_id($html, 'registroEventoTitulo', 'INSCRIPCION ' . $title);
$html = registro_replace_between_id($html, 'registroEventoFecha', registro_format_date($date));
$html = registro_replace_between_id($html, 'registroEventoTurno', registro_format_turn($turn));
$html = registro_set_input_value($html, 'eventId', $event ? (string) $event['id'] : (string) ($eventId ?: ''));
$html = registro_set_message($html, $message, $messageSuccess);
$html = preg_replace('/<title>.*?<\/title>/s', '<title>Inscripcion - ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>', $html, 1) ?? $html;

echo $html;
