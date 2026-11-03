# Base de datos y entornos

El entorno de este equipo es XAMPP: Apache, PHP en `D:\xampp\php\php.exe` y MySQL
compatible en `127.0.0.1:3306`. No arrancar Docker para trabajar aquí. Docker es una
alternativa descrita en [README.md](../README.md).

## Configuración

Usar [config/env.example.php](../config/env.example.php) como plantilla. No publicar credenciales.
`BURNOUT_ENV` selecciona `local`, `pre` o `pro`; si falta, se usa `default`.
`BURNOUT_ENV_FILE` permite indicar un archivo explícito.

`config/env_loader.php` carga el primer archivo existente:

1. La ruta de `BURNOUT_ENV_FILE`.
2. Las rutas privadas fuera del directorio público, calculadas según la ubicación del proyecto.
3. `config/env.php`.
4. `config/env.example.php`.

En PRE/producción conservar la configuración real fuera del directorio público. En este
equipo usar `config/env.php` con entorno `local`. No imprimir la configuración completa.

```powershell
& 'D:\xampp\php\php.exe' scripts/check_database.php
```

## Instalaciones nuevas y existentes

Para una instalación nueva, crear una base vacía e importar `database/schema.sql` mediante
phpMyAdmin o el cliente MySQL de XAMPP. Crear un administrador con una contraseña propia:

```powershell
& 'D:\xampp\php\php.exe' scripts/create_admin_user.php admin "CAMBIA_ESTA_CLAVE" "Burnout Admin"
```

En una base existente no reimportar el esquema completo: comprobar columnas, tablas y migraciones
y aplicar solo las pendientes, con copia previa. No ejecutar migraciones remotas sin autorización.
Los scripts SQL de ALTER se aplican una sola vez; no todos son idempotentes. Las peticiones web
no ejecutan DDL. El esquema completo y las migraciones deben producir estructuras compatibles.

## Tablas HQ

| Tabla | Contenido y conservación |
| --- | --- |
| `hq_operations` | Una operación por evento; `event_id` es único y puede quedar NULL si se elimina el evento |
| `hq_access` | Hash del código por equipo; renovarlo revoca sus sesiones |
| `hq_missions` | Copia del nombre y guion asignados, equipo, estado, recepción, origen y orden |
| `hq_messages` | Historial de chat por operación/equipo; remitente determinado por servidor |
| `hq_mission_catalog` | Catálogo global, categoría, hash de contenido y disponibilidad |
| `hq_maps` | Anotaciones PNG transparentes en MEDIUMBLOB, una fila por operación |

Eliminar un evento conserva la operación y sus datos HQ con `event_id = NULL`. Esas operaciones
no se reasignan ni se ofrecen en el selector. Eliminar físicamente una operación sí tiene
borrado en cascada, por lo que la aplicación no ofrece esa acción.

Las misiones nuevas se guardan como `draft` (Pendiente). Otros estados: `active`,
`completed`, `failed` y `deleted`. Eliminar del guion conserva la fila; Reiniciar vuelve a
`draft` y limpia `acknowledged_at`. Solo una misión por equipo/operación puede iniciarse
mediante la API; la regla se serializa bloqueando primero la operación, no mediante un ENUM.

Borrar del catálogo significa `is_available = 0`, nunca DELETE. El vínculo de las misiones
asignadas y sus textos se conservan. Los duplicados exactos usan SHA-256 de título, salto de
línea y guion; las variantes se mantienen. Crear un guion manual con contenido idéntico puede
reutilizar y reactivar una entrada existente. Editar nombre, guion y categoría es transaccional.
`mission_type` es histórico: la interfaz clasifica solo por categoría y no lo modifica.

Los mapas admiten data URLs PNG limitadas a 4.000.000 bytes y dimensiones máximas de
2000 × 2000. El guardado no mezcla cambios de varios administradores: prevalece el último.
Incluir las seis tablas HQ y las imágenes de galerías/Instagram en las copias de seguridad.

## Migraciones HQ para instalaciones existentes

Aplicar en este orden, omitiendo las que ya estén instaladas:

| Orden | Archivo | Cambio |
| --- | --- | --- |
| 1 | `2026_10_30_add_hq.sql` | Operaciones, accesos, misiones y mensajes |
| 2 | `2026_10_30_link_hq_events.sql` | Relación única con eventos; conserva operaciones antiguas sin evento |
| 3 | `2026_11_02_add_hq_mission_catalog.sql` | Catálogo inicial de 45 misiones, origen y vínculo en las asignadas |
| 4 | `2026_11_03_hq_deleted_missions.sql` | Estado `deleted` en misiones asignadas |
| 5 | `2026_11_03_add_hq_maps.sql` | Persistencia del mapa por operación |
| 6 | `2026_11_03_hq_mission_order.sql` | `sort_order`, conservando inicialmente el orden anterior |
| 7 | `2026_11_03_hq_mission_classification.sql` | Categoría y campo histórico `mission_type` |

El esquema nuevo ya incluye todos estos cambios. Las migraciones HQ se han aplicado en
XAMPP local; no se han aplicado desde esta sesión a PRE ni producción. En Hostalia hacer
copia previa, importar los SQL pendientes desde phpMyAdmin y subir también los PHP/JS/CSS.

## Comprobaciones y mantenimiento

```powershell
& 'D:\xampp\php\php.exe' scripts/check_hq.php
& 'D:\xampp\php\php.exe' scripts/check_http_security.php
node scripts/check_hq_catalog_tables.js
node scripts/check_hq_map_state.js
node scripts/check_rate_limit_concurrency.js
```

`check_hq.php` solo admite configuración local, utiliza Apache y las sesiones de XAMPP,
crea datos ficticios y los elimina. `--install-local` aplica antes las migraciones que falten:
revisar el estado y hacer copia completa antes de usarlo. El instalador guarda además una
copia SQL de `hq_missions` antes de instalar catálogo o estado eliminado; esa copia no
sustituye el backup completo de la base.

`scripts/reset_hq_pending.php` es una utilidad CLI exclusivamente local: guarda una copia
JSON de las misiones activas en el directorio temporal y las devuelve a Pendiente. No
invocarla como comprobación ni ejecutarla durante una partida en marcha.

Los límites de solicitudes de `config/rate_limit.php` actualizan contadores bajo bloqueo
transaccional. Las sesiones administrativas y de enlace no exigen tablas ni migraciones nuevas
por la revisión de seguridad. Detalle funcional: [docs/hq.md](../docs/hq.md).
