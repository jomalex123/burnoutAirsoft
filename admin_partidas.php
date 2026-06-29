<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/registration_notifications.php';

$adminUser = null;
$setupError = '';
$message = '';
$error = '';

try {
    $adminUser = burnout_current_admin();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $setupError = 'No se ha podido validar la sesión de administración.';
}

if (!$setupError && !$adminUser) {
    header('Location: admin.php');
    exit;
}

$flash = burnout_pull_admin_flash();

if ($flash) {
    if ($flash['type'] === 'error') {
        $error = $flash['message'];
    } else {
        $message = $flash['message'];
    }
}

function burnout_event_time_to_label(string $timeSlot): string
{
    return [
        'M' => 'Mañana',
        'T' => 'Tarde',
        'N' => 'Noche',
    ][$timeSlot] ?? $timeSlot;
}

function burnout_event_attendee_count_expression(): string
{
    return 'COALESCE(COUNT(ra.id), 0)';
}

function burnout_normalize_event_time(string $value): string
{
    $time = strtolower(trim($value));
    $map = [
        'm' => 'M',
        'Mañana' => 'M',
        'mañana' => 'M',
        't' => 'T',
        'tarde' => 'T',
        'n' => 'N',
        'noche' => 'N',
    ];

    if (!isset($map[$time])) {
        throw new RuntimeException('Selecciona un horario válido.');
    }

    return $map[$time];
}

function burnout_read_events(): array
{
    $statement = burnout_pdo()->query(
        'SELECT
            e.id,
            e.event_date,
            e.title,
            e.time_slot,
            e.max_attendees,
            ' . burnout_event_attendee_count_expression() . ' AS attendee_count
         FROM events e
         LEFT JOIN registrations r ON r.event_id = e.id
         LEFT JOIN registration_attendees ra ON ra.registration_id = r.id
         GROUP BY e.id, e.event_date, e.title, e.time_slot, e.max_attendees
         ORDER BY e.event_date ASC, FIELD(e.time_slot, "M", "T", "N"), e.id ASC'
    );

    return array_map(static function (array $event): array {
        return [
            'id' => (int) $event['id'],
            'date' => (string) $event['event_date'],
            'title' => (string) $event['title'],
            'time' => burnout_event_time_to_label((string) $event['time_slot']),
            'timeSlot' => (string) $event['time_slot'],
            'attendeeCount' => (int) $event['attendee_count'],
            'maxAttendees' => max(1, (int) $event['max_attendees']),
            'url' => 'registro.php',
        ];
    }, $statement->fetchAll());
}

function burnout_find_event(int $id): ?array
{
    $statement = burnout_pdo()->prepare('SELECT id FROM events WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $event = $statement->fetch();

    return $event ?: null;
}

function burnout_event_has_registrations(int $id): bool
{
    $statement = burnout_pdo()->prepare('SELECT COUNT(*) FROM registrations WHERE event_id = :id');
    $statement->execute(['id' => $id]);

    return (int) $statement->fetchColumn() > 0;
}

function burnout_event_attendee_count(int $id): int
{
    $statement = burnout_pdo()->prepare(
        'SELECT COALESCE(COUNT(ra.id), 0)
         FROM registrations r
         INNER JOIN registration_attendees ra ON ra.registration_id = r.id
         WHERE r.event_id = :id'
    );
    $statement->execute(['id' => $id]);

    return (int) $statement->fetchColumn();
}

function burnout_admin_event_attendee_count(PDO $pdo, int $id): int
{
    $statement = $pdo->prepare(
        'SELECT COALESCE(COUNT(ra.id), 0)
         FROM registrations r
         INNER JOIN registration_attendees ra ON ra.registration_id = r.id
         WHERE r.event_id = :id'
    );
    $statement->execute(['id' => $id]);

    return (int) $statement->fetchColumn();
}

function burnout_admin_find_event_for_registration(PDO $pdo, int $eventId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, event_date, title, time_slot, max_attendees
         FROM events
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
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
        'turn' => burnout_event_time_to_label((string) $event['time_slot']),
        'max_attendees' => max(1, (int) $event['max_attendees']),
        'attendee_count' => burnout_admin_event_attendee_count($pdo, (int) $event['id']),
    ];
}

