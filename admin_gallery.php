<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/gallery.php';

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

if (!$setupError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!burnout_check_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sesión caducada. Recarga la página e inténtalo de nuevo.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $src = trim((string) ($_POST['src'] ?? ''));
            $alt = trim((string) ($_POST['alt'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));

            if ($src === '' || $alt === '') {
                throw new RuntimeException('La ruta de imagen y el texto alternativo son obligatorios.');
            }

            burnout_gallery_add($src, $alt, $description);
            burnout_set_admin_flash('success', 'Imagen añadida a la galería.');
        } elseif ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if ($id === false || $id === null) {
                throw new RuntimeException('La imagen seleccionada no existe.');
            }

            burnout_gallery_delete((int) $id);
            burnout_set_admin_flash('success', 'Imagen eliminada de la galería.');
        }
    } catch (Throwable $exception) {
        burnout_set_admin_flash('error', $exception->getMessage());
    }

    header('Location: admin_gallery.php');
    exit;
}

try {
    $gallery = $setupError ? [] : burnout_gallery_all();
} catch (Throwable $exception) {
    $gallery = [];
    $error = $exception->getMessage();
}

$csrfToken = burnout_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Gestión Galería - Burnout Airsoft</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/resources/logoBurnout-3.png" />
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
              <h1>Gestión Galería</h1>
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
                  <h2>Imágenes actuales</h2>
                  <button class="admin-add-image-button" type="button" data-gallery-modal-open>
                    <span>+</span>
                    Añadir imagen
                  </button>
                </div>
                <?php if (!$gallery): ?>
                  <div class="admin-empty">No hay imágenes guardadas.</div>
                <?php else: ?>
                  <div class="admin-gallery-list" id="galleryList">
                    <?php foreach ($gallery as $item): ?>
                      <article class="admin-gallery-item">
                        <img src="<?= htmlspecialchars((string) ($item['src'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['alt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="admin-gallery-item__body">
                          <h3><?= htmlspecialchars((string) ($item['alt'] ?? 'Sin titulo'), ENT_QUOTES, 'UTF-8') ?></h3>
                          <span><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <form class="admin-gallery-delete-form" method="post" action="admin_gallery.php" data-gallery-delete-form data-gallery-delete-title="<?= htmlspecialchars((string) ($item['alt'] ?? 'esta imagen'), ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">
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
                  <div class="admin-gallery-pagination" id="galleryPagination">
                    <div class="admin-gallery-pagination__spacer"></div>
                    <div class="admin-gallery-pages" id="galleryPages" aria-label="Paginación galería"></div>
                    <label class="admin-gallery-page-size" for="galleryPageSize">
                      <span>n. imágenes</span>
                      <select id="galleryPageSize">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                      </select>
                    </label>
                  </div>
                <?php endif; ?>
              </section>
            </div>
            <div class="admin-gallery-modal" id="galleryModal" aria-hidden="true">
              <div class="admin-gallery-modal__overlay" data-gallery-modal-close></div>
              <div class="admin-gallery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="galleryModalTitle">
                <div class="admin-gallery-modal__header">
                  <h2 id="galleryModalTitle">Añadir imagen</h2>
                  <button type="button" data-gallery-modal-close aria-label="Cerrar ventana">x</button>
                </div>
                <form class="admin-gallery-form" method="post" action="admin_gallery.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="add">
                  <div class="admin-login-field">
                    <label for="src">URL imagen</label>
                    <input id="src" name="src" type="text" required placeholder="url">
                  </div>
                  <div class="admin-login-field">
                    <label for="alt">Titulo / Alt</label>
                    <input id="alt" name="alt" type="text" required placeholder="Titulo">
                  </div>
                  <div class="admin-login-field">
                    <label for="description">Descripcion</label>
                    <textarea id="description" name="description" rows="5" placeholder="Texto que aparecerá en el modal de la galería"></textarea>
                  </div>
                  <div class="admin-gallery-modal__actions">
                    <button class="admin-login-submit" type="submit">Guardar imagen</button>
                  </div>
                </form>
              </div>
            </div>
            <div class="admin-gallery-modal" id="galleryDeleteModal" aria-hidden="true">
              <div class="admin-gallery-modal__overlay" data-gallery-delete-cancel></div>
              <div class="admin-gallery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="galleryDeleteTitle">
                <div class="admin-gallery-modal__header">
                  <h2 id="galleryDeleteTitle">Eliminar imagen</h2>
                  <button type="button" data-gallery-delete-cancel aria-label="Cerrar ventana">x</button>
                </div>
                <div class="admin-gallery-modal__body">
                  <p id="galleryDeleteMessage">¿Eliminar esta imagen de la galería?</p>
                </div>
                <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split">
                  <button class="admin-back-link" type="button" data-gallery-delete-cancel>Cancelar</button>
                  <button class="admin-danger-submit" type="button" data-gallery-delete-confirm>Eliminar imagen</button>
                </div>
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
    <script type="text/javascript" src="assets/js/jquery-3.7.1.min.js"></script>
    <script type="text/javascript" src='assets/js/plugins.min.js'></script>
    <script type="text/javascript" src="assets/js/main.js"></script>
    <script type="text/javascript" src="assets/js/admin_gallery.js"></script>
  </body>
</html>
