const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/hq.js'), 'utf8');
let pixels = null, calls = 0, response = null, pending = null;
const component = { async setAnnotations(data) { pixels = data; } };
const map = { burnoutMap: component };
const context = vm.createContext({
    root: { isConnected: true, querySelector: () => map },
    teamView: true, operationId: 1, generation: 1,
    window: { burnoutCreateMap: () => component },
    async api(action) { assert.equal(action, 'team_map'); calls++; return pending ? await pending : response; }
});
vm.runInContext(source.slice(source.indexOf('        var teamMapSignature'), source.indexOf('        var rosterBusy')), context);
(async () => {
    const active = { mission_id: 5, version: 'first' };
    response = { mission_map: { ...active, annotations: 'drawing A' } };
    await context.updateTeamMissionMap(active, 1);
    assert.equal(pixels, 'drawing A');
    await context.updateTeamMissionMap(active, 1);
    assert.equal(calls, 1, 'El sondeo sin cambios no vuelve a descargar el PNG');
    const changed = { mission_id: 5, version: 'second' };
    response = { mission_map: { ...changed, annotations: 'drawing B' } };
    await context.updateTeamMissionMap(changed, 1);
    assert.equal(pixels, 'drawing B', 'Actualizar el mapa de una misma misión recarga su dibujo');
    await context.updateTeamMissionMap({ mission_id: 6, version: '' }, 1);
    assert.equal(pixels, null, 'Una misión sin mapa no hereda el dibujo anterior');
    await context.updateTeamMissionMap(null, 1);
    assert.equal(pixels, null);
    let resolve;
    pending = new Promise(done => { resolve = done; });
    const loading = context.updateTeamMissionMap(active, 1);
    await Promise.resolve();
    context.clearTeamMissionMap();
    resolve({ mission_map: { ...active, annotations: 'stale drawing' } });
    await loading;
    assert.equal(pixels, null, 'Cerrar sesión invalida la descarga pendiente');
    pending = null;
    response = { mission_map: { mission_id: 99, version: 'other', annotations: 'other mission' } };
    await context.updateTeamMissionMap(active, 1);
    assert.equal(pixels, null, 'Una misión que cambia durante la descarga no muestra datos obsoletos');
    console.log('Mapa del enlace: misión activa, versiones, ausencia de mapa y descargas obsoletas correctos.');
})().catch(error => { console.error(error); process.exitCode = 1; });
