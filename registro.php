<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/config/rate_limit.php';

function registro_param(string $key, string $source = 'get'): string
{
    $data = $source === 'post' ? $_POST : $_GET;

    return trim((string) ($data[$key] ?? ''));
}

function registro_event_time_to_label(string $timeSlot): string
{
    return [
        'M' => 'Mañana',
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

function registro_format_short_date(string $value): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return $value;
    }

    $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    return sprintf('%d %s', (int) $date->format('j'), $months[(int) $date->format('n') - 1]);
}

function registro_format_turn(string $value): string
{
    $turn = registro_normalize_turn($value);

    return strtoupper($turn === 'manana' ? 'mañana' : $turn);
}

function registro_normalize_turn(string $value): string
{
    $turn = strtolower(trim($value));
    $turn = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $turn);

    return $turn;
}

function registro_turn_hours(string $turn): array
{
    return [
        'manana' => ['open' => '8:00 AM', 'close' => '9:00 AM'],
        'm' => ['open' => '8:00 AM', 'close' => '9:00 AM'],
        'tarde' => ['open' => '15:00 PM', 'close' => '16:00 PM'],
        't' => ['open' => '15:00 PM', 'close' => '16:00 PM'],
        'noche' => ['open' => '19:00 PM', 'close' => '20:00 PM'],
        'n' => ['open' => '19:00 PM', 'close' => '20:00 PM'],
    ][registro_normalize_turn($turn)] ?? ['open' => '8:00 AM', 'close' => '9:00 AM'];
}

