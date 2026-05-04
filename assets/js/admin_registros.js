window.initAdminRegistrosPage = function () {
  var table = document.getElementById('registrationsTable');
  var filtersForm = document.getElementById('registrationFiltersForm');
  var count = document.getElementById('registrationCount');
  var exportToggle = document.getElementById('registrationExportToggle');
  var exportOptions = document.getElementById('registrationExportOptions');
  var exportCsvButton = document.getElementById('exportRegistrationsCsv');
  var exportPdfButton = document.getElementById('exportRegistrationsPdf');
  var sortButtons = Array.prototype.slice.call(document.querySelectorAll('[data-sort-key]'));
  var rows = table ? Array.prototype.slice.call(table.querySelectorAll('tbody tr')) : [];
  var activeFilters = {
    event: '',
    date: '',
    turn: '',
    email: '',
    phone: '',
    team: '',
    document: ''
  };
  var sortState = {
    key: '',
    direction: 'asc'
  };

  if (!table || table.getAttribute('data-admin-registros-ready') === 'true') {
    return;
  }

  table.setAttribute('data-admin-registros-ready', 'true');

  if (filtersForm) {
    filtersForm.addEventListener('submit', function (event) {
      event.preventDefault();
      readFilters();
      renderRows();
      closeModals();
    });

    filtersForm.addEventListener('reset', function () {
      setTimeout(function () {
        readFilters();
        renderRows();
      }, 0);
    });
  }

  sortButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var key = button.getAttribute('data-sort-key');

      if (sortState.key === key) {
        sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
      } else {
        sortState.key = key;
        sortState.direction = 'asc';
      }

      updateSortButtons();
      renderRows();
    });
  });

  if (exportToggle && exportOptions) {
    exportToggle.addEventListener('click', function (event) {
      event.stopPropagation();
      exportOptions.hidden = !exportOptions.hidden;
      exportToggle.setAttribute('aria-expanded', exportOptions.hidden ? 'false' : 'true');
    });

    exportOptions.addEventListener('click', function (event) {
      event.stopPropagation();
    });

    document.addEventListener('click', closeExportMenu);
  }

  if (exportCsvButton) {
    exportCsvButton.addEventListener('click', function () {
      downloadCsv();
      closeExportMenu();
    });
  }

  if (exportPdfButton) {
    exportPdfButton.addEventListener('click', function () {
      exportPdf();
      closeExportMenu();
    });
  }

  document.querySelectorAll('[data-registros-modal-open]').forEach(function (button) {
    button.addEventListener('click', function () {
      var modal = document.getElementById(button.getAttribute('data-registros-modal-open'));

      if (!modal) {
        return;
      }

      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('admin-gallery-modal-open');
    });
  });

  document.querySelectorAll('[data-registros-modal-close]').forEach(function (button) {
    button.addEventListener('click', closeModals);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeExportMenu();
      closeModals();
    }
  });

  renderRows();

  function normalize(value) {
    return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function normalizeDocument(value) {
    return normalize(value).replace(/[\s.-]/g, '');
  }

  function rowMatches(row) {
    return Object.keys(activeFilters).every(function (key) {
      var filterValue = activeFilters[key];

      if (!filterValue) {
        return true;
      }

      if (key === 'date') {
        return row.dataset.date === filterValue;
      }

      if (key === 'turn') {
        return row.dataset.turn === filterValue;
      }

      if (key === 'document') {
        return normalizeDocument(row.dataset.document).indexOf(normalizeDocument(filterValue)) !== -1;
      }

      return normalize(row.dataset[key]).indexOf(filterValue) !== -1;
    });
  }

  function readFilters() {
    if (!filtersForm) {
      return;
    }

    activeFilters = {
      event: normalize(filtersForm.elements.event.value),
      date: filtersForm.elements.date.value,
      turn: filtersForm.elements.turn.value,
      email: normalize(filtersForm.elements.email.value),
      phone: normalize(filtersForm.elements.phone.value),
      team: normalize(filtersForm.elements.team.value),
      document: normalizeDocument(filtersForm.elements.document.value)
    };
  }

  function compareRows(first, second) {
    if (!sortState.key) {
      return 0;
    }

    var firstValue = sortState.key === 'created' ? first.dataset.created : first.dataset.date;
    var secondValue = sortState.key === 'created' ? second.dataset.created : second.dataset.date;
    var result = firstValue.localeCompare(secondValue);

    return sortState.direction === 'asc' ? result : -result;
  }

  function updateSortButtons() {
    sortButtons.forEach(function (button) {
      var isActive = button.getAttribute('data-sort-key') === sortState.key;

      button.classList.toggle('is-asc', isActive && sortState.direction === 'asc');
      button.classList.toggle('is-desc', isActive && sortState.direction === 'desc');
    });
  }

  function renderRows() {
    var tbody = table.querySelector('tbody');
    var visibleCount = 0;

    rows.slice().sort(compareRows).forEach(function (row) {
      var isVisible = rowMatches(row);

      row.style.display = isVisible ? '' : 'none';
      row.setAttribute('aria-hidden', isVisible ? 'false' : 'true');

      if (isVisible) {
        visibleCount++;
      }

      tbody.appendChild(row);
    });

    if (count) {
      count.textContent = visibleCount + (visibleCount === 1 ? ' registro' : ' registros');
    }
  }

  function visibleRows() {
    return rows.filter(function (row) {
      return row.style.display !== 'none';
    });
  }

  function exportRows() {
    return visibleRows().reduce(function (items, row) {
      var cells = row.querySelectorAll('td');
      var registrationId = row.dataset.registrationId;
      var attendeeRows = Array.prototype.slice.call(document.querySelectorAll('#registrationAttendees' + registrationId + ' tbody tr'));

      attendeeRows.forEach(function (attendeeRow) {
        var attendeeCells = attendeeRow.querySelectorAll('td');

        items.push({
          event: cells[0] ? cells[0].textContent.trim() : '',
          date: cells[1] ? cells[1].textContent.trim() : '',
          turn: cells[2] ? cells[2].textContent.trim() : '',
          email: cells[3] ? cells[3].textContent.trim() : '',
          phone: cells[4] ? cells[4].textContent.trim() : '',
          team: cells[5] ? cells[5].textContent.trim() : '',
          attendeeNumber: attendeeCells[0] ? attendeeCells[0].textContent.trim() : '',
          name: attendeeCells[1] ? attendeeCells[1].textContent.trim() : '',
          document: attendeeCells[2] ? attendeeCells[2].textContent.trim() : '',
          createdAt: cells[7] ? cells[7].textContent.trim() : ''
        });
      });

      return items;
    }, []);
  }

  function csvEscape(value) {
    return '"' + String(value || '').replace(/"/g, '""') + '"';
  }

  function downloadCsv() {
    var rowsToExport = exportRows();

    if (!rowsToExport.length) {
      alert('No hay asistentes para exportar.');
      return;
    }

    var headers = ['Evento', 'Fecha', 'Turno', 'Email', 'Telefono', 'Equipo', 'N. asistente', 'Nombre completo', 'Documento', 'Fecha registro'];
    var lines = [headers.map(csvEscape).join(';')];

    rowsToExport.forEach(function (row) {
      lines.push([
        row.event,
        row.date,
        row.turn,
        row.email,
        row.phone,
        row.team,
        row.attendeeNumber,
        row.name,
        row.document,
        row.createdAt
      ].map(csvEscape).join(';'));
    });

    var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');

    link.href = URL.createObjectURL(blob);
    link.download = 'asistentes_registros.csv';
    document.body.appendChild(link);
    link.click();
    URL.revokeObjectURL(link.href);
    link.remove();
  }

  function htmlEscape(value) {
    return String(value || '').replace(/[&<>"']/g, function (character) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[character];
    });
  }

  function exportPdf() {
    var rowsToExport = exportRows();

    if (!rowsToExport.length) {
      alert('No hay asistentes para exportar.');
      return;
    }

    var tableRows = rowsToExport.map(function (row) {
      return [
        '<tr>',
          '<td>' + htmlEscape(row.event) + '</td>',
          '<td>' + htmlEscape(row.date) + '</td>',
          '<td>' + htmlEscape(row.turn) + '</td>',
          '<td>' + htmlEscape(row.email) + '</td>',
          '<td>' + htmlEscape(row.phone) + '</td>',
          '<td>' + htmlEscape(row.team) + '</td>',
          '<td>' + htmlEscape(row.attendeeNumber) + '</td>',
          '<td>' + htmlEscape(row.name) + '</td>',
          '<td>' + htmlEscape(row.document) + '</td>',
          '<td>' + htmlEscape(row.createdAt) + '</td>',
        '</tr>'
      ].join('');
    }).join('');
    var printWindow = window.open('', '_blank');

    if (!printWindow) {
      alert('El navegador ha bloqueado la ventana de exportacion.');
      return;
    }

    printWindow.document.write([
      '<!DOCTYPE html>',
      '<html lang="es">',
      '<head>',
        '<meta charset="utf-8">',
        '<title>Asistentes registros</title>',
        '<style>',
          'body{font-family:Arial,sans-serif;color:#151515;margin:24px;}',
          'h1{font-size:22px;margin:0 0 18px;}',
          'table{border-collapse:collapse;width:100%;font-size:11px;}',
          'th,td{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top;}',
          'th{background:#f1f1f1;text-transform:uppercase;}',
          '@media print{@page{size:landscape;margin:10mm;}body{margin:0;}}',
        '</style>',
      '</head>',
      '<body>',
        '<h1>Asistentes registros</h1>',
        '<table>',
          '<thead><tr><th>Evento</th><th>Fecha</th><th>Turno</th><th>Email</th><th>Telefono</th><th>Equipo</th><th>N. asistente</th><th>Nombre completo</th><th>Documento</th><th>Fecha registro</th></tr></thead>',
          '<tbody>' + tableRows + '</tbody>',
        '</table>',
        '<script>window.onload=function(){window.print();};<\/script>',
      '</body>',
      '</html>'
    ].join(''));
    printWindow.document.close();
  }

  function closeExportMenu() {
    if (!exportOptions || !exportToggle) {
      return;
    }

    exportOptions.hidden = true;
    exportToggle.setAttribute('aria-expanded', 'false');
  }

  function closeModals() {
    document.querySelectorAll('.admin-gallery-modal.is-open').forEach(function (modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    });
    document.body.classList.remove('admin-gallery-modal-open');
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initAdminRegistrosPage);
}
