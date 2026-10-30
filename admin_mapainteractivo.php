<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$adminUser = null;
$setupError = '';
try {
    $adminUser = burnout_current_admin();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    $setupError = 'No se ha podido validar la sesión de administración.';
}
if (!$setupError && !$adminUser) {
    header('Location: admin.php');
    exit;
}
$csrfToken = burnout_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Mapa interactivo - Burnout Airsoft</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/resources/logoBurnout-4.png" />
    <link rel="stylesheet" href="assets/css/plugins.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/admin_mapainteractivo.css">
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
      <main class="ms-container admin-page admin-map-page">
        <div class="ms-section__block">
          <div class="admin-header">
            <div class="admin-header__title">
              <span class="admin-kicker">Burnout Airsoft</span>
              <h1>Mapa interactivo</h1>
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
        <section class="ms-section__block admin-map-section">
          <?php if ($setupError): ?>
            <div class="admin-login-error" role="alert"><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php else: ?>
          <div class="admin-map-workspace">
            <aside class="admin-map-sidebar" aria-label="Secciones del mapa interactivo">
              <div class="admin-map-brand">HQ</div>
              <nav aria-label="Herramientas de operaciones">
                <button type="button" class="is-active" data-map-section="mapa" aria-pressed="true">Mapa</button>
                <button type="button" data-map-section="misiones" aria-pressed="false">Misiones</button>
                <button type="button" data-map-section="canales" aria-pressed="false">Canales</button>
                <button type="button" data-map-section="ajustes" aria-pressed="false">Ajustes</button>
              </nav>
            </aside>
            <div class="admin-map-content">
              <section data-map-panel="mapa" aria-label="Editor de mapa">
                <div class="admin-map-toolbar" role="group" aria-label="Herramientas de dibujo">
                  <button type="button" data-map-tool="pen" title="Lápiz" aria-label="Lápiz" aria-pressed="true" class="is-active"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m16 3 5 5-12 12-6 1 1-6Z"/><path d="m14 5 5 5"/></svg></button>
                  <button type="button" data-map-tool="line" title="Línea" aria-label="Línea" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m4 20 16-16"/><circle cx="4" cy="20" r="1"/><circle cx="20" cy="4" r="1"/></svg></button>
                  <button type="button" data-map-tool="arrow" title="Flecha" aria-label="Flecha" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20 20 4M8 4h12v12"/></svg></button>
                  <button type="button" data-map-tool="rectangle" title="Rectángulo" aria-label="Rectángulo" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="1"/></svg></button>
                  <button type="button" data-map-tool="circle" title="Círculo" aria-label="Círculo" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/></svg></button>
                  <button type="button" data-map-tool="eraser" title="Borrador" aria-label="Borrador" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14 3 7 7-11 11H6l-4-4Z"/><path d="m7 12 7 7M10 21h12"/></svg></button>
                  <button type="button" data-map-tool="text" title="Texto libre" aria-label="Texto libre" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m2 19 5-14 5 14M4 14h6M21 19v-8M21 15a4 4 0 1 0-8 0 4 4 0 0 0 8 0"/></svg></button>
                  <label title="Color"><input type="color" data-map-color value="#df1f29" aria-label="Color"></label>
                  <label title="Grosor"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h18M3 12h18M3 19h18"/><path d="M3 12h18" stroke-width="3"/><path d="M3 19h18" stroke-width="5"/></svg><input type="range" data-map-size min="1" max="40" value="5" aria-label="Grosor"><output data-map-size-label>5 px</output></label>
                  <button type="button" data-map-action="undo" title="Deshacer" aria-label="Deshacer" disabled><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 5-6 6 6 6"/><path d="M3 11h11a6 6 0 0 1 6 6v2"/></svg></button>
                  <button type="button" data-map-action="redo" title="Rehacer" aria-label="Rehacer" disabled><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 5 6 6-6 6"/><path d="M21 11H10a6 6 0 0 0-6 6v2"/></svg></button>
                  <button type="button" data-map-action="clear" title="Limpiar mapa" aria-label="Limpiar mapa" ><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg></button>
                  <button type="button" class="admin-map-fullscreen-button" data-map-action="fullscreen" title="Pantalla completa" aria-label="Pantalla completa" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path data-map-fullscreen-icon d="M8 3H3v5M16 3h5v5M21 16v5h-5M8 21H3v-5"/></svg></button>
                </div>
                <div class="admin-map-canvas-wrap">
                  <div class="admin-map-canvas-stage">
                    <img class="admin-map-background" src="assets/maps/carto-orto-detalle.png" alt="" draggable="false">
                    <canvas width="1600" height="1131" aria-label="Lienzo de dibujo sobre el plano del campo. Usa el ratón o un dedo para dibujar."></canvas>
                  </div>
                </div>
                <p class="admin-map-load-error" role="alert" hidden>No se ha podido cargar el plano del campo. Recarga la página para volver a intentarlo.</p>
              </section>
              <section class="admin-map-maintenance" data-map-panel="maintenance" hidden><span class="admin-kicker" data-map-maintenance-title></span><h2>En mantenimiento</h2><p>Estamos preparando este apartado del centro de operaciones.</p><button type="button" data-map-action="back">Volver al mapa</button></section>
            </div>
          </div>
          <?php endif; ?>
        </section>
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
    <script type="text/javascript" src="assets/js/admin_mapainteractivo.js"></script>
  </body>
</html>
