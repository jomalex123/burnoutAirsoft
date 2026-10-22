# Burnout Airsoft

Aplicación web de Burnout Airsoft para presentar el equipo y el campo, consultar partidas, gestionar inscripciones y mostrar galerías de eventos. Incluye un panel privado de administración y una integración con Instagram.

El entorno local habitual del proyecto utiliza **XAMPP**: Apache y MySQL locales, configuración en `config/env.php` y acceso mediante `http://localhost/burnoutAirsoft/`. Las instrucciones de Docker se conservan como alternativa.

## Funcionalidades

- Páginas públicas de inicio, equipo, campo, normativa y privacidad.
- Calendario de partidas con turnos de mañana, tarde y noche, aforo y asistentes inscritos.
- Inscripción a partidas con varios asistentes, validación y notificaciones por correo.
- Galerías por evento con imágenes administrables.
- Publicaciones de Instagram en la página «Nosotros», con carga adicional y visualización en modal.
- Administración de partidas, registros, galerías y contenido de la normativa.
- Consulta, filtrado, ordenación, eliminación y exportación CSV/PDF de inscripciones, además de reenvío de notificaciones.

## Tecnologías

| Área | Implementación |
| --- | --- |
| Backend | PHP; imagen Docker con PHP 8.3 y Apache |
| Base de datos | MySQL 8.4 en Docker y acceso mediante PDO |
| Frontend | HTML, CSS propio y JavaScript nativo con jQuery 3.7.1 |
| Interacciones | Anime.js, Swiper y librerías locales de galería |
| Exportación PDF | PDFLib en el navegador |
| Desarrollo local | Docker Compose o Apache/PHP/MySQL mediante XAMPP |

El proyecto no utiliza framework de aplicación, Composer, npm ni un proceso de compilación del frontend. Las librerías se incluyen directamente desde `assets/`.

## Estructura

```text
burnoutAirsoft/
├── AGENTS.md                   # Instrucciones para agentes de desarrollo
├── context.md                  # Contexto y convenciones del proyecto
├── index.html                  # Inicio
├── burnout.html                # Equipo e integración de Instagram
├── campo.html                  # Información del campo
├── partidas.html               # Calendario público
├── registro.html               # Interfaz de inscripción
├── registro.php                # Flujo dinámico y procesamiento de inscripciones
├── galeria.html                # Galerías por evento
├── normativa.html              # Normativa pública y contenido editable
├── privacidad.html             # Política de privacidad
├── admin*.php                  # Páginas de administración
├── events.php                  # Datos públicos de partidas
├── gallery_items.php           # Datos públicos de galerías
├── gallery_event_image.php     # Imágenes de galerías de eventos
├── instagram_feed.php          # Feed de Instagram
├── instagram_image.php         # Imágenes de Instagram almacenadas
├── assets/
│   ├── css/                    # Estilos globales, por página y librerías
│   └── js/                     # Scripts propios y librerías
├── config/                     # Entornos, PDO, autenticación y funciones compartidas
├── database/
│   ├── schema.sql              # Esquema para instalaciones nuevas
│   ├── migrations/             # Cambios para bases existentes
│   └── README.md               # Documentación de base de datos y entornos
├── images/resources/           # Logos e imágenes del sitio
├── scripts/                    # Utilidades de administración y sincronización
├── docker/                     # Configuración del entorno Docker
├── Dockerfile
├── docker-compose.yml
└── .htaccess                   # Protección de archivos y cabeceras HTTP
```

## Puesta en marcha con Docker

Requisitos: Docker y Docker Compose. Ejecutar los comandos desde la raíz del repositorio.

1. Revisar `docker-compose.yml` y la configuración local de `docker/env.php`. La conexión de PHP dentro de Docker debe utilizar `db` como host y coincidir con la base y el usuario definidos en el servicio MySQL.
2. Comprobar que los puertos locales `8080` y `3306` están disponibles. Si MySQL de XAMPP ya ocupa `3306`, detener ese servicio o ajustar el puerto publicado de Docker.
3. Construir y arrancar los servicios:

   ```sh
   docker compose up -d --build
   ```

