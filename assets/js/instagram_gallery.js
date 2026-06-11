window.initInstagramGalleryPage = function () {
  var grid = document.getElementById('instagramGalleryGrid');

  if (!grid || grid.getAttribute('data-instagram-gallery-ready') === 'true') {
    return;
  }

  grid.setAttribute('data-instagram-gallery-ready', 'true');

  var source = grid.getAttribute('data-instagram-source') || 'assets/data/instagram_gallery.json';
  var status = document.getElementById('instagramGalleryStatus');
  var profileLink = document.querySelector('[data-instagram-profile-link]');

  fetch(source, { cache: 'no-store' })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('No se ha podido cargar la galeria de Instagram.');
      }

      return response.json();
    })
    .then(function (payload) {
      var items = Array.isArray(payload.items) ? payload.items : [];
      var profileUrl = payload.profileUrl || 'https://www.instagram.com/burnoutairsoft/';

      if (profileLink) {
        profileLink.href = profileUrl;
      }

      renderItems(items, profileUrl);
    })
    .catch(function () {
      renderError();
    });

  function renderItems(items, profileUrl) {
    grid.innerHTML = '';

    if (!items.length) {
      renderEmpty(profileUrl);
      return;
    }

    if (status) {
      status.textContent = items.length + (items.length === 1 ? ' publicacion cargada' : ' publicaciones cargadas');
    }

    items.forEach(function (item) {
      grid.appendChild(createCard(item));
    });
  }

  function createCard(item) {
    var card = document.createElement('article');
    var link = document.createElement('a');
    var image = document.createElement('img');
    var body = document.createElement('div');
    var title = document.createElement('h2');
    var meta = document.createElement('span');
    var description = document.createElement('p');

    card.className = 'instagram-card';
    link.className = 'instagram-card__media';
    link.href = item.url || 'https://www.instagram.com/burnoutairsoft/';
    link.target = '_blank';
    link.rel = 'noopener noreferrer';

    image.src = item.image || 'images/resources/maintenance.webp';
    image.alt = item.alt || item.title || 'Publicacion de Instagram de Burnout Airsoft';
    image.loading = 'lazy';

    body.className = 'instagram-card__body';
    title.textContent = item.title || 'Burnout Airsoft';
    meta.textContent = item.date || '@burnoutairsoft';
    description.textContent = item.description || 'Ver publicacion en Instagram.';

    link.appendChild(image);
    body.appendChild(meta);
    body.appendChild(title);
    body.appendChild(description);
    card.appendChild(link);
    card.appendChild(body);

    return card;
  }

  function renderEmpty(profileUrl) {
    if (status) {
      status.textContent = 'Sin publicaciones configuradas todavia.';
    }

    grid.innerHTML = [
      '<div class="instagram-empty">',
      '<span>Galeria preparada</span>',
      '<h2>Lista para conectar Instagram</h2>',
      '<p>Para esta prueba, las publicaciones se leen desde <strong>assets/data/instagram_gallery.json</strong>. Anade ahi las fotos destacadas o cambia el origen de datos a un endpoint automatico cuando tengamos la API configurada.</p>',
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
      '<p>Revisa el JSON configurado o el endpoint indicado en <strong>data-instagram-source</strong>.</p>',
      '</div>'
    ].join('');
  }

  function escapeAttribute(value) {
    return String(value || '').replace(/"/g, '&quot;');
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initInstagramGalleryPage);
}
