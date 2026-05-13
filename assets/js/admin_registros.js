window.initAdminRegistrosPage = function () {
  var table = document.getElementById('registrationsTable');
  var filtersForm = document.getElementById('registrationFiltersForm');
  var count = document.getElementById('registrationCount');
  var exportToggle = document.getElementById('registrationExportToggle');
  var exportOptions = document.getElementById('registrationExportOptions');
  var exportCsvButton = document.getElementById('exportRegistrationsCsv');
  var exportPdfButton = document.getElementById('exportRegistrationsPdf');
  var deleteConfirmModal = document.getElementById('registrationDeleteConfirmModal');
  var deleteConfirmButton = document.getElementById('confirmRegistrationDelete');
  var sortButtons = Array.prototype.slice.call(document.querySelectorAll('[data-sort-key]'));
  var rows = table ? Array.prototype.slice.call(table.querySelectorAll('tbody tr')) : [];
  var pendingDeleteForm = null;
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

  document.querySelectorAll('[data-registration-delete-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      pendingDeleteForm = form;
      openDeleteConfirmModal();
    });
  });

  document.querySelectorAll('[data-registration-delete-cancel]').forEach(function (button) {
    button.addEventListener('click', function () {
      pendingDeleteForm = null;
      closeModals();
    });
  });

  if (deleteConfirmButton) {
    deleteConfirmButton.addEventListener('click', function () {
      if (!pendingDeleteForm) {
        closeModals();
        return;
      }

      pendingDeleteForm.submit();
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      pendingDeleteForm = null;
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

    var firstValue = sortValue(first, sortState.key);
    var secondValue = sortValue(second, sortState.key);
    var result = firstValue.localeCompare(secondValue);

    return sortState.direction === 'asc' ? result : -result;
  }

  function sortValue(row, key) {
    if (key === 'created') {
      return row.dataset.created || '';
    }

    if (key === 'date') {
      return row.dataset.date || '';
    }

    if (key === 'turn') {
      return {
        M: '1',
        T: '2',
        N: '3'
      }[row.dataset.turn] || row.dataset.turn || '';
    }

    return normalize(row.dataset[key]);
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

    var headers = ['Nombre completo', 'Telefono', 'Documento', 'Equipo', 'Firma'];
    var lines = [headers.map(csvEscape).join(';')];

    rowsToExport.forEach(function (row) {
      lines.push([
        row.name,
        row.phone,
        row.document,
        row.team,
        ''
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

  async function exportPdf() {
    var rowsToExport = exportRows();

    if (!rowsToExport.length) {
      alert('No hay asistentes para exportar.');
      return;
    }

    if (!window.PDFLib) {
      alert('No se ha podido cargar la libreria para generar el PDF.');
      return;
    }

    setPdfBusy(true);

    try {
      await buildMergedRegistrationsPdf(rowsToExport);
    } catch (error) {
      console.error(error);
      alert(error.message || 'No se ha podido generar el PDF.');
    } finally {
      setPdfBusy(false);
    }

  }

  async function buildMergedRegistrationsPdf(rowsToExport) {
    var PDFDocument = window.PDFLib.PDFDocument;
    var StandardFonts = window.PDFLib.StandardFonts;
    var rgb = window.PDFLib.rgb;
    var templateResponse = await fetch('docs/Registro_Burnout-Airsoft_v01.pdf', { cache: 'no-store' });

    if (!templateResponse.ok) {
      throw new Error('No se ha podido cargar el PDF base.');
    }

    var logoResponse = await fetch('images/resources/logo-barcelona-bout.jpg', { cache: 'no-store' });

    if (!logoResponse.ok) {
      throw new Error('No se ha podido cargar el logo de Barcelona Paintball.');
    }

    var pdfDoc = await PDFDocument.load(await templateResponse.arrayBuffer());
    var regularFont = await pdfDoc.embedFont(StandardFonts.Helvetica);
    var boldFont = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
    var logoImage = await pdfDoc.embedJpg(await logoResponse.arrayBuffer());
    var columns = pdfColumns();
    var layout = {
      width: 595.28,
      height: 841.89,
      margin: 36,
      tableTop: 748,
      headerHeight: 20,
      minRowHeight: 32,
      paddingX: 5,
      paddingY: 7,
      fontSize: 7.2,
      headerFontSize: 7.5,
      lineHeight: 9.2,
      borderColor: rgb(0.95, 0, 0),
      borderWidth: 1,
      backgroundColor: rgb(0.847, 0.847, 0.847),
      headerFill: rgb(0.847, 0.847, 0.847),
      textColor: rgb(0.08, 0.08, 0.08)
    };
    var tablePageNumber = 0;
    var pageState = addTablePage();

    rowsToExport.forEach(function (row) {
      var preparedRow = preparePdfRow(row, columns, regularFont, layout);

      if (pageState.y - preparedRow.height < layout.margin) {
        pageState = addTablePage();
      }

      drawPdfRow(pageState.page, columns, preparedRow, regularFont, layout, pageState.y);
      pageState.y -= preparedRow.height;
    });

    downloadPdf(await pdfDoc.save());

    function addTablePage() {
      tablePageNumber++;
      return createPdfTablePage(pdfDoc, columns, boldFont, layout, logoImage, tablePageNumber);
    }
  }

  function pdfColumns() {
    return [
      { key: 'name', label: 'NOMBRE', width: 185 },
      { key: 'phone', label: 'TELEFONO', width: 85 },
      { key: 'document', label: 'DOCUMENTO', width: 95 },
      { key: 'team', label: 'EQUIPO', width: 85 },
      { key: 'signature', label: 'FIRMA', width: 73 }
    ];
  }

  function createPdfTablePage(pdfDoc, columns, boldFont, layout, logoImage, tablePageNumber) {
    var page = pdfDoc.addPage([layout.width, layout.height]);
    var logoWidth = 156;
    var logoHeight = logoWidth * (logoImage.height / logoImage.width);

    page.drawRectangle({
      x: 0,
      y: 0,
      width: layout.width,
      height: layout.height,
      color: layout.backgroundColor
    });

    page.drawImage(logoImage, {
      x: layout.width - layout.margin - logoWidth,
      y: layout.height - 30 - logoHeight,
      width: logoWidth,
      height: logoHeight
    });

    drawPdfHeader(page, columns, boldFont, layout);

    return {
      page: page,
      y: layout.tableTop - layout.headerHeight,
      tablePageNumber: tablePageNumber
    };
  }

  function drawPdfHeader(page, columns, font, layout) {
    var x = layout.margin;

    columns.forEach(function (column) {
      page.drawRectangle({
        x: x,
        y: layout.tableTop - layout.headerHeight,
        width: column.width,
        height: layout.headerHeight,
        color: layout.headerFill,
        borderColor: layout.borderColor,
        borderWidth: layout.borderWidth
      });
      drawCenteredPdfText(page, safePdfText(column.label), {
        x: x,
        y: layout.tableTop - layout.paddingY - layout.headerFontSize,
        width: column.width,
        size: layout.headerFontSize,
        font: font,
        color: layout.textColor
      });
      x += column.width;
    });
  }

  function preparePdfRow(row, columns, font, layout) {
    var maxLines = 1;
    var cells = columns.map(function (column) {
      var value = row[column.key] || '';

      if (column.key === 'team' && !value) {
        value = '-';
      }

      var lines = column.key === 'signature'
        ? ['']
        : wrapPdfText(value, font, layout.fontSize, column.width - layout.paddingX * 2);
      maxLines = Math.max(maxLines, lines.length);

      return lines;
    });

    return {
      cells: cells,
      height: Math.max(layout.minRowHeight, maxLines * layout.lineHeight + layout.paddingY * 2)
    };
  }

  function drawPdfRow(page, columns, preparedRow, font, layout, yTop) {
    var x = layout.margin;

    columns.forEach(function (column, index) {
      page.drawRectangle({
        x: x,
        y: yTop - preparedRow.height,
        width: column.width,
        height: preparedRow.height,
        borderColor: layout.borderColor,
        borderWidth: layout.borderWidth
      });
      preparedRow.cells[index].forEach(function (line, lineIndex) {
        if (!line) {
          return;
        }

        page.drawText(line, {
          x: x + layout.paddingX,
          y: yTop - layout.paddingY - layout.fontSize - lineIndex * layout.lineHeight,
          size: layout.fontSize,
          font: font,
          color: layout.textColor
        });
      });
      x += column.width;
    });
  }

  function drawCenteredPdfText(page, text, options) {
    var textWidth = options.font.widthOfTextAtSize(text, options.size);
    var x = options.x + Math.max(0, (options.width - textWidth) / 2);

    page.drawText(text, {
      x: x,
      y: options.y,
      size: options.size,
      font: options.font,
      color: options.color
    });
  }

  function wrapPdfText(value, font, fontSize, maxWidth) {
    var text = safePdfText(value || '-');
    var words = text.split(/\s+/).filter(Boolean);
    var lines = [];
    var current = '';

    if (!words.length) {
      return ['-'];
    }

    words.forEach(function (word) {
      var candidate = current ? current + ' ' + word : word;

      if (pdfTextWidth(font, candidate, fontSize) <= maxWidth) {
        current = candidate;
        return;
      }

      if (current) {
        lines.push(current);
      }

      if (pdfTextWidth(font, word, fontSize) <= maxWidth) {
        current = word;
        return;
      }

      var brokenWord = breakPdfWord(word, font, fontSize, maxWidth);
      lines = lines.concat(brokenWord.slice(0, -1));
      current = brokenWord[brokenWord.length - 1] || '';
    });

    if (current) {
      lines.push(current);
    }

    return lines.length ? lines : ['-'];
  }

  function breakPdfWord(word, font, fontSize, maxWidth) {
    var parts = [];
    var current = '';

    word.split('').forEach(function (character) {
      var candidate = current + character;

      if (current && pdfTextWidth(font, candidate, fontSize) > maxWidth) {
        parts.push(current);
        current = character;
        return;
      }

      current = candidate;
    });

    if (current) {
      parts.push(current);
    }

    return parts.length ? parts : ['-'];
  }

  function pdfTextWidth(font, text, fontSize) {
    return font.widthOfTextAtSize(safePdfText(text), fontSize);
  }

  function safePdfText(value) {
    return String(value || '')
      .replace(/[\r\n\t]+/g, ' ')
      .replace(/[\x00-\x1f\x7f-\x9f]/g, ' ')
      .replace(/\s+/g, ' ')
      .replace(/\u00a0/g, ' ')
      .replace(/[\u2018\u2019]/g, "'")
      .replace(/[\u201c\u201d]/g, '"')
      .replace(/[\u2013\u2014]/g, '-')
      .replace(/\u2026/g, '...')
      .replace(/[^\x20-\xff]/g, '?')
      .trim();
  }

  function downloadPdf(pdfBytes) {
    var blob = new Blob([pdfBytes], { type: 'application/pdf' });
    var link = document.createElement('a');

    link.href = URL.createObjectURL(blob);
    link.download = 'registro_burnout_airsoft_asistentes.pdf';
    document.body.appendChild(link);
    link.click();
    URL.revokeObjectURL(link.href);
    link.remove();
  }

  function setPdfBusy(isBusy) {
    if (!exportPdfButton) {
      return;
    }

    if (isBusy) {
      exportPdfButton.dataset.originalText = exportPdfButton.textContent;
      exportPdfButton.textContent = 'Generando PDF...';
      exportPdfButton.disabled = true;
      return;
    }

    exportPdfButton.textContent = exportPdfButton.dataset.originalText || 'Exportar PDF';
    exportPdfButton.disabled = false;
  }

  function closeExportMenu() {
    if (!exportOptions || !exportToggle) {
      return;
    }

    exportOptions.hidden = true;
    exportToggle.setAttribute('aria-expanded', 'false');
  }

  function openDeleteConfirmModal() {
    if (!deleteConfirmModal) {
      if (pendingDeleteForm) {
        pendingDeleteForm.submit();
      }
      return;
    }

    deleteConfirmModal.classList.add('is-open');
    deleteConfirmModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('admin-gallery-modal-open');
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
