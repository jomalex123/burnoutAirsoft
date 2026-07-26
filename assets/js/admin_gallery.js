window.initAdminGalleryPage = function () {
  var root = document.querySelector('.admin-gallery-layout');
  var deleteModal = document.getElementById('galleryDeleteModal');
  var deleteMessage = document.getElementById('galleryDeleteMessage');
  var pendingDeleteForm = null;

  if (!root && !deleteModal) {
    return;
  }

  if (root && root.getAttribute('data-admin-gallery-ready') === 'true') {
    return;
  }

  if (root) {
    root.setAttribute('data-admin-gallery-ready', 'true');
  }

  document.querySelectorAll('[data-gallery-modal-open]').forEach(function (button) {
    button.addEventListener('click', function () {
      openModal(button.getAttribute('data-gallery-modal-open'), button);
    });
  });

  document.querySelectorAll('[data-gallery-modal-close]').forEach(function (button) {
    button.addEventListener('click', closeModals);
  });

  document.querySelectorAll('[data-event-gallery-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      toggleEventGallery(button);
    });
  });

  document.querySelectorAll('[data-gallery-delete-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      pendingDeleteForm = form;
      openDeleteModal(form);
    });
  });

  document.querySelectorAll('[data-gallery-delete-cancel]').forEach(function (button) {
    button.addEventListener('click', closeDeleteModal);
  });

  var confirmButton = document.querySelector('[data-gallery-delete-confirm]');

  if (confirmButton) {
    confirmButton.addEventListener('click', function () {
      if (pendingDeleteForm) {
        pendingDeleteForm.submit();
      }
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeDeleteModal();
      closeModals();
    }
  });

  function openModal(modalId, opener) {
    var modal = document.getElementById(modalId);

    if (!modal) {
      return;
    }

    var eventId = opener.getAttribute('data-gallery-event-id');
    var eventSelect = modal.querySelector('#gallery_event_id');

    if (eventId && eventSelect) {
      eventSelect.value = eventId;
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('admin-gallery-modal-open');

    var firstInput = modal.querySelector('select, input:not([type="hidden"]), textarea, button');

    if (firstInput) {
      firstInput.focus();
    }
  }

  function toggleEventGallery(button) {
    var body = document.getElementById(button.getAttribute('aria-controls'));

    if (!body) {
      return;
    }

    var isExpanded = button.getAttribute('aria-expanded') === 'true';

    button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
    body.hidden = isExpanded;
  }

  function closeModals() {
    document.querySelectorAll('.admin-gallery-modal.is-open').forEach(function (modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    });
    document.body.classList.remove('admin-gallery-modal-open');
  }

  function openDeleteModal(form) {
    var title = form.getAttribute('data-gallery-delete-title') || 'este elemento';
    var kind = form.getAttribute('data-gallery-delete-kind') || 'image';

    if (!deleteModal) {
      form.submit();
      return;
    }

    if (deleteMessage) {
      deleteMessage.textContent = kind === 'event'
        ? 'Eliminar "' + title + '" y todas sus imagenes?'
        : 'Eliminar "' + title + '" de la galeria?';
    }

    deleteModal.classList.add('is-open');
    deleteModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('admin-gallery-modal-open');
  }

  function closeDeleteModal() {
    pendingDeleteForm = null;

    if (!deleteModal) {
      return;
    }

    deleteModal.classList.remove('is-open');
    deleteModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('admin-gallery-modal-open');
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initAdminGalleryPage);
}
