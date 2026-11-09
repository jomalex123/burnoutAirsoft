'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/analytics.js', 'utf8');
const key = 'burnout-analytics-consent-v1';

function browser(options = {}) {
  const documentEvents = {};
  const windowEvents = {};
  const remoteScripts = [];
  const replacements = [];
  const elements = [];
  const storage = new Map();
  if (options.choice) storage.set(key, JSON.stringify({ value: options.choice, time: options.time || Date.now() }));
  function element(tag) {
    const attributes = {};
    const events = {};
    return {
      tag, hidden: false, isConnected: true,
      setAttribute: (name, value) => { attributes[name] = value; },
      getAttribute: name => attributes[name],
      addEventListener: (name, fn) => { events[name] = fn; },
      click: () => events.click(),
      focus: () => {},
      querySelectorAll: () => [],
      querySelector: () => null,
      set innerHTML(value) {
        const deny = element('button');
        const grant = element('button');
        deny.setAttribute('data-cookie-choice', 'denied');
        grant.setAttribute('data-cookie-choice', 'granted');
        this.buttons = [deny, grant];
        this.withdrawal = element('p');
        this.querySelectorAll = () => this.buttons;
        this.querySelector = selector => selector === 'button' ? deny : this.withdrawal;
      }
    };
  }
  const location = new URL(options.url || 'https://burnoutairsoft.com/partidas.html');
  location.replace = value => replacements.push(value);
  const document = {
    readyState: 'complete', title: 'Partidas', referrer: 'https://example.org/?private=value', cookie: '',
    head: { appendChild: script => remoteScripts.push(script) },
    body: { appendChild: node => elements.push(node) },
    createElement: element,
    addEventListener: (name, fn) => { documentEvents[name] = fn; }
  };
  const window = {
    location,
    localStorage: {
      getItem: name => { if (options.blockedStorage) throw Error('blocked'); return storage.get(name) || null; },
      setItem: (name, value) => { if (options.blockedStorage) throw Error('blocked'); storage.set(name, value); },
      removeItem: name => storage.delete(name)
    },
    addEventListener: (name, fn) => { windowEvents[name] = fn; }
  };
  const context = vm.createContext({ window, document, URL, Date });
  vm.runInContext(source, context);
  return {
    window, document, remoteScripts, replacements, storage, elements,
    banner: elements[0], settings: elements[1],
    navigate: path => { location.pathname = path; location.search = ''; documentEvents['burnout:pageview'](); },
    storageEvent: () => windowEvents.storage({ key }),
    runAgain: () => vm.runInContext(source, context)
  };
}

let b = browser();
assert.equal(b.remoteScripts.length, 0, 'No Google requests before consent');
assert.equal(b.banner.hidden, false);
b.banner.buttons[0].click();
assert.equal(b.remoteScripts.length, 0, 'Rejecting must not load GTM');
assert.equal(JSON.parse(b.storage.get(key)).value, 'denied');
b.settings.click();
b.banner.buttons[1].click();
assert.equal(b.remoteScripts.length, 1);
assert.match(b.remoteScripts[0].src, /gtm\.js\?id=GTM-W2FVT27Q$/);
b.window.BurnoutAnalytics.init();
b.runAgain();
assert.equal(b.remoteScripts.length, 1, 'Only one container per document');
assert.equal(b.elements.length, 2, 'No duplicated controls');
const initialCommands = b.window.dataLayer;
assert.equal(initialCommands[0][0], 'consent');
assert.equal(initialCommands[0][2].analytics_storage, 'denied');
assert.equal(initialCommands[1][2].analytics_storage, 'granted');
assert.equal(initialCommands[1][2].ad_storage, 'denied');
assert.equal(initialCommands[2][1].page_referrer, 'https://example.org/');
assert.equal(initialCommands.filter(item => item.event === 'burnout_page_view').length, 0);
b.navigate('/galeria.html');
b.navigate('/galeria.html');
let visits = b.window.dataLayer.filter(item => item.event === 'burnout_page_view');
assert.equal(visits.length, 1, 'One event per partial navigation');
assert.equal(visits[0].page_referrer, 'https://burnoutairsoft.com/partidas.html');
assert.equal(visits[0].page_location, 'https://burnoutairsoft.com/galeria.html');
b.settings.click();
b.document.cookie = '_ga=sample; _ga_TEST=sample; session=keep';
b.banner.buttons[0].click();
assert.equal(b.replacements.length, 1, 'Withdrawing unloads the container through GET');
assert.equal(JSON.parse(b.storage.get(key)).value, 'denied');
assert.equal(b.window.dataLayer.at(-1)[2].analytics_storage, 'denied');
b.navigate('/burnout.html');
assert.equal(b.window.dataLayer.filter(item => item.event === 'burnout_page_view').length, 1);

for (const url of ['http://localhost/burnoutAirsoft/partidas.html', 'https://pre.burnoutairsoft.com/partidas.html', 'https://burnoutairsoft.com/admin.php', 'https://burnoutairsoft.com/hq_equipo.php']) {
  const test = browser({ url, choice: 'granted' });
  assert.equal(test.remoteScripts.length, 0, 'No analytics outside public production pages');
}
for (const choice of ['denied', 'invalid']) {
  assert.equal(browser({ choice }).remoteScripts.length, 0);
}
assert.equal(browser({ choice: 'granted', time: Date.now() - 181 * 86400000 }).remoteScripts.length, 0);
assert.equal(browser({ choice: 'granted', time: Date.now() + 86400000 }).remoteScripts.length, 0);
assert.equal(browser({ blockedStorage: true }).remoteScripts.length, 0);
b = browser({ choice: 'granted', url: 'https://www.burnoutairsoft.com/registro.php?event_id=1&email=private#secret' });
assert.equal(b.remoteScripts.length, 1);
assert.equal(b.window.dataLayer[2][1].page_location, 'https://www.burnoutairsoft.com/registro.php');
b.storage.set(key, JSON.stringify({ value: 'denied', time: Date.now() }));
b.storageEvent();
assert.equal(b.replacements.length, 1, 'Consent withdrawal propagates between tabs');

for (const page of ['index', 'burnout', 'campo', 'galeria', 'partidas', 'registro', 'normativa', 'privacidad']) {
  const html = fs.readFileSync(page + '.html', 'utf8');
  assert.equal((html.match(/src="assets\/js\/analytics\.js"/g) || []).length, 1);
  assert.match(html, /<head>\s*<script defer src="assets\/js\/analytics\.js"><\/script>/);
}
console.log('OK: consentimiento, rechazo, caducidad, dominio, carga única, navegación parcial, URLs sin parámetros, retirada, pestañas y páginas públicas. Sin solicitudes externas.');
