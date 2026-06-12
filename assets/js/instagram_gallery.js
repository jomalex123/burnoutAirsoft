window.initInstagramGalleryPage = function () {
  var grid = document.getElementById('instagramGalleryGrid');

  if (!grid || grid.getAttribute('data-instagram-gallery-ready') === 'true') {
    return;
  }

  grid.setAttribute('data-instagram-gallery-ready', 'true');

  var source = grid.getAttribute('data-instagram-source') || 'instagram_feed.php';
  var pageSize = 12;
  var status = document.getElementById('instagramGalleryStatus');
  var profileLink = document.querySelector('[data-instagram-profile-link]');
  var loadMoreButton = document.getElementById('instagramLoadMore');
  var modal = document.getElementById('instagramModal');
  var modalImg = document.getElementById('instagramModalImg');
  var modalText = document.getElementById('instagramModalText');
  var modalMeta = document.getElementById('instagramModalMeta');
  var items = [];
  var currentIndex = 0;
  var profileUrl = 'https://www.instagram.com/burnoutairsoft/';
  var nextCursor = '';
  var hasMore = false;
  var isLoading = false;

  if (loadMoreButton) {
    loadMoreButton.addEventListener('click', function () {
      loadInstagramPage(nextCursor, true);
    });
  }

  loadInstagramPage('', false);

  function loadInstagramPage(cursor, append) {
    if (isLoading) {
      return;
    }

    isLoading = true;
    updateLoadMoreButton();

    fetch(buildFeedUrl(cursor), { cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('No se ha podido cargar la galeria de Instagram.');
        }

        return response.json();
      })
      .then(function (payload) {
        var nextItems = Array.isArray(payload.items) ? payload.items : [];
        var pagination = payload.pagination || {};

        profileUrl = payload.profileUrl || profileUrl;

        if (profileLink) {
          profileLink.href = profileUrl;
        }

        nextCursor = typeof pagination.nextCursor === 'string' ? pagination.nextCursor : '';
        hasMore = Boolean(pagination.hasMore && nextCursor !== '');

        if (append) {
          appendItems(nextItems);
        } else {
          renderItems(nextItems);
        }

        bindModal();
      })
      .catch(function () {
        if (append) {
          hasMore = true;
          renderLoadMoreError();
        } else {
          renderError();
        }
      })
      .finally(function () {
        isLoading = false;
        updateLoadMoreButton();
      });
  }

  function buildFeedUrl(cursor) {
    var url = new URL(source, window.location.href);

    url.searchParams.set('limit', String(pageSize));

    if (cursor) {
      url.searchParams.set('after', cursor);
    } else {
      url.searchParams.delete('after');
    }

    return url.href;
  }

  function renderItems(nextItems) {
    grid.innerHTML = '';
    items = [];

    if (!nextItems.length) {
      renderEmpty(profileUrl);
      return;
    }

    appendItems(nextItems);
  }

  function appendItems(nextItems) {
    nextItems.forEach(function (item) {
      var index = items.length;

      items.push(item);
      grid.appendChild(createThumb(item, index));
    });

    if (status) {
      status.textContent = items.length + (items.length === 1 ? ' publicacion cargada' : ' publicaciones cargadas');
    }
  }

  function createThumb(item, index) {
    var button = document.createElement('button');
    var image = document.createElement('img');

    button.className = 'instagram-gallery-thumb';
    button.type = 'button';
    button.setAttribute('aria-label', 'Abrir publicacion de Instagram');
    button.setAttribute('data-instagram-index', String(index));

    image.src = item.image || 'images/resources/maintenance.webp';
    image.alt = item.alt || item.title || 'Publicacion de Instagram de Burnout Airsoft';
    image.loading = 'lazy';
    image.onerror = function () {
      image.onerror = null;
      image.src = 'images/resources/maintenance.webp';
      button.classList.add('instagram-gallery-thumb--missing-image');
    };

    button.appendChild(image);
    button.addEventListener('click', function () {
      openModal(index);
    });

    return button;
  }

  function bindModal() {
    if (!modal || modal.getAttribute('data-instagram-modal-ready') === 'true') {
      return;
    }

    modal.setAttribute('data-instagram-modal-ready', 'true');

    document.querySelectorAll('[data-instagram-modal-close]').forEach(function (button) {
      button.addEventListener('click', closeModal);
    });

    document.querySelectorAll('[data-instagram-modal-prev]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.stopPropagation();
        showPrevImage();
      });
    });

    document.querySelectorAll('[data-instagram-modal-next]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.stopPropagation();
        showNextImage();
      });
    });

    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeModal();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (modal.style.display !== 'flex') {
        return;
      }

      if (event.key === 'Escape') {
        closeModal();
      } else if (event.key === 'ArrowLeft') {
        showPrevImage();
      } else if (event.key === 'ArrowRight') {
        showNextImage();
      }
    });
  }

  function openModal(index) {
    renderModalImage(index);

    if (modal) {
      modal.style.display = 'flex';
      document.body.classList.add('instagram-modal-open');
    }
  }

  function closeModal() {
    if (modal) {
      modal.style.display = 'none';
      document.body.classList.remove('instagram-modal-open');
    }
  }

  function showPrevImage() {
    renderModalImage(currentIndex - 1);
  }

  function showNextImage() {
    renderModalImage(currentIndex + 1);
  }

  function renderModalImage(index) {
    if (!items.length) {
      return;
    }

    currentIndex = (index + items.length) % items.length;
    var item = items[currentIndex];

    if (modalImg) {
      modalImg.src = item.image || 'images/resources/maintenance.webp';
      modalImg.alt = item.alt || item.title || 'Publicacion de Instagram de Burnout Airsoft';
    }

    if (modalText) {
      modalText.textContent = item.description || 'Ver publicacion en Instagram.';
    }

    if (modalMeta) {
      modalMeta.textContent = item.date || 'Fecha no disponible';
    }

  }

  function renderEmpty(profileUrl) {
    if (status) {
      status.textContent = 'Sin publicaciones configuradas todavia.';
    }

    grid.innerHTML = [
      '<div class="instagram-empty">',
      '<span>Galeria preparada</span>',
      '<h2>Lista para conectar Instagram</h2>',
      '<p>No se han recibido publicaciones desde el feed configurado.</p>',
      '<a href="' + escapeAttribute(profileUrl) + '" target="_blank" rel="noopener noreferrer">Abrir @burnoutairsoft</a>',
      '</div>'
    ].join('');
  }

  function renderError() {
    if (status) {
      status.textContent = 'No se ha podido cargar la galeria.';
    }

    grid.innerHTML = [
      '<div class="instagram-empty instagram-empty--error">',
      '<span>Error</span>',
      '<h2>No se ha podido cargar el feed</h2>',
      '<p>Revisa el endpoint indicado en <strong>data-instagram-source</strong>.</p>',
      '</div>'
    ].join('');
  }

  function renderLoadMoreError() {
    if (status) {
      status.textContent = 'No se han podido cargar mas publicaciones.';
    }
  }

  function updateLoadMoreButton() {
    if (!loadMoreButton) {
      return;
    }

    loadMoreButton.hidden = !hasMore && !(isLoading && items.length > 0);
    loadMoreButton.disabled = isLoading;
    loadMoreButton.textContent = isLoading ? 'Cargando...' : 'Cargar mas';
  }

  function escapeAttribute(value) {
    return String(value || '').replace(/"/g, '&quot;');
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initInstagramGalleryPage);
}
