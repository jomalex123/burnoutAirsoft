window.initRegistroPage = function () {
  var $form = $('#registroForm');
  var $terms = $('#normativaAceptada');
  var $submit = $('#enviarRegistro');
  var $modal = $('#normativaModal');
  var $firstInput = $('#email');
  var $asistentes = $('#asistentes');
  var $asistentesFields = $('#asistentesFields');
  var $acceptRules = $('#aceptarNormativa');
  var $rulesBody = $('#normativaModalBody');
  var rulesRead = false;

  if (!$form.length) {
    return;
  }

  renderEventData();
  lockRulesButton();
  loadRulesContent();
  bindRulesScroll();

  $form.off('input.registro change.registro').on('input.registro change.registro', 'input, select', function () {
    if ($(this).attr('id') === 'asistentes') {
      renderAsistentesFields();
    }

    validateField($(this));
    updateSubmitState();
  });

  $('#abrirNormativa').off('click.registro').on('click.registro', function () {
    openModal();
  });

  $('[data-close-modal]').off('click.registro').on('click.registro', function () {
    closeModal();
  });

  $acceptRules.off('click.registro').on('click.registro', function () {
    if (!rulesRead) {
      return;
    }

    $terms.prop('disabled', false).prop('checked', true);
    closeModal();
    updateSubmitState();
  });

  $(document).off('keydown.registro').on('keydown.registro', function (event) {
    if (event.key === 'Escape' && $modal.hasClass('is-open')) {
      closeModal();
    }
  });

  $form.off('reset.registro').on('reset.registro', function () {
    setTimeout(function () {
      $terms.prop('disabled', true).prop('checked', false);
      rulesRead = false;
      lockRulesButton();
      $rulesBody.scrollTop(0);
      bindRulesScroll();
      $asistentesFields.empty();
      clearErrors();
      updateSubmitState();
      $firstInput.trigger('focus');
    }, 0);
  });

  $form.off('submit.registro').on('submit.registro', function (event) {
    if (!validateForm()) {
      event.preventDefault();
      updateSubmitState();
      return;
    }
  });

  function openModal() {
    $modal.addClass('is-open').attr('aria-hidden', 'false');
    $('body').addClass('registro-modal-open');

    if (rulesRead) {
      $acceptRules.trigger('focus');
    } else {
      bindRulesScroll();
      $rulesBody.trigger('focus');
    }
  }

  function closeModal() {
    $modal.removeClass('is-open').attr('aria-hidden', 'true');
    $('body').removeClass('registro-modal-open');
    $('#abrirNormativa').trigger('focus');
  }

  function lockRulesButton() {
    if (rulesRead) {
      return;
    }

    $acceptRules
      .prop('disabled', true)
      .text('Aceptar normativa');
  }

  function unlockRulesButton() {
    rulesRead = true;
    $acceptRules
      .prop('disabled', false)
      .text('Aceptar normativa');
  }

  function loadRulesContent() {
    var source = $rulesBody.data('normativa-source');

    if (!$rulesBody.length || !source || $rulesBody.data('normativa-loaded')) {
      return;
    }

    fetch(source, { cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('No se ha podido cargar la normativa.');
        }

        return response.text();
      })
      .then(function (html) {
        var parser = new DOMParser();
        var page = parser.parseFromString(html, 'text/html');
        var content = page.querySelector('main.ms-container');

        $rulesBody
          .data('normativa-loaded', true)
          .html('<div class="registro-normativa-content">' + (content ? content.innerHTML : page.body.innerHTML) + '</div>');
        $rulesBody.scrollTop(0);
        bindRulesScroll();
      })
      .catch(function () {
        $rulesBody.html('<p class="registro-normativa-loading">No se ha podido cargar la normativa. Abre la normativa en una pestana nueva y vuelve a intentarlo.</p>');
      });
  }

  function bindRulesScroll() {
    if (!$rulesBody.length || rulesRead) {
      return;
    }

    $rulesBody.off('scroll.registro').on('scroll.registro', updateRulesReadState);
    updateRulesReadState();
  }

  function updateRulesReadState() {
    var scrollingElement = $rulesBody.get(0);
    var scrollTop = scrollingElement ? scrollingElement.scrollTop : 0;
    var visibleHeight = scrollingElement ? scrollingElement.clientHeight : 0;
    var scrollHeight = scrollingElement ? scrollingElement.scrollHeight : 0;

    if (!visibleHeight || !scrollHeight) {
      return;
    }

    if (scrollHeight <= visibleHeight || scrollTop + visibleHeight >= scrollHeight - 2) {
      unlockRulesButton();
    }
  }

  function renderEventData() {
    var params = new URLSearchParams(window.location.search);
    var title = params.get('titulo');
    var date = params.get('fecha');
    var turn = params.get('turno');
    var eventId = params.get('event_id') || params.get('id');

    if (eventId) {
      $('#eventId').val(eventId);
    }

    if (title) {
      $('#registroEventoTitulo').text('INSCRIPCION ' + title);
      document.title = 'Inscripcion - ' + title;
    }

    if (date) {
      $('#registroEventoFecha').text(formatEventDate(date));
    }

    if (turn) {
      $('#registroEventoTurno').text(normalizeTurn(turn).toUpperCase());
    }
  }

  function formatEventDate(value) {
    var parts = value.split('-');

    if (parts.length !== 3) {
      return value.toUpperCase();
    }

    var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));

    if (isNaN(date.getTime())) {
      return value.toUpperCase();
    }

    return date.toLocaleDateString('es-ES', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    }).toUpperCase();
  }

  function normalizeTurn(value) {
    var normalized = String(value || '').toLowerCase();

    if (normalized === 'mañana') {
      return 'Mañana';
    }

    return normalized;
  }

  function validateForm() {
    var isValid = true;

    $form.find('input[required], select[required]').each(function () {
      if (!validateField($(this))) {
        isValid = false;
      }
    });

    return isValid && $terms.is(':checked');
  }

  function validateField($field) {
    var id = $field.attr('id');
    var value = $.trim($field.val());
    var message = '';

    if ($field.prop('disabled')) {
      return true;
    }

    if ($field.prop('required') && !value && $field.attr('type') !== 'checkbox') {
      message = 'Este campo es obligatorio.';
    }

    if (!message && id === 'email' && !isValidEmail(value)) {
      message = 'Introduce una direccion electronica valida.';
    }

    if (!message && id === 'telefono' && !isValidPhone(value)) {
      message = 'Introduce un telefono valido.';
    }

    if (!message && isAttendeeNameField(id) && value.length < 3) {
      message = 'Introduce el nombre completo.';
    }

    if (!message && isAttendeeDocumentField(id) && value.length < 5) {
      message = 'Introduce un DNI, NIE o pasaporte valido.';
    }

    if (!message && id === 'asistentes' && !value) {
      message = 'Selecciona el numero de asistentes.';
    }

    setFieldError($field, message);
    return !message;
  }

  function renderAsistentesFields() {
    var total = parseInt($asistentes.val(), 10);
    var existingValues = {};

    $asistentesFields.find('input').each(function () {
      existingValues[this.id] = this.value;
    });

    $asistentesFields.empty();

    if (isNaN(total) || total < 1) {
      return;
    }

    for (var index = 1; index <= total; index++) {
      var nameId = 'asistenteNombre' + index;
      var documentId = 'asistenteDocumento' + index;
      var $field = $('<div class="registro-asistente-field"></div>');
      var $title = $('<h3></h3>').text('Asistente ' + index);
      var $nameField = $('<div class="registro-field"></div>');
      var $nameLabel = $('<label></label>').attr('for', nameId).text('Nombre completo *');
      var $nameInput = $('<input>')
        .attr({
          id: nameId,
          name: 'attendee_name[]',
          type: 'text',
          autocomplete: 'name',
          required: true,
          placeholder: 'Nombre y apellidos'
        })
        .val(existingValues[nameId] || '');
      var $nameError = $('<span class="registro-error"></span>').attr('data-error-for', nameId);
      var $documentField = $('<div class="registro-field"></div>');
      var $documentLabel = $('<label></label>').attr('for', documentId).text('DNI/NIE/Pasaporte *');
      var $documentInput = $('<input>')
        .attr({
          id: documentId,
          name: 'attendee_document[]',
          type: 'text',
          autocomplete: 'off',
          required: true,
          placeholder: 'DNI, NIE o pasaporte'
        })
        .val(existingValues[documentId] || '');
      var $documentError = $('<span class="registro-error"></span>').attr('data-error-for', documentId);

      $nameField.append($nameLabel, $nameInput, $nameError);
      $documentField.append($documentLabel, $documentInput, $documentError);
      $field.append($title, $nameField, $documentField);
      $asistentesFields.append($field);
    }
  }

  function updateSubmitState() {
    $submit.prop('disabled', !$terms.is(':checked'));
  }

  function setFieldError($field, message) {
    var id = $field.attr('id');
    var $wrapper = $field.closest('.registro-field');
    var $error = $('[data-error-for="' + id + '"]');

    $wrapper.toggleClass('is-invalid', Boolean(message));
    $error.text(message);
  }

  function clearErrors() {
    $('.registro-field').removeClass('is-invalid');
    $('.registro-error').text('');
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
  }

  function isValidPhone(value) {
    return /^(\+?\d[\d\s-]{7,18})$/.test(value);
  }

  function isAttendeeNameField(id) {
    return /^asistenteNombre\d+$/.test(id);
  }

  function isAttendeeDocumentField(id) {
    return /^asistenteDocumento\d+$/.test(id);
  }
};

if (!window.__burnoutLoadingPageScript) {
  $(document).ready(window.initRegistroPage);
}
