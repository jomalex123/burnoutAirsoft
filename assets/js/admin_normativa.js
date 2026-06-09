window.initAdminNormativaPage = function () {
  if ('scrollRestoration' in window.history) {
    window.history.scrollRestoration = 'manual';
  }

  window.scrollTo(0, 0);
  setTimeout(function () {
    window.scrollTo(0, 0);
  }, 0);

  document.querySelectorAll('[data-normativa-editor]').forEach(function (form) {
    var container = form.closest('.admin-normativa-block, .admin-normativa-add');

    if (!container || form.getAttribute('data-admin-normativa-ready') === 'true') {
      return;
    }

    form.setAttribute('data-admin-normativa-ready', 'true');

    var textarea = form.querySelector('textarea[name="content"]');
    var htmlPane = form.querySelector('[data-normativa-html-pane]');
    var webPane = form.querySelector('[data-normativa-web-pane]');
    var preview = form.querySelector('[data-normativa-preview]');
    var toggle = container.querySelector('[data-normativa-toggle]');
    var isPreviewVisible = false;

    if (!textarea || !htmlPane || !webPane || !preview || !toggle) {
      return;
    }

    toggle.addEventListener('click', function () {
      setPreviewVisible(!isPreviewVisible);
    });

    textarea.addEventListener('input', function () {
      if (isPreviewVisible) {
        renderPreview();
      }
    });

    setPreviewVisible(false);

    function setPreviewVisible(visible) {
      isPreviewVisible = Boolean(visible);
      htmlPane.hidden = isPreviewVisible;
      webPane.hidden = !isPreviewVisible;
      toggle.textContent = isPreviewVisible ? 'Ocultar contenido' : 'Ver contenido';
      toggle.setAttribute('aria-expanded', isPreviewVisible ? 'true' : 'false');

      if (isPreviewVisible) {
        renderPreview();
      } else {
        textarea.focus();
      }
    }

    function renderPreview() {
      var safeHtml = sanitizePreviewHtml(textarea.value);

      preview.innerHTML = safeHtml || '<p class="admin-normativa-preview-empty">Sin contenido para previsualizar.</p>';
    }
  });

  function sanitizePreviewHtml(html) {
    var template = document.createElement('template');
    var blockedTags = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button'];

    template.innerHTML = String(html || '');

    blockedTags.forEach(function (tagName) {
      template.content.querySelectorAll(tagName).forEach(function (node) {
        node.remove();
      });
    });

    template.content.querySelectorAll('*').forEach(function (node) {
      Array.prototype.slice.call(node.attributes).forEach(function (attribute) {
        var name = attribute.name.toLowerCase();
        var value = String(attribute.value || '').trim().toLowerCase();

        if (name.indexOf('on') === 0 || value.indexOf('javascript:') === 0) {
          node.removeAttribute(attribute.name);
        }
      });
    });

    return template.innerHTML;
  }
};

if (!window.__burnoutLoadingPageScript) {
  document.addEventListener('DOMContentLoaded', window.initAdminNormativaPage);
}
