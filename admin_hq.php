<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/map_component.php';

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
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Gestión HQ - Burnout Airsoft</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/resources/logoBurnout-4.png" />
    <link rel="stylesheet" href="assets/css/plugins.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/admin_mapainteractivo.css">
    <link rel="stylesheet" href="assets/css/map.css">
    <link rel="stylesheet" href="assets/css/hq.css">
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
              <h1>GESTIÓN HQ</h1>
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
            <aside class="admin-map-sidebar" aria-label="Secciones del HQ">
              <nav aria-label="Herramientas de operaciones">
                <button type="button" class="is-active" data-map-section="mapa" aria-pressed="true"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m10 5-2-1-6 3v14l6-3 8 3 6-3v-5M8 4v14M16 16v5M22 7c0 3-5 7-5 7s-5-4-5-7a5 5 0 0 1 10 0ZM19 7a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Mapa</span></button>
                <button type="button" data-map-section="misiones" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 3h2V1h4v2h2v2h5l-1 17H4L3 5h5Z"/><path d="M12 7v10m-4-4 4 4 4-4" fill="none" stroke="#151515" stroke-width="2"/></svg><span>Misiones</span></button>
                <button type="button" data-map-section="canales" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="10" r="2"/><path d="M12 14v7M7 5a7 7 0 0 0 0 10M17 5a7 7 0 0 1 0 10M4 2a11 11 0 0 0 0 16M20 2a11 11 0 0 1 0 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Canales</span></button>
                <button type="button" data-map-section="jugadores" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="9" cy="7" r="4"/><path d="M1 22v-4a8 8 0 0 1 16 0v4ZM18 3a4 4 0 0 1 0 8M19 14a7 7 0 0 1 4 8"/></svg><span>Jugadores</span></button>
                <button type="button" data-map-section="ajustes" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 1-.6 3-2 .9-2.6-1.6-3 5.2L3.2 11v2L.8 15.5l3 5.2 2.6-1.6 2 .9.6 3h6l.6-3 2-.9 2.6 1.6 3-5.2-2.4-2.5v-2l2.4-2.5-3-5.2-2.6 1.6-2-.9L15 1Z"/><circle cx="12" cy="12" r="4" fill="#151515"/></svg><span>Ajustes</span></button>
              </nav>
            </aside>
            <div class="admin-map-content">
              <section data-map-panel="mapa" aria-label="Editor de mapa">
                <div class="hq-console"><div class="hq-operation-bar"><div><h2 class="hq-eyebrow">Centro de Operaciones / Mapa de Operaciones</h2></div><label><select data-hq-map-operation aria-label="Evento del calendario"><option value="">Selecciona una partida</option></select></label></div></div>
                <?php burnout_render_map('hq-admin-map'); ?>
              </section>
              <?php require __DIR__ . '/config/hq_panels.php'; ?>
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
    <script src="assets/js/map.js"></script>
    <script type="text/javascript" src="assets/js/admin_mapainteractivo.js"></script>
    <script src="assets/js/hq.js"></script>
  </body>
</html>
