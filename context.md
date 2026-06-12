# Contexto del proyecto Burnout Airsoft

Este fichero sirve como contexto base para nuevas sesiones de desarrollo. Antes de programar en este
proyecto, leer este documento y contrastar los cambios con los patrones descritos aquí.

## Resumen rápido

- Proyecto PHP/HTML/CSS/JS sin framework.
- Páginas públicas en la raíz: `index.html`, `burnout.html`, `campo.html`, `galeria.html`,
  `galeria_bk.html`, `partidas.html`, `registro.html`, `normativa.html`, `privacidad.html`.
- Endpoints/controladores PHP en la raíz: `events.php`, `gallery_items.php`, `instagram_feed.php`,
  `registro.php`, `admin.php`, `admin_partidas.php`, `admin_registros.php`, `admin_gallery.php`,
  `admin_normativa.php`.
- CSS propio en `assets/css/`; JS propio en `assets/js/`.
- Configuración y helpers en `config/`.
- Base de datos MySQL definida en `database/schema.sql`.
- Imágenes de contenido en `images/resources/` y galería administrable en `images/gallery/`.

## Stack y dependencias

- PHP 8.3 en Docker, con Apache y extensión `pdo_mysql`.
- En este equipo puede no haber PHP local. Antes de lanzar cualquier comando PHP, comprobar si `php`
  está disponible; si no lo está, usar el PHP del contenedor Docker del servicio `web`.
- MySQL 8.4 en `docker-compose.yml`.
- Frontend sin bundler: HTML, CSS y JavaScript directo.
- Librerías JS/CSS locales:
  - jQuery 3.7.1.
  - `plugins.min.js` y `plugins.min.css`.
  - Anime.js para transiciones.
  - Swiper para slider del home.
  - Filterizr y UniteGallery para patrones de galería heredados.
  - PDFLib para exportar PDFs desde admin registros.
- Fuente principal: `Yantramanav` importada en `assets/css/style.css` desde Google Fonts.
- Colores base:
  - Negro/fondo oscuro: `#151515`.
  - Rojo marca/acento: `#df1f29`.
  - Gris texto: `#555`.
  - Fondo claro secciones: `#f4f4f4`.
  - Cards blancas: `#fff`.

## Estructura importante

- `assets/css/style.css`: base global, header, nav, footer, slider home, contenedores y estilos
  heredados de plantilla.
- `assets/css/admin.css`: estilos de todo el panel admin.
- `assets/css/partidas.css`: calendario público y layout reutilizado también por admin partidas.
- `assets/css/registro.css`: formulario público de inscripción y modales de normativa/confirmación.
- `assets/css/burnout.css`: página "Nosotros".
- `assets/css/campo.css`: página "Campo" y pantalla de mantenimiento del campo.
- `assets/css/gallery.css`: galería y pantalla de mantenimiento de galería.
- `assets/css/instagram_gallery.css`: galería actual conectada a Instagram con grid de 6 columnas y
  modal estilo `galeria_bk.html`.
- `assets/css/privacidad.css`: política de privacidad.
- `assets/css/normativa.css`: normativa, pensada también para cargarse dentro del modal de registro.
- `assets/css/template.css`: placeholder para páginas generadas desde `template.html`.
- `assets/js/main.js`: inicializador global, menu, transiciones parciales, slider y carga dinámica
  de scripts/estilos.
- `assets/js/partidas.js`: calendario público que consume `events.php`.
- `assets/js/registro.js`: validación del formulario público, modal de normativa y asistentes
  dinámicos.
- `assets/js/instagram_gallery.js`: carga `instagram_feed.php` para pintar la galería Instagram
  actual.
- `assets/js/admin_*.js`: interacciones de administración.
- `config/database.php`: crea un singleton PDO con `PDO::ERRMODE_EXCEPTION`, fetch assoc y prepares
  reales.
- `config/auth.php`: sesiones admin, login, logout, CSRF y rate limit de login.
- `config/rate_limit.php`: límites por tabla `rate_limits`.
- `config/gallery.php`: CRUD/validación de items de galería.
- `config/mail.php`: envío de correo por `mail()` o SMTP manual.
- `config/registration_notifications.php`: auditoría y reenvío de notificaciones de inscripción.
- `config/env_loader.php`: busca el fichero `env.php` según entorno.

