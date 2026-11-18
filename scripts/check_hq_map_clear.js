'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/map.js'), 'utf8');
const document = { activeElement: null, body: { classList: { add() {}, remove() {} } } };
function element() {
    return { isConnected: true, dataset: {}, attributes: {}, listeners: {}, classList: { add() {}, remove() {} },
        setAttribute(key, value) { this.attributes[key] = value; },
        addEventListener(key, callback) { this.listeners[key] = callback; },
        focus() { document.activeElement = this; }
    };
}
const close = element(), cancel = element(), accept = element(), overlay = element();
const modal = element();
modal.querySelectorAll = selector => selector === '[data-map-clear-close]' ? [overlay, close, cancel] : [close, accept, cancel];
modal.querySelector = selector => selector === '[data-map-clear-cancel]' ? cancel : accept;
const actions = ['clear', 'undo', 'redo'].map(action => { const button = element(); button.dataset.mapAction = action; return button; });
let pixels = 'anotaciones', history = [], future = [];
const context = vm.createContext({ listen(element, type, handler) { element.addEventListener(type, handler); },
    clearModal: modal, clearMapId: null, clearOpener: null, mapId: 1, busy: false, drawing: false, panStart: null, textPosition: null,
    document, closeFillMenu() {}, remember() { history.push(pixels); future.length = 0; }, blank() { pixels = 'vacio'; }, say() {}, buttons() {},
    history, future, canvas: { width: 1600, height: 1131 }, ctx: { getImageData() { return pixels; }, putImageData(value) { pixels = value; } },
    root: { querySelectorAll: () => actions }
});
vm.runInContext(source.slice(source.indexOf('        function closeClearModal('), source.indexOf("        textModal.querySelectorAll('[data-map-text-close]')")), context);
const start = source.indexOf("        root.querySelectorAll('[data-map-action]')");
vm.runInContext(source.slice(start, source.indexOf('\n        blank();', start)), context);
actions[0].listeners.click();
assert.equal(modal.attributes['aria-hidden'], 'false');
assert.equal(document.activeElement, cancel);
assert.equal(pixels, 'anotaciones');
cancel.listeners.click();
assert.equal(modal.attributes['aria-hidden'], 'true');
assert.equal(pixels, 'anotaciones');
assert.equal(history.length, 0);
assert.equal(document.activeElement, actions[0]);
actions[0].listeners.click();
let prevented = false;
modal.listeners.keydown({ key: 'Tab', shiftKey: false, preventDefault() { prevented = true; } });
assert.equal(prevented, true);
assert.equal(document.activeElement, close);
modal.listeners.keydown({ key: 'Escape', preventDefault() {}, stopPropagation() {} });
assert.equal(pixels, 'anotaciones');
assert.equal(modal.attributes['aria-hidden'], 'true');
actions[0].listeners.click();
accept.listeners.click();
assert.equal(pixels, 'vacio');
assert.equal(history.length, 1);
actions[1].listeners.click();
assert.equal(pixels, 'anotaciones', 'Se puede deshacer la limpieza');
actions[2].listeners.click();
assert.equal(pixels, 'vacio', 'Se puede rehacer la limpieza');
pixels = 'otra partida';
actions[0].listeners.click();
context.mapId = 2;
accept.listeners.click();
assert.equal(pixels, 'otra partida', 'Una confirmación anterior no limpia otra partida');
context.busy = true;
actions[0].listeners.click();
assert.equal(modal.attributes['aria-hidden'], 'true');
console.log('Limpiar mapa: confirmación, cancelación, Escape, foco, Tab, deshacer/rehacer y aislamiento de partida correctos.');
