const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
// Comprueba el filtrado y paginacion reales del catalogo sin navegador ni BD.
const source = fs.readFileSync(path.join(__dirname, '../assets/js/hq.js'), 'utf8');
function element() {
    return {
        children: [], attributes: {}, dataset: {}, textContent: '', disabled: false,
        classList: { toggle() {} },
        setAttribute(key, value) { this.attributes[key] = value; },
        append(...children) { this.children.push(...children); },
        replaceChildren(...children) { this.children = children; }
    };
}
function browser(mode) {
    const parts = {};
    ['body', 'count', 'pages', 'pagination'].forEach(key => { parts[key] = element(); });
    const sorters = ['title', 'category'].map(key => {
        const button = element(); button.dataset.hqTableSort = key;
        button.header = element(); button.closest = () => button.header; return button;
    });
    return {
        dataset: { hqCatalogBrowser: mode }, parts, sorters,
        querySelector(selector) { return parts[selector.replace('[data-hq-table-', '').replace(']', '')]; },
        querySelectorAll() { return sorters; }
    };
}
const catalog = Array.from({ length: 35 }, (_, i) => ({
    id: i + 1, title: 'Mision ' + String(i + 1).padStart(2, '0'),
    briefing: i < 17 ? 'Extracci\u00f3n del reh\u00e9n' : 'Defender el puesto',
    category: i < 17 ? 'Rescate' : 'Defensa', mission_type: i % 2 ? 'Escolta' : 'Reconocimiento'
}));
catalog.push({ id: 36, title: '<script>texto</script>', briefing: 'Sin etiquetas', category: '', mission_type: '' });
const context = vm.createContext({ catalog, document: { createElement: element, createElementNS: element } });
vm.runInContext(source.slice(source.indexOf('        function normalizeCatalog('), source.indexOf('        function syncCatalogTables(')) + source.slice(source.indexOf('        function node('), source.indexOf('        function report(')), context);
const state = { element: browser('manage'), filters: {}, sort: 'title', direction: 1, page: 1, size: 5, selected: 0 };
const render = () => context.renderCatalogTable(state);
const rows = () => state.element.parts.body.children;
render();
assert.equal(rows().length, 5);
assert.equal(state.element.parts.count.textContent, '36 de 36 misiones');
assert.equal(state.element.parts.pages.children.length, 8);
assert.equal(state.element.parts.pages.children[0].attributes['aria-current'], 'page');
assert.equal(rows()[0].children.length, 3);
assert.equal(rows()[0].children[2].children[0].children.length, 2);
assert.equal(rows()[0].children[2].children[0].children[0].dataset.hqCatalogAction, 'edit');
assert.equal(rows()[0].children[2].children[0].children[1].dataset.hqCatalogAction, 'delete');
state.page = 8; render(); assert.equal(rows().length, 1); assert.equal(state.element.parts.pages.children[7].attributes['aria-current'], 'page');
state.size = 10; state.page = 1; render(); assert.equal(rows().length, 10);
state.size = 15; render(); assert.equal(rows().length, 15);
state.filters = { search: 'EXTRACCION' }; state.page = 1; render(); assert.equal(state.element.parts.count.textContent, '17 de 36 misiones');
state.filters = { category: 'Rescate', search: 'extraccion' }; render(); assert.equal(state.element.parts.count.textContent, '17 de 36 misiones');
state.filters = { category: 'Sin clasificar' }; render(); assert.equal(rows().length, 1); assert.equal(rows()[0].children[0].textContent, '<script>texto</script>');
state.filters = { search: 'sin coincidencia' }; render(); assert.equal(rows()[0].children[0].colSpan, 3); assert.equal(state.element.parts.pagination.hidden, true);
state.filters = {}; state.sort = 'title'; state.direction = -1; state.page = 1; render(); assert.equal(rows()[0].children[0].textContent, 'Mision 35');
assert.equal(state.element.sorters[0].header.attributes['aria-sort'], 'descending');
const imported = { element: browser('import'), filters: { category: 'Rescate' }, sort: 'title', direction: 1, page: 1, size: 5, selected: 1 };
context.renderCatalogTable(imported);
assert.equal(imported.element.parts.count.textContent, '17 de 36 misiones');
assert.equal(imported.element.parts.body.children[0].children.length, 2);
assert.equal(imported.element.parts.body.children[0].attributes['aria-selected'], 'true');
assert.equal(imported.element.parts.body.children[0].tabIndex, 0);
assert.equal(imported.element.parts.body.children[0].dataset.hqCatalogItem, 1);
assert.equal(imported.element.parts.body.children.length, 5);
imported.size = 15;
context.renderCatalogTable(imported);
assert.equal(imported.size, 5);
assert.equal(imported.element.parts.body.children.length, 5);
assert.equal(state.element.parts.count.textContent, '36 de 36 misiones');
const scriptList = element();
scriptList.contains = () => false;
context.root = { querySelector: () => scriptList };
context.operationId = 1;
context.operation = { status: 'active' };
context.scriptSignature = '';
vm.runInContext(source.slice(source.indexOf('        function renderScript('), source.indexOf('        function renderMissions(')), context);
context.renderScript(catalog.slice(0, 2));
const firstScriptRow = scriptList.children[0];
context.renderScript(catalog.slice(0, 2));
assert.equal(scriptList.children[0], firstScriptRow, 'El refresco sin cambios conserva las filas y el foco');
context.operation.status = 'closed';
context.renderScript(catalog.slice(0, 2));
assert.equal(scriptList.children[0].children[1].children.every(button => button.disabled), true);
console.log('Catalogo HQ: filtros combinados, acentos, clasificacion vacia, orden, paginacion, seleccion y tablas independientes correctos.');
