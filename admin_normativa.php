<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

const BURNOUT_NORMATIVA_START = '<!-- BURNOUT_NORMATIVA_BLOCK_START -->';
const BURNOUT_NORMATIVA_END = '<!-- BURNOUT_NORMATIVA_BLOCK_END -->';

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

function burnout_normativa_file(): string
{
    return __DIR__ . '/normativa.html';
}

function burnout_normativa_read_html(): string
{
    $path = burnout_normativa_file();
    $html = file_get_contents($path);

    if ($html === false) {
        throw new RuntimeException('No se ha podido leer normativa.html.');
    }

    return $html;
}

function burnout_normativa_title(string $html): string
{
    if (!preg_match('/<h2\s+class="page-header"\s*>(.*?)<\/h2>/s', $html, $matches)) {
        throw new RuntimeException('No se ha encontrado el titulo editable de normativa.html.');
    }

    return html_entity_decode(trim(strip_tags((string) $matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function burnout_normativa_blocks(string $html): array
{
    $pattern = '/' . preg_quote(BURNOUT_NORMATIVA_START, '/') . '(.*?)' . preg_quote(BURNOUT_NORMATIVA_END, '/') . '/s';

    if (!preg_match_all($pattern, $html, $matches)) {
        throw new RuntimeException('No se han encontrado bloques editables en normativa.html.');
    }

    return array_map('burnout_normativa_block_content', $matches[1]);
}

function burnout_normativa_block_content(string $block): string
{
    $content = preg_replace('/^\s*<div\s+class="center-block">\s*<div\s+class="about__info col-md-12">\s*/s', '', $block, 1) ?? $block;
    $content = preg_replace('/\s*<\/div>\s*<\/div>\s*$/s', '', $content, 1) ?? $content;

    return trim($content);
}

function burnout_normativa_assert_safe_block(string $content): void
{
    if (trim($content) === '') {
        throw new RuntimeException('El contenido del bloque no puede estar vacio.');
    }

    $blockedPatterns = [
        '/<\?/i',
        '/<script\b/i',
        '/<iframe\b/i',
        '/<object\b/i',
        '/<embed\b/i',
        '/<form\b/i',
        '/<input\b/i',
        '/<button\b/i',
        '/\son[a-z]+\s*=/i',
        '/javascript\s*:/i',
    ];

    foreach ($blockedPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            throw new RuntimeException('El bloque contiene HTML no permitido para la normativa.');
        }
    }
}

function burnout_normativa_indent_content(string $content): string
{
    $lines = preg_split('/\R/', trim($content));

    if (!$lines) {
        return '';
    }

    return implode("\n", array_map(static function (string $line): string {
        return $line === '' ? '' : '                      ' . rtrim($line);
    }, $lines));
}

function burnout_normativa_render_block(string $content): string
{
    return implode("\n", [
        '              ' . BURNOUT_NORMATIVA_START,
        '              <div class="center-block">',
        '                  <div class="about__info col-md-12">',
        burnout_normativa_indent_content($content),
        '                  </div>',
        '              </div>',
        '              ' . BURNOUT_NORMATIVA_END,
    ]);
}

function burnout_normativa_write(string $title, array $blocks): void
{
    $title = trim($title);

    if ($title === '') {
        throw new RuntimeException('El titulo no puede estar vacio.');
    }

    if (!$blocks) {
        throw new RuntimeException('La normativa debe tener al menos un bloque.');
    }

    foreach ($blocks as $block) {
        burnout_normativa_assert_safe_block((string) $block);
    }

    $html = burnout_normativa_read_html();
    $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $titleReplacementCount = 0;
    $updated = preg_replace_callback(
        '/(<h2\s+class="page-header"\s*>).*?(<\/h2>)/s',
        static function (array $matches) use ($escapedTitle): string {
            return $matches[1] . $escapedTitle . $matches[2];
        },
        $html,
        1,
        $titleReplacementCount
    );

    if ($updated === null || $titleReplacementCount < 1) {
        throw new RuntimeException('No se ha podido actualizar el titulo.');
    }

    $renderedBlocks = implode("\n", array_map(static function (string $block): string {
        return burnout_normativa_render_block($block);
    }, $blocks));

    $blockPattern = '/\s*' . preg_quote(BURNOUT_NORMATIVA_START, '/') . '.*' . preg_quote(BURNOUT_NORMATIVA_END, '/') . '/s';
    $blockReplacementCount = 0;
    $updatedWithBlocks = preg_replace(
        $blockPattern,
        "\n" . $renderedBlocks,
        $updated,
        1,
        $blockReplacementCount
    );

    if ($updatedWithBlocks === null || $blockReplacementCount < 1) {
        throw new RuntimeException('No se han podido actualizar los bloques.');
    }

    if (file_put_contents(burnout_normativa_file(), $updatedWithBlocks, LOCK_EX) === false) {
        throw new RuntimeException('No se ha podido escribir normativa.html.');
    }
}

function burnout_normativa_current(): array
{
    $html = burnout_normativa_read_html();

    return [
        'title' => burnout_normativa_title($html),
        'blocks' => burnout_normativa_blocks($html),
    ];
}

if (!$setupError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!burnout_check_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sesion caducada. Recarga la pagina e intentalo de nuevo.');
        }

        $current = burnout_normativa_current();
        $action = (string) ($_POST['action'] ?? '');
        $title = (string) $current['title'];
        $blocks = $current['blocks'];

        if ($action === 'update_title') {
            $title = (string) ($_POST['title'] ?? '');
            burnout_normativa_write($title, $blocks);
            burnout_set_admin_flash('success', 'Titulo de normativa actualizado.');
        } elseif ($action === 'update_block') {
            $index = filter_input(INPUT_POST, 'index', FILTER_VALIDATE_INT);

            if ($index === false || $index === null || !isset($blocks[$index])) {
                throw new RuntimeException('El bloque seleccionado no existe.');
            }

            $blocks[$index] = (string) ($_POST['content'] ?? '');
            burnout_normativa_write($title, $blocks);
            burnout_set_admin_flash('success', 'Bloque actualizado correctamente.');
        } elseif ($action === 'delete_block') {
            $index = filter_input(INPUT_POST, 'index', FILTER_VALIDATE_INT);

            if ($index === false || $index === null || !isset($blocks[$index])) {
                throw new RuntimeException('El bloque seleccionado no existe.');
            }

            unset($blocks[$index]);
            $blocks = array_values($blocks);
            burnout_normativa_write($title, $blocks);
            burnout_set_admin_flash('success', 'Bloque eliminado correctamente.');
        } elseif ($action === 'add_block') {
            $blocks[] = (string) ($_POST['content'] ?? '');
            burnout_normativa_write($title, $blocks);
            burnout_set_admin_flash('success', 'Bloque anadido correctamente.');
        }
    } catch (Throwable $exception) {
        burnout_set_admin_flash('error', $exception->getMessage());
    }

    header('Location: admin_normativa.php');
    exit;
}

try {
    $normativa = $setupError ? ['title' => '', 'blocks' => []] : burnout_normativa_current();
} catch (Throwable $exception) {
    $normativa = ['title' => '', 'blocks' => []];
    $error = $exception->getMessage();
}

$csrfToken = burnout_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Gestion Normativa - Burnout Airsoft</title>
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
              <h1>Gestion Normativa</h1>
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

            <section class="admin-gallery-list-wrap admin-normativa-editor">
              <div class="admin-gallery-toolbar">
                <h2>Titulo de la pagina</h2>
                <a class="admin-normativa-top-link" href="normativa.html" target="_blank" rel="noopener noreferrer">Ver normativa</a>
              </div>
              <form class="admin-gallery-form admin-normativa-title-form" method="post" action="admin_normativa.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="update_title">
                <div class="admin-login-field">
                  <label for="normativaTitle">Titulo</label>
                  <input id="normativaTitle" name="title" type="text" required value="<?= htmlspecialchars((string) $normativa['title'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="admin-gallery-modal__actions">
                  <button class="admin-login-submit" type="submit">Guardar titulo</button>
                </div>
              </form>
            </section>

            <section class="admin-normativa-blocks" aria-label="Bloques de normativa">
              <?php foreach ($normativa['blocks'] as $index => $block): ?>
                <article class="admin-gallery-list-wrap admin-normativa-block">
                  <div class="admin-gallery-toolbar">
                    <h2>Bloque <?= (int) $index + 1 ?></h2>
                    <div class="admin-normativa-view-controls">
                      <button type="button" data-normativa-toggle>Ver contenido</button>
                    </div>
                  </div>
                  <form class="admin-gallery-form admin-normativa-editable" method="post" action="admin_normativa.php" data-normativa-editor>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="index" value="<?= (int) $index ?>">
                    <div class="admin-login-field" data-normativa-html-pane>
                      <label for="block<?= (int) $index ?>">Contenido HTML</label>
                      <textarea id="block<?= (int) $index ?>" name="content" rows="12" required><?= htmlspecialchars((string) $block, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="admin-normativa-preview-pane" data-normativa-web-pane hidden>
                      <div class="admin-normativa-preview" data-normativa-preview></div>
                    </div>
                    <div class="admin-gallery-modal__actions admin-gallery-modal__actions--split">
                      <button class="admin-login-submit" type="submit" name="action" value="update_block">Guardar bloque</button>
                      <button class="admin-danger-submit" type="submit" name="action" value="delete_block" formnovalidate>Eliminar bloque</button>
                    </div>
                  </form>
                </article>
              <?php endforeach; ?>
            </section>

            <section class="admin-gallery-list-wrap admin-normativa-add">
              <div class="admin-gallery-toolbar">
                <h2>Anadir bloque</h2>
                <div class="admin-normativa-view-controls">
                  <button type="button" data-normativa-toggle>Ver contenido</button>
                </div>
              </div>
              <form class="admin-gallery-form admin-normativa-editable" method="post" action="admin_normativa.php" data-normativa-editor>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="add_block">
                <div class="admin-login-field" data-normativa-html-pane>
                  <label for="newBlock">Contenido HTML</label>
                  <textarea id="newBlock" name="content" rows="8" required placeholder="<h3>Nuevo apartado</h3>&#10;<p>Texto del nuevo bloque.</p>"></textarea>
                </div>
                <div class="admin-normativa-preview-pane" data-normativa-web-pane hidden>
                  <div class="admin-normativa-preview" data-normativa-preview></div>
                </div>
                <div class="admin-gallery-modal__actions">
                  <button class="admin-login-submit" type="submit">Anadir bloque</button>
                </div>
              </form>
            </section>
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
    <script type="text/javascript" src="assets/js/admin_normativa.js"></script>
  </body>
</html>
