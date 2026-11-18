// Estado real de Jugadores con DOM simulado; sin navegador, paquetes ni MySQL.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/hq.js'), 'utf8');
const parts = {};
function element(tag = 'div', className = '', text = '') {
    return {
        tag, className, textContent: text, dataset: {}, children: [], disabled: false, attributes: {},
        classList: { toggle() {}, add() {}, remove() {} },
        addEventListener(key, handler) { this.listeners = this.listeners || {}; this.listeners[key] = handler; },
        append(...children) { this.children.push(...children); },
        replaceChildren(...children) { this.children = children; },
        setAttribute(key, value) { this.attributes[key] = value; },
        hasAttribute(key) { return Object.prototype.hasOwnProperty.call(this.attributes, key); },
        getClientRects() { return [1]; },
        focus() { this.focused = true; }
    };
}
for (const team of ['alpha', 'bravo']) {
    for (const type of ['list', 'total', 'roles']) parts[`[data-hq-players-${type}="${team}"]`] = element();
}
const moves = ['alpha', 'bravo'].map(team => {
    const button = element('button'); button.dataset.hqPlayersMove = team; return button;
});
parts['[data-hq-players-balance]'] = element('button');
const allGroups = () => ['alpha', 'bravo'].flatMap(team => parts[`[data-hq-players-list="${team}"]`].children.filter(child => child.tag === 'button'));
const roleNames = () => (parts['[data-hq-roster-list]']?.children || []).filter(row => row.className === 'hq-team-player').map(row => row.children[0].children[0]);
const groups = [
    { id: 1, name: '<script>grupo</script>', team: 'alpha', points: 4, players: [{ name: '<img src=x>', role: 'Fusilero', points: 1 }, { name: 'Jugador 2', role: 'Apoyo', points: 3 }] },
    { id: 2, name: '', team: 'bravo', points: 2, players: [{ name: 'Jugador 3', role: 'Tirador Selecto', points: 2 }] }
];
const context = vm.createContext({
    teamView: false, playerGroups: [], selectedPlayerGroup: 0, playersSignature: '', playersBusy: false, playersGeneration: 0,
    operation: { status: 'active' }, operationId: 10, section: 'jugadores', timer: 0,
    document: { activeElement: null, body: element(), createElementNS(namespace, tag) { return element(tag); } }, node: element, window: { clearTimeout() {} }, schedule() {}, report() {},
    root: {
        isConnected: true,
        querySelector(selector) { return parts[selector] || roleNames().find(select => selector === '[data-hq-player-role="' + select.dataset.hqPlayerRole + '"]'); },
        querySelectorAll(selector) { return selector === '[data-hq-players-move]' ? moves : selector === '[data-hq-player-role]' ? roleNames() : allGroups(); }
    }, groups
});
vm.runInContext(source.slice(source.indexOf('        function playerControls()'), source.indexOf('        function resetChannel()')), context);
vm.runInContext('renderPlayers(groups)', context);
assert.equal(allGroups().length, 2);
assert.equal(allGroups()[0].children.length, 3); // cabecera + ambos asistentes, una sola selección.
assert.equal(allGroups()[0].children[1].textContent, '<img src=x> · Fusilero');
assert.ok(!allGroups()[0].children[0].textContent.includes('puntos'));
assert.ok(!parts['[data-hq-players-roles="alpha"]'].textContent.includes('Sin especificar: 0'));
assert.equal(parts['[data-hq-players-total="alpha"]'].textContent, '2 jugadores · 4 puntos');
assert.ok(moves.every(button => button.disabled));
vm.runInContext('selectedPlayerGroup = 1; playerControls()', context);
assert.equal(moves[0].disabled, true);
assert.equal(moves[1].disabled, false);
assert.equal(allGroups()[0].attributes['aria-pressed'], 'true');
vm.runInContext("operation.status = 'closed'; playerControls()", context);
assert.ok(moves.every(button => button.disabled));
assert.equal(parts['[data-hq-players-balance]'].disabled, true);
assert.ok(allGroups().every(button => button.disabled));
vm.runInContext("operation.status = 'active'; playersBusy = true; playerControls()", context);
assert.ok(moves.every(button => button.disabled));
vm.runInContext('playersBusy = false; renderPlayers([])', context);
assert.equal(context.selectedPlayerGroup, 0);
assert.equal(parts['[data-hq-players-balance]'].disabled, true);
for (const part of ['team', 'count', 'list']) parts['[data-hq-roster-' + part + ']'] = element();
context.teamView = true;
context.rosterSignature = '';
context.teamRoster = []; context.rolePlayer = null; context.roleOpener = null;
context.rosterBusy = false;
const choices = ['TL', 'Ingeniero', 'Radio Operator', 'Segundo TL', 'Medico'].map(role => {
    const button = element('button'); button.dataset.hqRoleChoice = role; return button;
});
const popupParts = { '#hqPlayerRoleTitle': element(), '[data-hq-role-feedback]': element() };
context.rolePopup = element();
context.rolePopup.querySelector = selector => popupParts[selector] || (selector.includes('aria-pressed') ? choices.find(button => button.attributes['aria-pressed'] === 'true') : choices[0]);
context.rolePopup.querySelectorAll = () => choices;
vm.runInContext(source.slice(source.indexOf('        function trapPopupFocus('), source.indexOf('        function closeImport()')), context);
vm.runInContext(source.slice(source.indexOf('        function playerRoleIcon('), source.indexOf('        function playerControls()')), context);
context.roster = { team: 'bravo', count: 1, players: [{ id: 7, name: '<script>Jugador</script>', role: 'Apoyo', team_name: 'Equipo de prueba', tactical_role: 'TL' }] };
vm.runInContext('renderTeamPlayers(roster)', context);
assert.equal(parts['[data-hq-roster-team]'].textContent, 'Equipo Bravo');
assert.equal(parts['[data-hq-roster-count]'].textContent, '1 jugador');
assert.equal(parts['[data-hq-roster-list]'].children[0].children[0].children[0].textContent, '<script>Jugador</script>');
assert.equal(parts['[data-hq-roster-list]'].children[0].children[0].children[1].textContent, 'Equipo: Equipo de prueba');
assert.equal(parts['[data-hq-roster-list]'].children[0].children[0].children[2].textContent, 'Apoyo');
assert.equal(roleNames()[0].tag, 'button');
assert.equal(roleNames()[0].attributes['aria-haspopup'], 'dialog');
assert.equal(parts['[data-hq-roster-list]'].children[0].children[1].attributes.title, 'Team Leader');
assert.equal(parts['[data-hq-roster-list]'].children[0].dataset.hqFunction, 'TL');
context.opener = roleNames()[0];
vm.runInContext('openRolePopup(opener)', context);
assert.equal(context.rolePopup.attributes['aria-hidden'], 'false');
assert.equal(choices[0].attributes['aria-pressed'], 'true');
let prevented = false;
context.document.activeElement = choices[4];
context.trapPopupFocus({ key: 'Tab', shiftKey: false, preventDefault() { prevented = true; } }, context.rolePopup);
assert.equal(prevented, true);
assert.equal(choices[0].focused, true);
context.document.activeElement = choices[0];
context.trapPopupFocus({ key: 'Tab', shiftKey: true, preventDefault() {} }, context.rolePopup);
assert.equal(choices[4].focused, true);
context.rolePopup.listeners.keydown({ key: 'Escape', preventDefault() {} });
assert.equal(context.rolePopup.attributes['aria-hidden'], 'true');
context.document.activeElement = null;
vm.runInContext('closeRolePopup()', context);
assert.equal(context.rolePopup.attributes['aria-hidden'], 'true');
assert.equal(parts['[data-hq-roster-list]'].children[0].tag, 'li');
vm.runInContext('renderTeamPlayers(null)', context);
assert.equal(parts['[data-hq-roster-count]'].textContent, '0 jugadores');
assert.equal(parts['[data-hq-roster-list]'].children[0].textContent, 'Sin jugadores asignados.');
context.teamView = false;
async function run() {
    vm.runInContext('renderPlayers(groups)', context);
    let complete;
    context.api = () => new Promise(resolve => { complete = resolve; });
    const pending = vm.runInContext('loadPlayers()', context);
    vm.runInContext('operationId = 11; playersGeneration++; renderPlayers([])', context);
    complete({ groups });
    await pending;
    assert.equal(allGroups().length, 0, 'Respuesta de otra partida descartada');
    context.selectedPlayerGroup = 1;
    context.api = () => new Promise(resolve => { complete = resolve; });
    context.moveButton = moves[1];
    const mutation = vm.runInContext('changePlayers(moveButton)', context);
    assert.equal(context.playersBusy, true);
    vm.runInContext('operationId = 12; playersGeneration++; renderPlayers([])', context);
    complete({ groups });
    await mutation;
    assert.equal(allGroups().length, 0, 'Traslado tardío no altera la nueva partida');
    assert.equal(context.playersBusy, false);
    context.teamView = true;
    context.rosterBusy = false;
    context.generation = 0;
    vm.runInContext('renderTeamPlayers(roster)', context);
    context.opener = roleNames()[0];
    vm.runInContext('openRolePopup(opener)', context);
    context.choice = choices[1];
    context.api = (action, payload) => {
        assert.equal(action, 'player_role');
        assert.equal(payload.player_id, 7);
        assert.equal(payload.tactical_role, 'Ingeniero');
        return Promise.resolve({ roster: { ...context.roster, players: [{ ...context.roster.players[0], tactical_role: 'Ingeniero' }] } });
    };
    await vm.runInContext('savePlayerRole(choice)', context);
    assert.equal(context.rolePopup.attributes['aria-hidden'], 'true');
    assert.equal(parts['[data-hq-roster-list]'].children[0].children[1].attributes.title, 'Ingeniero');
    assert.equal(context.rosterBusy, false);
    context.opener = roleNames()[0];
    vm.runInContext('openRolePopup(opener)', context);
    context.choice = choices[0];
    context.api = () => Promise.reject(new Error('Error de prueba'));
    await vm.runInContext('savePlayerRole(choice)', context);
    assert.equal(popupParts['[data-hq-role-feedback]'].textContent, 'Error de prueba');
    assert.equal(context.rolePopup.attributes['aria-hidden'], 'false');
    assert.equal(choices[1].attributes['aria-pressed'], 'true', 'Fallo conserva la funcion anterior');
    context.choice = choices[1];
    context.api = (action, payload) => {
        assert.equal(payload.tactical_role, '', 'Repetir icono activo retira la funcion');
        return Promise.resolve({ roster: { ...context.roster, players: [{ ...context.roster.players[0], tactical_role: null }] } });
    };
    await vm.runInContext('savePlayerRole(choice)', context);
    assert.equal(parts['[data-hq-roster-list]'].children[0].children.length, 1);
    assert.equal(parts['[data-hq-roster-list]'].children[0].dataset.hqFunction, undefined);
    vm.runInContext("operation.status = 'closed'; renderTeamPlayers(roster)", context);
    assert.equal(roleNames()[0].disabled, true);
    console.log('Jugadores: grupos completos, puntos, selección, permisos visuales, texto seguro y respuestas tardías correctos.');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
