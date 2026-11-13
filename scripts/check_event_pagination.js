'use strict';

// DOM mínimo para verificar la inicialización real, sin navegador ni paquetes npm.
var assert = require('node:assert/strict');
var fs = require('node:fs');
var vm = require('node:vm');
var source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/admin_partidas.js'), 'utf8');

function element() {
  return {
    children: [], attrs: {}, listeners: {}, hidden: false,
    appendChild: function (child) { this.children.push(child); },
    setAttribute: function (key, value) { this.attrs[key] = value; },
    getAttribute: function (key) { return this.attrs[key] || null; },
    addEventListener: function (type, callback) { this.listeners[type] = callback; },
    focus: function () {},
    set innerHTML(value) { this.children = []; },
    querySelector: function () { return this.children.find(function (child) { return child.attrs['aria-current'] === 'page'; }); }
  };
}
function list(count) {
  var result = element();
  for (var index = 0; index < count; index++) { result.children.push(element()); }
  var pages = element();
  result.nextElementSibling = { hidden: true, querySelector: function () { return pages; } };
  result.pages = pages;
  return result;
}
function visible(items) { return items.children.filter(function (row) { return !row.hidden; }).length; }
var first = list(23);
var second = list(11);
var root = element();
root.querySelectorAll = function (selector) { return selector === '[data-event-paged-list]' ? [first, second] : []; };
var ids = { calendarGrid: element(), calendarTitle: element(), prevMonth: element(), nextMonth: element() };
var document = {
  querySelector: function () { return root; },
  querySelectorAll: function () { return []; },
  getElementById: function (id) { return ids[id] || null; },
  createElement: element,
  addEventListener: function () {}
};
var context = { window: { BurnoutAdminEvents: [] }, document: document, Date: Date };
vm.runInNewContext(source, context);
context.window.initAdminPartidasPage();
assert.equal(visible(first), 5);
assert.equal(visible(second), 5);
assert.equal(first.pages.children.length, 5);
first.pages.children[4].listeners.click.call(first.pages.children[4]);
assert.equal(visible(first), 3);
assert.equal(visible(second), 5, 'Listados independientes');
assert.equal(first.pages.children[4].attrs['aria-current'], 'page');
var previousPages = first.pages.children;
context.window.initAdminPartidasPage();
assert.equal(first.pages.children, previousPages, 'No duplicar inicialización');
// Un main nuevo simula la navegación parcial.
root.attrs = {};
first = list(5);
second = list(0);
context.window.initAdminPartidasPage();
assert.equal(visible(first), 5);
assert.equal(first.nextElementSibling.hidden, true);
assert.equal(second.nextElementSibling.hidden, true);
assert.equal(visible(second), 0);
console.log('OK: 5 eventos por página, última página, listados independientes, carga directa y reinicialización parcial con DOM simulado.');