function burnout_admin_event_capacity_remaining(array $event): int
{
    return max(
        0,
        max(1, (int) ($event['max_attendees'] ?? 40)) - max(0, (int) ($event['attendee_count'] ?? 0))
    );
}

function burnout_admin_clean_list(array $values): array
{
    return array_map(static function ($value): string {
        return trim((string) $value);
    }, $values);
}

function burnout_admin_valid_dni_letter(string $number, string $letter): bool
{
    $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';

    return $letters[((int) $number) % 23] === strtoupper($letter);
}

function burnout_admin_is_valid_phone(string $phone): bool
{
    if (!preg_match('/^\+?[\d\s-]+$/', $phone)) {
        return false;
    }

    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    return strlen($digits) >= 9;
}

function burnout_admin_is_valid_document(string $document): bool
{
    $value = strtoupper(preg_replace('/[\s-]+/', '', $document) ?? '');

    if (preg_match('/^(\d{8})([A-Z])$/', $value, $matches)) {
        return burnout_admin_valid_dni_letter($matches[1], $matches[2]);
    }

    if (preg_match('/^([XYZ])(\d{7})([A-Z])$/', $value, $matches)) {
        $prefix = ['X' => '0', 'Y' => '1', 'Z' => '2'][$matches[1]];

        return burnout_admin_valid_dni_letter($prefix . $matches[2], $matches[3]);
    }

    return (bool) preg_match('/^(?:[A-Z]{3}\d{6}[A-Z]?|[A-Z]{2}\d{7})$/', $value);
}

function burnout_admin_format_short_date(string $value): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return $value;
    }

    $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    return sprintf('%d %s', (int) $date->format('j'), $months[(int) $date->format('n') - 1]);
}

function burnout_admin_normalize_turn(string $value): string
{
    $turn = strtolower(trim($value));
    $turn = str_replace(['Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã±'], ['a', 'e', 'i', 'o', 'u', 'n'], $turn);

    return $turn;
}

function burnout_admin_turn_hours(string $turn): array
{
    return [
        'manana' => ['open' => '8:00 AM', 'close' => '9:00 AM'],
        'm' => ['open' => '8:00 AM', 'close' => '9:00 AM'],
        'tarde' => ['open' => '15:00 PM', 'close' => '16:00 PM'],
        't' => ['open' => '15:00 PM', 'close' => '16:00 PM'],
        'noche' => ['open' => '20:00 h', 'close' => '21:00 h'],
        'n' => ['open' => '20:00 h', 'close' => '21:00 h'],
    ][burnout_admin_normalize_turn($turn)] ?? ['open' => '8:00 AM', 'close' => '9:00 AM'];
}

function burnout_admin_registration_event_label(array $registration): string
{
    $event = is_array($registration['event'] ?? null) ? $registration['event'] : [];
    $parts = [
        trim((string) ($event['title'] ?? 'Burnout Airsoft')),
        !empty($event['date']) ? burnout_admin_format_short_date((string) $event['date']) : '',
        trim((string) ($event['turn'] ?? '')),
    ];

    return implode(' - ', array_values(array_filter($parts, static function (string $part): bool {
        return trim($part) !== '';
    })));
}

function burnout_admin_build_confirmation_email(array $registration): string
{
    $attendees = $registration['attendees'] ?? [];
    $attendeeLines = array_map(static function (array $attendee): string {
        return (string) $attendee['name'];
    }, $attendees);
    $event = is_array($registration['event'] ?? null) ? $registration['event'] : [];
    $hours = burnout_admin_turn_hours((string) ($event['turn'] ?? ''));

    return sprintf(
        "Tu inscripcion para el evento \"%s\" se ha registrado correctamente.\n\n" .
        "Recuerda que la hora de apertura sera a las %s y la \n" .
        "hora de cierre de puertas a las %s\n\n" .
        "Resumen de los datos enviados:\n" .
        "- Numero de asistentes: %d\n" .
        "- Lista de asistentes:\n%s\n\n" .
        "- Telefono de contacto: %s\n" .
        "- Correo electronico: %s\n" .
        "- Equipo: %s\n\n" .
        "Normativa:\n" .
        "https://drive.google.com/file/d/1ZwLOwiNFrWdmVO7XU9cix1nm4yUjLVK5/view?usp=sharing\n\n" .
        "Por favor, asegurate de leer la normativa. Su incumplimiento podra ser \n" .
        "sancionado por la organizacion, incluyendo la expulsion del terreno de \n" .
        "juego.\n\n" .
        "Gracias por tu inscripcion.\n" .
        "Un saludo.",
        burnout_admin_registration_event_label($registration),
        $hours['open'],
        $hours['close'],
        count($attendees),
        implode("\n", $attendeeLines),
        $registration['phone'],
        $registration['email'],
        $registration['team'] !== '' ? $registration['team'] : '-'
    );
}

