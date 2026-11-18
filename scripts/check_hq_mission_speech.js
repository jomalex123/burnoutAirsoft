const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/hq.js'), 'utf8');
const classes = new Set(), button = { title: '', attributes: {}, classList: { add: key => classes.add(key), remove: key => classes.delete(key) }, setAttribute(key, value) { this.attributes[key] = value; } };
const message = { textContent: '', classList: { add() {}, remove() {} } }, briefing = { value: '' };
let voices = [], spoken = [], cancelled = 0, changed, timer;
const engine = {
    getVoices: () => voices, speak: utterance => spoken.push(utterance), cancel: () => cancelled++,
    addEventListener(type, handler) { assert.equal(type, 'voiceschanged'); changed = handler; },
    removeEventListener(type, handler) { if (changed === handler) changed = null; }
};
const context = vm.createContext({ root: { isConnected: true, querySelector: () => button },
    teamView: false, feedback: message,
    catalogEditor: { querySelector: selector => selector.includes('briefing') ? briefing : message },
    window: { speechSynthesis: engine, SpeechSynthesisUtterance: class { constructor(text) { this.text = text; } },
        setTimeout(handler) { timer = handler; return 1; }, clearTimeout() { timer = null; } }
});
vm.runInContext(source.slice(source.indexOf('        var speechButton ='), source.indexOf('        var catalogDelete =')), context);
(async () => {
    voices = [{ name: 'David', lang: 'en-US', localService: true }, { name: 'Microsoft Pablo', lang: 'es-ES', localService: true }, { name: 'Elvira', lang: 'es-ES' }, { name: 'Jorge', lang: 'es-MX' }];
    briefing.value = '';
    await context.readMissionSpeech();
    assert.equal(spoken.length, 0);
    assert.match(message.textContent, /Escribe el guion/);
    briefing.value = 'Equipo Alpha, avanzad hasta el punto de reunión. '.repeat(120);
    await context.readMissionSpeech();
    assert.equal(spoken[0].voice.name, 'Microsoft Pablo');
    assert.equal(spoken[0].lang, 'es-ES');
    assert.equal(button.attributes['aria-pressed'], 'true');
    let index = 0;
    while (index < spoken.length) { assert.ok(spoken[index].text.length <= 220); spoken[index++].onend(); }
    assert.ok(spoken.length > 1, 'Guiones largos se leen en fragmentos consecutivos');
    assert.equal(spoken.map(item => item.text).join(' ').replace(/\s+/g, ' '), briefing.value.trim());
    assert.equal(button.attributes['aria-pressed'], 'false');
    spoken = [];
    await context.readMissionSpeech();
    const stale = spoken[0];
    await context.readMissionSpeech();
    const count = spoken.length;
    stale.onend(); stale.onerror();
    assert.equal(spoken.length, count, 'Cancelar impide que callbacks antiguos vuelvan a hablar');
    assert.ok(cancelled > 0);
    voices = [{ name: 'Jorge', lang: 'es-MX' }, { name: 'Elvira', lang: 'es-ES' }]; spoken = [];
    await context.readMissionSpeech();
    assert.equal(spoken.length, 0, 'No sustituye el acento de España por otro');
    assert.match(message.textContent, /es-ES/);
    voices = [];
    const pending = context.readMissionSpeech();
    assert.equal(typeof changed, 'function');
    voices = [{ name: 'Microsoft Álvaro', lang: 'es_ES', localService: true }]; changed(); await pending;
    assert.equal(spoken.length, 1, 'Espera a las voces que carga el navegador de forma asíncrona');
    context.stopMissionSpeech();
    voices = [];
    const closing = context.readMissionSpeech();
    context.stopMissionSpeech(); await closing;
    assert.equal(changed, null); assert.equal(timer, null);
    assert.equal(button.attributes['aria-pressed'], 'false');
    context.teamView = true; context.catalogEditor = null;
    voices = [{ name: 'Microsoft Pablo', lang: 'es-ES', localService: true }]; spoken = [];
    const teamButton = { ...button };
    await context.readMissionSpeech(teamButton, 'Equipo Bravo, mantened la posición.');
    assert.equal(spoken[0].text, 'Equipo Bravo, mantened la posición.');
    assert.equal(spoken[0].voice.name, 'Microsoft Pablo');
    await context.readMissionSpeech(teamButton, 'Equipo Bravo, mantened la posición.');
    assert.equal(context.speechActive, false, 'El altavoz del enlace detiene la lectura al pulsarlo otra vez');
    console.log('Audio de misión: voz es-ES, guiones largos, parada, carga de voces y errores correctos.');
})().catch(error => { console.error(error); process.exitCode = 1; });
