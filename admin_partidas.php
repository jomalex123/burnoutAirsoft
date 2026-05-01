<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$adminUser = null;
$setupError = '';
$message = '';
$error = '';
$eventsFile = __DIR__ . '/assets/data/events.json';

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

function burnout_read_events(string $eventsFile): array
{
    if (!is_file($eventsFile)) {
        return [];
    }

    $content = file_get_contents($eventsFile);

    if ($content === false || trim($content) === '') {
        return [];
    }

    $data = json_decode($content, true);

    if (!is_array($data)) {
        throw new RuntimeException('El fichero events.json no contiene un JSON valido.');
    }

    return array_values(array_filter($data, static function ($item): bool {
        return is_array($item);
    }));
}

function burnout_write_events(string $eventsFile, array $events): void
{
    usort($events, static function (array $a, array $b): int {
        return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
    });

    $json = json_encode(array_values($events), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException('No se ha podido generar el JSON de partidas.');
    }

    $result = file_put_contents($eventsFile, $json . PHP_EOL, LOCK_EX);

    if ($result === false) {
        throw new RuntimeException('No se ha podido guardar assets/data/events.json.');
    }
}

if (!$setupError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!burnout_check_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sesion caducada. Recarga la pagina e intentalo de nuevo.');
        }

        $events = burnout_read_events($eventsFile);
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'update') {
            $date = trim((string) ($_POST['date'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $time = trim((string) ($_POST['time'] ?? ''));
            $allowedTimes = ['manana', 'tarde', 'noche'];
            $eventData = [
                'date' => $date,
                'title' => $title,
                'time' => ucfirst($time),
                'url' => 'registro.html',
            ];

            if ($date === '' || $title === '' || $time === '') {
                throw new RuntimeException('La fecha, el titulo y el horario son obligatorios.');
            }

            if (!in_array($time, $allowedTimes, true)) {
                throw new RuntimeException('Selecciona un horario valido.');
            }

            $dateTime = DateTime::createFromFormat('Y-m-d', $date);

            if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
                throw new RuntimeException('La fecha debe tener formato YYYY-MM-DD.');
            }

            if ($action === 'update') {
                $index = filter_input(INPUT_POST, 'index', FILTER_VALIDATE_INT);

                if ($index === false || $index === null || !isset($events[$index])) {
                    throw new RuntimeException('El evento seleccionado no existe.');
                }

                $events[$index] = $eventData;
                $message = 'Evento actualizado correctamente.';
            } else {
                $events[] = $eventData;
                $message = 'Evento creado correctamente.';
            }

            burnout_write_events($eventsFile, $events);
        } elseif ($action === 'delete') {
            $index = filter_input(INPUT_POST, 'index', FILTER_VALIDATE_INT);

            if ($index === false || $index === null || !isset($events[$index])) {
                throw new RuntimeException('El evento seleccionado no existe.');
            }

            array_splice($events, $index, 1);
            burnout_write_events($eventsFile, $events);
            $message = 'Evento eliminado correctamente.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

try {
    $events = $setupError ? [] : burnout_read_events($eventsFile);
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
    <link rel="icon" type="image/png" href="resources/logoBurnout-3.png" />
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
              <div class="logo-dark"><img src="resources/logoBurnout-2.png" alt="logo image"></div>
              <div class="logo-light current"><img src="resources/logoBurnout-2.png" alt="logo image"></div>
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
              <form class="admin-logout" method="post" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Cerrar sesion</button>
              </form>
            <?php endif; ?>
            <p>Crea o elimina partidas del calendario publico.</p>
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
                <button class="admin-partidas-action" type="button" data-partidas-modal-open="create">Crear evento</button>
                <button class="admin-partidas-action" type="button" data-partidas-modal-open="delete">Borrar evento</button>
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
                        <input type="radio" name="time" value="manana" required checked>
                        <span>Manana</span>
                      </label>
                      <label>
                        <input type="radio" name="time" value="tarde" required>
                        <span>Tarde</span>
                      </label>
                      <label>
                        <input type="radio" name="time" value="noche" required>
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
                    <?php foreach ($events as $index => $event): ?>
                      <article class="admin-partidas-delete-item">
                        <div>
                          <strong><?= htmlspecialchars((string) ($event['title'] ?? 'Sin titulo'), ENT_QUOTES, 'UTF-8') ?></strong>
                          <span><?= htmlspecialchars((string) ($event['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($event['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <form method="post" action="admin_partidas.php" onsubmit="return confirm('Eliminar este evento?');">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="index" value="<?= (int) $index ?>">
                          <button type="submit">Borrar</button>
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
                  <input type="hidden" name="index" id="editEventIndex" value="">
                  <div class="admin-login-field">
                    <label for="editDate">Fecha</label>
                    <input id="editDate" name="date" type="date" required>
                  </div>
                  <div class="admin-login-field">
                    <label for="editTitle">Titulo</label>
                    <input id="editTitle" name="title" type="text" required>
                  </div>
                  <div class="admin-login-field">
                    <label for="editTimeManana">Horario</label>
                    <div class="admin-time-options" role="radiogroup" aria-label="Horario">
                      <label>
                        <input id="editTimeManana" type="radio" name="time" value="manana" required>
                        <span>Manana</span>
                      </label>
                      <label>
                        <input id="editTimeTarde" type="radio" name="time" value="tarde" required>
                        <span>Tarde</span>
                      </label>
                      <label>
                        <input id="editTimeNoche" type="radio" name="time" value="noche" required>
                        <span>Noche</span>
                      </label>
                    </div>
                  </div>
                  <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split">
                    <button class="admin-login-submit" type="submit" name="action" value="update">Guardar evento</button>
                    <button class="admin-danger-submit" type="submit" name="action" value="delete" data-confirm-delete>Borrar evento</button>
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