## Convenciones HTML públicas

Las páginas públicas siguen esta base:

```html
<!DOCTYPE html>
<html lang="es">
  <head>
    <title>Título - Burnout Airsoft</title>
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
      <!-- header común -->
      <main class="ms-container pagina-page">
        <section class="ms-section__block pagina-header"></section>
      </main>
      <!-- footer común -->
    </div>
    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/js/plugins.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/pagina.js"></script>
  </body>
</html>
```

Notas:

- `plugins.min.css` y `style.css` van antes del CSS específico de pagina.
- `jquery-3.7.1.min.js`, `plugins.min.js` y `main.js` van antes del JS específico de pagina.
- Si una página usa navegación parcial, los enlaces internos deben llevar
  `data-type="page-transition"`.
- Si una página tiene JS propio, exponer `window.initNombrePage = function () { ... }` y hacer que
  `main.js` pueda llamarlo desde `InitPage()`.
- Evitar inicializaciones duplicadas: usar `.off(...).on(...)`, clones de botones o atributos tipo
  `data-...-ready="true"` como hacen los ficheros actuales.

## Header, navbar y footer de referencia

Para crear nuevas páginas públicas, copiar el header/footer de una página actual estable.
Referencias recomendadas:

- Para página pública normal: `burnout.html`, `partidas.html` o `privacidad.html`.
- Para pantalla de mantenimiento/hero oscuro: `campo.html` o `galeria_bk.html`.
- Para página legal embebible en modal: `normativa.html`.
- Para página dinámica de inscripción: `registro.html` + `registro.php`.
- Para base genérica: `template.html`, aunque contiene textos con codificación corrupta y debe
  limpiarse antes de copiar.

Header público actual:

```html
<header class="ms-header">
  <nav class="ms-nav">
    <div class="ms-logo">
      <a class="logonav" href="./" data-type="page-transition">
        <div class="logo-dark"><img src="images/resources/logoBurnout-2.png" alt="logo image"></div>
        <div class="logo-light current">
          <img src="images/resources/logoBurnout-2.png" alt="logo image">
        </div>
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
            <span class="nav-item__label">Vuelve a la página principal</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="galeria.html" data-type="page-transition">
            <span class="ms-btn">Galería</span>
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

Footer público actual:

```html
<footer>
  <div class="ms-footer">
    <div class="copyright" data-copyright-start="2025">
      Copyright &copy; 2025-2026. Designed by Alex Serret
    </div>
    <span class="footer-links">
      <a href="privacidad.html" data-type="page-transition">Política de Privacidad de datos</a>
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
- En móvil, `.socials` se oculta por CSS.

## Patrones visuales públicos

### Página clara con cabecera simple

Usada en `burnout.html`, `partidas.html`, `registro.html`, `privacidad.html`.

- Clase de página: `.nombre-page`.
- Fondo de página: `#f4f4f4`.
- Alto mínimo: `min-height: calc(100vh - 90px)`.
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

- Título grande:

```css
.nombre-header h1 {
  font-size: 52px;
  line-height: 1;
  margin-bottom: 18px;
}
```

- Cards/paneles blancos:

```css
.nombre-card {
  background: #fff;
  border: 1px solid rgba(21, 21, 21, .1);
  border-radius: 8px;
  box-shadow: 0 18px 40px rgba(21, 21, 21, .08);
}
```

### Hero oscuro / mantenimiento

Usado en `campo.html` y `galeria_bk.html`.

- Página con fondo `#151515`.
- Sección full viewport con media absoluta, overlay y contenido encima.
- Imagen compartida de mantenimiento: clase `.maintenance-background` en `style.css`, apuntando a
  `images/resources/maintenance.webp`.
- Kicker rojo, h1 blanco grande con `clamp`, CTA rojo.
- Overlay con `linear-gradient` negro y `radial-gradient` rojo suave.

### Calendario

Usado en `partidas.html` y reutilizado en `admin_partidas.php`.

