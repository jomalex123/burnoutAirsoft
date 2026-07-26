<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/registration_notifications.php';

$adminUser = null;
$setupError = '';
$message = '';
$error = '';
$loadError = '';
$registrations = [];

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

function admin_registros_event_time_to_label(string $timeSlot): string
{
    return [
        'M' => 'Mañana',
        'T' => 'Tarde',
        'N' => 'Noche',
    ][$timeSlot] ?? $timeSlot;
}

function admin_registros_format_date(string $value): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return $value;
    }

    return $date->format('d/m/Y');
}

function admin_registros_read_all(): array
{
    $pdo = burnout_pdo();
    $statement = $pdo->query(
        'SELECT
            r.id,
            r.email,
            r.phone,
            r.team_name,
            r.created_at,
            e.title AS event_title,
            e.event_date,
            e.time_slot,
            COUNT(ra.id) AS attendee_count
         FROM registrations r
         INNER JOIN events e ON e.id = r.event_id
         LEFT JOIN registration_attendees ra ON ra.registration_id = r.id
         GROUP BY r.id, r.email, r.phone, r.team_name, r.created_at, e.title, e.event_date, e.time_slot
         ORDER BY r.created_at DESC, r.id DESC'
    );
    $registrations = $statement->fetchAll();
    $attendeesByRegistration = admin_registros_read_attendees($pdo, array_column($registrations, 'id'));
    $notificationsByRegistration = admin_registros_read_notifications($pdo, array_column($registrations, 'id'));

    return array_map(static function (array $registration) use ($attendeesByRegistration, $notificationsByRegistration): array {
        $registrationId = (int) $registration['id'];

        return [
            'id' => $registrationId,
            'created_at' => (string) $registration['created_at'],
            'event' => (string) $registration['event_title'],
            'event_date' => (string) $registration['event_date'],
            'date' => admin_registros_format_date((string) $registration['event_date']),
            'turn' => admin_registros_event_time_to_label((string) $registration['time_slot']),
            'time_slot' => (string) $registration['time_slot'],
            'email' => (string) $registration['email'],
            'phone' => (string) $registration['phone'],
            'team' => (string) ($registration['team_name'] ?? ''),
            'attendee_count' => (int) $registration['attendee_count'],
            'attendees' => $attendeesByRegistration[$registrationId] ?? [],
            'notification' => $notificationsByRegistration[$registrationId] ?? admin_registros_empty_notification(),
        ];
    }, $registrations);
}

function admin_registros_read_attendees(PDO $pdo, array $registrationIds): array
{
    $ids = array_values(array_unique(array_map('intval', $registrationIds)));

    if (!$ids) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(
        'SELECT registration_id, full_name, document
         FROM registration_attendees
         WHERE registration_id IN (' . $placeholders . ')
         ORDER BY registration_id ASC, id ASC'
    );
    $statement->execute($ids);
    $attendees = [];

    foreach ($statement->fetchAll() as $attendee) {
        $registrationId = (int) $attendee['registration_id'];
        $attendees[$registrationId][] = [
            'name' => (string) $attendee['full_name'],
            'document' => (string) $attendee['document'],
        ];
    }

    return $attendees;
}

function admin_registros_attendee_documents(array $attendees): string
{
    return implode(' ', array_map(static function (array $attendee): string {
        return $attendee['document'];
    }, $attendees));
}

function admin_registros_empty_notification(): array
{
    return [
        'id' => null,
        'status' => 'missing',
        'recipient_email' => '',
        'subject' => '',
        'sent_at' => '',
        'created_at' => '',
        'updated_at' => '',
        'error_message' => '',
        'attempts' => 0,
    ];
}

