$(document).ready(function () {
  $.getJSON('assets/data/gallery.json', function (data) {
    const galeria = $('#galeria');

    data.forEach((item, index) => {
      const img = $('<img>', {
        src: item.src,
        alt: item.alt,
        'data-index': index,
        class: 'galeria-img'
      });
      galeria.append(img);
    });

    $('.galeria-img').click(function () {
      const index = $(this).data('index');
      const imageData = data[index];
      $('#modal-img').attr('src', imageData.src);
      $('#modal-title').text(imageData.alt);
      $('#modal-text').text(imageData.description);
      $('#modal').fadeIn();
    });

    $('.close').click(function () {
      $('#modal').fadeOut();
    });

    $(document).on('click', function (e) {
      if ($(e.target).is('#modal')) {
        $('#modal').fadeOut();
      }
    });
  });
});


