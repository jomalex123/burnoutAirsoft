# Revisión de seguridad y regresiones — 03/11/2026

## Alcance y entorno

Revisión estática de los cambios de HQ y de los cambios relacionados en popups, CSS,
navegación y registro. Revisión de `hq_api.php`, `config/hq.php`, autenticación,
límites de solicitudes, plantillas HQ, esquema, migraciones y JavaScript del HQ/mapa.
Los cambios existentes del usuario se conservaron; no se hicieron commits ni despliegues.

Verificación en Apache/MySQL de XAMPP local con PHP CLI 8.0.30. Las pruebas de integración
usan datos ficticios que limpian al terminar. No se ejecutaron migraciones en esta revisión
ni se modificó PRE/producción. Los cambios de código de esta revisión no exigen migraciones nuevas.

## Hallazgos corregidos

| Área | Hallazgo | Corrección |
| --- | --- | --- |
| Entrada PHP | Algunas acciones convertían identificadores con `(int)`, pudiendo aceptar arrays como 1 o truncar valores | Validador común de enteros, tipo y rango; HTTP 422 para entradas malformadas |
| Límites de acceso | Lectura y escritura separadas podían perder incrementos concurrentes | Crear/bloquear la fila y actualizar dentro de una transacción; reserva del intento de login antes de verificar hashes |
| Sesiones | El inicio no configuraba expresamente el modo estricto | `session.use_strict_mode` y uso exclusivo de cookies, manteniendo HttpOnly, SameSite y Secure con HTTPS |
| Operaciones cerradas | Ordenar o retirar desde Gestionar Guion podía saltarse el cierre de comunicaciones | Relectura del estado bajo bloqueo de la operación antes de modificar |
| Concurrencia del guion | Las acciones no bloqueaban siempre la operación antes de sus misiones | Orden común de bloqueo y transacciones para inicios, cambios, recepción y mensajes |
| Catálogo | Nombre/guion y categoría se actualizaban en consultas sin una transacción común | Actualización atómica y bloqueo de la entrada antes de editar |
| Datos del enlace | La respuesta incluía metadatos de catálogo que no necesita el equipo | Campos de misión limitados en la vista de enlace |
| Mapa y navegación | La navegación parcial podía sustituir el lienzo con cambios pendientes | Esperar el guardado y bloquear la navegación si falla; aviso al recargar/cerrar |
| Guardado del mapa | Una ráfaga de dibujos generaba múltiples snapshots y podía dejar temporizadores pendientes | Agrupar cambios, una escritura simultánea, vaciar todos los cambios al salir y limpiar listeners/temporizadores |
| Respuestas asíncronas | Una respuesta antigua podía afectar la selección o cerrar un editor abierto después | Versiones de selección/editor y comprobación del contenedor antes de aplicar resultados |
| Refresco del guion | La lista se reconstruía cada cinco segundos aunque no hubiera cambios, pudiendo interrumpir una pulsación | No recrear filas sin cambios y desactivar acciones cuando la operación esté cerrada |
| Interfaz del enlace | La cabecera podía permanecer tras desconectar y las tarjetas seguían mostrando un código interno | Ocultar cabecera al salir y retirar el identificador visible |
| Cabeceras | PHP podía publicar `X-Powered-By` | Retirarlo mediante `.htaccess`; comprobado por HTTP |
| Documentación | Historial de instrucciones contradictorias con el código actual | Consolidar guías, separar referencia HQ e instalación, documentar tecnologías reales y límites |

## Controles revisados

- Las consultas variables de HQ usan parámetros PDO. Las partes dinámicas de SQL dependen
  de opciones internas, no de SQL enviado por el cliente. No se ha encontrado una inyección
  SQL reproducible en el flujo revisado; esto no certifica todo el repositorio.
- POST requiere CSRF, incluido login; la API rechaza métodos incorrectos. Una sesión de
  equipo no puede crear, editar, ordenar, borrar del catálogo ni escribir mapas administrativos.
- Los equipos no consultan misiones ni mensajes ajenos, ni borradores. Renovar código revoca
  sesiones anteriores. Un evento pasado o eliminado no se convierte en accesible enviando su ID.
- Los códigos se generan con `random_bytes`, se guardan con `password_hash` y no se devuelven
  en consultas posteriores. La búsqueda por código recorre accesos de eventos vigentes: su
  coste crece con el número de accesos, por lo que debe medirse si aumenta el volumen.
- El borrado del catálogo es una desactivación real, con fila conservada. Las misiones
  asignadas mantienen título, guion y vínculo aunque se retire la entrada.
