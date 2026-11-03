// Regresiones del guardado real del mapa, con red y temporizadores simulados.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/admin_mapainteractivo.js'), 'utf8');
const pending = [], timers = new Map(), listeners = new Map();
let timerId = 0;
const context = vm.createContext({
    Promise, Image: class { async decode() {} },
    root: { isConnected: true }, canvas: { width: 1600, height: 1131, toDataURL: () => 'png-snapshot' },
    ctx: { drawImage() {} }, saveStatus: { textContent: '' },
    mapId: 1, mapVersion: 0, savedVersion: 0, saveQueue: Promise.resolve(true), loadVersion: 0,
    retryTimer: null, saveTimer: null, saving: false, busy: false, drawing: false, textPosition: null,
    history: [], future: [], blank() {}, buttons() {}, closeTextModal() {},
    mapRequest(action, id, annotations) { return new Promise((resolve, reject) => pending.push({ action, id, annotations, resolve, reject })); },
    window: {
        initAdminMapaInteractivoPage: {},
        setTimeout(callback, delay) { const id = ++timerId; timers.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
        addEventListener(name, callback) { listeners.set(name, callback); },
        removeEventListener(name) { listeners.delete(name); }
    }
});
vm.runInContext(source.slice(source.indexOf('        function saveMap()'), source.indexOf('        function closeTextModal()')), context);
const tick = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); };
async function check() {
    for (let i = 0; i < 10; i++) context.mapChanged();
    assert.equal(timers.size, 1);
    assert.equal(pending.length, 0);
    let flush = context.root.hqMap.flush(); await tick();
    assert.equal(context.busy, true);
    let request = pending.shift(); assert.equal(request.id, 1); assert.equal(request.action, 'map_save');
    request.resolve({ ok: true }); assert.equal(await flush, true);
    assert.equal(context.savedVersion, 10); assert.equal(context.busy, false);

    context.mapChanged(); let save = context.saveMap(); await tick();
    context.mapChanged(); context.mapChanged();
    assert.equal(pending.length, 1, 'Solo una escritura simultanea');
    pending.shift().resolve({ ok: true }); await save;
    flush = context.root.hqMap.flush(); await tick();
    assert.equal(pending.length, 1, 'Guarda tambien la version mas reciente');
    pending.shift().resolve({ ok: true }); assert.equal(await flush, true);
    assert.equal(context.savedVersion, context.mapVersion);

    context.mapChanged(); flush = context.root.hqMap.flush(); await tick();
    pending.shift().reject(new Error('Fallo simulado')); assert.equal(await flush, false);
    assert.notEqual(context.savedVersion, context.mapVersion);
    assert.equal([...timers.values()].some(timer => timer.delay === 5000), true);
    flush = context.root.hqMap.flush(); await tick();
    pending.shift().resolve({ ok: true }); assert.equal(await flush, true);

    context.drawing = true; assert.equal(await context.root.hqMap.flush(), false); context.drawing = false;
    let firstLoad = context.root.hqMap.load(2); let secondLoad = context.root.hqMap.load(3);
    const oldRead = pending.shift(), currentRead = pending.shift();
    currentRead.resolve({ annotations: null }); await secondLoad;
    oldRead.resolve({ annotations: 'old-png' }); await firstLoad;
    assert.equal(context.mapId, 3); assert.equal(context.busy, false);
    context.root.isConnected = false; context.window.initAdminMapaInteractivoPage.cleanup();
    assert.equal(listeners.has('beforeunload'), false); assert.equal(timers.size, 0);
    console.log('Mapa HQ: guardado agrupado, escritura serial, flush, errores, reintento, carga obsoleta y limpieza correctos.');
}
check().catch(error => { console.error(error); process.exitCode = 1; });
