// Estado temporal del mapa: aislamiento por partida y ausencia de guardado remoto.
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/admin_mapainteractivo.js'), 'utf8');
let pixels = 'empty';
const context = {
    Map, drawing: false, textPosition: null, busy: false,
    root: { isConnected: true }, canvas: { width: 1600, height: 1131 },
    ctx: { getImageData() { return pixels; }, putImageData(value) { pixels = value; } },
    closeTextModal() {}, blank() { pixels = 'empty'; }, buttons() {},
    window: { initAdminMapaInteractivoPage: {} }
};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('        var mapId ='), source.indexOf('        function closeTextModal()')), context);
(async function () {
    await context.root.hqMap.load(1);
    pixels = 'alpha drawing';
    await context.root.hqMap.load(2);
    assert.equal(pixels, 'empty');
    pixels = 'bravo drawing';
    await context.root.hqMap.load(1);
    assert.equal(pixels, 'alpha drawing');
    await context.root.hqMap.load(2);
    assert.equal(pixels, 'bravo drawing');
    assert.equal(await context.root.hqMap.flush(), true);
    context.drawing = true;
    assert.equal(await context.root.hqMap.flush(), false);
    context.drawing = false;
    await context.root.hqMap.load(0);
    assert.equal(pixels, 'empty');
    assert.equal(context.busy, true);
    context.root.isConnected = false;
    context.window.initAdminMapaInteractivoPage.cleanup();
    await context.root.hqMap.load(1);
    assert.equal(pixels, 'empty');
    assert.ok(!/fetch\(|map_save|mapChanged|saveTimer|beforeunload/.test(source));
    console.log('Mapa HQ: memoria por partida, cambio sin red y limpieza correctos.');
})().catch(error => { console.error(error); process.exitCode = 1; });