- Las anotaciones requieren data URL PNG, tamaño y dimensiones limitados. Se guardan en
  base de datos y se usan como imagen, no como código ni archivo ejecutable. La validación
  de dimensiones/formato no sustituye un decodificador completo ni un análisis antivirus.
- Los nuevos textos dinámicos se renderizan con `textContent`; PHP escapa los valores HTML.
  Se han probado títulos con marcado como texto, sin construir HTML con el contenido.
- Apache bloquea `.git`, `config`, `database`, `scripts` y Markdown. Se comprobó no-store,
  CSP, nosniff, protección de marcos y cookie HttpOnly/SameSite en el acceso de enlace.

## Comprobaciones ejecutadas

| Comprobación | Resultado |
| --- | --- |
| `scripts/check_hq.php` | 104 comprobaciones de integración local: permisos, CSRF, entradas, sesiones, catálogo, mapas, estados, orden, HTML y conservación de datos |
| `scripts/check_http_security.php` | 17 comprobaciones HTTP: cabeceras, rutas privadas, métodos y acceso sin sesión |
| `scripts/check_error_messages.php` | 13 comprobaciones de mensajes seguros |
| `node scripts/check_hq_catalog_tables.js` | Filtros, acentos, orden, tamaños, selección y acciones por vista |
| `node scripts/check_hq_map_state.js` | Guardado agrupado/serial, cambios pendientes, fallos, reintentos, carga obsoleta y limpieza |
| `node scripts/check_rate_limit_concurrency.js` | 80 incrementos conservados con 8 procesos PHP simultáneos; fila ficticia eliminada |
| `node scripts/check_registration_pagination.js` | Regresión de filtros, orden, totales, tamaños y CSV de registros |
| Sintaxis PHP y JavaScript | 16 archivos PHP y 6 JavaScript modificados/nuevos comprobados con `php -l` y `node --check` |
| `git diff --check` | Sin errores de espacios en los cambios; Git advierte de conversión LF/CRLF en Windows |

Las pruebas Node del mapa y de tablas usan DOM/red simulados; no son pruebas de navegador.
Los scripts PHP verifican HTML servido y peticiones reales a Apache, no el diseño visual.

## Pendientes y límites

1. **Actualizar PHP del entorno.** Se detectó PHP 8.0.30, una rama sin soporte. Consultar
   la [lista oficial de versiones soportadas](https://www.php.net/supported-versions.php)
   y planificar una actualización de XAMPP o del runtime con copia y prueba de compatibilidad.
   Esta revisión no instala ni actualiza software fuera del repositorio.
2. No se han revisado la configuración efectiva de Hostalia, HTTPS, copias de seguridad ni
   las versiones reales del servidor remoto. Las migraciones HQ pendientes allí se aplican
   por un administrador, con copia previa y sin reimportar el esquema completo.
3. No se ha realizado pentest externo, escaneo de dependencias vendorizadas, prueba de carga
   ni prueba visual completa en navegadores/móvil. La comprobación concurrente se limita
   al contador; el bloqueo de misiones se ha verificado funcionalmente en integración.
4. El mapa conserva la política de último guardado si dos administradores editan la misma
   partida. No hay control de versiones/fusión entre navegadores.
5. La CSP existente permite estilos y scripts inline por compatibilidad con la aplicación.
   Reducir esos permisos exige revisar las páginas y recursos heredados; no se ha cambiado
   esa política de forma aislada ni se presenta como protección completa contra XSS.

La revisión confirma estos controles y regresiones en el entorno indicado; no constituye
una garantía de ausencia de vulnerabilidades en toda la aplicación.

## Despliegue de base de datos posterior a la revisión

Tras autorización expresa, el mismo 03/11/2026 se aplicaron en PRE las siete migraciones HQ
documentadas en `database/README.md`. Antes se guardó una copia SQL gzip de las 12 tablas
existentes. Se comprobó la integridad del archivo comprimido y que los datos y estructura
previos se conservaran. Se crearon las seis tablas HQ y se verificaron sus columnas, seis
claves foráneas, unicidad de evento, catálogo de 45 entradas y hashes de contenido.

Las pruebas directas de inserción/estados/mapa/chat se hicieron en una transacción con
rollback; no quedaron el evento ni la operación ficticios. Los contadores AUTO_INCREMENT
pueden avanzar como consecuencia de esas pruebas. Una segunda inspección detectó las siete
migraciones como instaladas, sin repetirlas. Producción sigue sin modificarse. Estas
comprobaciones de base de datos no equivalen a probar el sitio PRE en navegador.
