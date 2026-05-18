(function(window) {
  'use strict';

  window.initBurnoutGamesPage = function() {
    var page = document.querySelector('.burnoutgames-page');

    if (!page) {
      return;
    }

    page.dataset.ready = 'true';
  };

  if (!window.__burnoutLoadingPageScript) {
    document.addEventListener('DOMContentLoaded', function() {
      window.initBurnoutGamesPage();
    });
  }
})(window);
