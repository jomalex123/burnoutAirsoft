<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$adminUser = null;
$setupError = '';
$message = '';
$error = '';

try {
    $adminUser = burnout_current_admin();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $setupError = 'No se ha podido validar la sesion de administracion.';
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
        throw new RuntimeException('Selecciona un horario valido.');
    }

    return $map[$time];
}

function burnout_read_events(): array
{
    $statement = burnout_pdo()->query(
        'SELECT id, event_date, title, time_slot
         FROM events
         ORDER BY event_date ASC, FIELD(time_slot, "M", "T", "N"), id ASC'
    );

    return array_map(static function (array $event): array {
        return [
            'id' => (int) $event['id'],
            'date' => (string) $event['event_date'],
            'title' => (string) $event['title'],
            'time' => burnout_event_time_to_label((string) $event['time_slot']),
            'timeSlot' => (string) $event['time_slot'],
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

function burnout_validate_event_data(string $date, string $title, string $time): array
{
    if ($date === '' || $title === '' || $time === '') {
        throw new RuntimeException('La fecha, el titulo y el horario son obligatorios.');
    }

    $dateTime = DateTime::createFromFormat('Y-m-d', $date);

    if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
        throw new RuntimeException('La fecha debe tener formato YYYY-MM-DD.');
    }

    return [
        'date' => $date,
        'title' => $title,
        'time_slot' => burnout_normalize_event_time($time),
    ];
}

if (!$setupError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!burnout_check_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sesion caducada. Recarga la pagina e intentalo de nuevo.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'update') {
            $date = trim((string) ($_POST['date'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $time = trim((string) ($_POST['time'] ?? ''));
            $eventData = burnout_validate_event_data($date, $title, $time);

            if ($action === 'update') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

                if ($id === false || $id === null || !burnout_find_event((int) $id)) {
                    throw new RuntimeException('El evento seleccionado no existe.');
                }

                $statement = burnout_pdo()->prepare(
                    'UPDATE events
                     SET event_date = :event_date, title = :title, time_slot = :time_slot
                     WHERE id = :id'
                );
                $statement->execute([
                    'event_date' => $eventData['date'],
                    'title' => $eventData['title'],
                    'time_slot' => $eventData['time_slot'],
                    'id' => $id,
                ]);
                burnout_set_admin_flash('success', 'Evento actualizado correctamente.');
            } else {
                $statement = burnout_pdo()->prepare(
                    'INSERT INTO events (event_date, title, time_slot)
                     VALUES (:event_date, :title, :time_slot)'
                );
                $statement->execute([
                    'event_date' => $eventData['date'],
                    'title' => $eventData['title'],
                    'time_slot' => $eventData['time_slot'],
                ]);
                burnout_set_admin_flash('success', 'Evento creado correctamente.');
            }
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
    <title>Gestion Partidas - Burnout Airsoft</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/resources/logoBurnout-3.png" />
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
                <a href="partidas.html" data-type="page-transition">
                  <span class="ms-btn">Partidas</span>
                  <span class="nav-item__label">Calendario de partidas</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="admin.php" data-type="page-transition">
                  <span class="ms-btn">Admin</span>
                  <span class="nav-item__label">Panel de administracion</span>
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
              <h1>Gestion Partidas</h1>
            </div>
            <?php if ($adminUser): ?>
              <div class="admin-header-actions">
                <form class="admin-logout" method="post" action="admin.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="logout">
                  <button type="submit">Cerrar sesion</button>
                </form>
                <a class="admin-back-link" href="admin.php">Volver al panel</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="ms-section__block">
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
                          <span><?= htmlspecialchars((string) ($event['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($event['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
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
                    <label for="editTimeMañana">Horario</label>
                    <div class="admin-time-options" role="radiogroup" aria-label="Horario">
                      <label>
                        <input id="editTimeMañana" type="radio" name="time" value="M" required>
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
                  <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split">
                    <button class="admin-login-submit" type="submit" name="action" value="update">Guardar evento</button>
                    <button class="admin-danger-submit" type="submit" name="action" value="delete">Borrar evento</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </main>
      <footer>
        <div class="ms-footer">
          <div class="copyright">Copyright © 2025. Design by Alex Serret</div>
          <ul class="socials">
            <li><a href="#" class="socicon-instagram"></a></li>
            <li><a href="#" class="socicon-youtube"></a></li>
          </ul>
        </div>
      </footer>
    </div>
    <script>
      window.BurnoutAdminEvents = <?= json_encode($events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
    </script>
    <script type="text/javascript" src='assets/js/jquery-3.2.1.min.js'></script>
    <script type="text/javascript" src='assets/js/plugins.min.js'></script>
    <script type="text/javascript" src="assets/js/main.js"></script>
    <script type="text/javascript" src="assets/js/admin_partidas.js"></script>
  </body>
</html>
