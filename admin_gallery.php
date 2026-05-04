<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$adminUser = null;
$setupError = '';
$message = '';
$error = '';
$galleryFile = __DIR__ . '/assets/data/gallery.json';

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

function burnout_read_gallery(string $galleryFile): array
{
    if (!is_file($galleryFile)) {
        return [];
    }

    $content = file_get_contents($galleryFile);

    if ($content === false || trim($content) === '') {
        return [];
    }

    $data = json_decode($content, true);

    if (!is_array($data)) {
        throw new RuntimeException('El fichero gallery.json no contiene un JSON valido.');
    }

    return array_values(array_filter($data, static function ($item): bool {
        return is_array($item);
    }));
}

function burnout_write_gallery(string $galleryFile, array $gallery): void
{
    $json = json_encode(array_values($gallery), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException('No se ha podido generar el JSON de galeria.');
    }

    $result = file_put_contents($galleryFile, $json . PHP_EOL, LOCK_EX);

    if ($result === false) {
        throw new RuntimeException('No se ha podido guardar assets/data/gallery.json.');
    }
}

if (!$setupError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!burnout_check_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sesion caducada. Recarga la pagina e intentalo de nuevo.');
        }

        $gallery = burnout_read_gallery($galleryFile);
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $src = trim((string) ($_POST['src'] ?? ''));
            $alt = trim((string) ($_POST['alt'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));

            if ($src === '' || $alt === '') {
                throw new RuntimeException('La ruta de imagen y el texto alternativo son obligatorios.');
            }

            $gallery[] = [
                'src' => $src,
                'alt' => $alt,
                'description' => $description,
            ];
            burnout_write_gallery($galleryFile, $gallery);
            $message = 'Imagen anadida a la galeria.';
        } elseif ($action === 'delete') {
            $index = filter_input(INPUT_POST, 'index', FILTER_VALIDATE_INT);

            if ($index === false || $index === null || !isset($gallery[$index])) {
                throw new RuntimeException('La imagen seleccionada no existe.');
            }

            array_splice($gallery, $index, 1);
            burnout_write_gallery($galleryFile, $gallery);
            $message = 'Imagen eliminada de la galeria.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

try {
    $gallery = $setupError ? [] : burnout_read_gallery($galleryFile);
} catch (Throwable $exception) {
    $gallery = [];
    $error = $exception->getMessage();
}

$csrfToken = burnout_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Gestion Galeria - Burnout Airsoft</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="resources/logoBurnout-3.png" />
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
                  <h2>Imagenes actuales</h2>
                  <button class="admin-add-image-button" type="button" data-gallery-modal-open>
                    <span>+</span>
                    Anadir imagen
                  </button>
                </div>
                <?php if (!$gallery): ?>
                  <div class="admin-empty">No hay imagenes en gallery.json.</div>
                <?php else: ?>
                  <div class="admin-gallery-list" id="galleryList">
                    <?php foreach ($gallery as $index => $item): ?>
                      <article class="admin-gallery-item">
                        <img src="<?= htmlspecialchars((string) ($item['src'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['alt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="admin-gallery-item__body">
                          <h3><?= htmlspecialchars((string) ($item['alt'] ?? 'Sin titulo'), ENT_QUOTES, 'UTF-8') ?></h3>
                          <p><?= htmlspecialchars((string) ($item['src'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                          <span><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <form method="post" action="admin_gallery.php" onsubmit="return confirm('Eliminar esta imagen de la galeria?');">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="index" value="<?= (int) $index ?>">
                          <button type="submit">Borrar</button>
                        </form>
                      </article>
                    <?php endforeach; ?>
                  </div>
                  <div class="admin-gallery-pagination" id="galleryPagination">
                    <div class="admin-gallery-pagination__spacer"></div>
                    <div class="admin-gallery-pages" id="galleryPages" aria-label="Paginacion galeria"></div>
                    <label class="admin-gallery-page-size" for="galleryPageSize">
                      <span>n. imagenes</span>
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
                  <h2 id="galleryModalTitle">Anadir imagen</h2>
                  <button type="button" data-gallery-modal-close aria-label="Cerrar ventana">x</button>
                </div>
                <form class="admin-gallery-form" method="post" action="admin_gallery.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="add">
                  <div class="admin-login-field">
                    <label for="src">Ruta imagen</label>
                    <input id="src" name="src" type="text" required placeholder="assets/images/gallery/foto13.jpg">
                  </div>
                  <div class="admin-login-field">
                    <label for="alt">Titulo / Alt</label>
                    <input id="alt" name="alt" type="text" required placeholder="Operacion tactica 13">
                  </div>
                  <div class="admin-login-field">
                    <label for="description">Descripcion</label>
                    <textarea id="description" name="description" rows="5" placeholder="Texto que aparecera en el modal de la galeria"></textarea>
                  </div>
                  <div class="admin-gallery-modal__actions">
                    <button class="admin-login-submit" type="submit">Guardar imagen</button>
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
    <script type="text/javascript" src='assets/js/jquery-3.2.1.min.js'></script>
    <script type="text/javascript" src='assets/js/plugins.min.js'></script>
    <script type="text/javascript" src="assets/js/main.js"></script>
    <script>
      (function () {
        var modal = document.getElementById('galleryModal');
        var firstInput = document.getElementById('src');

        if (!modal) {
          return;
        }

        function openModal() {
          modal.classList.add('is-open');
          modal.setAttribute('aria-hidden', 'false');
          document.body.classList.add('admin-gallery-modal-open');

          if (firstInput) {
            firstInput.focus();
          }
        }

        function closeModal() {
          modal.classList.remove('is-open');
          modal.setAttribute('aria-hidden', 'true');
          document.body.classList.remove('admin-gallery-modal-open');
        }

        document.querySelectorAll('[data-gallery-modal-open]').forEach(function (button) {
          button.addEventListener('click', openModal);
        });

        document.querySelectorAll('[data-gallery-modal-close]').forEach(function (button) {
          button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
          }
        });
      }());

      (function () {
        var list = document.getElementById('galleryList');
        var pages = document.getElementById('galleryPages');
        var pageSizeSelect = document.getElementById('galleryPageSize');

        if (!list || !pages || !pageSizeSelect) {
          return;
        }

        var items = Array.prototype.slice.call(list.querySelectorAll('.admin-gallery-item'));
        var currentPage = 1;

        function renderPagination() {
          var pageSize = parseInt(pageSizeSelect.value, 10) || 5;
          var totalPages = Math.max(1, Math.ceil(items.length / pageSize));

          if (currentPage > totalPages) {
            currentPage = totalPages;
          }

          items.forEach(function (item, index) {
            var start = (currentPage - 1) * pageSize;
            var end = start + pageSize;
            var isVisible = index >= start && index < end;

            item.classList.toggle('is-hidden', !isVisible);
            item.style.display = isVisible ? '' : 'none';
            item.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
          });

          pages.innerHTML = '';

          for (var page = 1; page <= totalPages; page++) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(page);
            button.className = page === currentPage ? 'is-active' : '';
            button.setAttribute('aria-label', 'Pagina ' + page);
            button.setAttribute('aria-current', page === currentPage ? 'page' : 'false');
            button.addEventListener('click', function () {
              currentPage = parseInt(this.textContent, 10);
              renderPagination();
            });
            pages.appendChild(button);
          }
        }

        pageSizeSelect.addEventListener('change', function () {
          currentPage = 1;
          renderPagination();
        });

        renderPagination();
      }());
    </script>
  </body>
</html>
