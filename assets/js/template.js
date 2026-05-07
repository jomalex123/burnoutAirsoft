window.initTemplatePage = function () {
  var mount = document.getElementById('template');

  if (!mount) {
    return;
  }

  mount.innerHTML = '<div class="template-empty">Configura aquí el contenido de la página.</div>';
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initTemplatePage);
}