function admin_registros_read_notifications(PDO $pdo, array $registrationIds): array
{
    burnout_registration_notifications_table();

    $ids = array_values(array_unique(array_map('intval', $registrationIds)));

    if (!$ids) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(
        'SELECT id, registration_id, recipient_email, subject, status, error_message, sent_at, created_at, updated_at
         FROM registration_email_notifications
         WHERE registration_id IN (' . $placeholders . ')
         ORDER BY registration_id ASC, id DESC'
    );
    $statement->execute($ids);
    $notifications = [];

    foreach ($statement->fetchAll() as $notification) {
        $registrationId = (int) $notification['registration_id'];

        if (!isset($notifications[$registrationId])) {
            $notifications[$registrationId] = [
                'id' => (int) $notification['id'],
                'status' => (string) $notification['status'],
                'recipient_email' => (string) $notification['recipient_email'],
                'subject' => (string) $notification['subject'],
                'sent_at' => (string) ($notification['sent_at'] ?? ''),
                'created_at' => (string) $notification['created_at'],
                'updated_at' => (string) $notification['updated_at'],
                'error_message' => (string) ($notification['error_message'] ?? ''),
                'attempts' => 0,
            ];
        }

        $notifications[$registrationId]['attempts']++;
    }

    return $notifications;
}

function admin_registros_notification_status_label(string $status): string
{
    return [
        'sent' => 'Enviada',
        'failed' => 'Errónea',
        'pending' => 'Pendiente',
        'missing' => 'Sin registro',
    ][$status] ?? $status;
}

function admin_registros_delete(int $registrationId): void
{
    $pdo = burnout_pdo();
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare('SELECT id FROM registrations WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $registrationId]);

        if (!$statement->fetch()) {
            throw new RuntimeException('El registro seleccionado no existe.');
        }

        $attendees = $pdo->prepare('DELETE FROM registration_attendees WHERE registration_id = :id');
        $attendees->execute(['id' => $registrationId]);

        $registration = $pdo->prepare('DELETE FROM registrations WHERE id = :id');
        $registration->execute(['id' => $registrationId]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

if (!$setupError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!burnout_check_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sesión caducada. Recarga la página e inténtalo de nuevo.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id === false || $id === null) {
                throw new RuntimeException('El registro seleccionado no existe.');
            }

            admin_registros_delete((int) $id);
            burnout_set_admin_flash('success', 'Registro eliminado correctamente.');
        } elseif ($action === 'resend_notification') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id === false || $id === null) {
                throw new RuntimeException('El registro seleccionado no existe.');
            }

            burnout_registration_notification_resend_latest((int) $id);
            burnout_set_admin_flash('success', 'Notificación reenviada correctamente.');
        }
    } catch (Throwable $exception) {
        burnout_set_admin_flash('error', $exception->getMessage());
    }

    header('Location: admin_registros.php');
    exit;
}

