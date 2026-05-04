window.initAdminGalleryPage = function () {
  var modal = document.getElementById('galleryModal');
  var firstInput = document.getElementById('src');
  var list = document.getElementById('galleryList');
  var pages = document.getElementById('galleryPages');
  var pageSizeSelect = document.getElementById('galleryPageSize');

  if (!modal && !list) {
    return;
  }

  if (modal && modal.getAttribute('data-admin-gallery-ready') !== 'true') {
    modal.setAttribute('data-admin-gallery-ready', 'true');

    document.querySelectorAll('[data-gallery-modal-open]').forEach(function (button) {
      button.addEventListener('click', openModal);
    });

    document.querySelectorAll('[data-gallery-modal-close]').forEach(function (button) {
      button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });
  }

  if (list && pages && pageSizeSelect && list.getAttribute('data-admin-gallery-pagination-ready') !== 'true') {
    list.setAttribute('data-admin-gallery-pagination-ready', 'true');
    initPagination();
  }

  function openModal() {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('admin-gallery-modal-open');

    if (firstInput) {
      firstInput.focus();
    }
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('admin-gallery-modal-open');
  }

  function initPagination() {
    var items = Array.prototype.slice.call(list.querySelectorAll('.admin-gallery-item'));
    var currentPage = 1;

    function renderPagination() {
      var pageSize = parseInt(pageSizeSelect.value, 10) || 5;
      var totalPages = Math.max(1, Math.ceil(items.length / pageSize));

      if (currentPage > totalPages) {
        currentPage = totalPages;
      }

      items.forEach(function (item, index) {
        var start = (currentPage - 1) * pageSize;
        var end = start + pageSize;
        var isVisible = index >= start && index < end;

        item.classList.toggle('is-hidden', !isVisible);
        item.style.display = isVisible ? '' : 'none';
        item.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
      });

      pages.innerHTML = '';

      for (var page = 1; page <= totalPages; page++) {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = String(page);
        button.className = page === currentPage ? 'is-active' : '';
        button.setAttribute('aria-label', 'Pagina ' + page);
        button.setAttribute('aria-current', page === currentPage ? 'page' : 'false');
        button.addEventListener('click', function () {
          currentPage = parseInt(this.textContent, 10);
          renderPagination();
        });
        pages.appendChild(button);
      }
    }

    pageSizeSelect.addEventListener('change', function () {
      currentPage = 1;
      renderPagination();
    });

    renderPagination();
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initAdminGalleryPage);
}
