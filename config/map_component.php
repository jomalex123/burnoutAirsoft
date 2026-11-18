<?php

declare(strict_types=1);

// Un mismo componente para edicion y lectura. Los IDs deben ser unicos por pagina.
function burnout_render_map(string $id, bool $editable = true): void
{
    if (!preg_match('/^[a-z][a-z0-9_-]*$/D', $id)) {
        throw new InvalidArgumentException('Identificador de mapa no valido.');
    }
    $escapedId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
?>
<div class="burnout-map<?= $editable ? '' : ' burnout-map--readonly' ?>" id="<?= $escapedId ?>" data-burnout-map data-map-mode="<?= $editable ? 'edit' : 'readonly' ?>">
<div class="admin-map-toolbar" role="group" aria-label="Controles del mapa">
<?php if ($editable): ?>
  <button type="button" data-map-tool="pan" title="Mover mapa" aria-label="Mover mapa" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 12V5a1.5 1.5 0 0 1 3 0v6-8a1.5 1.5 0 0 1 3 0v8-6a1.5 1.5 0 0 1 3 0v7-3a1.5 1.5 0 0 1 3 0v6c0 4-2 7-6 7h-2c-2 0-3-1-4-3l-4-6a1.5 1.5 0 0 1 2-2Z"/></svg></button>
  <button type="button" data-map-tool="pen" title="Lápiz" aria-label="Lápiz" aria-pressed="true" class="is-active"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m16 3 5 5-12 12-6 1 1-6Z"/><path d="m14 5 5 5"/></svg></button>
  <button type="button" data-map-tool="line" title="Línea" aria-label="Línea" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m4 20 16-16"/><circle cx="4" cy="20" r="1"/><circle cx="20" cy="4" r="1"/></svg></button>
  <button type="button" data-map-tool="arrow" title="Flecha" aria-label="Flecha" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20 20 4M8 4h12v12"/></svg></button>
  <button type="button" data-map-tool="rectangle" title="Rectángulo" aria-label="Rectángulo" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="1"/></svg></button>
  <button type="button" data-map-tool="circle" title="Círculo" aria-label="Círculo" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/></svg></button>
  <button type="button" data-map-tool="eraser" title="Borrador" aria-label="Borrador" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14 3 7 7-11 11H6l-4-4Z"/><path d="m7 12 7 7M10 21h12"/></svg></button>
  <button type="button" data-map-tool="text" title="Texto libre" aria-label="Texto libre" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m2 19 5-14 5 14M4 14h6M21 19v-8M21 15a4 4 0 1 0-8 0 4 4 0 0 0 8 0"/></svg></button>
  <label title="Color"><input type="color" data-map-color value="#df1f29" aria-label="Color"></label>
  <label title="Grosor"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h18M3 12h18M3 19h18"/><path d="M3 12h18" stroke-width="3"/><path d="M3 19h18" stroke-width="5"/></svg><input type="range" data-map-size min="1" max="40" value="5" aria-label="Grosor"><output data-map-size-label>5 px</output></label>
  <button type="button" data-map-fill-toggle title="Relleno: sin relleno" aria-label="Relleno: sin relleno" aria-haspopup="listbox" aria-expanded="false" aria-controls="<?= $escapedId ?>-adminMapFillOptions"><svg data-map-fill-icon viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16"/><path d="m5 19 14-14"/></svg></button>
  <button type="button" data-map-action="undo" title="Deshacer" aria-label="Deshacer" disabled><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 5-6 6 6 6"/><path d="M3 11h11a6 6 0 0 1 6 6v2"/></svg></button>
  <button type="button" data-map-action="redo" title="Rehacer" aria-label="Rehacer" disabled><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 5 6 6-6 6"/><path d="M21 11H10a6 6 0 0 0-6 6v2"/></svg></button>
  <button type="button" data-map-action="clear" title="Limpiar mapa" aria-label="Limpiar mapa" ><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg></button>
  <?php endif; ?>
  <button type="button" class="admin-map-fullscreen-button" data-map-action="fullscreen" title="Pantalla completa" aria-label="Pantalla completa" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path data-map-fullscreen-icon d="M8 3H3v5M16 3h5v5M21 16v5h-5M8 21H3v-5"/></svg></button>
</div>
<?php if ($editable): ?>
<div class="admin-map-fill-menu" id="<?= $escapedId ?>-adminMapFillOptions" data-map-fill-menu role="listbox" aria-label="Relleno de rectángulos y círculos" hidden>
  <button type="button" class="admin-map-fill-option" role="option" data-map-fill="none" aria-label="Sin relleno" title="Sin relleno" aria-selected="true" tabindex="-1"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16"/><path d="m5 19 14-14"/></svg></button>
  <button type="button" class="admin-map-fill-option" role="option" data-map-fill="hatched" aria-label="Translúcido y rayado a 45°" title="Translúcido y rayado a 45°" aria-selected="false" tabindex="-1"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" fill="currentColor" fill-opacity=".18"/><path d="m4 10 6-6M4 16 16 4M6 20 20 6m-8 14 8-8m-2 8 2-2"/></svg></button>
  <button type="button" class="admin-map-fill-option" role="option" data-map-fill="solid" aria-label="Sólido" title="Sólido" aria-selected="false" tabindex="-1"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" fill="currentColor"/></svg></button>
</div>
<?php endif; ?>
<div class="admin-map-canvas-wrap">
  <div class="admin-map-viewport" tabindex="0" role="region" aria-label="Mapa del campo">
  <div class="admin-map-canvas-stage">
    <img class="admin-map-background" src="assets/maps/carto-orto-detalle.png" alt="Plano del campo de Burnout Airsoft" draggable="false">
    <?php if ($editable): ?>
<canvas width="1600" height="1131" aria-label="Lienzo de dibujo sobre el plano del campo. Usa el ratón o un dedo para dibujar."></canvas>
<?php endif; ?>
<div class="burnout-map-overlay" data-map-overlay aria-hidden="true"></div>
  </div>
  </div>
  <div class="admin-map-zoom" role="group" aria-label="Zoom del mapa">
    <button type="button" data-map-action="zoom-in" title="Acercar mapa" aria-label="Acercar mapa">+</button>
    <button type="button" data-map-action="zoom-out" title="Alejar mapa" aria-label="Alejar mapa" disabled>−</button>
  </div>
  </div>
<p class="admin-map-load-error" role="alert" hidden>No se ha podido cargar el plano del campo. Recarga la página para volver a intentarlo.</p>
<?php if ($editable): ?>
<div class="burnout-popup" data-map-clear-modal aria-hidden="true">
  <div class="burnout-popup__overlay" data-map-clear-close></div>
  <div class="burnout-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="<?= $escapedId ?>-mapClearTitle" aria-describedby="<?= $escapedId ?>-mapClearDescription">
    <div class="burnout-popup__header">
      <h2 id="<?= $escapedId ?>-mapClearTitle">Eliminar anotaciones</h2>
      <button type="button" class="burnout-popup__close" data-map-clear-close aria-label="Cerrar ventana"><span aria-hidden="true">&times;</span></button>
    </div>
    <div class="burnout-popup__body"><p id="<?= $escapedId ?>-mapClearDescription">¿Eliminar todas las anotaciones? El plano se conservará y podrás deshacer esta acción.</p></div>
    <div class="burnout-popup__actions burnout-popup__actions--split">
      <button type="button" class="admin-back-link" data-map-clear-close data-map-clear-cancel>Cancelar</button>
      <button type="button" class="admin-login-submit" data-map-clear-confirm>Aceptar</button>
    </div>
  </div>
</div>
<div class="admin-gallery-modal admin-map-text-modal burnout-popup" data-map-text-modal aria-hidden="true">
  <div class="admin-gallery-modal__overlay burnout-popup__overlay" data-map-text-close></div>
  <div class="admin-gallery-modal__dialog burnout-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="<?= $escapedId ?>-mapTextTitle">
    <div class="admin-gallery-modal__header burnout-popup__header">
      <h2 id="<?= $escapedId ?>-mapTextTitle">Añadir texto</h2>
      <button class="burnout-popup__close" type="button" data-map-text-close aria-label="Cerrar ventana"><span aria-hidden="true">&#215;</span></button>
    </div>
    <form data-map-text-form>
      <div class="admin-login-field">
        <label for="<?= $escapedId ?>-mapTextInput">Escribe el texto que quieres añadir al mapa:</label>
        <input id="<?= $escapedId ?>-mapTextInput" type="text" required autocomplete="off" data-map-text-input>
      </div>
      <div class="admin-gallery-modal__actions burnout-popup__actions">
        <button class="admin-login-submit" type="submit">Aceptar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
</div>
<?php
}
