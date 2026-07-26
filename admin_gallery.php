<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/gallery.php';

$adminUser = null;
$setupError = '';
$message = '';
$error = '';
$galleryEvents = [];
$eventOptions = [];

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

if (!$setupError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!burnout_check_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sesion caducada. Recarga la pagina e intentalo de nuevo.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'add_event') {
            $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

            if ($eventId === false || $eventId === null) {
                throw new RuntimeException('Selecciona una partida para crear su galeria.');
            }

            burnout_gallery_event_add(
                (int) $eventId,
                trim((string) ($_POST['title'] ?? '')),
                isset($_POST['is_visible'])
            );
            burnout_set_admin_flash('success', 'Galeria de evento creada.');
        } elseif ($action === 'add_image') {
            $galleryEventId = filter_input(INPUT_POST, 'gallery_event_id', FILTER_VALIDATE_INT);

            if ($galleryEventId === false || $galleryEventId === null) {
                throw new RuntimeException('Selecciona una galeria de evento.');
            }

            $uploadedFiles = burnout_gallery_upload_files($_FILES['image_files'] ?? []);

            if (!$uploadedFiles) {
                throw new RuntimeException('Selecciona al menos una imagen.');
            }

            $savedImages = 0;

            foreach ($uploadedFiles as $file) {
                $uploadedImage = burnout_gallery_store_upload($file);

                if ($uploadedImage === null) {
                    continue;
                }

                burnout_gallery_event_image_add(
                    (int) $galleryEventId,
                    $uploadedImage
                );
                $savedImages++;
            }

            if ($savedImages === 0) {
                throw new RuntimeException('No se ha podido guardar ninguna imagen.');
            }

            burnout_set_admin_flash('success', $savedImages . ($savedImages === 1 ? ' imagen anadida.' : ' imagenes anadidas.'));
        } elseif ($action === 'delete_image') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id === false || $id === null) {
                throw new RuntimeException('La imagen seleccionada no existe.');
            }

            burnout_gallery_event_image_delete((int) $id);
            burnout_set_admin_flash('success', 'Imagen eliminada de la galeria.');
        } elseif ($action === 'delete_event') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id === false || $id === null) {
                throw new RuntimeException('La galeria seleccionada no existe.');
            }

            burnout_gallery_event_delete((int) $id);
            burnout_set_admin_flash('success', 'Galeria de evento eliminada.');
        }
    } catch (Throwable $exception) {
        burnout_set_admin_flash('error', $exception->getMessage());
    }

    header('Location: admin_gallery.php');
    exit;
}

