window.initGalleryPage = function () {
  const galeria = $('#galeria');

  if (!galeria.length) {
    return;
  }

  const source = galeria.data('gallery-source') || 'gallery_items.php';

  $.getJSON(source, function (data) {
    data = Array.isArray(data) ? data : [];

    let currentIndex = 0;

    galeria.empty();
    $(document).off('click.galleryModal');
    $(document).off('keydown.galleryModal');

    const renderModalImage = function (index) {
      if (!data.length) {
        return;
      }

      currentIndex = (index + data.length) % data.length;
      const imageData = data[currentIndex];

      $('#modal-img').attr({
        src: imageData.src,
        alt: imageData.alt || ''
      });
      $('#modal-title').text(imageData.alt || '');
      $('#modal-text').text(imageData.description || '');
    };

    const openModal = function (index) {
      renderModalImage(index);
      $('#modal').stop(true, true).css('display', 'flex').hide().fadeIn();
    };

    const closeModal = function () {
      $('#modal').stop(true, true).fadeOut();
    };

    const showPrevImage = function () {
      renderModalImage(currentIndex - 1);
    };

    const showNextImage = function () {
      renderModalImage(currentIndex + 1);
    };

    data.forEach(function (item, index) {
      const img = $('<img>', {
        src: item.src,
        alt: item.alt || '',
        'data-index': index,
        class: 'galeria-img'
      });
      galeria.append(img);
    });

    $('.galeria-img').off('click').on('click', function () {
      const index = $(this).data('index');
      openModal(index);
    });

    $('.close').off('click').on('click', function () {
      closeModal();
    });

    $('.gallery-modal-prev').off('click').on('click', function (e) {
      e.stopPropagation();
      showPrevImage();
    });

    $('.gallery-modal-next').off('click').on('click', function (e) {
      e.stopPropagation();
      showNextImage();
    });

    $(document).on('click.galleryModal', function (e) {
      if ($(e.target).is('#modal')) {
        closeModal();
      }
    });

    $(document).on('keydown.galleryModal', function (e) {
      if (!$('#modal').is(':visible')) {
        return;
      }

      if (e.key === 'Escape') {
        closeModal();
      } else if (e.key === 'ArrowLeft') {
        showPrevImage();
      } else if (e.key === 'ArrowRight') {
        showNextImage();
      }
    });
  }).fail(function () {
    galeria.empty();
  });
};

if (!window.__burnoutLoadingPageScript) {
  $(document).ready(window.initGalleryPage);
}