function burnout_admin_send_registration_email(array $registration): void
{
    $event = is_array($registration['event'] ?? null) ? $registration['event'] : [];
    $subject = 'Confirmacion de inscripcion - ' . (trim((string) ($event['title'] ?? '')) ?: 'Burnout Airsoft');
    $notificationId = burnout_registration_notification_create(
        (int) $registration['id'],
        (string) $registration['email'],
        $subject,
        burnout_admin_build_confirmation_email($registration)
    );

    burnout_registration_notification_send($notificationId);
}

function burnout_admin_save_late_registration(): array
{
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    if ($eventId === false || $eventId === null) {
        throw new RuntimeException('No se ha recibido el ID de la partida.');
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['telefono'] ?? ''));
    $team = trim((string) ($_POST['equipo'] ?? ''));
    $attendeeNames = burnout_admin_clean_list(is_array($_POST['attendee_name'] ?? null) ? $_POST['attendee_name'] : []);
    $attendeeDocuments = burnout_admin_clean_list(is_array($_POST['attendee_document'] ?? null) ? $_POST['attendee_document'] : []);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Introduce una direccion electronica valida.');
    }

    if (!burnout_admin_is_valid_phone($phone)) {
        throw new RuntimeException('Introduce un telefono de contacto valido con al menos 9 numeros.');
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

        if (!burnout_admin_is_valid_document($document)) {
            throw new RuntimeException('Introduce un DNI, NIE o pasaporte valido para cada asistente.');
        }
    }

    $pdo = burnout_pdo();
    $pdo->beginTransaction();

    try {
        $event = burnout_admin_find_event_for_registration($pdo, (int) $eventId);

        if (!$event) {
            throw new RuntimeException('La partida seleccionada no existe.');
        }

        if (count($attendeeNames) > burnout_admin_event_capacity_remaining($event)) {
            throw new RuntimeException('La inscripcion supera el maximo de aforo de la partida.');
        }

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

function burnout_validate_event_data(string $date, string $title, string $time, string $maxAttendees): array
{
    if ($date === '' || $title === '' || $time === '') {
        throw new RuntimeException('La fecha, el titulo y el horario son obligatorios.');
    }

    if ($maxAttendees === '') {
        $maxAttendees = '40';
    }

    $capacity = filter_var($maxAttendees, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => 500,
        ],
    ]);

    if ($capacity === false) {
        throw new RuntimeException('El aforo debe ser un número entre 1 y 500.');
    }

    $dateTime = DateTime::createFromFormat('Y-m-d', $date);

    if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
        throw new RuntimeException('La fecha debe tener formato YYYY-MM-DD.');
    }

    return [
        'date' => $date,
        'title' => $title,
        'time_slot' => burnout_normalize_event_time($time),
        'max_attendees' => (int) $capacity,
    ];
}

