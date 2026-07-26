window.initGalleryPage = function () {
  var root = document.getElementById('eventGalleryFeeds');

  if (!root || root.getAttribute('data-event-gallery-ready') === 'true') {
    return;
  }

  root.setAttribute('data-event-gallery-ready', 'true');

  var source = root.getAttribute('data-gallery-source') || 'gallery_items.php';
  var modal = $('#modal');
  var modalImg = $('#modal-img');
  var activeImages = [];
  var currentIndex = 0;

  root.innerHTML = '<div class="event-gallery-empty"><span>Cargando</span><h2>Preparando galerias</h2></div>';

  $.getJSON(source, function (data) {
    var events = Array.isArray(data) ? data : [];

    $(document).off('click.galleryModal');
    $(document).off('keydown.galleryModal');

    if (!events.length) {
      renderEmpty();
      return;
    }

    root.innerHTML = '';
    events.forEach(function (event) {
      root.appendChild(createEventCard(event));
    });

    bindModalControls();
  }).fail(renderError);

  function createEventCard(event) {
    var article = document.createElement('article');
    var button = document.createElement('button');
    var image = document.createElement('img');
    var body = document.createElement('div');
    var meta = document.createElement('span');
    var title = document.createElement('h2');
    var count = document.createElement('strong');
    var images = Array.isArray(event.images) ? event.images : [];
    var eventMeta = [event.date, event.turn].filter(Boolean).join(' - ');

    article.className = 'event-gallery-card';
    button.className = 'event-gallery-card__media';
    button.type = 'button';
    button.setAttribute('aria-label', 'Abrir galeria ' + (event.title || 'del evento'));

    image.src = event.cover || 'images/resources/maintenance.webp';
    image.alt = images[0] && (images[0].alt || images[0].label) ? images[0].alt || images[0].label : event.title || 'Galeria de evento';
    image.loading = 'lazy';
    image.onerror = function () {
      image.onerror = null;
      image.src = 'images/resources/maintenance.webp';
    };

    body.className = 'event-gallery-card__body';
    meta.className = 'event-gallery-card__meta';
    meta.textContent = eventMeta || 'Evento Burnout';
    title.textContent = event.title || 'Galeria de evento';
    count.textContent = String(event.imageCount || images.length || 0) + ((event.imageCount || images.length) === 1 ? ' imagen' : ' imagenes');

    button.appendChild(image);
    button.addEventListener('click', function () {
      openAlbum(images, 0);
    });

    body.appendChild(meta);
    body.appendChild(title);
    body.appendChild(count);
    article.appendChild(button);
    article.appendChild(body);

    return article;
  }

  function bindModalControls() {
    $('.close').off('click.galleryModal').on('click.galleryModal', closeModal);

    $('.gallery-modal-prev').off('click.galleryModal').on('click.galleryModal', function (event) {
      event.stopPropagation();
      showPrevImage();
    });

    $('.gallery-modal-next').off('click.galleryModal').on('click.galleryModal', function (event) {
      event.stopPropagation();
      showNextImage();
    });

    $(document).on('click.galleryModal', function (event) {
      if ($(event.target).is('#modal')) {
        closeModal();
      }
    });

    $(document).on('keydown.galleryModal', function (event) {
      if (!modal.is(':visible')) {
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

  function openAlbum(images, index) {
    activeImages = Array.isArray(images) ? images : [];

    if (!activeImages.length) {
      return;
    }

    renderModalImage(index);
    modal.stop(true, true).css('display', 'flex').hide().fadeIn();
  }

  function closeModal() {
    modal.stop(true, true).fadeOut();
  }

  function showPrevImage() {
    renderModalImage(currentIndex - 1);
  }

  function showNextImage() {
    renderModalImage(currentIndex + 1);
  }

  function renderModalImage(index) {
    if (!activeImages.length) {
      return;
    }

    currentIndex = (index + activeImages.length) % activeImages.length;
    var imageData = activeImages[currentIndex];

    modalImg.attr({
      src: imageData.src || 'images/resources/maintenance.webp',
      alt: imageData.alt || imageData.label || ''
    });
  }

  function renderEmpty() {
    root.innerHTML = [
      '<div class="event-gallery-empty">',
      '<span>Galeria</span>',
      '<h2>Todavia no hay eventos publicados</h2>',
      '<p>Cuando subamos imagenes de una partida apareceran aqui.</p>',
      '</div>'
    ].join('');
  }

  function renderError() {
    root.innerHTML = [
      '<div class="event-gallery-empty event-gallery-empty--error">',
      '<span>Error</span>',
      '<h2>No se ha podido cargar la galeria</h2>',
      '<p>Revisa el endpoint de galerias por evento.</p>',
      '</div>'
    ].join('');
  }
};

if (!window.__burnoutLoadingPageScript) {
  $(document).ready(window.initGalleryPage);
}
