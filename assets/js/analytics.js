(function () {
  'use strict';

  if (window.BurnoutAnalytics) return;

  var containerId = 'GTM-W2FVT27Q';
  var storageKey = 'burnout-analytics-consent-v1';
  var lifetime = 180 * 24 * 60 * 60 * 1000;
  var publicPages = ['', 'index.html', 'burnout.html', 'campo.html', 'galeria.html', 'partidas.html', 'registro.html', 'registro.php', 'normativa.html', 'privacidad.html'];
  var production = ['burnoutairsoft.com', 'www.burnoutairsoft.com'].indexOf(window.location.hostname) !== -1;
  var loaded = false;
  var lastPage = '';
  var banner;
  var settings;
  var returnFocus;
  var choice = readChoice();

  function isPublicPage() {
    return publicPages.indexOf(window.location.pathname.split('/').pop()) !== -1;
  }

  function safeUrl(value) {
    if (!value) return '';
    try {
      var url = new URL(value, window.location.href);
      return url.origin + url.pathname;
    } catch (error) {
      return '';
    }
  }

  function readChoice() {
    try {
      var stored = JSON.parse(window.localStorage.getItem(storageKey));
      if (stored && (stored.value === 'granted' || stored.value === 'denied') &&
          typeof stored.time === 'number' && stored.time <= Date.now() && Date.now() - stored.time < lifetime) {
        return stored.value;
      }
    } catch (error) {
      // Storage may be unavailable. Keep the choice only for this document.
    }
    return null;
  }

  function consent(value) {
    return { analytics_storage: value, ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' };
  }

  function startAnalytics() {
    if (!production || !isPublicPage() || choice !== 'granted' || loaded) return;
    loaded = true;
    lastPage = safeUrl(window.location.href);
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('consent', 'default', consent('denied'));
    window.gtag('consent', 'update', consent('granted'));
    window.gtag('set', {
      page_location: lastPage,
      page_referrer: safeUrl(document.referrer || ''),
      allow_google_signals: false,
      allow_ad_personalization_signals: false
    });
    window.gtag('js', new Date());
    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtm.js?id=' + containerId;
    document.head.appendChild(script);
  }

  function clearAnalyticsCookies() {
    document.cookie.split(';').forEach(function (cookie) {
      var name = cookie.split('=')[0].trim();
      if (!/^(_ga(?:_|$)|_gid$|_gat(?:_|$))/.test(name)) return;
      ['', window.location.hostname, '.' + window.location.hostname, 'burnoutairsoft.com', '.burnoutairsoft.com'].forEach(function (domain) {
        document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax' + (domain ? '; domain=' + domain : '');
      });
    });
  }

  function saveChoice(value) {
    choice = value;
    try {
      window.localStorage.setItem(storageKey, JSON.stringify({ value: value, time: Date.now() }));
    } catch (error) {
      // The current document still respects the selected preference.
      try { window.localStorage.removeItem(storageKey); } catch (storageError) { /* Storage is disabled. */ }
    }
    banner.hidden = true;
    if (value === 'granted') {
      startAnalytics();
    } else {
      if (loaded) window.gtag('consent', 'update', consent('denied'));
      clearAnalyticsCookies();
      if (loaded) {
        // A fresh GET unloads the container, including tags already running.
        // Do not reload a possible registration POST.
        window.location.replace(window.location.href);
        return;
      }
    }
    if (returnFocus && returnFocus.isConnected) returnFocus.focus();
  }

  function createControls() {
    banner = document.createElement('section');
    banner.className = 'burnout-cookies';
    banner.setAttribute('aria-labelledby', 'burnoutCookiesTitle');
    banner.hidden = true;
    banner.innerHTML = '<div class="burnout-cookies__content"><h2 id="burnoutCookiesTitle">Cookies de estadísticas</h2>' +
      '<p>Con tu permiso usamos Google Analytics para conocer las visitas y mejorar la web. Puedes aceptar o rechazar las cookies de estadísticas; las necesarias seguirán funcionando. ' +
      '<a href="privacidad.html" data-type="page-transition">Política de privacidad</a>.</p>' +
      '<p class="burnout-cookies__withdrawal" hidden>Si rechazas ahora, se recargará la página para detener la analítica.</p></div>' +
      '<div class="burnout-cookies__actions"><button type="button" data-cookie-choice="denied">Rechazar</button>' +
      '<button type="button" data-cookie-choice="granted">Aceptar estadísticas</button></div>';
    banner.querySelectorAll('[data-cookie-choice]').forEach(function (button) {
      button.addEventListener('click', function () { saveChoice(button.getAttribute('data-cookie-choice')); });
    });
    settings = document.createElement('button');
    settings.type = 'button';
    settings.className = 'burnout-cookies-settings';
    settings.textContent = 'Cookies';
    settings.setAttribute('aria-controls', 'burnoutCookies');
    banner.id = 'burnoutCookies';
    settings.addEventListener('click', function () {
      returnFocus = settings;
      banner.hidden = false;
      banner.querySelector('.burnout-cookies__withdrawal').hidden = !loaded;
      banner.querySelector('button').focus();
    });
    document.body.appendChild(banner);
    document.body.appendChild(settings);
  }

  function init() {
    if (!document.body) return;
    if (!banner) createControls();
    var visible = isPublicPage();
    settings.hidden = !visible;
    if (!visible) {
      banner.hidden = true;
      return;
    }
    if (!choice) banner.hidden = false;
    startAnalytics();
  }

  function pageView() {
    init();
    if (!production || !loaded || choice !== 'granted' || !isPublicPage()) return;
    var page = safeUrl(window.location.href);
    if (page === lastPage) return;
    var referrer = lastPage;
    lastPage = page;
    window.gtag('set', { page_location: page, page_referrer: referrer });
    window.dataLayer.push({
      event: 'burnout_page_view',
      page_location: page,
      page_referrer: referrer,
      page_title: document.title
    });
  }

  window.BurnoutAnalytics = { init: init };
  window.initBurnoutAnalyticsPage = init;
  document.addEventListener('burnout:pageview', pageView);
  window.addEventListener('storage', function (event) {
    if (event.key !== storageKey && event.key !== null) return;
    choice = readChoice();
    if (loaded && choice !== 'granted') {
      window.gtag('consent', 'update', consent('denied'));
      clearAnalyticsCookies();
      window.location.replace(window.location.href);
      return;
    }
    if (banner) banner.hidden = choice !== null;
    init();
  });
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
