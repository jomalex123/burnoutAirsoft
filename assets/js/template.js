window.initTemplatePage = function () {
  var mount = document.getElementById('template');

  if (!mount) {
    return;
  }

  mount.innerHTML = '<div class="template-empty">Configura aqui el contenido de la pagina.</div>';
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initTemplatePage);
}