if (!$setupError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!burnout_check_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sesión caducada. Recarga la página e inténtalo de nuevo.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'update') {
            $date = trim((string) ($_POST['date'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $time = trim((string) ($_POST['time'] ?? ''));
            $maxAttendees = trim((string) ($_POST['max_attendees'] ?? '40'));
            $eventData = burnout_validate_event_data($date, $title, $time, $maxAttendees);

            if ($action === 'update') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

                if ($id === false || $id === null || !burnout_find_event((int) $id)) {
                    throw new RuntimeException('El evento seleccionado no existe.');
                }

                $attendeeCount = burnout_event_attendee_count((int) $id);

                if ($eventData['max_attendees'] < $attendeeCount) {
                    throw new RuntimeException(sprintf(
                        'No puedes bajar el aforo por debajo de los %d inscritos actuales.',
                        $attendeeCount
                    ));
                }

                $statement = burnout_pdo()->prepare(
                    'UPDATE events
                     SET event_date = :event_date, title = :title, time_slot = :time_slot, max_attendees = :max_attendees
                     WHERE id = :id'
                );
                $statement->execute([
                    'event_date' => $eventData['date'],
                    'title' => $eventData['title'],
                    'time_slot' => $eventData['time_slot'],
                    'max_attendees' => $eventData['max_attendees'],
                    'id' => $id,
                ]);
                burnout_set_admin_flash('success', 'Evento actualizado correctamente.');
            } else {
                $statement = burnout_pdo()->prepare(
                    'INSERT INTO events (event_date, title, time_slot, max_attendees)
                     VALUES (:event_date, :title, :time_slot, :max_attendees)'
                );
                $statement->execute([
                    'event_date' => $eventData['date'],
                    'title' => $eventData['title'],
                    'time_slot' => $eventData['time_slot'],
                    'max_attendees' => $eventData['max_attendees'],
                ]);
                burnout_set_admin_flash('success', 'Evento creado correctamente.');
            }
        } elseif ($action === 'add_registration') {
            $registration = burnout_admin_save_late_registration();

            try {
                burnout_admin_send_registration_email($registration);
            } catch (Throwable $emailException) {
                error_log('No se ha podido enviar el correo de confirmacion del registro ' . $registration['id'] . ': ' . $emailException->getMessage());
            }

            burnout_set_admin_flash('success', 'Registro añadido correctamente.');
        } elseif ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id === false || $id === null || !burnout_find_event((int) $id)) {
                throw new RuntimeException('El evento seleccionado no existe.');
            }

            if (burnout_event_has_registrations((int) $id)) {
                throw new RuntimeException('No se pueden eliminar eventos con asistentes, eliminar los registros primero.');
            }

            $statement = burnout_pdo()->prepare('DELETE FROM events WHERE id = :id');
            $statement->execute(['id' => $id]);
            burnout_set_admin_flash('success', 'Evento eliminado correctamente.');
        }
    } catch (Throwable $exception) {
        burnout_set_admin_flash('error', $exception->getMessage());
    }

    header('Location: admin_partidas.php');
    exit;
}

try {
    $events = $setupError ? [] : burnout_read_events();
} catch (Throwable $exception) {
    $events = [];
    $error = $exception->getMessage();
}

