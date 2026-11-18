<?php
declare(strict_types=1);
require_once __DIR__ . '/map_component.php';

// Incluido por admin_hq.php y hq_equipo.php; no se sirve directamente.
$hqTeamView = $hqTeamView ?? false;
?>
<div class="hq-console" data-hq-console data-hq-mode="<?= $hqTeamView ? 'team' : 'admin' ?>" data-hq-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" <?= $hqTeamView ? '' : 'hidden' ?>>
  <p class="hq-feedback" data-hq-feedback role="status" aria-live="polite"></p>
  <?php if ($hqTeamView): ?>
  <section class="hq-card hq-login" data-hq-login>
    <span class="hq-eyebrow">Frecuencia restringida / Enlace de equipo</span>
    <h2>Establece contacto con HQ</h2>
    <p>Introduce el código que te ha facilitado la organización.</p>
    <form data-hq-form="login" class="hq-form">
      <label>Código de acceso<input name="code" type="password" autocomplete="off" maxlength="100" required></label>
      <button type="submit">Conectar</button>
    </form>
  </section>
  <?php endif; ?>
  <div data-hq-dashboard <?= $hqTeamView ? 'hidden' : '' ?>>
    <div class="hq-operation-bar">
      <div><?php if ($hqTeamView): ?><span class="hq-eyebrow" data-hq-operation-heading>Centro de operaciones</span><h2 data-hq-operation-title>Selecciona una partida</h2><?php else: ?><h2 class="hq-eyebrow" data-hq-operation-heading>Centro de operaciones</h2><?php endif; ?></div>
      <?php if (!$hqTeamView): ?>
      <label><select data-hq-operation aria-label="Evento del calendario"><option value="">Selecciona una partida</option></select></label>
      <?php else: ?>
      <button type="button" data-hq-logout>Desconectar</button>
      <?php endif; ?>
      <span class="hq-signal" data-hq-signal <?= $hqTeamView ? 'hidden' : '' ?>>Sin conexión</span>
    </div>
    <div class="hq-new-call" data-hq-call hidden role="status">Llamada entrante de HQ · Nuevo briefing recibido</div>
    <?php if ($hqTeamView): ?>
    <details class="hq-team-roster hq-team-accordion">
      <summary class="hq-team-accordion-heading"><h2>Jugadores</h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <div class="hq-team-accordion-body">
      <div class="hq-players-team">
        <div class="hq-radio-header"><strong data-hq-roster-team>Equipo</strong><span data-hq-roster-count aria-live="polite">0 jugadores</span></div>
        <ul class="hq-team-roster-list" data-hq-roster-list></ul>
      </div>
      </div>
    </details>
    <div class="burnout-popup" data-hq-role-popup aria-hidden="true">
      <div class="burnout-popup__overlay" data-hq-role-close></div>
      <div class="burnout-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="hqPlayerRoleTitle">
        <div class="burnout-popup__header">
          <h2 id="hqPlayerRoleTitle">Función del jugador</h2>
          <button type="button" class="burnout-popup__close" data-hq-role-close aria-label="Cerrar ventana"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="burnout-popup__body">
          <div class="hq-role-choices" role="group" aria-label="Función del jugador">
            <button type="button" data-hq-role-choice="TL" aria-label="Team Leader" title="Team Leader" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 9 7-5 7 5M5 14l7-5 7 5M5 19l7-5 7 5"/></svg></button>
            <button type="button" data-hq-role-choice="Segundo TL" aria-label="Segundo TL" title="Segundo TL" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 11 7-5 7 5M5 18l7-5 7 5"/></svg></button>
            <button type="button" data-hq-role-choice="Radio Operator" aria-label="Radio Operator" title="Radio Operator" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 9h10v13H7ZM9 9V2m1 11h4m-4 4h4M14 2a6 6 0 0 1 6 6M14 5a3 3 0 0 1 3 3"/></svg></button>
            <button type="button" data-hq-role-choice="Ingeniero" aria-label="Ingeniero" title="Ingeniero" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6 4 4 4-4a6 6 0 0 1-8 8L6 22l-4-4 8-8a6 6 0 0 1 8-8Z"/></svg></button>
            <button type="button" data-hq-role-choice="Medico" aria-label="Médico" title="Médico" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6Z"/></svg></button>
          </div>
          <p class="hq-feedback" data-hq-role-feedback role="status" aria-live="polite"></p>
        </div>
      </div>
    </div>
    <?php endif; ?>
      <?php if ($hqTeamView): ?>
    <details class="hq-team-accordion" data-map-panel="misiones" open>
      <summary class="hq-team-accordion-heading"><h2>Misiones</h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <div class="hq-team-accordion-body">
      <?php else: ?>
    <section data-map-panel="misiones" hidden>
      <?php endif; ?>
      <?php if (!$hqTeamView): ?>
      <div class="hq-channel-tabs" role="group" aria-label="Misiones del equipo">
        <button type="button" class="is-active" data-hq-mission-team="alpha" aria-pressed="true">Equipo Alpha</button>
        <button type="button" data-hq-mission-team="bravo" aria-pressed="false">Equipo Bravo</button>
      </div>
      <?php endif; ?>
      <div class="hq-missions" data-hq-missions><p class="hq-empty">El canal está en espera. Selecciona un evento del calendario.</p></div>
      <?php if ($hqTeamView): ?>
      </div>
    </details>
    <details class="hq-team-accordion hq-team-map-section">
      <summary class="hq-team-accordion-heading"><h2>Mapa</h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <div class="hq-team-accordion-body">
        <?php burnout_render_map('hq-team-map', false); ?>
      </div>
    </details>
    <details class="hq-team-accordion" data-map-panel="canales">
      <summary class="hq-team-accordion-heading"><h2>Canales</h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <div class="hq-team-accordion-body">
      <?php else: ?>
    </section>
    <section data-map-panel="canales" hidden>
      <?php endif; ?>
      <?php if (!$hqTeamView): ?>
      <div class="hq-channel-tabs" role="group" aria-label="Canal del equipo">
        <button type="button" class="is-active" data-hq-channel="alpha" aria-pressed="true">Equipo Alpha</button>
        <button type="button" data-hq-channel="bravo" aria-pressed="false">Equipo Bravo</button>
      </div>
      <?php endif; ?>
      <div class="hq-radio">
        <div class="hq-radio-header"><span data-hq-channel-title>Equipo Alpha</span><span>COMMS / TEXTO</span></div>
        <div class="hq-chat" data-hq-messages role="log" aria-label="Mensajes del canal" aria-live="polite"><p class="hq-empty">Sin transmisiones. Abre el canal con un mensaje.</p></div>
        <form data-hq-form="message" class="hq-chat-compose">
          <label class="hq-message-label">Mensaje<textarea name="body" rows="2" maxlength="1500" placeholder="Aquí HQ. ¿Me recibes?" required></textarea></label>
          <button type="submit" data-hq-send>Transmitir</button>
        </form>
      </div>
      <?php if ($hqTeamView): ?>
      </div>
    </details>
      <?php else: ?>
    </section>
      <?php endif; ?>
    <?php if (!$hqTeamView): ?>
    <section data-map-panel="jugadores" hidden>
      <div class="hq-players-layout">
        <div class="hq-players-team">
          <div class="hq-radio-header"><strong>Equipo Alpha</strong><span data-hq-players-total="alpha">0 jugadores · 0 puntos</span></div>
          <div class="hq-players-roles" data-hq-players-roles="alpha"></div>
          <div class="hq-players-list" data-hq-players-list="alpha" role="group" aria-label="Inscripciones del equipo Alpha"><p class="hq-empty">Selecciona una partida.</p></div>
        </div>
        <div class="hq-players-controls" role="group" aria-label="Repartir jugadores">
          <button type="button" class="hq-players-generate" data-hq-players-balance title="Generar equipos equilibrados" aria-label="Generar equipos equilibrados por puntos y roles, sin separar inscripciones" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 8a8 8 0 0 0-14-3L3 8m0-5v5h5M4 16a8 8 0 0 0 14 3l3-3m0 5v-5h-5"/></svg></button>
          <button type="button" data-hq-players-move="bravo" title="Mover inscripción a Bravo" aria-label="Mover inscripción seleccionada al equipo Bravo" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16m-7-7 7 7-7 7"/></svg></button>
          <button type="button" data-hq-players-move="alpha" title="Mover inscripción a Alpha" aria-label="Mover inscripción seleccionada al equipo Alpha" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12H4m7-7-7 7 7 7"/></svg></button>
        </div>
        <div class="hq-players-team">
          <div class="hq-radio-header"><strong>Equipo Bravo</strong><span data-hq-players-total="bravo">0 jugadores · 0 puntos</span></div>
          <div class="hq-players-roles" data-hq-players-roles="bravo"></div>
          <div class="hq-players-list" data-hq-players-list="bravo" role="group" aria-label="Inscripciones del equipo Bravo"><p class="hq-empty">Selecciona una partida.</p></div>
        </div>
      </div>
    </section>
    <section data-map-panel="ajustes" hidden>
      <div class="hq-card hq-create hq-settings-panel" data-hq-needs-operation hidden><h3>GESTIONAR GUION</h3>
        <div class="hq-script-layout">
        <form data-hq-form="mission" class="hq-form">
          <input type="hidden" name="catalog_id" value="">
          <label>Equipo<select name="team"><option value="alpha">Alpha</option><option value="bravo">Bravo</option></select></label>
          <p class="hq-source-note" data-hq-source-note>Importa una misión o escribe una nueva. Las misiones manuales se guardan también en el catálogo.</p>
          <label>Nombre de la misión<input name="title" maxlength="180" required placeholder="Recuperar el paquete"></label>
          <label>Guion y objetivos<textarea name="briefing" maxlength="8000" rows="5" required placeholder="Enlace, escucha con atención. Tu equipo debe…"></textarea></label>
          <div class="hq-mission-actions"><button type="submit" name="status" value="draft">Añadir misión</button></div>
        </form>
        <div class="hq-script-list"><div class="hq-script-import"><button type="button" data-hq-import>Importar misión</button></div><h4>Misiones de la partida</h4><ul data-hq-script-list aria-label="Misiones de la partida"></ul></div>
        </div>
      </div>
      <p class="hq-empty" data-hq-settings-empty>Selecciona una partida para configurar sus accesos.</p>
      <div class="hq-settings hq-settings--access" data-hq-needs-operation hidden>
        <div class="hq-card hq-settings-panel"><h3>ENLACES DE EQUIPO</h3><p>Cada código da acceso únicamente a las misiones transmitidas y al chat de ese equipo. Compártelo solo con su enlace.</p>
          <p>Acceso: <a href="hq_equipo.php" target="_blank" rel="noopener" data-hq-team-link>Pantalla de enlace</a></p>
          <div class="hq-access-actions"><button type="button" data-hq-access="alpha">Generar código Alpha</button><button type="button" data-hq-access="bravo">Generar código Bravo</button></div>
          <p class="hq-muted">Renovar un código desconecta los accesos anteriores de ese equipo. El código nuevo solo se muestra aquí al generarlo.</p>
          <output class="hq-access-code" data-hq-access-code aria-live="polite" hidden></output>
        </div>
      </div>
      <div class="hq-card hq-catalog hq-settings-panel" data-hq-catalog>
        <div class="hq-settings-panel__heading"><h3>GESTIONAR MISIONES</h3><button class="admin-filter-button" type="button" data-hq-catalog-filter-open>Filtrar</button></div>
        <?php $hqCatalogTableMode = 'manage'; require __DIR__ . '/hq_catalog_table.php'; ?>
        <div class="hq-catalog-new"><button type="button" data-hq-catalog-new>Nueva misión</button></div>

      </div>
    </section>
    <div class="burnout-popup hq-catalog-editor-popup" data-hq-catalog-editor-popup aria-hidden="true">
      <div class="burnout-popup__overlay" data-hq-catalog-editor-close></div>
      <div class="burnout-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="hqCatalogEditorTitle">
        <div class="burnout-popup__header"><h2 id="hqCatalogEditorTitle">Nueva misión</h2><button class="burnout-popup__close" type="button" data-hq-catalog-editor-close aria-label="Cerrar ventana"><span aria-hidden="true">&times;</span></button></div>
        <form data-hq-form="catalog_save" class="hq-form">
          <input type="hidden" name="catalog_id" value="">
          <label>Nombre de la misión<input name="title" maxlength="180" required></label>
          <label>Categoría<select name="category"><option value="">Sin clasificar</option></select></label>
          <label>Guion<textarea name="briefing" maxlength="8000" rows="6" required></textarea></label>
          <button type="button" class="hq-mission-map-button" data-hq-mission-map-edit title="Editar mapa" aria-label="Editar mapa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 5-2-1-6 3v14l6-3 8 3 6-3v-5M8 4v14M16 16v5M22 7c0 3-5 7-5 7s-5-4-5-7a5 5 0 0 1 10 0ZM19 7a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg></button>
          <p class="hq-feedback" data-hq-catalog-editor-feedback role="status"></p>
          <div class="burnout-popup__actions"><button class="admin-login-submit" type="submit">Guardar misión</button></div>
        </form>
      </div>
    </div>
    <div class="burnout-popup hq-catalog-delete-popup" data-hq-catalog-delete-popup aria-hidden="true">
      <div class="burnout-popup__overlay" data-hq-catalog-delete-close></div>
      <div class="burnout-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="hqCatalogDeleteTitle" aria-describedby="hqCatalogDeleteDescription">
        <div class="burnout-popup__header"><h2 id="hqCatalogDeleteTitle">Borrar misión</h2><button class="burnout-popup__close" type="button" data-hq-catalog-delete-close aria-label="Cerrar ventana"><span aria-hidden="true">&times;</span></button></div>
        <div class="burnout-popup__body"><p id="hqCatalogDeleteDescription">¿Quieres borrar <strong data-hq-catalog-delete-name></strong>? Se desactivará en el catálogo. Las misiones ya asignadas a partidas se conservarán.</p></div>
        <p class="hq-feedback" data-hq-catalog-delete-feedback role="status"></p>
        <div class="burnout-popup__actions burnout-popup__actions--split"><button class="admin-back-link" type="button" data-hq-catalog-delete-close>Cancelar</button><button class="admin-login-submit" type="button" data-hq-catalog-delete-confirm>Borrar misión</button></div>
      </div>
    </div>
    <div class="burnout-popup hq-catalog-filter-popup" data-hq-catalog-filter-popup aria-hidden="true">
      <div class="burnout-popup__overlay" data-hq-catalog-filter-close></div>
      <div class="burnout-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="hqCatalogFiltersTitle">
        <div class="burnout-popup__header"><h2 id="hqCatalogFiltersTitle">Filtrar misiones</h2><button class="burnout-popup__close" type="button" data-hq-catalog-filter-close aria-label="Cerrar ventana"><span aria-hidden="true">&times;</span></button></div>
        <form class="hq-form" data-hq-catalog-filter-form>
          <label>Buscar<input type="search" data-hq-filter="search" placeholder="Nombre o texto del guion"></label>
          <label>Categor&iacute;a<select data-hq-filter="category"><option value="">Todas</option></select></label>
          <div class="burnout-popup__actions burnout-popup__actions--split"><button type="reset" class="admin-danger-submit">Limpiar filtros</button><button type="submit" class="admin-login-submit">Aplicar filtros</button></div>
        </form>
      </div>
    </div>
    <div class="burnout-popup hq-import-popup" data-hq-import-popup aria-hidden="true">
      <div class="burnout-popup__overlay" data-hq-import-close></div>
      <div class="burnout-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="hqImportTitle">
        <div class="burnout-popup__header"><h2 id="hqImportTitle">Importar misión</h2><button class="burnout-popup__close" type="button" data-hq-import-close aria-label="Cerrar ventana"><span aria-hidden="true">&times;</span></button></div>
        <form data-hq-import-form class="hq-form">
          <input type="hidden" data-hq-import-select>
          <?php $hqCatalogTableMode = 'import'; require __DIR__ . '/hq_catalog_table.php'; ?>
          <p class="hq-import-preview" data-hq-import-preview>Selecciona una misión para consultar su guion.</p>
          <p class="hq-muted" data-hq-import-feedback role="status"></p>
          <div class="burnout-popup__actions"><button type="submit" data-hq-import-accept disabled>IMPORTAR</button></div>
        </form>
      </div>
    </div>
    <div class="burnout-popup" data-hq-access-popup aria-hidden="true">
      <div class="burnout-popup__overlay" data-hq-access-close></div>
      <div class="burnout-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="hqAccessConfirmTitle" aria-describedby="hqAccessConfirmDescription">
        <div class="burnout-popup__header"><h2 id="hqAccessConfirmTitle">Generar c&oacute;digo</h2><button class="burnout-popup__close" type="button" data-hq-access-close aria-label="Cerrar ventana"><span aria-hidden="true">&times;</span></button></div>
        <div class="burnout-popup__body"><p id="hqAccessConfirmDescription">&iquest;Generar un c&oacute;digo para el equipo <strong data-hq-access-confirm-team></strong>? Se invalidar&aacute; su c&oacute;digo anterior.</p></div>
        <div class="burnout-popup__actions burnout-popup__actions--split"><button class="admin-back-link" type="button" data-hq-access-close>Cancelar</button><button class="admin-login-submit" type="button" data-hq-access-confirm>Aceptar</button></div>
      </div>
    </div>
    <div class="burnout-popup hq-mission-map-popup" data-hq-mission-map-popup aria-hidden="true">
      <div class="burnout-popup__overlay" data-hq-mission-map-close></div>
      <div class="burnout-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="hqMissionMapTitle">
        <div class="burnout-popup__header"><h2 id="hqMissionMapTitle">Mapa de misi&oacute;n</h2><button class="burnout-popup__close" type="button" data-hq-mission-map-close aria-label="Cerrar ventana"><span aria-hidden="true">&times;</span></button></div>
        <div data-hq-mission-map-editable><?php burnout_render_map('hq-mission-editor'); ?></div>
        <div data-hq-mission-map-preview hidden><?php burnout_render_map('hq-mission-preview', false); ?></div>
        <p class="hq-feedback" data-hq-mission-map-feedback role="status"></p>
        <div class="burnout-popup__actions"><button class="admin-login-submit" type="button" data-hq-mission-map-save>Guardar mapa</button></div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