- Layout principal: `.partidas-layout` con grid de calendario + panel.
- Calendario: `.partidas-calendar`.
- Panel lateral: `.partidas-panel`.
- Días: `.partidas-day`.
- Evento dentro de día: `.partidas-event-slice`.
- Colores por turno:
  - Manana: negro.
  - Tarde: rojo.
  - Noche: amarillo.

### Formularios públicos

Usado en `registro.html`.

- Inputs dentro de `.registro-field`.
- Errores por `<span class="registro-error" data-error-for="id"></span>`.
- Validación cliente en `assets/js/registro.js`.
- Validación servidor en `registro.php`; la validación cliente no sustituye a la de servidor.
- Botón principal rojo: `.registro-submit`.
- Botón secundario blanco: `.registro-clear`.
- Modales: `.registro-modal`, `.registro-modal__overlay`, `.registro-modal__dialog`, `.is-open`.

## Navegación parcial y ciclo de vida JS

`assets/js/main.js` implementa una navegación parcial:

- Intercepta clicks en enlaces con `data-type="page-transition"`.
- Si el origen y destino no son index, hace `fetch()` de la página destino.
- Extrae `main.ms-container` del HTML recibido.
- Carga CSS específicos no cargados previamente.
- Sustituye el `main.ms-container` actual.
- Sincroniza modales fuera/dentro del main para `#modal`, `#instagramModal`, `#normativaModal`,
  `#registroConfirmationModal`.
- Carga scripts específicos de página que no sean base.
- Llama a `InitPage()` otra vez.

Implicaciones para nuevas páginas:

- Mantener siempre un único `<main class="ms-container ...">`.
- Si la página tiene modales, comprobar si deben agregarse a `collectDetachedPageElements()` y
  `syncDetachedPageElements()` en `main.js`.
- Si se añade JS específico, registrarlo en `InitPage()`:

```js
if (typeof window.initMiPaginaPage === 'function') {
    window.initMiPaginaPage();
}
```

- Dentro del JS específico, usar un patrón compatible con carga directa y carga parcial:

