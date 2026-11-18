# HQ: funcionamiento y estándares actuales

## Mapas del catálogo de misiones

El icono de mapa de «Editar misión» abre el componente compartido con todas sus
herramientas. «Guardar mapa» guarda únicamente las anotaciones PNG de esa misión y
vuelve al editor, conservando los campos del formulario. «Cerrar» vuelve sin guardar
los cambios del dibujo. En una misión nueva, el mapa queda como borrador hasta pulsar
«Guardar misión»; la creación de ambos datos es transaccional.

La tabla de «Importar misión» incluye una columna «Mapa». Su icono abre el dibujo
guardado en modo lectura, con zoom y pantalla completa. Una misión sin anotaciones
muestra el plano vacío. El cierre devuelve a la tabla, conservando filtros y selección.

Los PNG se guardan en `hq_mission_maps`, asociados por `catalog_id`. Los endpoints
`catalog_map` (GET) y `catalog_map_save` (POST con CSRF) requieren administrador.
El tamaño del canvas es 1600 × 1131 y el límite del dato recibido es 4 MB.
La consulta del catálogo no incluye las imágenes; se recuperan al abrir el popup.
Estos mapas no cambian el mapa temporal de la partida. El enlace de equipo muestra
en lectura el mapa guardado de su misión en curso. Al cambiar la misión o actualizar
su dibujo, el sondeo detecta la nueva versión y descarga el PNG mediante `team_map`.
Sin misión activa o sin dibujo, se muestra solo el plano. El servidor determina el
equipo desde la sesión y no admite elegir otra misión o equipo mediante parámetros.
El catálogo y los mapas de otras misiones siguen reservados a administración.

Migración: `database/migrations/2026_11_18_hq_mission_maps.sql`, aplicada en local
el 18/11/2026 con copia previa. PRE y PRO pendientes de autorización.
Comprobaciones: `node scripts/check_hq_mission_maps.js` y `scripts/check_hq.php`.

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

Mapa, Misiones, Canales, Jugadores y Ajustes comparten el evento seleccionado. Se ofrecen eventos
de hoy o futuros según `Europe/Madrid`, ordenados por fecha y turno. Cada evento tiene
una operación HQ única; su nombre y fecha se editan desde Gestionar Partidas.
No hay un campo independiente de activación del evento.

Las cabeceras muestran únicamente «Centro de Operaciones / …»: Mapa de Operaciones,
Briefing de operaciones, Canales y Control de operación. Los títulos grandes
duplicados de las pestañas se han retirado.

## Mapa

### Componente compartido

Todos los mapas actuales usan `config/map_component.php`, `assets/js/map.js` y
`assets/css/map.css`. El renderer `burnout_render_map($id, $editable = true)` recibe
un identificador único por instancia. Administración lo usa en edición y Enlace HQ
lo usa con `false`: solo genera el plano, la capa vacía, zoom y pantalla completa,
sin canvas de dibujo, controles de edición ni sus ventanas.

`initBurnoutMapsPage` inicializa todas las instancias antes del adaptador
`initAdminMapaInteractivoPage` y de `initHqPage`. La navegación parcial carga
`map.js` antes de los scripts de página. Cada elemento conserva su controlador
`burnoutMap`, con `load`, `flush`, `closePopups` y `destroy`. La inicialización es
idempotente; al retirar la página se eliminan listeners y anotaciones temporales.
Zoom, pantalla completa, capas y errores usan el mismo código en ambos modos.
Los modos editor y lectura comparten la barra de controles y el diseño responsive.
El zoom se mantiene flotante en la esquina inferior derecha del mapa, como en el
editor original; pantalla completa permanece en la barra superior.

Para reutilizarlo, incluir el renderer con `require_once`, cargar `map.css` después
de los estilos base y `map.js` antes del script de página, y llamar a
`burnout_render_map('identificador-unico', true)` (editor) o con `false` (lector).
Los IDs de popups y referencias accesibles se generan a partir de ese identificador.
`admin_mapainteractivo.js` queda únicamente como adaptador del menú del HQ y expone
el controlador al selector de partida mediante `root.hqMap`.

