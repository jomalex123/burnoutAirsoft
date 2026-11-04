# HQ: funcionamiento y estándares actuales

Referencia funcional y técnica de `admin_hq.php`, `hq_equipo.php` y `hq_api.php`.
Instalación y orden de migraciones: [database/README.md](../database/README.md).

## Tecnologías

El HQ utiliza PHP procedural, PDO/MySQL y las sesiones existentes. No añade frameworks,
paquetes npm, Composer, WebSockets, servicios externos ni procesos permanentes.

| Función | Implementación |
| --- | --- |
| Dibujo | Canvas 2D, Pointer Events y captura del puntero |
| Plano | PNG local generado previamente desde el PDF; no se procesa el PDF en cada petición |
| Navegación del mapa | CSS, desplazamiento nativo, zoom del 100 al 300 % y Fullscreen API |
| Anotaciones | Memoria local por partida |
| Peticiones | Fetch, URLSearchParams y JSON, con cookies del mismo origen |
| Chat y estados | Consulta incremental cada cinco segundos; se pausa con la pestaña oculta |
| Tablas del catálogo | JavaScript nativo, filtros y paginación en cliente |
| Popups | Componente CSS `.burnout-popup`, con foco, Escape y confirmación explícita |
| Estilos | CSS Grid/Flexbox, propiedades personalizadas y selectores `:is()`, `:where()` y `:has()`; navegador moderno |
| Pruebas | PHP CLI/cURL/DOM para integración; Node.js integrado (`assert`, `vm`) para regresiones |

PHP 8.0 es el mínimo sintáctico actual, no una versión recomendada para despliegue.
Usar una rama de PHP con soporte de seguridad. El diagnóstico local del 03/11/2026
detectó PHP 8.0.30 en XAMPP; su actualización es un pendiente del entorno.
PDO y `pdo_mysql` son necesarios en ejecución. cURL y DOM son necesarios para las
comprobaciones de integración. Node.js se usa solo para pruebas, no para servir el HQ.

## Partida y navegación

Mapa, Misiones, Canales y Ajustes comparten el evento seleccionado. Se ofrecen eventos
de hoy o futuros según `Europe/Madrid`, ordenados por fecha y turno. Cada evento tiene
una operación HQ única; su nombre y fecha se editan desde Gestionar Partidas.
No hay un campo independiente de activación del evento.

Las cabeceras muestran únicamente «Centro de Operaciones / …»: Mapa de Operaciones,
Briefing de operaciones, Comunicaciones y Control de operación. Los títulos grandes
duplicados de las pestañas se han retirado.

## Mapa

- Fondo: `assets/maps/carto-orto-detalle.png`, procedente de `assets/maps/Carto-Orto_Detalle.pdf`.
- La capa transparente admite lápiz, línea, flecha, rectángulo, círculo, texto y borrador.
- Deshacer y rehacer guardan hasta 15 cambios durante la sesión del mapa seleccionado.
- Limpiar borra anotaciones, nunca el plano. La mano permite desplazar el mapa ampliado.
- Las anotaciones se conservan en memoria por partida mientras el HQ permanece abierto.
  Recargar o abandonar la página descarta los dibujos.
- No hay carga ni guardado en servidor, ni mensajes de guardado bajo el mapa.
- Pantalla completa incluye lienzo, botonera y popup de texto, sin cabecera ni selector.
- La tabla hq_maps y sus endpoints se conservan por compatibilidad; el editor no los usa.

## Misiones y guion

Los botones Equipo Alpha / Equipo Bravo filtran las tarjetas. El título abre y pliega
el guion sin símbolos +/−. Al pasar a completada, fallida o eliminada, la tarjeta se
pliega. El fondo normal es negro con separadores grises; completada usa verde, fallida
rojo y eliminada gris con título y guion tachados.

| Estado visible | Valor interno | Acciones de administración |
| --- | --- | --- |
| Pendiente | `draft` | Iniciar |
| En curso | `active` | Completada, Fallida, Eliminar, Reiniciar |
| Completada | `completed` | Reiniciar |
| Fallida | `failed` | Reiniciar |
| Eliminada | `deleted` | Reiniciar |

Solo una misión por equipo y operación puede estar en curso. Iniciar, finalizar y
reiniciar se validan en servidor bajo bloqueo transaccional de la operación. Reiniciar
devuelve a Pendiente y elimina la confirmación de recepción. Eliminar conserva la fila.

Ajustes contiene tres paneles fijos, sin desplegables:

1. **GESTIONAR GUION:** dos columnas iguales por encima de 640 px; en móvil se apilan.
   A la izquierda se elige equipo y se introducen nombre y guion. Añadir misión está
   a la derecha del formulario y siempre guarda como Pendiente. A la derecha están
   Importar misión y la lista compacta de títulos asignados, con subir, bajar y papelera.
   Las nuevas misiones van al final. El orden se guarda por operación y se respeta al
   filtrar por equipo. La papelera retira del guion y conserva el estado Eliminada.
