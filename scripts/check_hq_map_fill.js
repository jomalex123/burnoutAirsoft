// Dibujo y selección reales de relleno; sin paquetes ni servicios externos.
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/map.js'), 'utf8');
const operations = [], tiles = [];
function drawingContext() {
    return {
        beginPath() {}, moveTo(...args) { this.lines.push(['move', ...args]); }, lineTo(...args) { this.lines.push(['line', ...args]); },
        lines: [], rect() {}, ellipse() {}, putImageData() {},
        fillRect() { this.background = { color: this.fillStyle, alpha: this.globalAlpha }; },
        fill() { operations.push(['fill', this.fillStyle]); },
        stroke() { operations.push(['stroke', this.strokeStyle]); },
        createPattern(tile, repetition) { assert.equal(repetition, 'repeat'); return { tile }; }
    };
}
const ctx = drawingContext();
const color = { value: '#df1f29', addEventListener() {} };
const context = vm.createContext({
    fillMode: 'none', fillPattern: null, fillPatternColor: '', tool: 'rectangle', snapshot: {}, start: { x: 100, y: 100 }, ctx,
    root: { querySelector: selector => selector === '[data-map-color]' ? color : { value: '5' } },
    document: { createElement(tag) { assert.equal(tag, 'canvas'); const painter = drawingContext(); const tile = { painter, getContext: () => painter }; tiles.push(tile); return tile; } }
});
vm.runInContext(source.slice(source.indexOf('        function shapeFillStyle()'), source.indexOf("        listen(canvas, 'pointerdown'")), context);
function draw(tool, fillMode) {
    operations.length = 0;
    context.tool = tool; context.fillMode = fillMode;
    vm.runInContext('stroke({ x: 20, y: 30 })', context); // arrastre inverso también.
    return operations.filter(item => item[0] === 'fill');
}
for (const tool of ['rectangle', 'circle']) {
    assert.equal(draw(tool, 'none').length, 0);
    assert.equal(draw(tool, 'solid')[0][1], color.value);
    const pattern = draw(tool, 'hatched')[0][1];
    assert.equal(pattern.tile.painter.background.color, color.value);
    assert.equal(pattern.tile.painter.background.alpha, .18);
    assert.equal(pattern.tile.painter.globalAlpha, .65);
    const lines = pattern.tile.painter.lines;
    for (let i = 0; i < lines.length; i += 2) {
        assert.equal(lines[i + 1][1] - lines[i][1], -(lines[i + 1][2] - lines[i][2]), 'Rayado a 45 grados');
    }
}
assert.equal(tiles.length, 1, 'El patrón se reutiliza mientras no cambia el color');
color.value = '#123456';
assert.equal(draw('rectangle', 'hatched')[0][1].tile.painter.background.color, '#123456');
assert.equal(tiles.length, 2);
for (const tool of ['pen', 'eraser', 'line', 'arrow']) assert.equal(draw(tool, 'solid').length, 0, tool + ' no se rellena');

function element() {
    return {
        dataset: {}, attributes: {}, hidden: true, textContent: '', listeners: {}, children: [],
        style: { setProperty() {} },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        setAttribute(name, value) { this.attributes[name] = value; },
        getAttribute(name) { return this.attributes[name]; },
        focus() { menuDocument.activeElement = this; },
        contains(target) { return target === this; },
        closest() { return this; },
        getBoundingClientRect() { return { left: 100, bottom: 50, top: 0 }; }
    };
}
const icon = () => ({ cloneNode() { return { setAttribute() {} }; }, replaceWith() {} });
const toggle = element(); toggle.querySelector = icon;
const options = ['none', 'hatched', 'solid'].map(mode => {
    const option = element(); option.dataset.mapFill = mode; option.attributes['aria-label'] = mode; option.querySelector = icon;
    option.attributes['aria-selected'] = String(mode === 'none'); return option;
});
const menu = element(); menu.offsetWidth = 240;
menu.querySelectorAll = () => options;
menu.querySelector = () => options.find(option => option.attributes['aria-selected'] === 'true');
menu.contains = target => target === menu || options.includes(target);
const panel = element(); Object.assign(panel, { scrollLeft: 0, scrollTop: 0, clientWidth: 800 });
const toolbar = element();
const menuDocument = { activeElement: null, listeners: {}, addEventListener(name, callback) { this.listeners[name] = callback; }, removeEventListener(name) { delete this.listeners[name]; } };
const menuRoot = { querySelector: selector => selector === '[data-map-color]' ? color : toolbar };
const selection = vm.createContext({ listen(element, type, handler) { element.addEventListener(type, handler); }, fillMode: 'none', fillToggle: toggle, fillMenu: menu, root: menuRoot, mapPanel: panel, drawing: false, panStart: null, document: menuDocument });
vm.runInContext(source.slice(source.indexOf('        function closeFillMenu('), source.indexOf("        listen(root, 'fullscreenchange'")), selection);
toggle.listeners.click();
assert.equal(menu.hidden, false);
assert.equal(menuDocument.activeElement, options[0]);
menu.listeners.keydown({ key: 'ArrowDown', preventDefault() {} });
assert.equal(menuDocument.activeElement, options[1]);
menu.listeners.click({ target: options[1] });
assert.equal(selection.fillMode, 'hatched');
assert.equal(menu.hidden, true);
assert.equal(toggle.attributes['aria-expanded'], 'false');
assert.equal(menuDocument.activeElement, toggle);
toggle.listeners.keydown({ key: 'ArrowDown', preventDefault() {} });
menu.listeners.keydown({ key: 'Escape', preventDefault() {}, stopPropagation() {} });
assert.equal(menu.hidden, true);
toggle.listeners.click();
menuDocument.listeners.pointerdown({ target: element() });
assert.equal(menu.hidden, true);
menuRoot.hqFillCleanup();
assert.equal(menuDocument.listeners.pointerdown, undefined);
console.log('Rellenos: modos, rectángulo/círculo, color, transparencia, rayado a 45°, teclado, cierre y limpieza correctos.');
