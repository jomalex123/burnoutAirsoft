// Componente real: controles comunes, lectura, varias instancias y ciclo de vida.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/map.js'), 'utf8');
function element() {
    return { dataset: {}, style: { setProperty() {} }, attributes: {}, listeners: {}, disabled: false, isConnected: true,
        classList: { add() {}, remove() {}, toggle() {} },
        addEventListener(type, handler) { (this.listeners[type] ||= []).push(handler); },
        removeEventListener(type, handler) { this.listeners[type] = (this.listeners[type] || []).filter(item => item !== handler); },
        setAttribute(name, value) { this.attributes[name] = value; },
        closest() { return this; }, focus() {},
        async emit(type, event = {}) { await Promise.all((this.listeners[type] || []).map(handler => handler(event))); }
    };
}
const document = Object.assign(element(), { fullscreenEnabled: true, fullscreenElement: null, body: element() });
document.exitFullscreen = async () => { document.fullscreenElement = null; };
function makeMap(mode) {
    const root = element(), parts = {}, actions = ['zoom-in', 'zoom-out', 'fullscreen'];
    root.dataset.mapMode = mode;
    if (mode === 'edit') actions.push('undo', 'redo', 'clear');
    for (const action of actions) {
        const button = element(); button.dataset.mapAction = action; parts['[data-map-action="' + action + '"]'] = button;
    }
    const viewport = element(), stage = element(), image = element();
    parts['.admin-map-viewport'] = viewport; parts['.admin-map-canvas-stage'] = stage; parts['.admin-map-background'] = image;
    parts['[data-map-zoom-label]'] = element(); parts['.admin-map-load-error'] = element();
    const overlay = element(); overlay.children = [];
    overlay.replaceChildren = () => { overlay.children = []; };
    overlay.append = child => overlay.children.push(child);
    parts['[data-map-overlay]'] = overlay;
    const fullIcon = element(); parts['[data-map-action="fullscreen"]'].querySelector = () => fullIcon;
    Object.assign(viewport, { clientWidth: 800, clientHeight: 500, scrollLeft: 0, scrollTop: 0 });
    stage.style.width = '100%';
    Object.defineProperty(stage, 'offsetWidth', { get: () => 800 * parseInt(stage.style.width) / 100 });
    Object.defineProperty(stage, 'offsetHeight', { get: () => 500 * parseInt(stage.style.width) / 100 });
    Object.assign(image, { complete: true, naturalWidth: 2000 });
    let pixels = 'empty';
    if (mode === 'edit') {
        const canvas = element();
        Object.assign(canvas, { width: 1600, height: 1131, toDataURL: () => pixels, getContext: () => ({ getImageData: () => pixels, putImageData(value) { pixels = value; }, drawImage(img) { pixels = img.src; }, clearRect() { pixels = 'empty'; } }) });
        parts.canvas = canvas;
        for (const key of ['color', 'size', 'size-label', 'text-input', 'text-form']) parts['[data-map-' + key + ']'] = element();
        parts['[data-map-color]'].value = '#df1f29'; parts['[data-map-size]'].value = '5';
        const textModal = element(); textModal.querySelectorAll = () => []; parts['[data-map-text-modal]'] = textModal;
    }
    root.querySelector = selector => parts[selector] || null;
    root.querySelectorAll = selector => selector === '[data-map-action]' ? actions.map(action => parts['[data-map-action="' + action + '"]']) : [];
    root.requestFullscreen = async () => { document.fullscreenElement = root; };
    root.parts = parts;
    root.writePixels = value => { pixels = value; }; root.readPixels = () => pixels;
    return root;
}
const reader = makeMap('readonly'), editorA = makeMap('edit'), editorB = makeMap('edit');
const roots = [reader, editorA, editorB];
document.querySelectorAll = () => roots.filter(root => root.isConnected);
const context = vm.createContext({ window: { __burnoutLoadingPageScript: true }, document,
    Image: class { set src(value) { this.value = value; Promise.resolve().then(() => this.onload()); } get src() { return this.value; } } });
