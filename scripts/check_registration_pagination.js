// Prueba del listado y exportación CSV con datos ficticios, sin navegador ni BD.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

function element() {
  return {
    dataset: {}, style: {}, attributes: {}, listeners: {}, textContent: '', children: [],
    set innerHTML(value) { this.children = []; },
    classList: { toggle() {}, remove() {} },
    setAttribute(key, value) { this.attributes[key] = value; },
    getAttribute(key) { return this.attributes[key]; },
    addEventListener(key, callback) { this.listeners[key] = callback; },
    fire(key) { this.listeners[key].call(this, { preventDefault() {}, stopPropagation() {} }); },
    click() {}, remove() {}, appendChild(child) { this.children.push(child); }
  };
}

const rows = Array.from({ length: 35 }, (_, i) => {
  const row = element();
  row.dataset = {
    registrationId: String(i + 1), event: i < 17 ? 'alpha' : 'beta',
    date: '2026-12-01', turn: 'M', email: '', phone: '', team: '', document: '',
    created: String(i).padStart(3, '0'), attendeeCount: i < 17 ? '2' : '1'
  };
  row.querySelectorAll = () => Array.from({ length: 8 }, () => ({ textContent: row.dataset.event }));
  return row;
});
const ids = {};
[
  'registrationsTable', 'registrationFiltersForm', 'registrationCount',
  'registrationAttendeeCount', 'registrationPageSize', 'registrationPages',
  'registrationPageStatus', 'registrationPagination', 'exportRegistrationsCsv'
].forEach(id => { ids[id] = element(); });
ids.registrationsTable.querySelectorAll = () => rows;
ids.registrationsTable.querySelector = () => ({ appendChild() {} });
ids.registrationFiltersForm.elements = {};
['event', 'date', 'turn', 'email', 'phone', 'team', 'document'].forEach(key => {
  ids.registrationFiltersForm.elements[key] = { value: '' };
});
const sortButton = element();
sortButton.setAttribute('data-sort-key', 'created');
let csv = '';
const context = {
  window: { __burnoutLoadingPageScript: true }, console,
  setTimeout: callback => callback(),
  Blob: class { constructor(parts) { csv = parts.join(''); } },
  URL: { createObjectURL: () => 'blob:test', revokeObjectURL() {} },
  document: {
    getElementById: id => ids[id] || null,
    createElement: element, addEventListener() {}, body: element(),
    querySelectorAll(selector) {
      if (selector === '[data-sort-key]') return [sortButton];
      const match = selector.match(/^#registrationAttendees(\d+) tbody tr$/);
      if (!match) return [];
      return Array.from({ length: Number(rows[Number(match[1]) - 1].dataset.attendeeCount) }, () => ({
        querySelectorAll: () => ['1', 'Asistente ficticio', 'Documento ficticio'].map(textContent => ({ textContent }))
      }));
    }
  }
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/js/admin_registros.js'), 'utf8'), context);
context.window.initAdminRegistrosPage();
const visible = () => rows.filter(row => row.style.display !== 'none');
function goToNextPage() {
  const buttons = ids.registrationPages.children;
  const selected = buttons.find(button => button.className === 'is-active');
  const next = Number(selected.getAttribute('data-page')) + 1;
  buttons.find(button => Number(button.getAttribute('data-page')) === next).fire('click');
}
assert.equal(visible().length, 10);
assert.equal(ids.registrationCount.textContent, '35 registros');
assert.equal(ids.registrationPagination.hidden, false);
assert.equal(ids.registrationAttendeeCount.textContent, '52 asistentes');
goToNextPage();
assert.equal(visible()[0].dataset.registrationId, '11');
goToNextPage();
goToNextPage();
assert.equal(visible().length, 5);
assert.equal(ids.registrationPages.children.length === 0 || ids.registrationPages.children[ids.registrationPages.children.length - 1].className === 'is-active', true);
for (const size of [20, 30, 10]) {
  ids.registrationPageSize.value = String(size);
  ids.registrationPageSize.fire('change');
  assert.equal(visible().length, size);
  assert.equal(ids.registrationPages.children.length === 0 || ids.registrationPages.children[0].className === 'is-active', true);
}
ids.registrationFiltersForm.elements.event.value = 'alpha';
ids.registrationFiltersForm.fire('submit');
assert.equal(ids.registrationCount.textContent, '17 registros');
assert.equal(ids.registrationAttendeeCount.textContent, '34 asistentes');
assert.equal(visible().length, 10);
goToNextPage();
assert.equal(visible().length, 7);
ids.exportRegistrationsCsv.fire('click');
assert.equal(csv.split('\r\n').length, 35); // Cabecera + los 34 asistentes de ambas páginas.
sortButton.fire('click');
sortButton.fire('click');
assert.equal(visible().length, 10);
assert.equal(ids.registrationPages.children.length === 0 || ids.registrationPages.children[0].className === 'is-active', true);
assert.equal(visible().some(row => row.dataset.registrationId === '17'), true);
ids.registrationFiltersForm.elements.event.value = 'sin resultados';
ids.registrationFiltersForm.fire('submit');
assert.equal(visible().length, 0);
assert.equal(ids.registrationPagination.hidden, true);
assert.equal(ids.registrationCount.textContent, '0 registros');
assert.equal(ids.registrationAttendeeCount.textContent, '0 asistentes');
assert.equal(ids.registrationPages.children.length === 0 || ids.registrationPages.children[0].className === 'is-active', true);
assert.equal(ids.registrationPages.children.length === 0 || ids.registrationPages.children[ids.registrationPages.children.length - 1].className === 'is-active', true);
ids.registrationFiltersForm.elements.event.value = '';
ids.registrationFiltersForm.fire('reset');
assert.equal(visible().length, 10);
assert.equal(ids.registrationCount.textContent, '35 registros');
console.log('Paginación, filtros, totales, ordenación, tamaños y exportación CSV: correctos.');