function registro_confirmation_schedule(string $turn): string
{
    return [
        'manana' => 'Recuerda que el horario de apertura de puertas será a las 8:00 AM y el cierre de ellas a las 9:00 AM.',
        'm' => 'Recuerda que el horario de apertura de puertas será a las 8:00 AM y el cierre de ellas a las 9:00 AM.',
        'tarde' => 'Recuerda que el horario de apertura de puertas será a las 15:00 PM y el cierre de ellas a las 16:00 PM.',
        't' => 'Recuerda que el horario de apertura de puertas será a las 15:00 PM y el cierre de ellas a las 16:00 PM.',
        'noche' => 'Recuerda que el horario de apertura de puertas será a las 19:00 PM y el cierre de ellas a las 20:00 PM.',
        'n' => 'Recuerda que el horario de apertura de puertas será a las 19:00 PM y el cierre de ellas a las 20:00 PM.',
    ][registro_normalize_turn($turn)] ?? 'Recuerda que el horario de apertura de puertas será a las 8:00 AM y el cierre de ellas a las 9:00 AM.';
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

function registro_set_confirmation_texts(string $html, string $date, string $turn): string
{
    $html = registro_replace_between_id(
        $html,
        'registroConfirmationIntro',
        'Hemos recibido tu inscripción para la partida del ' . registro_format_short_date($date) . '.'
    );

    return registro_replace_between_id($html, 'registroConfirmationSchedule', registro_confirmation_schedule($turn));
}

function registro_set_confirmation_modal(string $html, bool $success): string
{
    if (!$success) {
        return $html;
    }

    $html = preg_replace(
        '/<section class="ms-section__block registro-form" id="registroFormSection">/',
        '<section class="ms-section__block registro-form is-hidden" id="registroFormSection" aria-hidden="true">',
        $html,
        1
    ) ?? $html;

    return preg_replace(
        '/<div class="registro-modal registro-confirmation-modal" id="registroConfirmationModal" aria-hidden="true">/',
        '<div class="registro-modal registro-confirmation-modal is-open" id="registroConfirmationModal" aria-hidden="false" data-registration-success="true">',
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

function registro_assert_rate_limit(string $email): void
{
    $ipBlock = burnout_rate_limit_hit('public_registration_ip', burnout_client_ip(), 5, 60 * 60, 60 * 60);

    if ($ipBlock > 0) {
        throw new RuntimeException('Demasiados intentos de registro. Espera ' . max(1, (int) ceil($ipBlock / 60)) . ' minutos e intentalo de nuevo.');
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailBlock = burnout_rate_limit_hit('public_registration_email', $email, 3, 60 * 60, 60 * 60);

        if ($emailBlock > 0) {
            throw new RuntimeException('Se han enviado demasiados registros con este correo. Espera ' . max(1, (int) ceil($emailBlock / 60)) . ' minutos e intentalo de nuevo.');
        }
    }
}

function registro_email_event_title(array $registration): string
{
    $event = is_array($registration['event'] ?? null) ? $registration['event'] : [];
    $title = trim((string) ($event['title'] ?? ''));

    return $title !== '' ? $title : 'Burnout Airsoft';
}

function registro_email_event_label(array $registration): string
{
    $event = is_array($registration['event'] ?? null) ? $registration['event'] : [];
    $parts = [
        registro_email_event_title($registration),
        !empty($event['date']) ? registro_format_short_date((string) $event['date']) : '',
        trim((string) ($event['turn'] ?? '')),
    ];

    return implode(' - ', array_values(array_filter($parts, static function (string $part): bool {
        return trim($part) !== '';
    })));
}

function registro_build_confirmation_email(array $registration): string
{
    $attendees = $registration['attendees'] ?? [];
    $attendeeLines = array_map(static function (array $attendee): string {
        return sprintf('%s %s', $attendee['name'], $attendee['document']);
    }, $attendees);
    $event = is_array($registration['event'] ?? null) ? $registration['event'] : [];
    $hours = registro_turn_hours((string) ($event['turn'] ?? ''));

    return sprintf(
        "Tu inscripción para el evento \"%s\" se ha registrado correctamente.\n\n" .
        "Recuerda que el horario de apertura de puertas será a las %s y el \n" .
        "cierre de ellas a las %s\n\n" .
        "Resumen de los datos enviados:\n" .
        "• Número de asistentes: %d\n" .
        "• Lista de asistentes:\n%s\n\n" .
        "• Teléfono de contacto: %s\n" .
        "• Correo electrónico: %s\n" .
        "• Equipo: %s\n\n" .
        "Normativa:\n" .
        "https://drive.google.com/file/d/104gDRmUVKkp6AtADIaEomPMzdUN7tZVT/\n\n" .
        "Por favor, asegúrate de leer la normativa. Su incumplimiento podrá ser \n" .
        "sancionado por la organización, incluyendo la expulsión del terreno de \n" .
        "juego.\n\n" .
        "Gracias por tu inscripción.\n" .
        "Un saludo.",
        registro_email_event_label($registration),
        $hours['open'],
        $hours['close'],
        count($attendees),
        implode("\n", $attendeeLines),
        $registration['phone'],
        $registration['email'],
        $registration['team'] !== '' ? $registration['team'] : '-'
    );
}

function registro_send_confirmation_email(array $registration): void
{
    burnout_send_plain_mail(
        $registration['email'],
        'Confirmación de inscripcion - ' . registro_email_event_title($registration),
        registro_build_confirmation_email($registration)
    );
}

function registro_save_submission(): array
{
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    if ($eventId === false || $eventId === null) {
        throw new RuntimeException('No se ha recibido el ID de la partida.');
    }

    $event = registro_find_event((int) $eventId);

    if (!$event) {
        throw new RuntimeException('La partida seleccionada no existe.');
    }

    $email = registro_param('email', 'post');
    $phone = registro_param('telefono', 'post');
    $team = registro_param('equipo', 'post');
    $acceptedRules = isset($_POST['normativaAceptada']);
    $attendeeNames = registro_clean_list(is_array($_POST['attendee_name'] ?? null) ? $_POST['attendee_name'] : []);
    $attendeeDocuments = registro_clean_list(is_array($_POST['attendee_document'] ?? null) ? $_POST['attendee_document'] : []);

    registro_assert_rate_limit($email);

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

        return [
            'id' => $registrationId,
            'email' => $email,
            'phone' => $phone,
            'team' => $team,
            'event' => $event,
            'attendees' => array_map(static function (string $name, string $document): array {
                return [
                    'name' => $name,
                    'document' => $document,
                ];
            }, $attendeeNames, $attendeeDocuments),
        ];
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

$message = '';
$messageSuccess = false;
$submittedEvent = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $registration = registro_save_submission();
        $submittedEvent = $registration['event'] ?? null;

        try {
            registro_send_confirmation_email($registration);
        } catch (Throwable $emailException) {
            error_log('No se ha podido enviar el correo de confirmacion del registro ' . $registration['id'] . ': ' . $emailException->getMessage());
        }

        $message = sprintf('Registro guardado correctamente. Numero de registro: %d.', $registration['id']);
        $messageSuccess = true;
        $_POST = [];
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
    }
}

$event = $submittedEvent;
$eventId = $event ? (int) $event['id'] : filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

if (!$event && ($eventId === false || $eventId === null)) {
    $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
}

if (!$event && ($eventId === false || $eventId === null)) {
    $eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
}

if (!$event) {
    $event = $eventId ? registro_find_event((int) $eventId) : null;
}
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
$html = $messageSuccess ? $html : registro_set_message($html, $message, false);
$html = $messageSuccess ? registro_set_confirmation_texts($html, $date, $turn) : $html;
$html = registro_set_confirmation_modal($html, $messageSuccess);
$html = preg_replace('/<title>.*?<\/title>/s', '<title>Inscripcion - ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>', $html, 1) ?? $html;

echo $html;