vm.runInContext(source, context);
context.window.initBurnoutMapsPage();
const api = reader.burnoutMap;
context.window.initBurnoutMapsPage();
assert.equal(reader.burnoutMap, api, 'Inicializacion repetida reutiliza la instancia');
assert.equal(reader.listeners.click.length, 1);
assert.equal(api.readonly, true);
assert.equal(reader.querySelector('canvas'), null);
async function click(root, action) {
    const button = root.parts['[data-map-action="' + action + '"]'];
    await button.emit('click', { target: button });
    await root.emit('click', { target: button });
}
async function run() {
    await editorA.burnoutMap.setAnnotations('data:image/png;base64,AAAA');
    assert.equal(editorA.burnoutMap.exportAnnotations(), 'data:image/png;base64,AAAA');
    assert.equal(editorB.readPixels(), 'empty', 'Cargar un mapa persistido no afecta a otras instancias');
    await editorA.burnoutMap.setAnnotations(null);
    assert.equal(editorA.readPixels(), 'empty');
    await api.setAnnotations('data:image/png;base64,AAAA');
    assert.equal(reader.parts['[data-map-overlay]'].children.length, 1);
    assert.equal(api.exportAnnotations(), null);
    await api.setAnnotations(null);
    assert.equal(reader.parts['[data-map-overlay]'].children.length, 0);
    await assert.rejects(api.setAnnotations('data:text/html,invalid'));
    assert.equal(reader.parts['.admin-map-load-error'].hidden, true);
    await click(reader, 'zoom-in');
    assert.equal(reader.parts['.admin-map-canvas-stage'].style.width, '125%');
    assert.equal(reader.parts['.admin-map-viewport'].scrollLeft, 100);
    assert.equal(reader.parts['.admin-map-viewport'].scrollTop, 62.5);
    assert.equal(editorA.parts['.admin-map-canvas-stage'].style.width, '100%');
    for (let i = 0; i < 20; i++) await click(reader, 'zoom-in');
    assert.equal(reader.parts['.admin-map-canvas-stage'].style.width, '300%');
    assert.equal(reader.parts['[data-map-action="zoom-in"]'].disabled, true);
    for (let i = 0; i < 20; i++) await click(reader, 'zoom-out');
    assert.equal(reader.parts['.admin-map-canvas-stage'].style.width, '100%');
    assert.equal(reader.parts['[data-map-action="zoom-out"]'].disabled, true);
    await click(reader, 'fullscreen');
    assert.equal(document.fullscreenElement, reader);
    assert.equal(reader.parts['[data-map-action="fullscreen"]'].attributes['aria-pressed'], 'true');
    await click(reader, 'fullscreen');
    assert.equal(document.fullscreenElement, null);
    await editorA.burnoutMap.load(1); await editorB.burnoutMap.load(1);
    editorA.writePixels('drawing A'); editorB.writePixels('drawing B');
    await editorA.burnoutMap.load(2);
    assert.equal(editorA.readPixels(), 'empty');
    assert.equal(editorB.readPixels(), 'drawing B');
    await editorA.burnoutMap.load(1);
    assert.equal(editorA.readPixels(), 'drawing A');
    await click(editorA, 'zoom-in');
    assert.equal(editorA.parts['.admin-map-canvas-stage'].style.width, '125%');
    assert.equal(editorB.parts['.admin-map-canvas-stage'].style.width, '100%');
    reader.parts['.admin-map-background'].naturalWidth = 0;
    await reader.parts['.admin-map-background'].emit('error');
    assert.equal(reader.parts['.admin-map-load-error'].hidden, false);
    const old = editorA.burnoutMap;
    editorA.isConnected = false;
    context.window.initBurnoutMapsPage();
    assert.equal(editorA.burnoutMap, undefined, 'La navegacion parcial destruye mapas retirados');
    assert.equal(editorA.listeners.click.length, 0);
    assert.equal(editorA.parts.canvas.listeners.pointerdown.length, 0);
    assert.equal(reader.burnoutMap, api);
    editorA.isConnected = true;
    context.window.initBurnoutMapsPage();
    assert.notEqual(editorA.burnoutMap, old);
    await editorA.burnoutMap.load(1);
    assert.equal(editorA.readPixels(), 'empty', 'Salir del HQ descarta las anotaciones temporales');
    assert.equal(editorA.parts.canvas.listeners.pointerdown.length, 1);
    const page = element(), consolePanel = element();
    const names = ['mapa', 'misiones', 'canales', 'jugadores', 'ajustes'];
    const navigation = names.map(name => { const item = element(); item.dataset.mapSection = name; return item; });
    const panels = names.map(name => { const item = element(); item.dataset.mapPanel = name; return item; });
    let section = '';
    consolePanel.dispatchEvent = event => { section = event.detail; };
    page.querySelector = selector => selector === '[data-hq-console]' ? consolePanel : editorA;
    page.querySelectorAll = selector => selector === '[data-map-section]' ? navigation : panels;
    document.querySelector = () => page;
    context.CustomEvent = class { constructor(type, options) { this.type = type; this.detail = options.detail; } };
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../assets/js/admin_mapainteractivo.js'), 'utf8'), context);
    context.window.initAdminMapaInteractivoPage(); context.window.initAdminMapaInteractivoPage();
    assert.equal(page.hqMap, editorA.burnoutMap);
    assert.equal(navigation[1].listeners.click.length, 1);
    await navigation[1].emit('click');
    assert.equal(section, 'misiones');
    assert.equal(panels[0].hidden, true);
    assert.equal(panels[1].hidden, false);
    assert.equal(consolePanel.hidden, false);
    await navigation[0].emit('click');
    assert.equal(section, 'mapa');
    assert.equal(panels[0].hidden, false);
    assert.equal(consolePanel.hidden, true);
    roots.forEach(root => root.burnoutMap.destroy());
    console.log('Mapa compartido: lectura/edicion, zoom y fullscreen comunes, instancias aisladas, reinicializacion y limpieza correctos.');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
