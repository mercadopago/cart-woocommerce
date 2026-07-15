const path = require('path');
const { loadFile } = require('../helpers/load-file');

// InputDocument is a Web Component (extends HTMLElement) and ends with
// customElements.define(...). It is not under assets/js (no alias), so we
// resolve the real path and load it via the vm helper, stubbing the custom
// element registry so define() is a no-op in the test context.
const inputDocumentPath = path.resolve(
  __dirname,
  '../../../packages/narciso/components/input-document/InputDocument.js'
);

const InputDocument = loadFile(inputDocumentPath, 'InputDocument', {
  HTMLElement: global.HTMLElement,
  customElements: { define: () => {} },
  document: global.document,
  window: global.window,
});

describe('InputDocument - isValidCNPJ (alphanumeric CNPJ)', () => {
  // isValidCNPJ only uses `this.updateHelperErrorMessage` and `this.getAttribute`
  // from the instance, so a minimal stub is enough to exercise the algorithm.
  function isValidCNPJ(value) {
    const ctx = {
      updateHelperErrorMessage: () => {},
      getAttribute: () => 'error',
    };
    const helper = { setAttribute: () => {}, querySelector: () => ({ innerHTML: '' }) };
    return InputDocument.prototype.isValidCNPJ.call(ctx, value, helper);
  }

  // Table-driven cases (test.each). Add new scenarios as rows only.
  const cases = [
    // --- Spec cases ---
    { input: '12.ABC.345/01DE-35', expected: true, desc: 'valid alphanumeric (SERPRO example)' },
    { input: '11.222.333/0001-81', expected: true, desc: 'valid legacy numeric (no regression)' },
    { input: '12.ABC.345/01DE-99', expected: false, desc: 'alphanumeric with a wrong check digit' },
    { input: 'AAAAAAAAAAAAAA', expected: false, desc: 'repeated alphanumeric sequence' },
    { input: '00000000000000', expected: false, desc: 'repeated numeric sequence' },
    { input: '12.abc.345/01de-35', expected: true, desc: 'lowercase (normalized via toUpperCase)' },
    { input: '12ABC345', expected: false, desc: 'invalid length after strip' },
    { input: '12ABC3450001A1', expected: false, desc: 'letter in the check-digit position (pos 13)' },
    // --- Cases generated from the SERPRO DV algorithm ---
    { input: 'ME.LI1.234/5678-93', expected: true, desc: 'valid generated alphanumeric (calculated check digit)' },
    { input: 'AB.12C.D34/EF56-02', expected: true, desc: 'valid generated alphanumeric (calculated check digit)' },
    { input: 'ME.LI1.234/5678-04', expected: false, desc: '14 chars, valid format, wrong check digit' },
  ];

  test.each(cases)('isValidCNPJ("$input") === $expected — $desc', ({ input, expected }) => {
    expect(isValidCNPJ(input)).toBe(expected);
  });
});

describe('InputDocument - setMaskInputDocument (CNPJ)', () => {
  // Fires the mask `input` handler and returns both the displayed value and
  // the raw value stored in the hidden field.
  function applyMask(typed) {
    const select = document.createElement('select');
    const option = document.createElement('option');
    option.value = 'CNPJ';
    option.text = 'CNPJ';
    select.appendChild(option);
    select.value = 'CNPJ';

    const input = document.createElement('input');
    const hidden = document.createElement('input');

    // setMaskInputDocument only uses `this.validateDocumentRealTime` from the instance.
    const ctx = { validateDocumentRealTime: () => {} };
    InputDocument.prototype.setMaskInputDocument.call(ctx, select, input, hidden);

    input.value = typed;
    input.dispatchEvent(new Event('input'));
    return { display: input.value, hidden: hidden.value };
  }

  // display = as typed (change 5: preserves case); hidden = raw uppercase (change 6a)
  const maskCases = [
    { typed: '12abc34501de35', display: '12.abc.345/01de-35', hidden: '12ABC34501DE35', desc: 'lowercase preserved in the display (change 5)' },
    { typed: '12ABC34501DE35', display: '12.ABC.345/01DE-35', hidden: '12ABC34501DE35', desc: 'uppercase' },
    { typed: '11222333000181', display: '11.222.333/0001-81', hidden: '11222333000181', desc: 'legacy numeric (no regression)' },
  ];

  test.each(maskCases)('mask("$typed") → displays "$display" / hidden "$hidden" — $desc', ({ typed, display, hidden }) => {
    const r = applyMask(typed);
    expect(r.display).toBe(display);
    expect(r.hidden).toBe(hidden);
  });
});

describe('InputDocument - isValidCNPJ helper message', () => {
  // Captures which message attribute was requested via getAttribute so we can assert
  // 'helper-invalid' (wrong length) vs 'helper-wrong' (invalid CNPJ) per error branch.
  function isValidCNPJMessage(value) {
    let message = null;
    const ctx = {
      updateHelperErrorMessage: (_helper, msg) => { message = msg; },
      getAttribute: (attr) => attr,
    };
    const helper = { setAttribute: () => {}, querySelector: () => ({ innerHTML: '' }) };
    InputDocument.prototype.isValidCNPJ.call(ctx, value, helper);
    return message;
  }

  test('short input with repeated chars shows helper-invalid (not helper-wrong)', () => {
    expect(isValidCNPJMessage('A')).toBe('helper-invalid');
    expect(isValidCNPJMessage('AAAA')).toBe('helper-invalid');
  });

  test('14 repeated chars show helper-wrong', () => {
    expect(isValidCNPJMessage('AAAAAAAAAAAAAA')).toBe('helper-wrong');
    expect(isValidCNPJMessage('00000000000000')).toBe('helper-wrong');
  });
});