try {
    if (!$setupError) {
        $registrations = admin_registros_read_all();
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $loadError = 'No se han podido cargar los registros.';
}

$csrfToken = burnout_csrf_token();
$attendeeTotal = array_sum(array_map(static function (array $registration): int {
    return (int) $registration['attendee_count'];
}, $registrations));
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Gestión Registros - Burnout Airsoft</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/resources/logoBurnout-4.png" />
    <link rel="stylesheet" href="assets/css/plugins.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
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
              <h1>Gestión Registros</h1>
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
        <div class="ms-section__block">
          <?php if ($setupError): ?>
            <div class="admin-login-error" role="alert"><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php elseif ($loadError): ?>
            <div class="admin-message admin-message--error" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php else: ?>
            <?php if ($message): ?>
              <div class="admin-message admin-message--success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
              <div class="admin-message admin-message--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <section class="admin-table-wrap admin-table-wrap--full">
              <div class="admin-gallery-toolbar">
                <h2>Todos los registros</h2>
                <?php if ($registrations): ?>
                  <div class="admin-toolbar-actions">
                    <div class="admin-export-menu">
                      <button class="admin-icon-button" type="button" id="registrationExportToggle" aria-label="Exportar asistentes" aria-expanded="false" aria-controls="registrationExportOptions">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                          <path d="M12 3v12"></path>
                          <path d="m7 10 5 5 5-5"></path>
                          <path d="M5 21h14"></path>
                        </svg>
                      </button>
                      <div class="admin-export-menu__options" id="registrationExportOptions" hidden>
                        <button type="button" id="exportRegistrationsCsv">Exportar CSV</button>
                        <button type="button" id="exportRegistrationsPdf">Exportar PDF</button>
                      </div>
                    </div>
                    <button class="admin-filter-button" type="button" data-registros-modal-open="registrationFiltersModal">Filtrar</button>
                  </div>
                <?php endif; ?>
              </div>
              <?php if (!$registrations): ?>
                <div class="admin-empty">No hay registros guardados.</div>
              <?php else: ?>
                <div class="admin-table-controls">
                  <span class="admin-table-count" id="registrationCount"><?= count($registrations) ?> registros</span>
                  <span class="admin-table-count" id="registrationAttendeeCount"><?= (int) $attendeeTotal ?> asistentes</span>
                </div>
                <div class="admin-table-scroll">
                  <table class="admin-table admin-table--wide" id="registrationsTable">
                    <thead>
                      <tr>
                        <th><button class="admin-sort-button" type="button" data-sort-key="event" aria-label="Ordenar por evento">Evento</button></th>
                        <th><button class="admin-sort-button" type="button" data-sort-key="date" aria-label="Ordenar por fecha">Fecha</button></th>
                        <th><button class="admin-sort-button" type="button" data-sort-key="turn" aria-label="Ordenar por turno">Turno</button></th>
                        <th><button class="admin-sort-button" type="button" data-sort-key="email" aria-label="Ordenar por email">Email</button></th>
                        <th><button class="admin-sort-button" type="button" data-sort-key="phone" aria-label="Ordenar por teléfono">Teléfono</button></th>
                        <th><button class="admin-sort-button" type="button" data-sort-key="team" aria-label="Ordenar por equipo">Equipo</button></th>
                        <th>Asistentes</th>
                        <th><button class="admin-sort-button" type="button" data-sort-key="created" aria-label="Ordenar por fecha de registro">Registro</button></th>
                        <th class="admin-table-icon-column" aria-label="Notificación"></th>
                        <th class="admin-table-delete-column" aria-label="Eliminar"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($registrations as $registration): ?>
                        <tr
                          data-event="<?= htmlspecialchars(strtolower($registration['event']), ENT_QUOTES, 'UTF-8') ?>"
                          data-date="<?= htmlspecialchars($registration['event_date'], ENT_QUOTES, 'UTF-8') ?>"
                          data-date-label="<?= htmlspecialchars(strtolower($registration['date']), ENT_QUOTES, 'UTF-8') ?>"
                          data-turn="<?= htmlspecialchars($registration['time_slot'], ENT_QUOTES, 'UTF-8') ?>"
                          data-email="<?= htmlspecialchars(strtolower($registration['email']), ENT_QUOTES, 'UTF-8') ?>"
                          data-phone="<?= htmlspecialchars(strtolower($registration['phone']), ENT_QUOTES, 'UTF-8') ?>"
                          data-team="<?= htmlspecialchars(strtolower($registration['team']), ENT_QUOTES, 'UTF-8') ?>"
                          data-attendee-count="<?= (int) $registration['attendee_count'] ?>"
                          data-document="<?= htmlspecialchars(strtolower(admin_registros_attendee_documents($registration['attendees'])), ENT_QUOTES, 'UTF-8') ?>"
                          data-created="<?= htmlspecialchars($registration['created_at'], ENT_QUOTES, 'UTF-8') ?>"
                          data-registration-id="<?= (int) $registration['id'] ?>"
                        >
                          <td><?= htmlspecialchars($registration['event'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['date'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['turn'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['email'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['phone'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($registration['team'] !== '' ? $registration['team'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                          <td>
                            <button class="admin-table-action" type="button" data-registros-modal-open="registrationAttendees<?= (int) $registration['id'] ?>">
                              VER
                            </button>
                          </td>
                          <td><?= htmlspecialchars($registration['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="admin-table-icon-column">
                            <button
                              class="admin-notification-icon-button is-<?= htmlspecialchars((string) $registration['notification']['status'], ENT_QUOTES, 'UTF-8') ?>"
                              type="button"
                              data-registros-modal-open="registrationNotification<?= (int) $registration['id'] ?>"
                              aria-label="Ver notificaciones"
                            >
                              <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M4 5h16v12H7l-3 3V5Z"></path>
                                <path d="M8 9h8"></path>
                                <path d="M8 13h5"></path>
                              </svg>
                            </button>
                          </td>
                          <td class="admin-table-delete-column">
                            <form class="admin-delete-registration-form" method="post" action="admin_registros.php" data-registration-delete-form>
                              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int) $registration['id'] ?>">
                              <button class="admin-delete-icon-button" type="submit" aria-label="Eliminar registro">
                                <svg aria-hidden="true" viewBox="0 0 24 24">
                                  <path d="M3 6h18"></path>
                                  <path d="M8 6V4h8v2"></path>
                                  <path d="M19 6l-1 14H6L5 6"></path>
                                  <path d="M10 11v5"></path>
                                  <path d="M14 11v5"></path>
                                </svg>
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  </div>
                <?php foreach ($registrations as $registration): ?>
                  <?php $notification = $registration['notification']; ?>
                  <div class="admin-gallery-modal" id="registrationAttendees<?= (int) $registration['id'] ?>" aria-hidden="true">
                    <div class="admin-gallery-modal__overlay" data-registros-modal-close></div>
                    <div class="admin-gallery-modal__dialog admin-registrations-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="registrationAttendeesTitle<?= (int) $registration['id'] ?>">
                      <div class="admin-gallery-modal__header">
                        <h2 id="registrationAttendeesTitle<?= (int) $registration['id'] ?>">Asistentes</h2>
                        <button type="button" data-registros-modal-close aria-label="Cerrar ventana">x</button>
                      </div>
                      <div class="admin-registrations-modal__meta">
                        <strong><?= htmlspecialchars($registration['event'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($registration['date'] . ' - ' . $registration['turn'], ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                      <?php if (!$registration['attendees']): ?>
                        <div class="admin-empty">No hay asistentes guardados para este registro.</div>
                      <?php else: ?>
                        <div class="admin-table-scroll">
                          <table class="admin-table admin-registrations-attendees-table">
                            <thead>
                              <tr>
                                <th>N. asistente</th>
                                <th>Nombre completo</th>
                                <th>Documento</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($registration['attendees'] as $index => $attendee): ?>
                                <tr>
                                  <td><?= $index + 1 ?></td>
                                  <td><?= htmlspecialchars($attendee['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><?= htmlspecialchars($attendee['document'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="admin-gallery-modal" id="registrationNotification<?= (int) $registration['id'] ?>" aria-hidden="true">
                    <div class="admin-gallery-modal__overlay" data-registros-modal-close></div>
                    <div class="admin-gallery-modal__dialog admin-registrations-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="registrationNotificationTitle<?= (int) $registration['id'] ?>">
                      <div class="admin-gallery-modal__header">
                        <h2 id="registrationNotificationTitle<?= (int) $registration['id'] ?>">Notificación</h2>
                        <button type="button" data-registros-modal-close aria-label="Cerrar ventana">x</button>
                      </div>
                      <dl class="admin-notification-detail">
                        <div>
                          <dt>Estado</dt>
                          <dd><?= htmlspecialchars(admin_registros_notification_status_label((string) $notification['status']), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                          <dt>Destinatario</dt>
                          <dd><?= htmlspecialchars($notification['recipient_email'] !== '' ? $notification['recipient_email'] : '-', ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                          <dt>Enviada</dt>
                          <dd><?= htmlspecialchars($notification['sent_at'] !== '' ? $notification['sent_at'] : '-', ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                          <dt>Último intento</dt>
                          <dd><?= htmlspecialchars($notification['updated_at'] !== '' ? $notification['updated_at'] : '-', ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                      </dl>
                      <?php if ($notification['status'] === 'failed'): ?>
                        <form class="admin-gallery-modal__actions" method="post" action="admin_registros.php">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="resend_notification">
                          <input type="hidden" name="id" value="<?= (int) $registration['id'] ?>">
                          <button class="admin-login-submit" type="submit">Reenviar notificación</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div class="admin-gallery-modal" id="registrationFiltersModal" aria-hidden="true">
                  <div class="admin-gallery-modal__overlay" data-registros-modal-close></div>
                  <div class="admin-gallery-modal__dialog admin-registrations-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="registrationFiltersTitle">
                    <div class="admin-gallery-modal__header">
                      <h2 id="registrationFiltersTitle">Filtrar registros</h2>
                      <button type="button" data-registros-modal-close aria-label="Cerrar ventana">x</button>
                    </div>
                    <form class="admin-gallery-form" id="registrationFiltersForm">
                      <div class="admin-login-field">
                        <label for="filterEvent">Evento</label>
                        <input id="filterEvent" name="event" type="text" autocomplete="off">
                      </div>
                      <div class="admin-login-field">
                        <label for="filterDate">Fecha</label>
                        <input id="filterDate" name="date" type="date">
                      </div>
                      <div class="admin-login-field admin-login-field--compact">
                        <label for="filterTurn">Turno</label>
                        <select id="filterTurn" name="turn">
                          <option value="">Todos</option>
                          <option value="M">Ma&ntilde;ana</option>
                          <option value="T">Tarde</option>
                          <option value="N">Noche</option>
                        </select>
                      </div>
                      <div class="admin-login-field">
                        <label for="filterEmail">Email</label>
                        <input id="filterEmail" name="email" type="text" autocomplete="off">
                      </div>
                      <div class="admin-login-field">
                        <label for="filterPhone">Teléfono</label>
                        <input id="filterPhone" name="phone" type="text" autocomplete="off">
                      </div>
                      <div class="admin-login-field">
                        <label for="filterTeam">Equipo</label>
                        <input id="filterTeam" name="team" type="text" autocomplete="off">
                      </div>
                      <div class="admin-login-field">
                        <label for="filterDocument">Número Documento</label>
                        <input id="filterDocument" name="document" type="text" autocomplete="off">
                      </div>
                      <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split">
                        <button class="admin-danger-submit" type="reset">Limpiar filtros</button>
                        <button class="admin-login-submit" type="submit">Aplicar filtros</button>
                      </div>
                    </form>
                  </div>
                </div>
                <div class="admin-gallery-modal" id="registrationDeleteConfirmModal" aria-hidden="true">
                  <div class="admin-gallery-modal__overlay" data-registration-delete-cancel></div>
                  <div class="admin-gallery-modal__dialog admin-registrations-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="registrationDeleteConfirmTitle">
                    <div class="admin-gallery-modal__header">
                      <h2 id="registrationDeleteConfirmTitle">Eliminar registro</h2>
                      <button type="button" data-registration-delete-cancel aria-label="Cerrar ventana">x</button>
                    </div>
                    <p class="admin-confirmation-text">Vas a eliminar el registro y todos sus asistentes. Esta acción no se puede deshacer.</p>
                    <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split">
                      <button class="admin-back-link" type="button" data-registration-delete-cancel>Cancelar</button>
                      <button class="admin-danger-submit" type="button" id="confirmRegistrationDelete">Eliminar registro</button>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </section>
          <?php endif; ?>
        </div>
      </main>
      <footer>
        <div class="ms-footer">
          <div class="copyright" data-copyright-start="2025">Copyright &copy; 2025-2026. Designed by Alex Serret</div>
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
    <script type="text/javascript" src="assets/js/jquery-3.7.1.min.js"></script>
    <script type="text/javascript" src='assets/js/plugins.min.js'></script>
    <script type="text/javascript" src="assets/js/main.js"></script>
    <script type="text/javascript" src="assets/js/vendors/pdf-lib.min.js"></script>
    <script type="text/javascript" src="assets/js/admin_registros.js"></script>
  </body>
</html>
