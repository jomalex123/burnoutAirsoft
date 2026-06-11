# Contexto del proyecto Burnout Airsoft

Este fichero sirve como contexto base para nuevas sesiones de desarrollo. Antes de programar en este proyecto, leer este documento y contrastar los cambios con los patrones descritos aqui.

## Resumen rapido

- Proyecto PHP/HTML/CSS/JS sin framework.
- Paginas publicas en la raiz: `index.html`, `burnout.html`, `campo.html`, `galeria.html`, `galeria_instagram.html`, `partidas.html`, `registro.html`, `normativa.html`, `privacidad.html`.
- Endpoints/controladores PHP en la raiz: `events.php`, `gallery_items.php`, `instagram_feed.php`, `registro.php`, `admin.php`, `admin_partidas.php`, `admin_registros.php`, `admin_gallery.php`, `admin_normativa.php`.
- CSS propio en `assets/css/`; JS propio en `assets/js/`.
- Configuracion y helpers en `config/`.
- Base de datos MySQL definida en `database/schema.sql`.
- Imagenes de contenido en `images/resources/` y galeria administrable en `images/gallery/`.

## Stack y dependencias

- PHP 8.3 en Docker, con Apache y extension `pdo_mysql`.
- En este equipo puede no haber PHP local. Antes de lanzar cualquier comando PHP, comprobar si `php` esta disponible; si no lo esta, usar el PHP del contenedor Docker del servicio `web`.
- MySQL 8.4 en `docker-compose.yml`.
- Frontend sin bundler: HTML, CSS y JavaScript directo.
- Librerias JS/CSS locales:
  - jQuery 3.7.1.
  - `plugins.min.js` y `plugins.min.css`.
  - Anime.js para transiciones.
  - Swiper para slider del home.
  - Filterizr y UniteGallery para patrones de galeria heredados.
  - PDFLib para exportar PDFs desde admin registros.
- Fuente principal: `Yantramanav` importada en `assets/css/style.css` desde Google Fonts.
- Colores base:
  - Negro/fondo oscuro: `#151515`.
  - Rojo marca/acento: `#df1f29`.
  - Gris texto: `#555`.
  - Fondo claro secciones: `#f4f4f4`.
  - Cards blancas: `#fff`.

## Estructura importante

- `assets/css/style.css`: base global, header, nav, footer, slider home, contenedores y estilos heredados de plantilla.
- `assets/css/admin.css`: estilos de todo el panel admin.
- `assets/css/partidas.css`: calendario publico y layout reutilizado tambien por admin partidas.
- `assets/css/registro.css`: formulario publico de inscripcion y modales de normativa/confirmacion.
- `assets/css/burnout.css`: pagina "Nosotros".
- `assets/css/campo.css`: pagina "Campo" y pantalla de mantenimiento del campo.
- `assets/css/gallery.css`: galeria y pantalla de mantenimiento de galeria.
- `assets/css/instagram_gallery.css`: prueba de galeria Instagram con grid y fuente JSON.
- `assets/css/privacidad.css`: politica de privacidad.
- `assets/css/normativa.css`: normativa, pensada tambien para cargarse dentro del modal de registro.
- `assets/css/template.css`: placeholder para paginas generadas desde `template.html`.
- `assets/js/main.js`: inicializador global, menu, transiciones parciales, slider y carga dinamica de scripts/estilos.
- `assets/js/partidas.js`: calendario publico que consume `events.php`.
- `assets/js/registro.js`: validacion del formulario publico, modal de normativa y asistentes dinamicos.
- `assets/js/instagram_gallery.js`: carga `instagram_feed.php` para pintar la prueba de galeria Instagram.
- `assets/js/admin_*.js`: interacciones de administracion.
- `config/database.php`: crea un singleton PDO con `PDO::ERRMODE_EXCEPTION`, fetch assoc y prepares reales.
- `config/auth.php`: sesiones admin, login, logout, CSRF y rate limit de login.
- `config/rate_limit.php`: limites por tabla `rate_limits`.
- `config/gallery.php`: CRUD/validacion de items de galeria.
- `config/mail.php`: envio de correo por `mail()` o SMTP manual.
- `config/registration_notifications.php`: auditoria y reenvio de notificaciones de inscripcion.
- `config/env_loader.php`: busca el fichero `env.php` segun entorno.