2. **ENLACES DE EQUIPO:** genera códigos independientes de Alpha y Bravo. Solo se
   muestra cada código al generarlo; renovarlo revoca las sesiones anteriores del equipo.
3. **GESTIONAR MISIONES:** catálogo global, independiente de la partida seleccionada.

## Catálogo e importación

Las 45 misiones iniciales de `misiones.md` conservan sus variantes. Los guiones manuales
también se incorporan al catálogo. Se evitan duplicados exactos por SHA-256 de nombre,
salto de línea y guion. Los textos asignados a partidas son copias independientes.

La clasificación actual usa únicamente Categoría. Nueva misión, alineado a la derecha,
abre un popup con Nombre, Categoría (desplegable de valores existentes o Sin clasificar)
y Guion. No hay formulario debajo de la tabla. Los lápices abren ese mismo popup con
los datos actuales. La papelera abre una confirmación y desactiva `is_available`; no
borra la entrada ni modifica las copias ya asignadas. Pulsar la fila solo la selecciona.

Filtros y editor cargan la lista independiente `hq_mission_categories`, también para
categorías sin misiones. El servidor valida la categoría contra esa lista. Se mantiene
directamente en MySQL, sin pantalla de gestión; instrucciones en `database/README.md`.
Las 45 misiones de `misiones.md` y `misiones_por_categorias.md` coinciden exactamente
en nombre y guion; el segundo documento asigna las 17 categorías iniciales.

| Vista | Filtros | Paginación y acciones |
| --- | --- | --- |
| Importar misión | Buscar por nombre/guion y Categoría, aplicados directamente | Siempre 5 filas; seleccionar fila, consultar vista previa y confirmar IMPORTAR |
| Gestionar Misiones | Botón Filtrar a la derecha del título; popup con Buscar y Categoría | 5/10/15, por defecto 5; lápiz y papelera por fila |

El popup de filtros dispone de Limpiar filtros a la izquierda y Aplicar filtros a la
derecha. Cerrar sin aplicar conserva el filtro anterior. Ambas tablas tienen ordenación
por columnas y botones numerados como Registros; filtros y selección son independientes.
La búsqueda no distingue mayúsculas ni acentos. Las filas admiten Enter y Espacio.

`mission_type` es un campo histórico de la migración de clasificación. La interfaz no
lo usa y guardar la categoría no lo modifica. No se elimina mediante esta revisión.
`mission_update` se conserva en la API para compatibilidad, aunque las tarjetas ya no
ofrecen editar o sustituir guiones; las importadas siguen tomando el texto del servidor.

## Enlace de equipo

Compartir `hq_equipo.php` y el código del equipo. No hace falta introducir identificador
de operación ni elegir equipo. El servidor resuelve la partida vigente y el equipo.
Una sesión solo accede a su equipo; se recupera al recargar y se revoca al renovar el código.
La cabecera «Burnout Airsoft / Enlace HQ» aparece tras validar la sesión y se oculta al salir.
El enlace recibe misiones transmitidas de su equipo, confirma recepción y participa en
su chat. No recibe el catálogo ni sus metadatos administrativos.

## Seguridad y mantenimiento

- Consultas preparadas para valores, identificadores enteros validados y opciones en listas cerradas.
- CSRF en todos los POST, incluido login. Los códigos usan `password_hash()` y `password_verify()`.
- Ocho intentos de acceso por IP antes del bloqueo; el siguiente se bloquea durante quince minutos.
  El contador se reserva antes de verificar hashes y se actualiza transaccionalmente.
- Sesión con modo estricto, cookie HttpOnly y SameSite=Lax; Secure cuando se usa HTTPS.
- El servidor limita cada acción a su rol, equipo, operación y estado actual. Cerrar una
  operación bloquea mensajes, recepción y cambios del guion. Mapas y catálogo son administración.
- Los textos dinámicos se pintan con `textContent`; PHP escapa el HTML. No construir HTML
  con títulos, categorías, mensajes o guiones recibidos.
- No se ejecutan migraciones en peticiones web. `config/`, `database/`, `scripts/` y los Markdown
  están bloqueados por `.htaccess`. La API usa JSON y no-store; los errores técnicos van al log.
- Limpiar temporizadores y listeners al abandonar la página; inicializar mapa antes del HQ.
- Usar `--admin-button-height: 44px` para acciones de texto en mayúsculas. Los iconos compactos,
  cierres y paginación conservan su tamaño. El menú lateral usa 64 px en móvil.

Detalle de la revisión y sus límites: [revisión del 03/11/2026](security-review-2026-11-03.md).
