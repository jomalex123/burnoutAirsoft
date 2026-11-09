'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function loadFunctions(path, names, context) {
  const source = fs.readFileSync(path, 'utf8');
  names.forEach(function (name) {
    const start = source.indexOf('  function ' + name + '(');
    assert.ok(start >= 0, 'Missing function ' + name);
    const next = source.indexOf('\n  }', start + 1) + 4;
    assert.ok(next > start, 'Missing function boundary');
    vm.runInContext(source.slice(start, next), context);
  });
}

for (const admin of [false, true]) {
  const fields = [];
  const errors = new Map();
  const form = {
    find: () => ({ each: fn => fields.forEach(field => fn.call(field)) }),
    querySelectorAll: () => fields
  };
  const jquery = value => value;
  jquery.trim = value => value.trim();
  const context = vm.createContext({
    $: jquery,
    $form: form,
    lateRegistrationForm: form,
    capacityRemaining: 40,
    setFieldError: (field, message) => errors.set(field, message),
    setLateRegistrationFieldError: (field, message) => errors.set(field, message)
  });
  const names = ['hasValidDniLetter', 'normalizeDocument', 'isValidDocument', 'isValidEmail', 'isValidPhone'];
  names.push(...(admin ? ['validateLateRegistrationField'] : ['isAttendeeNameField', 'isAttendeeDocumentField', 'validateField']));
  loadFunctions(admin ? 'assets/js/admin_partidas.js' : 'assets/js/registro.js', names, context);
  const prefix = admin ? 'lateRegistrationAttendeeDocument' : 'asistenteDocumento';
  function field(number, value) {
    const result = {
      id: prefix + number, value, required: true,
      attr: name => name === 'id' ? result.id : 'text',
      prop: name => name === 'required',
      val: () => result.value,
      get: () => result
    };
    return result;
  }
  const validate = admin ? context.validateLateRegistrationField : context.validateField;
  assert.equal(context.normalizeDocument('12345678 z-\t\r\n'), '12345678Z');
  assert.equal(context.isValidDocument('12345678 Z'), true);
  assert.equal(context.isValidDocument('123456078-Z'), false, 'Extra digit must be rejected');
  assert.equal(context.isValidDocument('12345678A'), false, 'Wrong letter must be rejected');
  fields.push(field(1, '12345678z'), field(2, '12345678-Z'));
  assert.equal(validate(fields[0]), false);
  assert.equal(validate(fields[1]), false);
  fields[1].value = '87654321X';
  assert.equal(validate(fields[0]), true, 'Correcting duplicate must clear error');
  assert.equal(validate(fields[1]), true);
}
console.log('OK: formularios público y administrativo: normalización, duplicados, corrección, dígito extra y letra incorrecta.');
