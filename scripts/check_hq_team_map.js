// Controles reales del visor, sin navegador ni dependencias externas.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/hq.js'), 'utf8');
function element() {
    return { dataset: {}, style: {}, attributes: {}, listeners: {}, disabled: false,
        addEventListener(name, callback) { this.listeners[name] = callback; },
        setAttribute(name, value) { this.attributes[name] = value; },
        closest() { return this; }
    };
}
const parts = {};
for (const key of ['viewport', 'stage', 'image', 'zoom', 'error', 'fullscreen-icon']) parts[`[data-hq-map-${key}]`] = element();
for (const action of ['zoom-in', 'zoom-out', 'fullscreen']) {
    const button = element(); button.dataset.hqMapView = action;
    parts[`[data-hq-map-view="${action}"]`] = button;
}
const viewer = element(); viewer.querySelector = selector => parts[selector];
const fullscreen = parts['[data-hq-map-view="fullscreen"]'];
fullscreen.querySelector = selector => parts[selector];
const viewport = parts['[data-hq-map-viewport]'];
Object.assign(viewport, { clientWidth: 800, clientHeight: 500, scrollLeft: 0, scrollTop: 0 });
const stage = parts['[data-hq-map-stage]'];
stage.style.width = '100%';
Object.defineProperty(stage, 'offsetWidth', { get: () => 800 * parseInt(stage.style.width) / 100 });
Object.defineProperty(stage, 'offsetHeight', { get: () => 500 * parseInt(stage.style.width) / 100 });
Object.assign(parts['[data-hq-map-image]'], { complete: true, naturalWidth: 2000 });
const document = { fullscreenEnabled: true, fullscreenElement: null };
viewer.requestFullscreen = async () => { document.fullscreenElement = viewer; };
document.exitFullscreen = async () => { document.fullscreenElement = null; };
const context = vm.createContext({ teamView: true, root: { querySelector: () => viewer }, document });
vm.runInContext(source.slice(source.indexOf('        function setupTeamMap()'), source.indexOf('        function playerRoleIcon(')), context);
vm.runInContext('setupTeamMap()', context);
async function click(action) {
    await viewer.listeners.click({ target: parts[`[data-hq-map-view="${action}"]`] });
}
async function run() {
    assert.equal(parts['[data-hq-map-error]'].hidden, true);
    await click('zoom-in');
    assert.equal(stage.style.width, '125%');
    assert.equal(viewport.scrollLeft, 100);
    assert.equal(viewport.scrollTop, 62.5);
    for (let i = 0; i < 20; i++) await click('zoom-in');
    assert.equal(stage.style.width, '300%');
    assert.equal(parts['[data-hq-map-view="zoom-in"]'].disabled, true);
    for (let i = 0; i < 20; i++) await click('zoom-out');
    assert.equal(stage.style.width, '100%');
    assert.equal(parts['[data-hq-map-view="zoom-out"]'].disabled, true);
    await click('fullscreen');
    assert.equal(document.fullscreenElement, viewer);
    assert.equal(fullscreen.attributes['aria-pressed'], 'true');
    await click('fullscreen');
    assert.equal(document.fullscreenElement, null);
    document.fullscreenElement = viewer;
    viewer.listeners.fullscreenchange();
    assert.equal(fullscreen.attributes['aria-label'], 'Salir de pantalla completa');
    parts['[data-hq-map-image]'].naturalWidth = 0;
    parts['[data-hq-map-image]'].listeners.error();
    assert.equal(parts['[data-hq-map-error]'].hidden, false);
    console.log('Mapa de equipo: zoom, límites, centrado, pantalla completa y errores correctos.');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
