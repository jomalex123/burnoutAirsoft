(function () {
    'use strict';
    window.initAdminMapaInteractivoPage = function () {
        var root = document.querySelector('.admin-map-page');
        var map = root && root.querySelector('[data-burnout-map][data-map-mode="edit"]');
        if (map && !map.burnoutMap && window.initBurnoutMapsPage) window.initBurnoutMapsPage();
        if (!map || !map.burnoutMap) return;
        root.hqMap = map.burnoutMap;
        if (root.dataset.mapReady) return;
        root.dataset.mapReady = 'true';
        root.querySelectorAll('[data-map-section]').forEach(function (button) {
            button.addEventListener('click', function () {
                map.burnoutMap.closePopups();
                var name = button.dataset.mapSection;
                root.querySelectorAll('[data-map-section]').forEach(function (item) {
                    var active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', String(active));
                });
                root.querySelectorAll('[data-map-panel]').forEach(function (panel) { panel.hidden = panel.dataset.mapPanel !== name; });
                var console = root.querySelector('[data-hq-console]');
                if (console) {
                    console.hidden = name === 'mapa';
                    console.dispatchEvent(new CustomEvent('burnout:hq-section', { detail: name }));
                }
            });
        });
    };
    if (!window.__burnoutLoadingPageScript) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.initAdminMapaInteractivoPage);
        else window.initAdminMapaInteractivoPage();
    }
})();
