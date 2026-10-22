# Instrucciones del proyecto Burnout Airsoft

Estas instrucciones se aplican a todo el repositorio. Antes de realizar cambios, leer `context.md`, `README.md` y, si el trabajo afecta a datos o entornos, `database/README.md`. Revisar los archivos relacionados antes de implementar y respetar los patrones existentes.

La documentación puede estar desactualizada. Si difiere del código, contrastar el comportamiento actual y comunicar las discrepancias relevantes. No reproducir funcionalidades antiguas solo porque aparezcan en `context.md`.

## Comunicación y alcance

- Comunicarse con el usuario en español.
- Hacer cambios centrados en la tarea, conservando el trabajo existente del usuario.
- No introducir frameworks, gestores de dependencias, herramientas de compilación ni reorganizaciones generales sin que la tarea lo requiera.
- No exponer credenciales, tokens ni datos personales. Usar `config/env.example.php` para ejemplos de configuración.

## Arquitectura

- El entorno local del usuario utiliza XAMPP, no Docker. Usar Apache/MySQL de XAMPP y el PHP de `D:\xampp\php\php.exe` si `php` no está en el PATH. No arrancar Docker para trabajar en local.
- La conexión local se configura en `config/env.php`, con entorno `local`, host `127.0.0.1` y puerto MySQL `3306`. Verificarla con `scripts/check_database.php` sin exponer credenciales.

- Aplicación PHP, HTML, CSS y JavaScript sin framework ni bundler.
- Páginas públicas HTML, páginas dinámicas PHP y endpoints en la raíz.
- CSS propio en `assets/css/`, JavaScript propio en `assets/js/` y librerías locales en sus directorios actuales.
- Configuración y funciones compartidas en `config/`.
- Esquema de base de datos en `database/schema.sql` y cambios para instalaciones existentes en `database/migrations/`.
- Backend principalmente procedural, con funciones auxiliares. Mantener esta organización en los cambios.

## HTML y aspecto visual

- Mantener la identidad visual: Yantramanav, negro `#151515`, rojo `#df1f29`, gris de texto `#555`, fondo claro `#f4f4f4` y blanco `#fff`.
- Reutilizar el header, navegación y footer de una página actual equivalente.
- Respetar la estructura `.ms-main-container`, un único `main.ms-container` y secciones `.ms-section__block`.
- Cargar CSS en este orden: `plugins.min.css`, `style.css` y estilos específicos de página.
- Usar `burnout.html`, `partidas.html`, `privacidad.html` o `registro.html` como referencias según el tipo de página. Revisar las referencias antes de copiarlas.
- Conservar los marcadores `BURNOUT_NORMATIVA_BLOCK_START` y `BURNOUT_NORMATIVA_BLOCK_END`: el editor de normativa depende de ellos.

## CSS

- Usar CSS tradicional en archivos `.css`; mantener los estilos generales en `style.css` y los de cada funcionalidad en su archivo correspondiente.
- Emplear clases con prefijo de página o funcionalidad: `burnout-`, `registro-`, `partidas-`, `admin-`, etc.
- Respetar los nombres existentes, incluidos elementos como `.burnout-about__content` y estados como `.is-open`.
- Seguir los layouts existentes con Grid y Flexbox, tarjetas blancas, bordes sutiles, radio habitual de 8px y sombras suaves cuando corresponda.
- Mantener el enfoque responsive con media queries. Revisar los breakpoints del archivo afectado; son frecuentes 900px, 700px y 640px, pero hay otros.
- Evitar reglas globales que alteren páginas ajenas a la tarea.

## JavaScript y navegación parcial

- Mantener JavaScript directo y jQuery donde ya se utilice, sin introducir módulos o compilación de forma aislada.
- Cargar scripts base antes de los específicos: jQuery, `plugins.min.js`, `main.js` y el script de página.
- Exponer la inicialización de página como `window.initNombrePage` y registrarla en `InitPage()` de `assets/js/main.js`.
- Comprobar que existe el contenedor de la página antes de inicializar.
- Permitir carga directa y navegación parcial. Seguir el tratamiento existente de `DOMContentLoaded` y `window.__burnoutLoadingPageScript`.
- Evitar listeners o inicializaciones duplicadas mediante atributos de estado, eventos con namespace y `.off().on()`, u otro patrón ya utilizado en el archivo.
- Los enlaces internos con transición usan `data-type="page-transition"`.
- La navegación parcial sustituye `main.ms-container` y carga recursos específicos. Si se añaden modales fuera del main, revisar `collectDetachedPageElements()` y `syncDetachedPageElements()`.

## PHP y seguridad

- Usar `declare(strict_types=1);` en archivos PHP nuevos y `require_once` con rutas basadas en `__DIR__`.
- Mantener funciones compartidas con prefijo `burnout_` y las convenciones locales del archivo.
- Acceder a MySQL mediante `burnout_pdo()` y consultas preparadas para valores variables.
- Escapar valores renderizados en HTML con `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- Validar entradas en servidor, aunque exista validación en cliente.
- Proteger páginas administrativas mediante la autenticación existente y comprobar CSRF en acciones POST.
- Respetar las redirecciones tras acciones y los mensajes flash donde ya se utilicen.
- Devolver JSON con `Content-Type: application/json; charset=utf-8`, códigos HTTP adecuados y las opciones de codificación existentes.
- Capturar errores siguiendo el patrón con `Throwable`, registrar detalles mediante `error_log()` y devolver mensajes seguros al usuario.
- Respetar `.htaccess` y su CSP al añadir recursos, rutas o servicios externos.

## Referencias actuales y documentación

- `galeria.html` utiliza galerías por evento a través de `gallery_items.php`, `assets/js/gallery.js` y `config/gallery.php`.
- La administración de galerías admite subidas y almacenamiento de imágenes en base de datos. Consultar el código y las migraciones antes de modificar ese flujo.
- La integración de Instagram está presente en `burnout.html`, con `instagram_gallery.js` y los endpoints correspondientes.
- No asumir que `galeria_bk.html` u otros archivos citados por documentación antigua siguen existiendo.
- Actualizar la documentación relacionada cuando un cambio funcional deje sus instrucciones obsoletas.

## Codificación y comprobaciones

- Leer y guardar texto como UTF-8; no copiar secuencias de texto corruptas de plantillas antiguas.
- Mantener las entidades HTML cuando sea la convención del bloque editado. Usar identificadores de código ASCII.
- Antes de ejecutar PHP, comprobar si está disponible localmente. Si no lo está, utilizar el PHP del servicio Docker `web` cuando esté disponible.
- Realizar las comprobaciones apropiadas al cambio. Para PHP, comprobar sintaxis de los archivos modificados cuando se disponga del intérprete.
- Para cambios de interfaz, verificar carga directa, navegación parcial y adaptación móvil cuando sea posible.
- Para cambios de datos, revisar coherencia entre esquema, migraciones y consultas; no ejecutar migraciones sobre entornos remotos sin autorización.
- No afirmar que se han ejecutado pruebas o verificaciones que no se hayan realizado. Indicar cualquier limitación relevante.