4. Abrir [http://localhost:8080](http://localhost:8080).
5. Crear un administrador con una contraseña propia:

   ```sh
   docker compose exec web php scripts/create_admin_user.php admin "CAMBIA_ESTA_CLAVE" "Burnout Admin"
   ```

6. Acceder a [http://localhost:8080/admin.php](http://localhost:8080/admin.php).

El esquema se importa automáticamente en la primera inicialización del volumen MySQL. Los datos persisten en el volumen `burnoutairsoft_mysql_db`; reiniciar los contenedores no vuelve a importar el esquema.

Comandos de uso habitual:

```sh
docker compose ps
docker compose logs --tail=100 web
docker compose logs --tail=100 db
docker compose down
```

`docker compose down` detiene los servicios y conserva el volumen de datos.

## Puesta en marcha con XAMPP

1. Situar el proyecto dentro de `htdocs`, por ejemplo en `D:\xampp\htdocs\burnoutAirsoft`.
2. Activar Apache y MySQL y comprobar que PHP tiene PDO y `pdo_mysql` disponibles.
3. Crear una base de datos e importar `database/schema.sql` mediante phpMyAdmin o el cliente MySQL.
4. Copiar `config/env.example.php` a `config/env.php` y ajustar la conexión `local` con los datos de esa base. Mantener `default` en `local` o seleccionar el entorno mediante `BURNOUT_ENV`.
5. Comprobar que no haya una configuración privada de mayor prioridad que sustituya el archivo local; el orden de carga se describe abajo.
6. Crear el administrador desde la raíz:

   ```powershell
   php scripts/create_admin_user.php admin "CAMBIA_ESTA_CLAVE" "Burnout Admin"
   ```

   Si PHP no está en el `PATH`, utilizar la ruta del ejecutable de XAMPP:

   ```powershell
   & 'D:\xampp\php\php.exe' scripts/create_admin_user.php admin "CAMBIA_ESTA_CLAVE" "Burnout Admin"
   ```

7. Abrir [http://localhost/burnoutAirsoft/](http://localhost/burnoutAirsoft/) y entrar al panel mediante `admin.php`.

Apache debe permitir las directivas de `.htaccess` y disponer de los módulos correspondientes para aplicar las cabeceras de seguridad.

## Configuración y entornos

La configuración es un archivo PHP que devuelve un array. Usar `config/env.example.php` como plantilla, sin publicar credenciales ni tokens reales.

- `BURNOUT_ENV`: selecciona `local`, `pre` o `pro`. Si no está definido, se utiliza `default` del archivo de configuración.
- `BURNOUT_ENV_FILE`: permite indicar una ruta explícita al archivo de configuración.
- `connections`: parámetros de MySQL por entorno.
- `mail`: configuración de correo por entorno.
- `instagram`: configuración de la integración y su sincronización.

`config/env_loader.php` utiliza el primer archivo existente de esta búsqueda:

1. Ruta indicada en `BURNOUT_ENV_FILE`.
2. Rutas privadas calculadas según el directorio del proyecto: para `httpdocs`, `../private/env.php`; para `burnoutairsoft.com`, `../private/burnoutairsoft/env.php`; en otros directorios se prueban ambas rutas privadas, empezando por la de `burnoutairsoft`.
3. `config/env.php`.
4. `config/env.example.php`.

En PRE y producción, guardar la configuración real fuera del directorio público. Docker selecciona su archivo local mediante `BURNOUT_ENV_FILE` en Compose.

### Correo

Revisar `config/mail.php` y la sección `mail` de la plantilla para configurar el envío y, cuando corresponda, el servidor SMTP. La plantilla desactiva el correo en local. Las notificaciones de inscripción tienen auditoría y posibilidad de reenvío desde administración.

### Instagram

La integración utiliza los archivos `instagram_feed.php`, `instagram_image.php`, `assets/js/instagram_gallery.js` y el script de sincronización. La base de datos contiene tablas de caché para publicaciones, imágenes y estado de sincronización.

Configurar la sección `instagram` con los parámetros de la cuenta y la API necesarios. Con la integración configurada, ejecutar:

```sh
docker compose exec web php scripts/sync_instagram_gallery.php
```

En un entorno con PHP local:

```sh
php scripts/sync_instagram_gallery.php
```

El script puede ejecutarse desde una tarea programada del servidor. Esa tarea debe utilizar una versión de PHP con PDO y `pdo_mysql`, y acceder al archivo de configuración del entorno correcto. Mantener la verificación SSL activa y configurar `ca_file` si el entorno necesita un certificado CA explícito.

## Base de datos y migraciones

Las tablas incluyen usuarios y auditoría administrativa, límites de solicitudes, partidas, inscripciones, asistentes, notificaciones de correo, galerías por evento y caché de Instagram.

Para una instalación nueva, importar `database/schema.sql`. Para una instalación existente, revisar las migraciones de `database/migrations/` y aplicar únicamente las necesarias según su estado actual.

Para importar SQL con Docker desde PowerShell, abrir primero el cliente MySQL; solicitará la contraseña del servicio:

```sh
docker compose exec db mysql -u root -p
```

Dentro del cliente, seleccionar la base configurada en Compose y utilizar la ruta montada del esquema:

```sql
USE `NOMBRE_DE_LA_BASE`;
SOURCE /docker-entrypoint-initdb.d/01-schema.sql;
```

Sustituir `NOMBRE_DE_LA_BASE` por el nombre real antes de ejecutar. Para importar una migración concreta con este método, copiarla primero al contenedor y cargarla desde el cliente:

```sh
docker compose cp database/migrations/ARCHIVO.sql db:/tmp/burnout-migration.sql
```

```sql
SOURCE /tmp/burnout-migration.sql;
```

Consultar [database/README.md](database/README.md) para más detalles. Hacer copia de seguridad antes de modificar bases con datos existentes.

## Convenciones de desarrollo

Leer [AGENTS.md](AGENTS.md) y [context.md](context.md) antes de realizar cambios. Contrastar la documentación con los archivos actuales.

- **Estilos:** CSS propio, global en `assets/css/style.css` y específico por página. Utilizar prefijos de clases por funcionalidad, Grid/Flexbox y media queries acordes al archivo afectado.
- **Identidad:** Yantramanav; rojo `#df1f29`, negro `#151515`, blanco y fondo claro `#f4f4f4`.
- **HTML:** mantener header y footer existentes, un único `main.ms-container` y bloques `.ms-section__block`.
- **Navegación:** los enlaces con `data-type="page-transition"` pueden reemplazar el contenido principal sin recargar toda la página.
- **JavaScript:** exponer `window.initNombrePage`, integrarlo en `InitPage()` de `main.js` y evitar inicializaciones duplicadas. Comprobar carga directa y navegación parcial.
- **PHP:** tipado estricto en archivos nuevos, funciones compartidas con prefijo `burnout_`, acceso mediante PDO, consultas preparadas y escape de valores al generar HTML.
- **Formularios:** validar en servidor y cliente; mantener autenticación y CSRF en acciones administrativas.
- **Normativa:** conservar los comentarios que delimitan los bloques editables de `normativa.html`.
- **Texto:** guardar en UTF-8 y evitar copiar texto con codificación corrupta.

La galería pública actual es por eventos. Instagram se integra en `burnout.html`; algunas referencias históricas de `context.md` describen una organización anterior.

## Comprobaciones

El repositorio incluye una comprobación específica de mensajes de error, pero no una suite general de pruebas automatizadas. Realizar las verificaciones apropiadas al cambio y registrar cuáles se han ejecutado.

Comprobar mensajes seguros y respuestas de excepciones sin conectar con MySQL:

```sh
docker compose exec web php scripts/check_error_messages.php
```

Los fallos técnicos se registran en el servidor y se muestran mediante mensajes breves. Los errores de validación usan `BurnoutUserException`; no utilizar esa clase para detalles SQL, SMTP u otros diagnósticos internos.

Diagnóstico del intérprete y extensiones:

```sh
docker compose exec web php scripts/check_php_extensions.php
```

Comprobación de sintaxis de un archivo PHP modificado:

```sh
docker compose exec web php -l registro.php
```

Con PHP local, los equivalentes son `php scripts/check_php_extensions.php` y `php -l registro.php`. Antes de usarlos, comprobar si PHP está disponible con `Get-Command php` en PowerShell.

Para cambios de interfaz, verificar navegación directa y parcial, consola del navegador, versión móvil, modales y formularios afectados. Para cambios de backend, comprobar respuestas HTTP, validación, permisos y persistencia de los datos implicados.

## Seguridad y despliegue

`.htaccess` desactiva el listado de directorios, restringe archivos y directorios privados y configura cabeceras de seguridad, incluida una política CSP. Revisar esa política si se añaden recursos externos.

Para desplegar, configurar el entorno y la base de datos del servidor, aplicar los cambios de esquema necesarios y comprobar autenticación, correo y tareas programadas. Las imágenes de galerías e Instagram se almacenan en base de datos, por lo que deben incluirse en las copias de seguridad de MySQL.
