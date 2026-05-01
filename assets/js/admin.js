window.initAdminPage = function () {
  var $admin = $('#admin');

  if (!$admin.length) {
    return;
  }

  $.when(
    loadJson('resources/registros.json'),
    loadJson('assets/data/gallery.json')
  ).done(function (registros, gallery) {
    renderAdmin($admin, registros, gallery);
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

function renderAdmin($admin, registros, gallery) {
  var totalRegistros = registros.length;
  var totalGaleria = gallery.length;
  var menores = registros.filter(isMinorRegistration).length;
  var proximasPartidas = countFutureEvents(registros);

  $admin.html([
    '<div class="admin-stats">',
      statCard('Registros', totalRegistros),
      statCard('Menores', menores),
      statCard('Fotos galeria', totalGaleria),
      statCard('Proximas partidas', proximasPartidas),
    '</div>',
    '<div class="admin-grid">',
      '<div class="admin-table-wrap">',
        '<h2>Ultimos registros</h2>',
        renderRegistrationsTable(registros),
      '</div>',
      '<aside class="admin-actions">',
        '<h2>Gestion</h2>',
        '<a href="galeria.html">Ver Galeria <span>-></span></a>',
        '<a href="admin_gallery.php">Modificar Galeria <span>-></span></a>',
        '<a href="resources/registros.json">Ver Registros <span>-></span></a>',
      '</aside>',
    '</div>'
  ].join(''));
}

function statCard(label, value) {
  return '<article class="admin-card"><span>' + escapeHtml(label) + '</span><strong>' + value + '</strong></article>';
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

function isMinorRegistration(registro) {
  var edad = parseInt(readField(registro, ['edad', 'age', 'Edad'], ''), 10);
  return !isNaN(edad) && edad < 18;
}

function countFutureEvents(registros) {
  var today = new Date();
  today.setHours(0, 0, 0, 0);

  return registros.filter(function (registro) {
    var value = readField(registro, ['fecha', 'date', 'partida', 'Fecha'], '');
    var eventDate = new Date(value);
    return !isNaN(eventDate.getTime()) && eventDate >= today;
  }).length;
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
