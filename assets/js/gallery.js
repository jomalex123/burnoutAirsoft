window.initGalleryPage = function () {
  const galeria = $('#galeria');

  if (!galeria.length) {
    return;
  }

  $.getJSON('assets/data/gallery.json', function (data) {
    galeria.empty();
    $(document).off('click.galleryModal');

    data.forEach((item, index) => {
      const img = $('<img>', {
        src: item.src,
        alt: item.alt,
        'data-index': index,
        class: 'galeria-img'
      });
      galeria.append(img);
    });

    $('.galeria-img').off('click').on('click', function () {
      const index = $(this).data('index');
      const imageData = data[index];
      $('#modal-img').attr('src', imageData.src);
      $('#modal-title').text(imageData.alt);
      $('#modal-text').text(imageData.description);
      $('#modal').fadeIn();
    });

    $('.close').off('click').on('click', function () {
      $('#modal').fadeOut();
    });

    $(document).on('click.galleryModal', function (e) {
      if ($(e.target).is('#modal')) {
        $('#modal').fadeOut();
      }
    });
  });
};

if (!window.__burnoutLoadingPageScript) {
  $(document).ready(window.initGalleryPage);
}