$csrfToken = burnout_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Gestión Partidas - Burnout Airsoft</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/resources/logoBurnout-4.png" />
    <link rel="stylesheet" href="assets/css/plugins.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/partidas.css">
    <link rel="stylesheet" href="assets/css/admin.css">
  </head>
  <body>
    <div class="ms-main-container">
      <div class="ms-preloader"></div>
      <header class="ms-header">
        <nav class="ms-nav">
          <div class="ms-logo">
            <a class="logonav" href="./" data-type="page-transition">
              <div class="logo-dark"><img src="images/resources/logoBurnout-2.png" alt="logo image"></div>
              <div class="logo-light current"><img src="images/resources/logoBurnout-2.png" alt="logo image"></div>
            </a>
          </div>
          <button class="hamburger" type="button" data-toggle="navigation">
          <span class="hamburger-box">
            <span class="hamburger-label">menu</span>
            <span class="hamburger-inner"></span>
          </span>
          </button>
          <div class="height-full-viewport">
            <ul class="ms-navbar">
              <li class="nav-item">
                <a href="./" data-type="page-transition">
                  <span class="ms-btn">Inicio</span>
                  <span class="nav-item__label">Vuelve a la pagina principal</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="galeria.html" data-type="page-transition">
                  <span class="ms-btn">Galeria</span>
                  <span class="nav-item__label">Ver nuestros momentos</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="burnout.html" data-type="page-transition">
                  <span class="ms-btn">Nosotros</span>
                  <span class="nav-item__label">Conoce al equipo</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="campo.html" data-type="page-transition">
                  <span class="ms-btn">Campo</span>
                  <span class="nav-item__label">Descubre el terreno</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="partidas.html" data-type="page-transition">
                  <span class="ms-btn">Partidas</span>
                  <span class="nav-item__label">Calendario de partidas</span>
                </a>
              </li>
            </ul>
          </div>
        </nav>
      </header>
      <main class="ms-container admin-page">
        <div class="ms-section__block">
          <div class="admin-header">
            <div class="admin-header__title">
              <span class="admin-kicker">Burnout Airsoft</span>
              <h1>Gestión Partidas</h1>
            </div>
            <?php if ($adminUser): ?>
              <div class="admin-header-actions">
                <form class="admin-logout" method="post" action="admin.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="logout">
                  <button type="submit">Cerrar sesión</button>
                </form>
                <a class="admin-back-link" href="admin.php">Volver al panel</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="ms-section__block admin-partidas-section">
          <?php if ($setupError): ?>
            <div class="admin-login-error" role="alert"><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php else: ?>
            <?php if ($message): ?>
              <div class="admin-message admin-message--success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
              <div class="admin-message admin-message--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="partidas-layout admin-partidas-layout">
              <div class="partidas-calendar" aria-live="polite">
                <div class="partidas-toolbar">
                  <button class="partidas-nav" type="button" id="prevMonth" aria-label="Mes anterior">&lt;</button>
                  <h2 id="calendarTitle">Mayo 2026</h2>
                  <button class="partidas-nav" type="button" id="nextMonth" aria-label="Mes siguiente">&gt;</button>
                </div>
                <div class="partidas-weekdays" aria-hidden="true">
                  <span>Lun</span>
                  <span>Mar</span>
                  <span>Mie</span>
                  <span>Jue</span>
                  <span>Vie</span>
                  <span>Sab</span>
                  <span>Dom</span>
                </div>
                <div class="partidas-grid" id="calendarGrid"></div>
              </div>
              <aside class="partidas-panel admin-partidas-panel">
                <h2>Gestionar partidas</h2>
                <button class="admin-partidas-action" type="button" data-partidas-modal-open="create">
                  <span>Crear evento</span>
                  <svg aria-hidden="true" viewBox="0 0 24 24">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                  </svg>
                </button>
                <button class="admin-partidas-action" type="button" data-partidas-modal-open="delete">
                  <span>Borrar evento</span>
                  <svg aria-hidden="true" viewBox="0 0 24 24">
                    <path d="M3 6h18"></path>
                    <path d="M8 6V4h8v2"></path>
                    <path d="M19 6l-1 14H6L5 6"></path>
                    <path d="M10 11v5"></path>
                    <path d="M14 11v5"></path>
                  </svg>
                </button>
              </aside>
            </div>

            <div class="admin-gallery-modal" id="createEventModal" aria-hidden="true">
              <div class="admin-gallery-modal__overlay" data-partidas-modal-close></div>
              <div class="admin-gallery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="createEventTitle">
                <div class="admin-gallery-modal__header">
                  <h2 id="createEventTitle">Crear evento</h2>
                  <button type="button" data-partidas-modal-close aria-label="Cerrar ventana">x</button>
                </div>
                <form class="admin-gallery-form" method="post" action="admin_partidas.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="add">
                  <div class="admin-login-field">
                    <label for="date">Fecha</label>
                    <input id="date" name="date" type="date" required>
                  </div>
                  <div class="admin-login-field">
                    <label for="title">Titulo</label>
                    <input id="title" name="title" type="text" required placeholder="">
                  </div>
                  <div class="admin-login-field">
                    <label for="maxAttendees">Aforo máximo</label>
                    <input id="maxAttendees" name="max_attendees" type="number" min="1" max="500" step="1" value="40" required>
                  </div>
                  <div class="admin-login-field">
                    <label for="time">Horario</label>
                    <div class="admin-time-options" role="radiogroup" aria-label="Horario">
                      <label>
                          <input type="radio" name="time" value="M" required checked>
                        <span>Mañana</span>
                      </label>
                      <label>
                          <input type="radio" name="time" value="T" required>
                        <span>Tarde</span>
                      </label>
                      <label>
                          <input type="radio" name="time" value="N" required>
                        <span>Noche</span>
                      </label>
                    </div>
                  </div>
                  <div class="admin-gallery-modal__actions">
                    <button class="admin-login-submit" type="submit">Guardar evento</button>
                  </div>
                </form>
              </div>
            </div>

            <div class="admin-gallery-modal" id="deleteEventModal" aria-hidden="true">
              <div class="admin-gallery-modal__overlay" data-partidas-modal-close></div>
              <div class="admin-gallery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="deleteEventTitle">
                <div class="admin-gallery-modal__header">
                  <h2 id="deleteEventTitle">Borrar evento</h2>
                  <button type="button" data-partidas-modal-close aria-label="Cerrar ventana">x</button>
                </div>
                <?php if (!$events): ?>
                  <div class="admin-empty">No hay eventos creados.</div>
                <?php else: ?>
                  <div class="admin-partidas-delete-list">
                    <?php foreach ($events as $event): ?>
                      <article class="admin-partidas-delete-item">
                        <div>
                          <strong><?= htmlspecialchars((string) ($event['title'] ?? 'Sin titulo'), ENT_QUOTES, 'UTF-8') ?></strong>
                          <span><?= htmlspecialchars((string) ($event['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($event['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= (int) ($event['attendeeCount'] ?? 0) ?>/<?= (int) ($event['maxAttendees'] ?? 40) ?></span>
                        </div>
                        <form method="post" action="admin_partidas.php">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= (int) ($event['id'] ?? 0) ?>">
                          <button class="admin-delete-icon-button" type="submit" aria-label="Eliminar evento">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                              <path d="M3 6h18"></path>
                              <path d="M8 6V4h8v2"></path>
                              <path d="M19 6l-1 14H6L5 6"></path>
                              <path d="M10 11v5"></path>
                              <path d="M14 11v5"></path>
                            </svg>
                          </button>
                        </form>
                      </article>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="admin-gallery-modal" id="editEventModal" aria-hidden="true">
              <div class="admin-gallery-modal__overlay" data-partidas-modal-close></div>
              <div class="admin-gallery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="editEventTitle">
                <div class="admin-gallery-modal__header">
                  <h2 id="editEventTitle">Modificar evento</h2>
                  <button type="button" data-partidas-modal-close aria-label="Cerrar ventana">x</button>
                </div>
                <form class="admin-gallery-form" method="post" action="admin_partidas.php" id="editEventForm">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="id" id="editEventId" value="">
                  <div class="admin-login-field">
                    <label for="editDate">Fecha</label>
                    <input id="editDate" name="date" type="date" required>
                  </div>
                  <div class="admin-login-field">
                    <label for="editTitle">Titulo</label>
                    <input id="editTitle" name="title" type="text" required>
                  </div>
                  <div class="admin-login-field">
                    <label for="editMaxAttendees">Aforo máximo</label>
                    <input id="editMaxAttendees" name="max_attendees" type="number" min="1" max="500" step="1" required>
                  </div>
                  <div class="admin-login-field">
                    <label for="editTimeManana">Horario</label>
                    <div class="admin-time-options" role="radiogroup" aria-label="Horario">
                      <label>
                        <input id="editTimeManana" type="radio" name="time" value="M" required>
                        <span>Mañana</span>
                      </label>
                      <label>
                        <input id="editTimeTarde" type="radio" name="time" value="T" required>
                        <span>Tarde</span>
                      </label>
                      <label>
                        <input id="editTimeNoche" type="radio" name="time" value="N" required>
                        <span>Noche</span>
                      </label>
                    </div>
                  </div>
                  <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split admin-event-edit-actions">
                    <button class="admin-danger-submit" type="submit" name="action" value="delete">Borrar evento</button>
                    <button class="admin-secondary-submit admin-event-registration-button" type="button" data-open-late-registration>A&ntilde;adir registro</button>
                    <button class="admin-login-submit" type="submit" name="action" value="update">Guardar evento</button>
                  </div>
                </form>
              </div>
            </div>

            <div class="admin-gallery-modal" id="lateRegistrationModal" aria-hidden="true">
              <div class="admin-gallery-modal__overlay" data-partidas-modal-close></div>
              <div class="admin-gallery-modal__dialog admin-late-registration-dialog" role="dialog" aria-modal="true" aria-labelledby="lateRegistrationTitle">
                <div class="admin-gallery-modal__header">
                  <h2 id="lateRegistrationTitle">A&ntilde;adir registro</h2>
                  <button type="button" data-partidas-modal-close aria-label="Cerrar ventana">x</button>
                </div>
                <div class="admin-gallery-modal__body admin-late-registration-summary">
                  <p><strong>Evento:</strong> <span id="lateRegistrationEventTitle">Selecciona un evento</span></p>
                  <p><strong>Fecha:</strong> <span id="lateRegistrationEventDate">-</span></p>
                  <p><strong>Turno:</strong> <span id="lateRegistrationEventTime">-</span></p>
                </div>
                <form class="admin-gallery-form admin-late-registration-form" method="post" action="admin_partidas.php" id="lateRegistrationForm" novalidate>
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="add_registration">
                  <input type="hidden" name="event_id" id="lateRegistrationEventId" value="">
                  <div class="admin-login-field">
                    <label for="lateRegistrationEmail">Direcci&oacute;n electr&oacute;nica *</label>
                    <input id="lateRegistrationEmail" name="email" type="email" autocomplete="email" required placeholder="nombre@dominio.com">
                    <span class="admin-registration-error" data-error-for="lateRegistrationEmail"></span>
                  </div>
                  <div class="admin-login-field">
                    <label for="lateRegistrationPhone">Tel&eacute;fono de contacto *</label>
                    <input id="lateRegistrationPhone" name="telefono" type="tel" autocomplete="tel" inputmode="tel" required placeholder="600000000">
                    <span class="admin-registration-error" data-error-for="lateRegistrationPhone"></span>
                  </div>
                  <div class="admin-login-field">
                    <label for="lateRegistrationAttendees">Asistentes *</label>
                    <select id="lateRegistrationAttendees" name="asistentes" required>
                      <option value="">Selecciona asistentes</option>
                      <option value="1">1</option>
                      <option value="2">2</option>
                      <option value="3">3</option>
                      <option value="4">4</option>
                      <option value="5">5</option>
                      <option value="6">6</option>
                      <option value="7">7</option>
                      <option value="8">8</option>
                      <option value="9">9</option>
                      <option value="10">10</option>
                    </select>
                    <span class="admin-registration-error" data-error-for="lateRegistrationAttendees"></span>
                  </div>
                  <div class="admin-registration-attendees" id="lateRegistrationAttendeeFields" aria-live="polite"></div>
                  <div class="admin-login-field">
                    <label for="lateRegistrationTeam">Equipo</label>
                    <input id="lateRegistrationTeam" name="equipo" type="text" autocomplete="organization" placeholder="Nombre del equipo">
                  </div>
                  <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split">
                    <button class="admin-secondary-submit" type="reset">Limpiar</button>
                    <button class="admin-login-submit" type="submit">Guardar registro</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </main>
      <footer>
        <div class="ms-footer">
          <div class="copyright" data-copyright-start="2025">Copyright © 2025-2026. Designed by Alex Serret</div>
          <span class="footer-links">
            <a href="privacidad.html" data-type="page-transition">Política de Privacidad de datos</a>
          </span>
          <ul class="socials">
            <li><a href="#" class="socicon-instagram"></a></li>
            <li><a href="#" class="socicon-youtube"></a></li>
          </ul>
        </div>
      </footer>
    </div>
    <script>
      window.BurnoutAdminEvents = <?= json_encode($events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
    </script>
    <script type="text/javascript" src="assets/js/jquery-3.7.1.min.js"></script>
    <script type="text/javascript" src='assets/js/plugins.min.js'></script>
    <script type="text/javascript" src="assets/js/main.js"></script>
    <script type="text/javascript" src="assets/js/admin_partidas.js"></script>
  </body>
</html>