```js
window.initMiPaginaPage = function () {
  var root = document.querySelector('.mi-pagina-page');

  if (!root || root.getAttribute('data-mi-pagina-ready') === 'true') {
    return;
  }

  root.setAttribute('data-mi-pagina-ready', 'true');
  // binds aquí
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
- En admin, usuario requerido con `burnout_current_admin()` y redirección/login si no hay sesión.

### Base de datos

Tablas principales:

- `admin_users`: usuarios admin.
- `admin_login_audit`: auditoría de login.
- `rate_limits`: rate limiting.
- `events`: partidas.
- `registrations`: inscripciones.
- `registration_attendees`: asistentes de cada inscripción.
- `registration_email_notifications`: auditoría de emails.
- `gallery`: items de galería.

### Entornos

`config/env_loader.php` busca configuración en este orden:

- `BURNOUT_ENV_FILE` si existe.
- Rutas privadas fuera del directorio público.
- `config/env.php`.
- `config/env.example.php`.

No documentar ni exponer credenciales reales. Para nuevas referencias usar siempre
`config/env.example.php`.

## Admin

Paginas:

- `admin.php`: login, dashboard y últimos registros.
- `admin_partidas.php`: CRUD de eventos/partidas y calendario administrable.
- `admin_registros.php`: listado de registros, filtros, ordenación, borrado, detalle, reenvío de
  notificaciones, export CSV/PDF.
- `admin_gallery.php`: alta/borrado/listado/paginación de imágenes de galería.
- `admin_normativa.php`: editor del título y de los bloques HTML de `normativa.html`.

### Galería Instagram

- `galeria.html` es la galería actual con el mismo header/footer y estética Burnout.
- Visualmente replica el patrón de `galeria_bk.html`: miniaturas cuadradas, 6 imágenes por fila en
  escritorio, 3 en móvil y modal con imagen + texto de la publicación.
- El grid carga datos desde `instagram_feed.php` mediante `data-instagram-source`.
- La carga inicial trae 12 publicaciones y el botón `Cargar más` solicita 12 adicionales usando
  `pagination.nextCursor` del endpoint.
- `instagram_feed.php` devuelve `assets/data/instagram_gallery.json` si la API no está configurada.
- Para automatizar publicaciones reales, configurar en `env.php` la clave `instagram` con `enabled`,
  `graph_version`, `user_id`, `access_token`, `limit` y `profile_url`.
- En local XAMPP puede ser necesario `instagram.ca_file` o, solo para pruebas locales,
  `instagram.ssl_verify => false` si PHP no valida certificados. En producción debe mantenerse
  verificación SSL activa.
- No hacer scraping de Instagram público: usar API oficial o mantener el JSON local.

Patrones UI admin:

- Fondo claro `.admin-page`.
- Cabecera `.admin-header` + `.admin-kicker`.
- Panels/cards con fondo blanco, border, radius 8 y sombra.
- Botón principal rojo: `.admin-login-submit`, `.admin-add-image-button`.
- Boton peligro: `.admin-danger-submit`.
- Modales reutilizan clases `.admin-gallery-modal*` incluso fuera de galería.
- Tablas con `.admin-table`, `.admin-table-scroll`, botones de sort `data-sort-key`.

Patrones PHP admin:

- CSRF en todos los formularios POST.
- Redirecciones post-action con flash messages cuando aplica.
- Borrados protegidos: por ejemplo, eventos con registros no deben eliminarse sin control.
- Usar `htmlspecialchars` en cada valor renderizado.

### Normativa editable

- `normativa.html` sigue siendo la URL publica y también la fuente que carga el modal de
  `registro.html`.
- Los bloques editables estan delimitados por comentarios `BURNOUT_NORMATIVA_BLOCK_START` y
  `BURNOUT_NORMATIVA_BLOCK_END`.
- `admin_normativa.php` lee esos marcadores, permite editar cada bloque, borrar bloques y anadir
  bloques nuevos.
- Cada bloque del editor tiene un boton `Ver contenido` / `Ocultar contenido` para alternar entre
  código HTML y previsualización WEB.
- La previsualización cliente se gestiona en `assets/js/admin_normativa.js` y no guarda cambios
  hasta que se pulsa el boton de guardar.
- No eliminar esos comentarios manualmente: si faltan, el admin no puede reconstruir la pagina.
- El editor permite HTML de contenido como `h3`, `h4`, `p`, `ul`, `li`, `strong`, `div`, `table`,
  `thead`, `tbody`, `tr`, `th` y `td`.
- El editor bloquea etiquetas peligrosas o interactivas como `script`, `iframe`, `object`, `embed`,
  `form`, `input`, `button`, atributos `on...` y URLs `javascript:`.

## Seguridad y configuración HTTP

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

- Si se añaden recursos externos, revisar CSP.
- Si se añaden nuevas rutas privadas, bloquearlas en `.htaccess`.
- Los `.md` no son servibles por navegador según `FilesMatch`, por tanto `context.md` queda
  protegido.

## Codificación y texto

Hay mezcla de:

- Texto UTF-8 correcto.
- Entidades HTML (`&aacute;`, `&copy;`).
- Texto corrupto/mojibake en algunas plantillas antiguas; por ejemplo, palabras con acentos
  renderizadas como secuencias extrañas.

Regla para nuevos cambios:

- No copiar textos corruptos.
- Preferir UTF-8 real si el fichero se guarda correctamente.
- Si hay duda en HTML, usar entidades HTML ASCII para acentos: `Galer&iacute;a`, `p&aacute;gina`,
  `Inscripci&oacute;n`, `Ma&ntilde;ana`.
- En JS/CSS, preferir identificadores ASCII sin acentos (`manana`, `tarde`, `noche`) y mostrar
  textos de usuario separados.

## Referencias para crear nuevas páginas

Usar estas páginas como base según el caso:

- Nueva página informativa clara: `privacidad.html` + `assets/css/privacidad.css`.
- Nueva página con bloque texto/imagen: `burnout.html` + `assets/css/burnout.css`.
- Nueva página con calendario o panel lateral: `partidas.html` + `assets/css/partidas.css`.
- Nueva página con formulario público: `registro.html` + `assets/css/registro.css` + patrón servidor
  de `registro.php`.
- Nueva pantalla de mantenimiento o landing oscura: `campo.html` o `galeria_bk.html`.
- Nueva galería conectable a Instagram: `galeria.html` + `assets/css/instagram_gallery.css` +
  `assets/js/instagram_gallery.js`.
- Nueva página admin: `admin_gallery.php` si necesita modal/form/listado simple;
  `admin_registros.php` si necesita tabla/filtros/export; `admin_partidas.php` si necesita
  calendario editable.

## Checklist antes de implementar una página nueva

- Definir si será `.html` estática, `.php` dinámica o endpoint JSON.
- Copiar header/footer desde una página estable y limpiar codificación.
- Incluir `plugins.min.css`, `style.css` y CSS específico en ese orden.
- Usar `<main class="ms-container nombre-page">`.
- Agrupar contenido en `.ms-section__block`.
- Crear CSS específico en `assets/css/nombre.css`, con prefijo de clases `nombre-`.
- Si hay JS, crear `assets/js/nombre.js` con `window.initNombrePage`.
- Registrar init en `main.js`.
- Incluir script específico después de `main.js`.
- Si hay formularios, validar en cliente y servidor.
- Si hay POST admin, incluir CSRF.
- Si hay salida PHP, escapar con `htmlspecialchars`.
- Si hay JSON, devolver `Content-Type: application/json; charset=utf-8`.
- Revisar responsive en 900px, 700px y 640px, que son breakpoints habituales.
- Revisar navegación directa y navegación parcial por `data-type="page-transition"`.

## Riesgos técnicos actuales

- Duplicación de header/footer en casi todas las páginas. Cualquier cambio global exige editar
  múltiples ficheros.
- Problemas de codificación ya presentes en HTML/PHP/JS/CSS.
- `template.html` no es una fuente perfecta porque contiene textos corruptos.
- `main.js` tiene una lista fija de modales externos; nuevos modales fuera del main pueden romperse
  en navegación parcial si no se sincronizan.
- El proyecto no tiene pruebas automatizadas.
- No hay gestor de dependencias frontend/backend; las librerías están vendorizadas manualmente.
- El CSS global mezcla plantilla original con overrides del proyecto.
- El endpoint `events.php` devuelve todos los eventos sin paginación/filtro; suficiente para volumen
  bajo, pero a vigilar si crece.
- La galería valida rutas locales existentes; para subir imágenes reales todavía haría falta un
  flujo de upload si se quiere administrar desde UI.

## Comandos útiles

Local con Docker:

```bash
docker compose up --build
```

Crear tablas:

```bash
docker compose exec -T db sh -c \
  'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  < database/schema.sql
