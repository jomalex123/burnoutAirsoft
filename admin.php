<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$error = '';
$setupError = '';
$adminUser = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'login';

        if (!burnout_check_csrf($_POST['csrf_token'] ?? null)) {
            $error = 'Sesion caducada. Recarga la pagina e intentalo de nuevo.';
        } elseif ($action === 'logout') {
            burnout_logout();
            header('Location: admin.php');
            exit;
        } else {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || $password === '' || !burnout_login($username, $password)) {
                $error = 'Usuario o contrasena incorrectos.';
            } else {
                header('Location: admin.php');
                exit;
            }
        }
    }

    $adminUser = burnout_current_admin();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $setupError = 'No se ha podido conectar con la base de datos o faltan las tablas de administracion.';
}

$csrfToken = burnout_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Administracion - Burnout Airsoft</title>
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
              <h1>Administracion</h1>
            </div>
            <?php if ($adminUser): ?>
              <form class="admin-logout" method="post" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Cerrar sesion</button>
              </form>
              <p>Resumen rapido de registros, galeria y enlaces de gestion de la web.</p>
            <?php else: ?>
              <p>Acceso restringido al panel de administracion.</p>
            <?php endif; ?>
          </div>
        </div>
        <div class="ms-section__block">
          <?php if ($setupError): ?>
            <section class="admin-login-panel">
              <div class="admin-login-error" role="alert"><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?></div>
            </section>
          <?php elseif ($adminUser): ?>
            <section id="admin" class="admin-panel" aria-live="polite">
              <div class="admin-state">Cargando panel...</div>
            </section>
          <?php else: ?>
            <section class="admin-login-panel">
              <form class="admin-login-form" method="post" action="admin.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="login">
                <div class="admin-login-field">
                  <label for="username">Usuario</label>
                  <input id="username" name="username" type="text" autocomplete="username" required autofocus>
                </div>
                <div class="admin-login-field">
                  <label for="password">Contrasena</label>
                  <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <?php if ($error): ?>
                  <div class="admin-login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <button class="admin-login-submit" type="submit">Entrar</button>
              </form>
            </section>
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
    <?php if ($adminUser): ?>
      <script type="text/javascript" src="assets/js/admin.js"></script>
    <?php endif; ?>
  </body>
</html>