## Convenciones HTML publicas

Las paginas publicas siguen esta base:

```html
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Titulo - Burnout Airsoft</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/resources/logoBurnout-4.png" />
    <link rel="stylesheet" href="assets/css/plugins.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/pagina.css">
  </head>
  <body>
    <div class="ms-main-container">
      <div class="ms-preloader"></div>
      <!-- header comun -->
      <main class="ms-container pagina-page">
        <section class="ms-section__block pagina-header"></section>
      </main>
      <!-- footer comun -->
    </div>
    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/js/plugins.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/pagina.js"></script>
  </body>
</html>
```

Notas:

- `plugins.min.css` y `style.css` van antes del CSS especifico de pagina.
- `jquery-3.7.1.min.js`, `plugins.min.js` y `main.js` van antes del JS especifico de pagina.
- Si una pagina usa navegacion parcial, los enlaces internos deben llevar `data-type="page-transition"`.
- Si una pagina tiene JS propio, exponer `window.initNombrePage = function () { ... }` y hacer que `main.js` pueda llamarlo desde `InitPage()`.
- Evitar inicializaciones duplicadas: usar `.off(...).on(...)`, clones de botones o atributos tipo `data-...-ready="true"` como hacen los ficheros actuales.

## Header, navbar y footer de referencia

Para crear nuevas paginas publicas, copiar el header/footer de una pagina actual estable. Referencias recomendadas:

- Para pagina publica normal: `burnout.html`, `partidas.html` o `privacidad.html`.
- Para pantalla de mantenimiento/hero oscuro: `campo.html` o `galeria.html`.
- Para pagina legal embebible en modal: `normativa.html`.
- Para pagina dinamica de inscripcion: `registro.html` + `registro.php`.
- Para base generica: `template.html`, aunque contiene textos con codificacion corrupta y debe limpiarse antes de copiar.

Header publico actual:

```html
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
```

Footer publico actual:

```html
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
```

Notas de footer:

- `main.js` recalcula el texto de copyright con `data-copyright-start`.
- Los iconos sociales dependen de clases `socicon-*` incluidas en los plugins.
- En movil, `.socials` se oculta por CSS.

## Patrones visuales publicos

### Pagina clara con cabecera simple

Usada en `burnout.html`, `partidas.html`, `registro.html`, `privacidad.html`.

- Clase de pagina: `.nombre-page`.
- Fondo de pagina: `#f4f4f4`.
- Alto minimo: `min-height: calc(100vh - 90px)`.
- Bloque principal: `section.ms-section__block`.
- Kicker rojo:

```css
.nombre-kicker {
  color: #df1f29;
  display: block;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: .16em;
  margin-bottom: 12px;
  text-transform: uppercase;
}
```

- Titulo grande:

```css
.nombre-header h1 {
  font-size: 52px;
  line-height: 1;
  margin-bottom: 18px;
}
```

- Cards/paneles blancos:

```css
background: #fff;
border: 1px solid rgba(21, 21, 21, .1);
border-radius: 8px;
box-shadow: 0 18px 40px rgba(21, 21, 21, .08);
```

### Hero oscuro / mantenimiento

Usado en `campo.html` y `galeria.html`.

- Pagina con fondo `#151515`.
- Seccion full viewport con media absoluta, overlay y contenido encima.
- Imagen compartida de mantenimiento: clase `.maintenance-background` en `style.css`, apuntando a `images/resources/maintenance.webp`.
- Kicker rojo, h1 blanco grande con `clamp`, CTA rojo.
- Overlay con `linear-gradient` negro y `radial-gradient` rojo suave.

### Calendario

Usado en `partidas.html` y reutilizado en `admin_partidas.php`.

- Layout principal: `.partidas-layout` con grid de calendario + panel.
- Calendario: `.partidas-calendar`.
- Panel lateral: `.partidas-panel`.
- Dias: `.partidas-day`.
- Evento dentro de dia: `.partidas-event-slice`.
- Colores por turno:
  - Manana: negro.
  - Tarde: rojo.
  - Noche: amarillo.

Aviso: hay clases generadas como `is-MaÃ±ana` por problema de codificacion. Si se corrige, alinear JS y CSS a valores ASCII (`is-manana`, `is-tarde`, `is-noche`) para evitar errores.