try {
    if (!$setupError) {
        $galleryEvents = burnout_gallery_admin_events();
        $eventOptions = burnout_gallery_events_for_select();
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'No se han podido cargar las galerias de evento.';
}

$csrfToken = burnout_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Gestion Galeria - Burnout Airsoft</title>
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
                  <span class="nav-item__label">Ver nuestros eventos</span>
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
              <h1>Gestion Galeria</h1>
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
            <div class="admin-gallery-layout">
              <section class="admin-gallery-list-wrap">
                <div class="admin-gallery-toolbar">
                  <div>
                    <h2>Galerias por evento</h2>
                    <p class="admin-gallery-toolbar__note">Cada partida puede tener su propio feed publico de imagenes.</p>
                  </div>
                  <div class="admin-toolbar-actions">
                    <button class="admin-add-image-button" type="button" data-gallery-modal-open="galleryEventModal">
                      <span>+</span>
                      Crear feed
                    </button>
                    <?php if ($galleryEvents): ?>
                      <button class="admin-add-image-button" type="button" data-gallery-modal-open="galleryImageModal">
                        <span>+</span>
                        Anadir imagenes
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if (!$galleryEvents): ?>
                  <div class="admin-empty">No hay galerias de evento creadas.</div>
                <?php else: ?>
                  <div class="admin-event-gallery-list" id="galleryEventList">
                    <?php foreach ($galleryEvents as $event): ?>
                      <article class="admin-event-gallery">
                        <div class="admin-event-gallery__header">
                          <div>
                            <span class="admin-event-gallery__meta">
                              <?= htmlspecialchars(trim($event['date'] . ' ' . $event['turn']) ?: 'Sin fecha vinculada', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <h3>
                              <button
                                class="admin-event-gallery__toggle"
                                type="button"
                                data-event-gallery-toggle
                                aria-expanded="false"
                                aria-controls="eventGalleryBody<?= (int) $event['id'] ?>"
                              >
                                <?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?>
                              </button>
                            </h3>
                            <p><?= htmlspecialchars($event['image_count'] . ((int) $event['image_count'] === 1 ? ' imagen' : ' imagenes'), ENT_QUOTES, 'UTF-8') ?> - <?= $event['is_visible'] ? 'Visible' : 'Oculta' ?></p>
                          </div>
                          <div class="admin-event-gallery__actions">
                            <button class="admin-add-image-button" type="button" data-gallery-modal-open="galleryImageModal" data-gallery-event-id="<?= (int) $event['id'] ?>">
                              <span>+</span>
                              Imagenes
                            </button>
                            <form method="post" action="admin_gallery.php" data-gallery-delete-form data-gallery-delete-title="<?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?>" data-gallery-delete-kind="event">
                              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                              <input type="hidden" name="action" value="delete_event">
                              <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                              <button class="admin-delete-icon-button" type="submit" aria-label="Eliminar galeria">
                                <svg aria-hidden="true" viewBox="0 0 24 24">
                                  <path d="M3 6h18"></path>
                                  <path d="M8 6V4h8v2"></path>
                                  <path d="M19 6l-1 14H6L5 6"></path>
                                  <path d="M10 11v5"></path>
                                  <path d="M14 11v5"></path>
                                </svg>
                              </button>
                            </form>
                          </div>
                        </div>
                        <div class="admin-event-gallery__body" id="eventGalleryBody<?= (int) $event['id'] ?>" hidden>
                          <?php if (!$event['images']): ?>
                            <div class="admin-empty">Este evento todavia no tiene imagenes.</div>
                          <?php else: ?>
                            <div class="admin-gallery-list">
                              <?php foreach ($event['images'] as $image): ?>
                                <article class="admin-gallery-item">
                                  <img src="<?= htmlspecialchars($image['src'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($image['alt'], ENT_QUOTES, 'UTF-8') ?>">
                                  <div class="admin-gallery-item__body">
                                    <span><?= htmlspecialchars($image['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                  </div>
                                  <form method="post" action="admin_gallery.php" data-gallery-delete-form data-gallery-delete-title="<?= htmlspecialchars($image['label'], ENT_QUOTES, 'UTF-8') ?>" data-gallery-delete-kind="image">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="id" value="<?= (int) $image['id'] ?>">
                                    <button class="admin-delete-icon-button" type="submit" aria-label="Eliminar imagen">
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
                      </article>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </section>
            </div>
            <div class="admin-gallery-modal" id="galleryEventModal" aria-hidden="true">
              <div class="admin-gallery-modal__overlay" data-gallery-modal-close></div>
              <div class="admin-gallery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="galleryEventModalTitle">
                <div class="admin-gallery-modal__header">
                  <h2 id="galleryEventModalTitle">Crear feed de evento</h2>
                  <button type="button" data-gallery-modal-close aria-label="Cerrar ventana">x</button>
                </div>
                <form class="admin-gallery-form" method="post" action="admin_gallery.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="add_event">
                  <div class="admin-login-field">
                    <label for="event_id">Partida</label>
                    <select id="event_id" name="event_id" required>
                      <option value=""><?= $eventOptions ? 'Selecciona una partida' : 'No hay partidas disponibles sin feed' ?></option>
                      <?php foreach ($eventOptions as $event): ?>
                        <option value="<?= (int) $event['id'] ?>">
                          <?= htmlspecialchars($event['date'] . ' - ' . $event['turn'] . ' - ' . $event['title'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="admin-login-field">
                    <label for="event_title">Titulo publico opcional</label>
                    <input id="event_title" name="title" type="text" placeholder="Si lo dejas vacio se usa el titulo de la partida">
                  </div>
                  <label class="admin-checkbox-field">
                    <input name="is_visible" type="checkbox" value="1" checked>
                    <span>Mostrar en la galeria publica</span>
                  </label>
                  <div class="admin-gallery-modal__actions">
                    <button class="admin-login-submit" type="submit">Crear feed</button>
                  </div>
                </form>
              </div>
            </div>
            <div class="admin-gallery-modal" id="galleryImageModal" aria-hidden="true">
              <div class="admin-gallery-modal__overlay" data-gallery-modal-close></div>
              <div class="admin-gallery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="galleryImageModalTitle">
                <div class="admin-gallery-modal__header">
                  <h2 id="galleryImageModalTitle">Anadir imagenes</h2>
                  <button type="button" data-gallery-modal-close aria-label="Cerrar ventana">x</button>
                </div>
                <form class="admin-gallery-form" method="post" action="admin_gallery.php" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="add_image">
                  <div class="admin-login-field">
                    <label for="gallery_event_id">Feed del evento</label>
                    <select id="gallery_event_id" name="gallery_event_id" required>
                      <option value="">Selecciona una galeria</option>
                      <?php foreach ($galleryEvents as $event): ?>
                        <option value="<?= (int) $event['id'] ?>"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="admin-login-field">
                    <label for="image_files">Subir imagenes</label>
                    <input id="image_files" name="image_files[]" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" multiple required>
                  </div>
                  <div class="admin-gallery-modal__actions">
                    <button class="admin-login-submit" type="submit">Guardar imagenes</button>
                  </div>
                </form>
              </div>
            </div>
            <div class="admin-gallery-modal" id="galleryDeleteModal" aria-hidden="true">
              <div class="admin-gallery-modal__overlay" data-gallery-delete-cancel></div>
              <div class="admin-gallery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="galleryDeleteTitle">
                <div class="admin-gallery-modal__header">
                  <h2 id="galleryDeleteTitle">Eliminar</h2>
                  <button type="button" data-gallery-delete-cancel aria-label="Cerrar ventana">x</button>
                </div>
                <div class="admin-gallery-modal__body">
                  <p id="galleryDeleteMessage">Eliminar elemento de la galeria?</p>
                </div>
                <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split">
                  <button class="admin-back-link" type="button" data-gallery-delete-cancel>Cancelar</button>
                  <button class="admin-danger-submit" type="button" data-gallery-delete-confirm>Eliminar</button>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </main>
      <footer>
        <div class="ms-footer">
          <div class="copyright" data-copyright-start="2025">Copyright &copy; 2025-2026. Designed by Alex Serret</div>
          <span class="footer-links">
            <a href="privacidad.html" data-type="page-transition">Politica de Privacidad de datos</a>
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
    <script type="text/javascript" src="assets/js/admin_gallery.js"></script>
  </body>
</html>
