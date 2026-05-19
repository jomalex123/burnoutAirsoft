(function() {
  'use strict';

  if (!('serviceWorker' in navigator)) {
    return;
  }

  var currentScript = document.currentScript;
  var scriptUrl = currentScript && currentScript.src ? currentScript.src : 'assets/js/burnoutgames_pwa.js';
  var serviceWorkerUrl = new URL('../../burnoutgames-sw.js', scriptUrl);
  var scopeUrl = new URL('../../', scriptUrl);

  window.addEventListener('load', function() {
    navigator.serviceWorker.register(serviceWorkerUrl, {
      scope: scopeUrl.pathname
    }).catch(function(error) {
      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('No se ha podido registrar Burnout Games offline.', error);
      }
    });
  });
})();
