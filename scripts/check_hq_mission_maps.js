const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/hq.js'), 'utf8');
function element() {
    const classes = new Set();
    return { style: {}, hidden: false, disabled: false, isConnected: true, attributes: {},
        classList: { add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name) },
        setAttribute(name, value) { this.attributes[name] = value; },
        focus() { this.focused = true; }, addEventListener(type, listener) { this[type] = listener; } };
}
const popup = element(), editor = element(), importer = element(), save = element(), message = element(), close = element();
const editable = element(), preview = element();
const editApi = { async setAnnotations(data) { this.data = data; }, changeZoom() {}, closePopups() {}, async flush() { return true; }, exportAnnotations() { return 'data:image/png;base64,EDIT'; } };
const readApi = { ...editApi };
editable.querySelector = () => editApi; preview.querySelector = () => readApi;
const parts = { '[data-hq-mission-map-editable]': editable, '[data-hq-mission-map-preview]': preview,
    '[data-hq-mission-map-save]': save, '[data-hq-mission-map-feedback]': message, 'button[data-hq-mission-map-close]': close };
popup.querySelector = selector => parts[selector]; popup.contains = () => false;
let calls = [], fail = false;
const context = vm.createContext({
    root: { isConnected: true }, document: { body: element(), fullscreenElement: null },
    window: { burnoutCreateMap: value => value }, missionMapPopup: popup,
    catalogEditor: editor, importPopup: importer, missionMapGeneration: 0, missionMapBusy: false,
    missionMapReady: false, missionMapDraft: null, missionMapParent: null, missionMapOpener: null, missionMapApi: null,
    async api(action, data, post) { calls.push({ action, data, post }); if (fail) throw new Error('Error de prueba'); return { annotations: 'data:image/png;base64,SAVED' }; }
});
vm.runInContext(source.slice(source.indexOf('        async function closeMissionMap('), source.indexOf('        function missionSource(')), context);
const saveStart = source.indexOf("            missionMapPopup.querySelector('[data-hq-mission-map-save]').addEventListener");
vm.runInContext(source.slice(saveStart, source.indexOf("            root.querySelectorAll('[data-hq-catalog-browser]')", saveStart)), context);
(async () => {
    const opener = element();
    editor.classList.add('is-open');
    await context.openMissionMap(42, true, opener);
    assert.equal(editor.classList.contains('is-open'), false);
    assert.equal(popup.classList.contains('is-open'), true);
    assert.equal(editApi.data, 'data:image/png;base64,SAVED');
    assert.equal(save.hidden, false); assert.equal(save.disabled, false);
    await save.click({});
    assert.equal(calls.at(-1).action, 'catalog_map_save');
    assert.equal(calls.at(-1).data.catalog_id, 42);
    assert.equal(calls.at(-1).post, true);
    assert.equal(editor.classList.contains('is-open'), true);
    assert.equal(opener.focused, true);
    await context.openMissionMap(42, false, opener);
    assert.equal(save.hidden, true); assert.equal(editable.hidden, true);
    assert.equal(readApi.data, 'data:image/png;base64,SAVED');
    await context.closeMissionMap();
    assert.equal(importer.classList.contains('is-open'), true);
    calls = [];
    await context.openMissionMap(0, true, opener);
    assert.equal(editApi.data, null);
    await save.click({});
    assert.equal(calls.length, 0, 'El mapa nuevo espera a guardar su misión');
    assert.equal(context.missionMapDraft, 'data:image/png;base64,EDIT');
    await context.openMissionMap(0, true, opener);
    assert.equal(editApi.data, 'data:image/png;base64,EDIT');
    await context.closeMissionMap();
    fail = true;
    await context.openMissionMap(42, true, opener);
    assert.equal(save.disabled, true);
    assert.equal(editable.style.visibility, 'hidden', 'Un error no muestra el mapa de otra misión');
    assert.equal(message.textContent, 'Error de prueba');
    console.log('Mapas de misión: editor, consulta, guardar, retorno, borrador y errores correctos.');
})().catch(error => { console.error(error); process.exitCode = 1; });
