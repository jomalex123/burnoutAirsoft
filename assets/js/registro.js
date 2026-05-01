window.initRegistroPage = function () {
  var $form = $('#registroForm');
  var $terms = $('#normativaAceptada');
  var $submit = $('#enviarRegistro');
  var $modal = $('#normativaModal');
  var $firstInput = $('#email');
  var $asistentes = $('#asistentes');
  var $asistentesFields = $('#asistentesFields');

  if (!$form.length) {
    return;
  }

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

  $('#aceptarNormativa').off('click.registro').on('click.registro', function () {
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
      $asistentesFields.empty();
      clearErrors();
      updateSubmitState();
      $firstInput.trigger('focus');
    }, 0);
  });

  $form.off('submit.registro').on('submit.registro', function (event) {
    event.preventDefault();

    if (!validateForm()) {
      updateSubmitState();
      return;
    }

    alert('Registro preparado. Mas adelante se conectara con la base de datos.');
  });

  function openModal() {
    $modal.addClass('is-open').attr('aria-hidden', 'false');
    $('body').addClass('registro-modal-open');
    $('#aceptarNormativa').trigger('focus');
  }

  function closeModal() {
    $modal.removeClass('is-open').attr('aria-hidden', 'true');
    $('body').removeClass('registro-modal-open');
    $('#abrirNormativa').trigger('focus');
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

    if (!message && isNombreDocumentoField(id) && value.length < 6) {
      message = 'Introduce el nombre completo y el documento.';
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
      existingValues[this.name] = this.value;
    });

    $asistentesFields.empty();

    if (isNaN(total) || total < 1) {
      return;
    }

    for (var index = 1; index <= total; index++) {
      var fieldId = 'nombreDocumento' + index;
      var $field = $('<div class="registro-field registro-asistente-field"></div>');
      var $label = $('<label></label>').attr('for', fieldId).text('Nombre completo y documento asistente ' + index + ' *');
      var $input = $('<input>')
        .attr({
          id: fieldId,
          name: fieldId,
          type: 'text',
          autocomplete: 'name',
          required: true,
          placeholder: 'Nombre Apellidos - DNI/NIE/Pasaporte'
        })
        .val(existingValues[fieldId] || '');
      var $error = $('<span class="registro-error"></span>').attr('data-error-for', fieldId);

      $field.append($label, $input, $error);
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

  function isNombreDocumentoField(id) {
    return /^nombreDocumento\d+$/.test(id);
  }
};

if (!window.__burnoutLoadingPageScript) {
  $(document).ready(window.initRegistroPage);
}
