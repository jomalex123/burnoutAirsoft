# Estadísticas de las páginas públicas

La integración utiliza el contenedor `GTM-W2FVT27Q` mediante
`assets/js/analytics.js`, cargado una vez en el `head` de inicio, Nosotros, Campo,
Galería, Partidas, Registro, Normativa y Privacidad. `registro.php` reutiliza
`registro.html`, por lo que no debe llevar una segunda etiqueta.

Solo se solicita el script remoto desde `burnoutairsoft.com` o
`www.burnoutairsoft.com`, después de aceptar las cookies de estadísticas.
En localhost y PRE se puede comprobar la interfaz sin enviar visitas a Google.
Administración y el enlace HQ no incorporan el script; cruzar entre esas páginas
y las públicas usa una carga completa para descargar el contenedor anterior.

## Configuración necesaria en Google Tag Manager

El identificador de contenedor no indica el identificador de medición GA4
(`G-…`) ni garantiza que exista una etiqueta publicada. En Google:

1. Configurar/publicar una etiqueta de Google con el identificador de medición
   de la propiedad GA4, activa en todas las páginas. Esta etiqueta envía la
   primera visita al cargar el contenedor tras el consentimiento.
2. Para navegación parcial, crear un activador de evento personalizado con
   nombre exacto `burnout_page_view`. Asociarlo a una etiqueta de evento GA4
   con nombre `page_view` y parámetros `page_location`, `page_referrer` y
   `page_title` obtenidos de las variables correspondientes de la capa de datos.
   Esta etiqueta se activa solo con ese evento personalizado, no en todas las
   páginas ni con History Change.
3. En la medición mejorada de GA4, desactivar la detección de cambios de página
   basada en eventos del historial. Mantener la medición de cargas iniciales.
   Así se evita contar dos veces los cambios parciales.
4. Desactivar la medición automática de interacciones con formularios. No
   configurar etiquetas o variables que lean nombres, documentos, correos,
   teléfonos, valores de formulario o contenido privado. Revisar también las
   opciones de recopilación de datos proporcionados por usuarios y publicidad:
   esta integración se limita a estadísticas de navegación.
5. Publicar el contenedor y comprobar con Tag Assistant y Tiempo real de GA4
   una carga inicial y un cambio parcial después de aceptar. Comprobar que al
   rechazar antes de aceptar no se solicita `gtm.js` ni se envían visitas.

El código envía los estados de consentimiento de analítica y mantiene
`ad_storage`, `ad_user_data` y `ad_personalization` denegados. Añadir etiquetas
de terceros al contenedor exige revisar sus controles de consentimiento y CSP.

## Consentimiento y navegación

El aviso permite aceptar o rechazar con la misma jerarquía visual. La elección
se conserva en `localStorage` durante 180 días. El botón persistente «Cookies»
permite cambiarla; no está dentro del `main` que reemplaza la navegación parcial.
No se trata de un modal y no bloquea la navegación ni exige aceptar.

Al retirar el consentimiento se actualiza el estado, se eliminan las cookies
de Analytics accesibles en el dominio y se realiza una carga GET de la página
actual para descargar las etiquetas ya activas. Se avisa de esa recarga en el
panel; cualquier contenido sin guardar se pierde. No se reenvía un POST de
inscripción. Las otras pestañas aplican el cambio mediante el evento `storage`.
Si el almacenamiento está bloqueado, la elección dura solo en el documento.

La integración omite consultas y fragmentos de URL en sus datos de navegación,
y no incorpora campos de las inscripciones a `dataLayer`. La configuración
externa del contenedor debe respetar esos límites. No se puede verificar la
recogida real ni las etiquetas publicadas sin acceso a esa configuración.

`main.js` emite `burnout:pageview` después de actualizar título, contenido e
historial. Solo una URL pública distinta de la última emite el evento de capa
de datos. La carga inicial se deja a la etiqueta de Google, sin duplicarla.

`.htaccess` permite los recursos de Tag Manager y las conexiones de Analytics.
No se ha añadido `unsafe-eval`, modo Preview ampliado, publicidad ni un iframe
`noscript`: no debe existir una vía de medición que eluda el consentimiento.

## Comprobaciones

```powershell
node --check assets/js/analytics.js
node --check assets/js/main.js
node scripts/check_analytics.js
& 'D:\xampp\php\php.exe' scripts/check_http_security.php
```

La prueba Node simula el navegador, sin contactar con Google. Verifica el
bloqueo inicial, rechazo, aceptación, caducidad, dominio, exclusión de páginas
privadas, carga única, navegación parcial y retirada del consentimiento.

Referencias oficiales:
[instalación del contenedor](https://support.google.com/tagmanager/answer/14847097),
[consentimiento básico](https://developers.google.com/tag-platform/security/guides/consent?consentmode=basic)
y [CSP para Analytics](https://developers.google.com/tag-platform/security/guides/csp).