### Formularios publicos

Usado en `registro.html`.

- Inputs dentro de `.registro-field`.
- Errores por `<span class="registro-error" data-error-for="id"></span>`.
- Validacion cliente en `assets/js/registro.js`.
- Validacion servidor en `registro.php`; la validacion cliente no sustituye a la de servidor.
- Boton principal rojo: `.registro-submit`.
- Boton secundario blanco: `.registro-clear`.
- Modales: `.registro-modal`, `.registro-modal__overlay`, `.registro-modal__dialog`, `.is-open`.

## Navegacion parcial y ciclo de vida JS

`assets/js/main.js` implementa una navegacion parcial:

- Intercepta clicks en enlaces con `data-type="page-transition"`.
- Si el origen y destino no son index, hace `fetch()` de la pagina destino.
- Extrae `main.ms-container` del HTML recibido.
- Carga CSS especificos no cargados previamente.
- Sustituye el `main.ms-container` actual.
- Sincroniza modales fuera/dentro del main para `#modal`, `#normativaModal`, `#registroConfirmationModal`.
- Carga scripts especificos de pagina que no sean base.
- Llama a `InitPage()` otra vez.

Implicaciones para nuevas paginas:

- Mantener siempre un unico `<main class="ms-container ...">`.
- Si la pagina tiene modales, comprobar si deben agregarse a `collectDetachedPageElements()` y `syncDetachedPageElements()` en `main.js`.
- Si se anade JS especifico, registrarlo en `InitPage()`:

```js
if (typeof window.initMiPaginaPage === 'function') {
    window.initMiPaginaPage();
}
```

- Dentro del JS especifico, usar un patron compatible con carga directa y carga parcial:

```js
window.initMiPaginaPage = function () {
  var root = document.querySelector('.mi-pagina-page');

  if (!root || root.getAttribute('data-mi-pagina-ready') === 'true') {
    return;
  }

  root.setAttribute('data-mi-pagina-ready', 'true');
  // binds aqui
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initMiPaginaPage);
}
```

## Backend PHP

Patrones usados:

- `declare(strict_types=1);` al inicio de PHP nuevo.
- `require_once __DIR__ . '/config/...';`.
- Acceso a BD por `burnout_pdo()`.
- Consultas preparadas con PDO.
- Salida HTML escapada con `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- Respuestas JSON con:

```php
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
```

- Excepciones capturadas con `Throwable`, `error_log($exception->getMessage())` y respuesta segura.
- En admin, proteger POST con `burnout_check_csrf()`.
- En admin, usuario requerido con `burnout_current_admin()` y redireccion/login si no hay sesion.

### Base de datos

Tablas principales:

- `admin_users`: usuarios admin.
- `admin_login_audit`: auditoria de login.
- `rate_limits`: rate limiting.
- `events`: partidas.
- `registrations`: inscripciones.
- `registration_attendees`: asistentes de cada inscripcion.
- `registration_email_notifications`: auditoria de emails.
- `gallery`: items de galeria.

### Entornos

`config/env_loader.php` busca configuracion en este orden:

- `BURNOUT_ENV_FILE` si existe.
- Rutas privadas fuera del directorio publico.
- `config/env.php`.
- `config/env.example.php`.

No documentar ni exponer credenciales reales. Para nuevas referencias usar siempre `config/env.example.php`.

## Admin

Paginas:

- `admin.php`: login, dashboard y ultimos registros.
- `admin_partidas.php`: CRUD de eventos/partidas y calendario administrable.
- `admin_registros.php`: listado de registros, filtros, ordenacion, borrado, detalle, reenvio de notificaciones, export CSV/PDF.
- `admin_gallery.php`: alta/borrado/listado/paginacion de imagenes de galeria.
- `admin_normativa.php`: editor del titulo y de los bloques HTML de `normativa.html`.

### Galeria Instagram

- `galeria_instagram.html` es una prueba de galeria con el mismo header/footer y estetica Burnout.
- El grid carga datos desde `instagram_feed.php` mediante `data-instagram-source`.
- `instagram_feed.php` devuelve `assets/data/instagram_gallery.json` si la API no esta configurada.
- Para automatizar publicaciones reales, configurar en `env.php` la clave `instagram` con `enabled`, `graph_version`, `user_id`, `access_token`, `limit` y `profile_url`.
- No hacer scraping de Instagram publico: usar API oficial o mantener el JSON local.

Patrones UI admin:

- Fondo claro `.admin-page`.
- Cabecera `.admin-header` + `.admin-kicker`.
- Panels/cards con fondo blanco, border, radius 8 y sombra.
- Boton principal rojo: `.admin-login-submit`, `.admin-add-image-button`.
- Boton peligro: `.admin-danger-submit`.
- Modales reutilizan clases `.admin-gallery-modal*` incluso fuera de galeria.
- Tablas con `.admin-table`, `.admin-table-scroll`, botones de sort `data-sort-key`.

Patrones PHP admin:

- CSRF en todos los formularios POST.
- Redirecciones post-action con flash messages cuando aplica.
- Borrados protegidos: por ejemplo, eventos con registros no deben eliminarse sin control.
- Usar `htmlspecialchars` en cada valor renderizado.

### Normativa editable

- `normativa.html` sigue siendo la URL publica y tambien la fuente que carga el modal de `registro.html`.
- Los bloques editables estan delimitados por comentarios `BURNOUT_NORMATIVA_BLOCK_START` y `BURNOUT_NORMATIVA_BLOCK_END`.
- `admin_normativa.php` lee esos marcadores, permite editar cada bloque, borrar bloques y anadir bloques nuevos.
- Cada bloque del editor tiene un boton `Ver contenido` / `Ocultar contenido` para alternar entre codigo HTML y previsualizacion WEB.
- La previsualizacion cliente se gestiona en `assets/js/admin_normativa.js` y no guarda cambios hasta que se pulsa el boton de guardar.
- No eliminar esos comentarios manualmente: si faltan, el admin no puede reconstruir la pagina.
- El editor permite HTML de contenido como `h3`, `h4`, `p`, `ul`, `li`, `strong`, `div`, `table`, `thead`, `tbody`, `tr`, `th` y `td`.
- El editor bloquea etiquetas peligrosas o interactivas como `script`, `iframe`, `object`, `embed`, `form`, `input`, `button`, atributos `on...` y URLs `javascript:`.

## Seguridad y configuracion HTTP

`.htaccess` actual:

- `Options -Indexes`.
- Headers de seguridad:
  - `X-Content-Type-Options: nosniff`.
  - `X-Frame-Options: SAMEORIGIN`.
  - `Referrer-Policy: strict-origin-when-cross-origin`.
  - `Permissions-Policy`.
  - CSP restrictiva con scripts/styles self + inline, fonts Google y frames Google/Drive.
  - HSTS solo si HTTPS.
- Bloquea ficheros sensibles como `env.php`, `*.sql`, `*.md`, logs, dotfiles.
- Devuelve 404 para `.git`, `.idea`, `config`, `database`, `scripts` y otros directorios.

Implicacion:

- Si se anaden recursos externos, revisar CSP.
- Si se anaden nuevas rutas privadas, bloquearlas en `.htaccess`.
- Los `.md` no son servibles por navegador segun `FilesMatch`, por tanto `context.md` queda protegido.

## Codificacion y texto

Hay mezcla de:

- Texto UTF-8 correcto.
- Entidades HTML (`&aacute;`, `&copy;`).
- Texto corrupto/mojibake (`GalerÃ­a`, `InscripciÃ³n`, `MaÃ±ana`).

Regla para nuevos cambios:

- No copiar textos corruptos.
- Preferir UTF-8 real si el fichero se guarda correctamente.
- Si hay duda en HTML, usar entidades HTML ASCII para acentos: `Galer&iacute;a`, `p&aacute;gina`, `Inscripci&oacute;n`, `Ma&ntilde;ana`.
- En JS/CSS, preferir identificadores ASCII sin acentos (`manana`, `tarde`, `noche`) y mostrar textos de usuario separados.

## Referencias para crear nuevas paginas

Usar estas paginas como base segun el caso:

- Nueva pagina informativa clara: `privacidad.html` + `assets/css/privacidad.css`.
- Nueva pagina con bloque texto/imagen: `burnout.html` + `assets/css/burnout.css`.
- Nueva pagina con calendario o panel lateral: `partidas.html` + `assets/css/partidas.css`.
- Nueva pagina con formulario publico: `registro.html` + `assets/css/registro.css` + patron servidor de `registro.php`.
- Nueva pantalla de mantenimiento o landing oscura: `campo.html` o `galeria.html`.
- Nueva galeria conectable a Instagram: `galeria_instagram.html` + `assets/css/instagram_gallery.css` + `assets/js/instagram_gallery.js`.
- Nueva pagina admin: `admin_gallery.php` si necesita modal/form/listado simple; `admin_registros.php` si necesita tabla/filtros/export; `admin_partidas.php` si necesita calendario editable.

## Checklist antes de implementar una pagina nueva

- Definir si sera `.html` estatica, `.php` dinamica o endpoint JSON.
- Copiar header/footer desde una pagina estable y limpiar codificacion.
- Incluir `plugins.min.css`, `style.css` y CSS especifico en ese orden.
- Usar `<main class="ms-container nombre-page">`.
- Agrupar contenido en `.ms-section__block`.
- Crear CSS especifico en `assets/css/nombre.css`, con prefijo de clases `nombre-`.
- Si hay JS, crear `assets/js/nombre.js` con `window.initNombrePage`.
- Registrar init en `main.js`.
- Incluir script especifico despues de `main.js`.
- Si hay formularios, validar en cliente y servidor.
- Si hay POST admin, incluir CSRF.
- Si hay salida PHP, escapar con `htmlspecialchars`.
- Si hay JSON, devolver `Content-Type: application/json; charset=utf-8`.
- Revisar responsive en 900px, 700px y 640px, que son breakpoints habituales.
- Revisar navegacion directa y navegacion parcial por `data-type="page-transition"`.

## Riesgos tecnicos actuales

- Duplicacion de header/footer en casi todas las paginas. Cualquier cambio global exige editar multiples ficheros.
- Problemas de codificacion ya presentes en HTML/PHP/JS/CSS.
- `template.html` no es una fuente perfecta porque contiene textos corruptos.
- `main.js` tiene una lista fija de modales externos; nuevos modales fuera del main pueden romperse en navegacion parcial si no se sincronizan.
- El proyecto no tiene tests automatizados.
- No hay gestor de dependencias frontend/backend; las librerias estan vendorizadas manualmente.
- El CSS global mezcla plantilla original con overrides del proyecto.
- El endpoint `events.php` devuelve todos los eventos sin paginacion/filtro; suficiente para volumen bajo, pero a vigilar si crece.
- La galeria valida rutas locales existentes; para subir imagenes reales todavia haria falta un flujo de upload si se quiere administrar desde UI.

## Comandos utiles

Local con Docker:

```bash
docker compose up --build
```

Crear tablas:

```bash
docker compose exec -T db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < database/schema.sql
```

En la primera creacion del contenedor MySQL, `database/schema.sql` se importa automaticamente desde `/docker-entrypoint-initdb.d/01-schema.sql`. Si la base ya existe, importar el esquema manualmente solo cuando haga falta.

Migraciones:

```bash
docker compose exec -T db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/archivo.sql
```

Crear admin:

```bash
php scripts/create_admin_user.php admin "password-seguro" "Burnout Admin"
```

Si no hay PHP local, usar Docker:

```bash
docker compose run --rm web php scripts/create_admin_user.php admin "password-seguro" "Burnout Admin"
```

Patron general para comandos PHP en este equipo:

```bash
command -v php >/dev/null 2>&1 && php ruta/al/script.php || docker compose run --rm web php ruta/al/script.php
```

## Instruccion para futuras sesiones

Cuando se abra una sesion nueva, empezar diciendo:

```text
Lee context.md en la raiz del proyecto antes de hacer cambios.
```

Despues de leerlo, cualquier cambio debe respetar:

- Estetica Burnout: negro, rojo `#df1f29`, blanco, gris claro, tipografia Yantramanav.
- Header/nav/footer existentes.
- Estructura `ms-main-container` + `ms-container` + `ms-section__block`.
- Inicializacion JS compatible con navegacion parcial.
- Validacion y seguridad PHP existentes.
- Evitar introducir mas codificacion corrupta.
