<?php
declare(strict_types=1);
// Tabla compartida entre el catalogo de Ajustes y el popup de importacion.
?>
<div class="hq-catalog-browser" data-hq-catalog-browser="<?= htmlspecialchars($hqCatalogTableMode, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($hqCatalogTableMode === 'import'): ?>
  <div class="hq-catalog-filters hq-catalog-filters--import hq-form">
    <label>Buscar<input type="search" data-hq-filter="search" placeholder="Nombre o texto del guion"></label>
    <label>Categor&iacute;a<select data-hq-filter="category"><option value="">Todas</option></select></label>
  </div>
  <?php endif; ?>
  <div class="admin-table-controls hq-catalog-controls">
    <span class="admin-table-count" data-hq-table-count aria-live="polite"></span>
    <?php if ($hqCatalogTableMode === 'manage'): ?>
    <select class="hq-catalog-page-size" data-hq-table-size aria-label="Misiones por página"><option value="5" selected>5</option><option value="10">10</option><option value="15">15</option></select>
    <?php endif; ?>
  </div>
  <div class="admin-table-scroll">
    <table class="admin-table hq-catalog-table">
      <caption class="hq-sr-only"><?= $hqCatalogTableMode === 'import' ? 'Seleccionar una misión para importar' : 'Catálogo de misiones' ?></caption>
      <thead><tr>
        <th aria-sort="ascending"><button type="button" class="admin-sort-button" data-hq-table-sort="title">Misi&oacute;n</button></th>
        <th aria-sort="none"><button type="button" class="admin-sort-button" data-hq-table-sort="category">Categor&iacute;a</button></th>
        <?php if ($hqCatalogTableMode === 'manage'): ?><th class="hq-catalog-actions-column" aria-label="Acciones"></th><?php endif; ?>
      </tr></thead>
      <tbody data-hq-table-body></tbody>
    </table>
  </div>
  <nav class="admin-registrations-pagination hq-catalog-pagination" data-hq-table-pagination aria-label="Paginaci&oacute;n de misiones" hidden><div class="admin-registration-pages" data-hq-table-pages></div></nav>
</div>