```

En la primera creación del contenedor MySQL, `database/schema.sql` se importa automáticamente desde
`/docker-entrypoint-initdb.d/01-schema.sql`. Si la base ya existe, importar el esquema manualmente
solo cuando haga falta.

Migraciones:

```bash
docker compose exec -T db sh -c \
  'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  < database/migrations/archivo.sql
```

Crear admin:

```bash
php scripts/create_admin_user.php admin "password-seguro" "Burnout Admin"
```

Si no hay PHP local, usar Docker:

```bash
docker compose run --rm web php scripts/create_admin_user.php \
  admin "password-seguro" "Burnout Admin"
```

Patrón general para comandos PHP en este equipo:

```bash
command -v php >/dev/null 2>&1 \
  && php ruta/al/script.php \
  || docker compose run --rm web php ruta/al/script.php
```

## Instrucción para futuras sesiones

Cuando se abra una sesión nueva, empezar diciendo:

```text
Lee context.md en la raíz del proyecto antes de hacer cambios.
```

Después de leerlo, cualquier cambio debe respetar:

- Estética Burnout: negro, rojo `#df1f29`, blanco, gris claro, tipografía Yantramanav.
- Header/nav/footer existentes.
- Estructura `ms-main-container` + `ms-container` + `ms-section__block`.
- Inicialización JS compatible con navegación parcial.
- Validación y seguridad PHP existentes.
- Evitar introducir más codificación corrupta.