Pruebas de estructura y múltiples instancias: `php scripts/check_map_component.php`
y `node scripts/check_hq_team_map.js`.

### Edición y conservación

- Fondo: `assets/maps/carto-orto-detalle.png`, procedente de `assets/maps/Carto-Orto_Detalle.pdf`.
- La capa transparente admite lápiz, línea, flecha, rectángulo, círculo, texto y borrador.
- Tras el grosor y antes de Deshacer, el selector de relleno ofrece iconos para
  Sin relleno (predeterminado), Translúcido y rayado a 45° y Sólido. Usa el color
  seleccionado y afecta solo a rectángulos y círculos nuevos. Conserva el contorno.
  El desplegable admite teclado, Escape y cierre al pulsar fuera, y está dentro del
  editor para funcionar también en pantalla completa, fuera del scroll de la barra.
  Los rellenos forman parte de la misma imagen temporal y del historial existente.
- Deshacer y rehacer guardan hasta 15 cambios durante la sesión del mapa seleccionado.
- Limpiar borra anotaciones, nunca el plano. La mano permite desplazar el mapa ampliado.
- Limpiar abre el popup compartido con Aceptar y Cancelar, sin confirmación nativa
  del navegador. Solo Aceptar borra, conservando Deshacer/Rehacer. El foco inicial
  va a Cancelar; admite Tab, Escape y retorno al botón de limpiar. El popup está
  dentro del editor para funcionar en pantalla completa y se cierra al cambiar de partida.
- Las anotaciones se conservan en memoria por partida mientras el HQ permanece abierto.
  Recargar o abandonar la página descarta los dibujos.
- No hay carga ni guardado en servidor, ni mensajes de guardado bajo el mapa.
- Pantalla completa incluye lienzo, botonera y popup de texto, sin cabecera ni selector.
- La tabla hq_maps y sus endpoints se conservan por compatibilidad; el editor no los usa.

Pruebas de rellenos y selector: `node scripts/check_hq_map_fill.js`.

El enlace de equipo usa el mismo componente en modo lectura debajo de Misiones, plegado por
defecto. Muestra el mismo plano PNG, con desplazamiento nativo, zoom 100–300 % en
pasos de 25 % y pantalla completa del mapa y su botonera. No contiene canvas de
edición ni consulta/guarda anotaciones administrativas. Incluye una capa vacía
`data-map-overlay` reservada para detalles de misión futuros, todavía sin datos.
Lo inicializa `initBurnoutMapsPage`, compatible con carga directa y parcial.

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

## Jugadores (administración)

Dos cajas muestran Alpha y Bravo, con nombres, roles, totales de jugadores y puntos,
y cantidades por rol. Se selecciona una inscripción completa y las flechas centrales
la trasladan; en móvil las cajas se apilan y las flechas apuntan arriba/abajo.
El botón circular regenera y guarda el reparto. El reparto se conserva al cambiar
de partida o recargar. Las altas nuevas empiezan en Alpha.

La unidad indivisible es la inscripción (`registrations.id`), no el nombre de equipo
de contacto. `registrations.hq_team` guarda Alpha/Bravo independientemente de
`team_name`. No cambia misiones, códigos ni canales del HQ.

Puntos: Fusilero 1, Tirador Selecto 2, Francotirador 2, Apoyo 3 y Escudo 2.
Los asistentes anteriores sin rol mantienen «Sin especificar» y cuentan 1 punto.
El generador usa varias distribuciones deterministas y mejoras mediante movimientos
o intercambios de grupos completos. Minimiza `4 × diferencia_puntos² +
diferencia_jugadores² + suma((peso_rol × diferencia_cantidad_rol)²)`.
Busca un reparto aproximado; las inscripciones indivisibles pueden impedir la igualdad.
No garantiza el óptimo matemático. Regenerar reemplaza las asignaciones manuales.

