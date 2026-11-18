window.initAdminPartidasPage = function () {
  var events = Array.isArray(window.BurnoutAdminEvents) ? window.BurnoutAdminEvents : [];
  var root = document.querySelector('.admin-page');

  var today = new Date();
  var currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
  var monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  var grid = document.getElementById('calendarGrid');
  var title = document.getElementById('calendarTitle');
  var previous = document.getElementById('prevMonth');
  var next = document.getElementById('nextMonth');
  var lateRegistrationForm = document.getElementById('lateRegistrationForm');
  var lateRegistrationAttendees = document.getElementById('lateRegistrationAttendees');
  var lateRegistrationFields = document.getElementById('lateRegistrationAttendeeFields');
  var currentEditEvent = null;
  var modalReturnFocus = null;
  var sendingAnnouncement = false;
  var announcementRun = null;
  var announcementForm = document.getElementById('announcementForm');

  if (!root || !grid || !title || !previous || !next) {
    return;
  }

  if (root.getAttribute('data-admin-partidas-ready') === 'true') {
    return;
  }

  root.setAttribute('data-admin-partidas-ready', 'true');

  previous.addEventListener('click', function () {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
  });

  next.addEventListener('click', function () {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
  });

  document.querySelectorAll('[data-partidas-modal-open]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = button.getAttribute('data-partidas-modal-open');
      openModal(target === 'delete' ? 'deleteEventModal' : target === 'announcement' ? 'announcementEventModal' : 'createEventModal');
    });
  });

  document.querySelectorAll('[data-partidas-modal-close]').forEach(function (button) {
    button.addEventListener('click', closeModals);
  });

  document.querySelectorAll('[data-open-late-registration]').forEach(function (button) {
    button.addEventListener('click', function () {
      openLateRegistrationModal(currentEditEvent);
    });
  });

  if (lateRegistrationAttendees) {
    lateRegistrationAttendees.addEventListener('change', renderLateRegistrationAttendees);
  }

  if (lateRegistrationForm) {
    lateRegistrationForm.addEventListener('input', function (event) {
      if (event.target && event.target.matches('input, select')) {
        validateLateRegistrationField(event.target);
        if (/^lateRegistrationAttendeeDocument\d+$/.test(event.target.id)) {
          lateRegistrationForm.querySelectorAll('input[name="attendee_document[]"]').forEach(validateLateRegistrationField);
        }
      }
    });

    lateRegistrationForm.addEventListener('change', function (event) {
      if (event.target && event.target.matches('input, select')) {
        validateLateRegistrationField(event.target);
        if (/^lateRegistrationAttendeeDocument\d+$/.test(event.target.id)) {
          lateRegistrationForm.querySelectorAll('input[name="attendee_document[]"]').forEach(validateLateRegistrationField);
        }
      }
    });

    lateRegistrationForm.addEventListener('reset', function () {
      setTimeout(function () {
        if (lateRegistrationFields) {
          lateRegistrationFields.innerHTML = '';
        }

        clearLateRegistrationErrors();
      }, 0);
    });

    lateRegistrationForm.addEventListener('submit', function (event) {
      if (!validateLateRegistrationForm()) {
        event.preventDefault();
      }
    });
  }

  root.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModals();
    }
    if (event.key === 'Tab') {
      var modal = root.querySelector('.admin-gallery-modal.is-open');
      if (!modal) { return; }
      var focusable = Array.prototype.filter.call(modal.querySelectorAll('button, input, select, textarea, a[href]'), function (item) {
        return !item.disabled && item.getClientRects().length > 0;
      });
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first.focus();
      }
    }
  });

  root.querySelectorAll('[data-event-paged-list]').forEach(function (list) {
    var rows = Array.prototype.slice.call(list.children);
    var nav = list.nextElementSibling;
    var pages = nav.querySelector('.admin-registration-pages');
    var totalPages = Math.ceil(rows.length / 5);
    function render(page) {
      rows.forEach(function (row, index) { row.hidden = index < (page - 1) * 5 || index >= page * 5; });
      nav.hidden = totalPages <= 1;
      pages.innerHTML = '';
      for (var number = 1; number <= totalPages; number++) {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = number;
        button.setAttribute('aria-label', 'Página ' + number);
        if (number === page) {
          button.className = 'is-active'; button.setAttribute('aria-current', 'page');
        }
        button.addEventListener('click', function () { render(Number(this.textContent));
          pages.querySelector('[aria-current="page"]').focus(); });
        pages.appendChild(button);
      }
    }
    render(1);
  });

  root.querySelectorAll('[data-event-announce]').forEach(function (button) {
    button.addEventListener('click', async function () {
      try {
        var status = await announcementRequest({ action: 'status' });
        announcementRun = status.run && !status.run.done ? status.run : null;
        if (announcementRun) {
          showPendingAnnouncement();
          return;
        }
        announcementForm.reset();
        setValue('announcementEventId', button.getAttribute('data-event-announce'));
        setText('announcementEventSummary', button.getAttribute('data-event-summary'));
        setText('announcementProgress', '');
        setText('announcementSend', 'Enviar comunicado');
        announcementForm.querySelectorAll('input, textarea').forEach(function (field) { field.disabled = false; });
        document.getElementById('announcementSend').disabled = false;
        closeModals();
        openModal('announcementComposeModal');
      } catch (error) {
        setText('announcementProgress', error.message);
        closeModals(); openModal('announcementComposeModal');
      }
    });
  });

  function showPendingAnnouncement() {
    setText('announcementEventSummary', 'Comunicado pendiente: ' + announcementRun.subject);
    setValue('announcementSubject', announcementRun.subject);
    setValue('announcementBody', announcementRun.body);
    announcementForm.querySelectorAll('input:not([type="hidden"]), textarea').forEach(function (field) { field.disabled = true; });
    setText('announcementProgress', 'Enviados: ' + announcementRun.sent + '. Fallidos: ' + announcementRun.failed + '. Total: ' + announcementRun.total + '. Pulsa Continuar para terminar el envío pendiente.');
    setText('announcementSend', 'Continuar envío');
    document.getElementById('announcementSend').disabled = false;
    closeModals(); openModal('announcementComposeModal');
  }

  async function announcementRequest(values) {
    var params = new URLSearchParams(values);
    params.set('csrf_token', announcementForm.querySelector('[name="csrf_token"]').value);
    var response = await fetch('admin_event_announcement.php', { method: 'POST', credentials: 'same-origin', body: params });
    var result = await response.json();
    if (!response.ok || !result.ok) { throw new Error(result.message || 'No se ha podido enviar. Vuelve a pulsar para consultar el estado y continuar.'); }
    return result;
  }

  if (announcementForm) {
    announcementForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (sendingAnnouncement) { return; }
      if (announcementRun && announcementRun.done) {
        closeModals();
        return;
      }
      sendingAnnouncement = true;
      var submit = document.getElementById('announcementSend');
      submit.disabled = true;
      try {
        var status = await announcementRequest({ action: 'status' });
        announcementRun = status.run && (!status.run.done || (announcementRun && announcementRun.token === status.run.token)) ? status.run : null;
        if (!announcementRun) {
          var data = new FormData(announcementForm);
          announcementRun = (await announcementRequest({ action: 'start', event_id: data.get('event_id'), subject: data.get('subject'), body: data.get('body') })).run;
        }
        announcementForm.querySelectorAll('input:not([type="hidden"]), textarea').forEach(function (field) { field.disabled = true; });
        while (!announcementRun.done) {
          setText('announcementProgress', 'Procesando ' + (announcementRun.processed + 1) + ' de ' + announcementRun.total + '… No cierres esta página.');
          announcementRun = (await announcementRequest({ action: 'next', token: announcementRun.token, index: announcementRun.processed })).run;
        }
        setText('announcementProgress', 'Envío terminado. Enviados: ' + announcementRun.sent + '. Fallidos: ' + announcementRun.failed + '. Total: ' + announcementRun.total + '.');
        setText('announcementSend', 'Envío terminado');
        submit.disabled = false;
      } catch (error) {
        setText('announcementProgress', error.message || 'Se ha interrumpido la conexión. Pulsa Continuar para consultar el estado y seguir.');
        setText('announcementSend', 'Continuar envío');
        submit.disabled = false;
      } finally {
        sendingAnnouncement = false;
      }
    });
  }

  renderCalendar();
  renderAvailableEvents();

  function renderCalendar() {
    var year = currentDate.getFullYear();
    var month = currentDate.getMonth();
    var firstDay = new Date(year, month, 1);
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var leadingEmptyDays = (firstDay.getDay() + 6) % 7;

    title.textContent = monthNames[month] + ' ' + year;
    grid.innerHTML = '';

    for (var emptyIndex = 0; emptyIndex < leadingEmptyDays; emptyIndex++) {
      var emptyCell = document.createElement('div');
      emptyCell.className = 'partidas-day is-empty';
      grid.appendChild(emptyCell);
    }

    for (var day = 1; day <= daysInMonth; day++) {
      grid.appendChild(createDayCell(year, month, day));
    }
  }

  function createDayCell(year, month, day) {
    var dateKey = formatDate(year, month, day);
    var dayEvents = getEventsByDate(dateKey);
    var cell = document.createElement('div');
    var number = document.createElement('span');

    cell.className = dayEvents.length ? 'partidas-day has-event' : 'partidas-day';

    if (isToday(year, month, day)) {
      cell.className += ' is-today';
    }

    number.className = 'partidas-day-number';
    number.textContent = day;
    cell.appendChild(number);

    if (dayEvents.length) {
      var stack = document.createElement('div');
      stack.className = 'partidas-event-stack';

      dayEvents.forEach(function (event) {
        var eventButton = document.createElement('button');
        var eventTitle = document.createElement('span');
        eventButton.className = 'partidas-event-slice is-' + normalizeTime(event.time);
        eventButton.type = 'button';
        eventTitle.textContent = event.title;
        eventButton.appendChild(eventTitle);
        if (dayEvents.length === 1) {
          var eventCapacity = document.createElement('small');
          eventCapacity.textContent = formatCapacity(event);
          eventButton.appendChild(eventCapacity);
        }
        eventButton.addEventListener('click', function () {
          openEditModal(event);
        });
        stack.appendChild(eventButton);
      });

      cell.appendChild(stack);
    }

    return cell;
  }

  function renderAvailableEvents() {
    var list = document.getElementById('adminAvailableEvents');

    if (!list) {
      return;
    }

    var now = new Date();
    var todayKey = formatDate(now.getFullYear(), now.getMonth(), now.getDate());
    var upcomingEvents = events.filter(function (event) {
      return event.date >= todayKey;
    }).sort(compareEvents);

    list.innerHTML = '';

    if (!upcomingEvents.length) {
      var empty = document.createElement('p');
      empty.className = 'partidas-empty';
      empty.textContent = 'No hay partidas programadas';
      list.appendChild(empty);
      return;
    }

    upcomingEvents.forEach(function (event) {
      var card = document.createElement('button');
      var heading = document.createElement('strong');
      var date = document.createElement('span');
      var capacity = document.createElement('span');
      var parts = event.date.split('-');
      var eventDate = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));

      card.className = 'partidas-event-card';
      card.type = 'button';
      heading.textContent = event.title;
      date.textContent = eventDate.toLocaleDateString('es-ES', {
        day: 'numeric', month: 'long', year: 'numeric'
      }) + ' - ' + event.time;
      capacity.className = 'partidas-event-capacity';
      capacity.textContent = 'Participantes: ' + formatCapacity(event);

      if (Number(event.attendeeCount) >= Number(event.maxAttendees)) {
        capacity.classList.add('is-full');
      }

      card.appendChild(heading);
      card.appendChild(date);
      card.appendChild(capacity);
      card.addEventListener('click', function () {
        openEditModal(event);
      });
      list.appendChild(card);
    });
  }

  function getEventsByDate(dateKey) {
    return events.filter(function (event) {
      return event.date === dateKey;
    }).sort(compareEvents);
  }

  function compareEvents(first, second) {
    if (first.date !== second.date) {
      return String(first.date || '').localeCompare(String(second.date || ''));
    }

    return timeOrder(first.time) - timeOrder(second.time);
  }

  function normalizeTime(time) {
    var value = String(time || '').toLowerCase();

    if (value === 'm' || value === 'mañana' || value === 'manana') {
      return 'manana';
    }

    if (value === 't') {
      return 'tarde';
    }

    if (value === 'n') {
      return 'noche';
    }

    return value;
  }

  function timeOrder(time) {
    return {
      manana: 1,
      tarde: 2,
      noche: 3
    }[normalizeTime(time)] || 99;
  }

  function formatDate(year, month, day) {
    return [year, String(month + 1).padStart(2, '0'), String(day).padStart(2, '0')].join('-');
  }

  function isToday(year, month, day) {
    var now = new Date();

    return now.getFullYear() === year && now.getMonth() === month && now.getDate() === day;
  }

  function openModal(id) {
    var modal = document.getElementById(id);

    if (!modal) {
      return;
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('admin-gallery-modal-open');
    modalReturnFocus = document.activeElement;
    var first = modal.querySelector('button, input:not([type="hidden"]), textarea');
    if (first) { first.focus(); }
  }

  function openEditModal(event) {
    currentEditEvent = event;

    var id = document.getElementById('editEventId');
    var date = document.getElementById('editDate');
    var title = document.getElementById('editTitle');
    var maxAttendees = document.getElementById('editMaxAttendees');
    var time = normalizeTime(event.timeSlot || event.time);

    if (id) {
      id.value = event.id || '';
    }

    if (date) {
      date.value = event.date || '';
    }

    if (title) {
      title.value = event.title || '';
    }

    if (maxAttendees) {
      maxAttendees.value = event.maxAttendees || 40;
    }

    document.querySelectorAll('#editEventModal input[name="time"]').forEach(function (input) {
      input.checked = normalizeTime(input.value) === time;
    });

    if (!document.querySelector('#editEventModal input[name="time"]:checked')) {
      var defaultTime = document.querySelector('#editEventModal input[name="time"][value="M"]');

      if (defaultTime) {
        defaultTime.checked = true;
      }
    }

    openModal('editEventModal');
  }

  function openLateRegistrationModal(event) {
    if (!event || !event.id) {
      return;
    }

    setLateRegistrationEvent(event);

    if (lateRegistrationForm) {
      lateRegistrationForm.reset();
      setLateRegistrationEvent(event);
    }

    if (lateRegistrationFields) {
      lateRegistrationFields.innerHTML = '';
    }

    clearLateRegistrationErrors();
    closeModals();
    openModal('lateRegistrationModal');

    var firstInput = document.getElementById('lateRegistrationEmail');

    if (firstInput) {
      firstInput.focus();
    }
  }

  function setLateRegistrationEvent(event) {
    setValue('lateRegistrationEventId', event.id || '');
    setText('lateRegistrationEventTitle', event.title || 'Sin titulo');
    setText('lateRegistrationEventDate', event.date || '-');
    setText('lateRegistrationEventTime', event.time || event.timeSlot || '-');
  }

  function renderLateRegistrationAttendees() {
    if (!lateRegistrationAttendees || !lateRegistrationFields) {
      return;
    }

    var total = parseInt(lateRegistrationAttendees.value, 10);
    var existingValues = {};

    lateRegistrationFields.querySelectorAll('input, select').forEach(function (input) {
      existingValues[input.id] = input.value;
    });

    lateRegistrationFields.innerHTML = '';

    if (isNaN(total) || total < 1) {
      return;
    }

    for (var index = 1; index <= total; index++) {
      var nameId = 'lateRegistrationAttendeeName' + index;
      var documentId = 'lateRegistrationAttendeeDocument' + index;
      var field = document.createElement('div');
      var heading = document.createElement('h3');
      var nameField = document.createElement('div');
      var nameLabel = document.createElement('label');
      var nameInput = document.createElement('input');
      var nameError = document.createElement('span');
      var documentField = document.createElement('div');
      var documentLabel = document.createElement('label');
      var documentInput = document.createElement('input');
      var documentError = document.createElement('span');

      field.className = 'admin-registration-attendee-field';
      heading.textContent = 'Asistente ' + index;

      nameField.className = 'admin-login-field';
      nameLabel.setAttribute('for', nameId);
      nameLabel.textContent = 'Nombre completo *';
      nameInput.id = nameId;
      nameInput.name = 'attendee_name[]';
      nameInput.type = 'text';
      nameInput.autocomplete = 'name';
      nameInput.required = true;
      nameInput.placeholder = 'Nombre y apellidos';
      nameInput.value = existingValues[nameId] || '';
      nameError.className = 'admin-registration-error';
      nameError.setAttribute('data-error-for', nameId);

      documentField.className = 'admin-login-field';
      documentLabel.setAttribute('for', documentId);
      documentLabel.textContent = 'DNI/NIE/Pasaporte *';
      documentInput.id = documentId;
      documentInput.name = 'attendee_document[]';
      documentInput.type = 'text';
      documentInput.autocomplete = 'off';
      documentInput.required = true;
      documentInput.placeholder = 'DNI, NIE o pasaporte';
      documentInput.value = existingValues[documentId] || '';
      documentError.className = 'admin-registration-error';
      documentError.setAttribute('data-error-for', documentId);

      nameField.appendChild(nameLabel);
      nameField.appendChild(nameInput);
      nameField.appendChild(nameError);
      documentField.appendChild(documentLabel);
      documentField.appendChild(documentInput);
      documentField.appendChild(documentError);
      field.appendChild(heading);
      field.appendChild(nameField);
      field.appendChild(documentField);
      var roleId = 'lateRegistrationAttendeeRole' + index;
      var roleField = document.createElement('div');
      roleField.className = 'admin-login-field';
      var roleLabel = document.createElement('label');
      roleLabel.htmlFor = roleId;
      roleLabel.textContent = 'Rol de arma *';
      var roleSelect = document.createElement('select');
      roleSelect.id = roleId;
      roleSelect.name = 'attendee_role[]';
      roleSelect.required = true;
      ['', 'Fusilero', 'Tirador Selecto', 'Apoyo', 'Francotirador', 'Escudo'].forEach(function (role) {
        var option = document.createElement('option');
        option.value = role;
        option.textContent = role || 'Selecciona el rol de arma';
        roleSelect.appendChild(option);
      });
      roleSelect.value = existingValues[roleId] || '';
      var roleError = document.createElement('span');
      roleError.className = 'admin-registration-error';
      roleError.setAttribute('data-error-for', roleId);
      roleField.appendChild(roleLabel);
      roleField.appendChild(roleSelect);
      roleField.appendChild(roleError);
      field.appendChild(roleField);
      lateRegistrationFields.appendChild(field);
    }
  }

  function validateLateRegistrationForm() {
    var valid = true;

    if (!lateRegistrationForm) {
      return true;
    }

    lateRegistrationForm.querySelectorAll('input[required], select[required]').forEach(function (field) {
      if (!validateLateRegistrationField(field)) {
        valid = false;
      }
    });

    return valid;
  }

  function validateLateRegistrationField(field) {
    var id = field.id;
    var value = String(field.value || '').trim();
    var message = '';

    if (field.required && !value) {
      message = 'Este campo es obligatorio.';
    }

    if (!message && id === 'lateRegistrationEmail' && !isValidEmail(value)) {
      message = 'Introduce una direccion electronica valida.';
    }

    if (!message && id === 'lateRegistrationPhone' && !isValidPhone(value)) {
      message = 'Introduce un telefono valido con al menos 9 numeros.';
    }

    if (!message && /^lateRegistrationAttendeeName\d+$/.test(id) && value.length < 3) {
      message = 'Introduce el nombre completo.';
    }

    if (!message && /^lateRegistrationAttendeeDocument\d+$/.test(id) && !isValidDocument(value)) {
      message = 'Introduce un DNI, NIE o pasaporte valido.';
    }

    if (!message && /^lateRegistrationAttendeeDocument\d+$/.test(id)) {
      lateRegistrationForm.querySelectorAll('input[name="attendee_document[]"]').forEach(function (other) {
        if (other !== field && normalizeDocument(other.value) === normalizeDocument(value)) {
          message = 'Este documento está repetido entre los asistentes.';
        }
      });
    }
    setLateRegistrationFieldError(field, message);

    return !message;
  }

  function setLateRegistrationFieldError(field, message) {
    var wrapper = field.closest('.admin-login-field');
    var error = document.querySelector('[data-error-for="' + field.id + '"]');

    if (wrapper) {
      wrapper.classList.toggle('is-invalid', Boolean(message));
    }

    if (error) {
      error.textContent = message;
    }
  }

  function clearLateRegistrationErrors() {
    if (!lateRegistrationForm) {
      return;
    }

    lateRegistrationForm.querySelectorAll('.admin-login-field').forEach(function (field) {
      field.classList.remove('is-invalid');
    });

    lateRegistrationForm.querySelectorAll('.admin-registration-error').forEach(function (error) {
      error.textContent = '';
    });
  }

  function setValue(id, value) {
    var element = document.getElementById(id);

    if (element) {
      element.value = value;
    }
  }

  function setText(id, value) {
    var element = document.getElementById(id);

    if (element) {
      element.textContent = value;
    }
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
  }

  function isValidPhone(value) {
    var digits = String(value || '').replace(/\D/g, '');

    return /^\+?[\d\s-]+$/.test(value) && digits.length >= 9;
  }

  function hasValidDniLetter(number, letter) {
    var letters = 'TRWAGMYFPDXBNJZSQVHLCKE';

    return letters[Number(number) % 23] === String(letter || '').toUpperCase();
  }

  function normalizeDocument(value) {
    return String(value || '').toUpperCase().replace(/[ \t\n\r\v\f-]+/g, '');
  }
  function isValidDocument(value) {
    var normalized = normalizeDocument(value);
    var dni = normalized.match(/^(\d{8})([A-Z])$/);
    var nie = normalized.match(/^([XYZ])(\d{7})([A-Z])$/);

    if (dni) {
      return hasValidDniLetter(dni[1], dni[2]);
    }

    if (nie) {
      return hasValidDniLetter({ X: '0', Y: '1', Z: '2' }[nie[1]] + nie[2], nie[3]);
    }

    return /^(?:[A-Z]{3}\d{6}[A-Z]?|[A-Z]{2}\d{7})$/.test(normalized);
  }

  function closeModals() {
    if (sendingAnnouncement) { return; }
    document.querySelectorAll('.admin-gallery-modal.is-open').forEach(function (modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    });
    document.body.classList.remove('admin-gallery-modal-open');
    if (modalReturnFocus && modalReturnFocus.isConnected) { modalReturnFocus.focus(); }
  }

  function formatCapacity(event) {
    var current = parseInt(event.attendeeCount, 10);
    var max = parseInt(event.maxAttendees, 10);

    if (isNaN(current)) {
      current = 0;
    }

    if (isNaN(max) || max < 1) {
      max = 40;
    }

    return current + '/' + max;
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initAdminPartidasPage);
}
