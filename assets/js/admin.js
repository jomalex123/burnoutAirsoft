window.initAdminPage = function () {
  var $admin = $('#admin');

  if (!$admin.length) {
    return;
  }

  $.when(
    loadJson('resources/registros.json')
  ).done(function (registros) {
    renderAdmin($admin, registros);
  });
};

if (!window.__burnoutLoadingPageScript) {
  $(document).ready(window.initAdminPage);
}

function loadJson(path) {
  return $.getJSON(path).then(function (data) {
    return Array.isArray(data) ? data : [];
  }, function () {
    return [];
  });
}

function renderAdmin($admin, registros) {
  $admin.html([
    '<div class="admin-grid">',
      '<div class="admin-table-wrap">',
        '<h2>Ultimos registros</h2>',
        renderRegistrationsTable(registros),
      '</div>',
      '<aside class="admin-actions">',
        '<h2>Gestion</h2>',
        '<a href="galeria.html">Ver Galeria <span>-></span></a>',
        '<a href="admin_gallery.php">Modificar Galeria <span>-></span></a>',
        '<a href="admin_partidas.php">Gestionar Partidas <span>-></span></a>',
      '</aside>',
    '</div>'
  ].join(''));
}

function renderRegistrationsTable(registros) {
  if (!registros.length) {
    return '<div class="admin-empty">No hay registros locales en resources/registros.json.</div>';
  }

  var rows = registros.slice(-10).reverse().map(function (registro) {
    return [
      '<tr>',
        '<td>' + escapeHtml(readField(registro, ['nombre', 'name', 'Nombre'], 'Sin nombre')) + '</td>',
        '<td>' + escapeHtml(readField(registro, ['email', 'correo', 'Email'], 'Sin email')) + '</td>',
        '<td>' + escapeHtml(readField(registro, ['telefono', 'phone', 'Telefono'], '-') ) + '</td>',
        '<td>' + escapeHtml(readField(registro, ['fecha', 'date', 'partida', 'Fecha'], '-') ) + '</td>',
      '</tr>'
    ].join('');
  }).join('');

  return [
    '<div class="admin-table-scroll">',
      '<table class="admin-table">',
        '<thead><tr><th>Nombre</th><th>Email</th><th>Telefono</th><th>Partida</th></tr></thead>',
        '<tbody>' + rows + '</tbody>',
      '</table>',
    '</div>'
  ].join('');
}

function readField(item, keys, fallback) {
  for (var index = 0; index < keys.length; index++) {
    if (item[keys[index]]) {
      return item[keys[index]];
    }
  }

  return fallback;
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, function (character) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[character];
  });
}