`players` es GET; `players_move` y `players_balance` son POST con CSRF y sesión admin.
El servidor valida partida vigente, operación abierta e inscripción perteneciente a
ese evento, y serializa cambios bloqueando operación, evento e inscripciones.
Los enlaces solo reciben los identificadores de jugadores, nombres, roles de arma,
equipo de inscripción y funciones de su propio equipo en la consulta
`state`; no reciben puntos, documentos ni contactos. Las acciones
administrativas de jugadores siguen restringidas a HQ.
La vista se actualiza cada cinco segundos mientras está visible. Las respuestas
de una partida anterior se descartan al cambiar la selección.

Pruebas: `php scripts/check_hq_players.php` (algoritmo sin MySQL),
`node scripts/check_hq_players_ui.js` (estado de interfaz y respuestas tardías), y
`php scripts/check_hq.php` (integración HTTP local con datos ficticios que limpia).

## Enlace de equipo

Compartir `hq_equipo.php` y el código del equipo. No hace falta introducir identificador
de operación ni elegir equipo. El servidor resuelve la partida vigente y el equipo.
Una sesión solo accede a su equipo; se recupera al recargar y se revoca al renovar el código.
La cabecera «Burnout Airsoft / Enlace HQ» aparece tras validar la sesión y se oculta al salir.
El enlace recibe misiones transmitidas de su equipo, confirma recepción y participa en
su chat. No recibe el catálogo ni sus metadatos administrativos.
La sección Jugadores muestra el equipo Alpha/Bravo, el número de jugadores y sus
nombres, equipos de inscripción y roles de arma. Permite elegir la función de cada
jugador pulsando su nombre: un popup ofrece cinco iconos cuadrados para Team Leader
(tres galones), Ingeniero (llave), Radio Operator (radio), Segundo TL (dos galones)
y Médico (cruz). Las filas con función asignada tienen un fondo rojizo muy suave,
que desaparece al retirar la función.
La lista se ordena por función: TL, Segundo TL, Radio Operator, Ingeniero, Médico
y finalmente los jugadores sin función. Dentro de cada función conserva el orden
de inscripción. El orden se actualiza al asignar o retirar funciones.
El icono activo marca la función; pulsarlo otra vez la retira. El popup reutiliza
el componente compartido, con foco inicial, Tab, Escape y retorno al nombre.
Jugadores, Misiones, Mapa y Canales son paneles plegables independientes, con cabeceras
oscuras, bordes y flechas que cambian al abrirlos. Misiones empieza abierto;
Jugadores, Mapa y Canales, cerrados. El enlace no muestra el
indicador verde de canal disponible. La función se guarda automáticamente en
`registration_attendees.tactical_role`, separada del rol de arma y de la puntuación.
Cada equipo admite un Team Leader, un Segundo TL, un Ingeniero, un Radio Operator
y hasta dos Médicos. Asignar una función única a otro jugador retira la asignación
anterior del mismo equipo. Un tercer Médico se rechaza sin modificar a los dos
existentes. Las comprobaciones se serializan bloqueando la operación. Mover o
reequilibrar inscripciones también aplica estos límites: conserva primero a los
titulares que ya estaban en el equipo destino y retira funciones sobrantes.
La acción POST `player_role` exige CSRF,
sesión vigente y operación abierta; bloquea la operación y el acceso, relee la
pertenencia del jugador y solo permite editar el equipo de la sesión. Trasladar al
jugador conserva su función cuando hay plaza en el equipo destino y revoca la
posibilidad de editarlo al equipo anterior.
Se actualiza con la consulta periódica de estado, incluidos los
traslados y repartos que haga administración. El servidor determina el equipo por
la sesión validada, sin aceptar un equipo alternativo del cliente. La lista se
limpia al desconectar o caducar el acceso.

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
  cierres y paginación conservan su tamaño. En horizontal, hasta 900 px, el menú
  HQ pasa arriba con botones de 64 px y columnas adaptables. En vertical conserva
  el diseño anterior: barra lateral hasta 900 px y cinco columnas hasta 640 px.

Detalle de la revisión y sus límites: [revisión del 03/11/2026](security-review-2026-11-03.md).
