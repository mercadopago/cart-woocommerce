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
  // InputDocument requires DocumentHandlerFactory at load time. Resolve it for real (relative to the source file) so mask() and validation work.
  require: (mod) => require(path.resolve(path.dirname(inputDocumentPath), mod)),
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

    // setMaskInputDocument uses validateDocumentRealTime, getAttribute (site-id) and buildDocumentNameWithSiteId from the instance; provide them so the DocumentHandlerFactory-based mask runs. CNPJ is not site-scoped, so site-id '' is fine.
    const ctx = {
      validateDocumentRealTime: () => {},
      getAttribute: () => '',
      buildDocumentNameWithSiteId: InputDocument.prototype.buildDocumentNameWithSiteId,
    };
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

describe('InputDocument - createSelect default selection', () => {
  // Builds the <select> the same way the component does, given the raw document
  // values the SDK/template provides and the store site-id.
  function buildSelect(siteId, documents) {
    const attrs = {
      'site-id': siteId,
      'select-name': 'doc',
      'select-id': 'doc',
      'select-data-checkout': 'doc',
    };
    const ctx = {
      getAttribute: (attr) => attrs[attr] ?? '',
      createOption: InputDocument.prototype.createOption,
      buildDocumentNameWithSiteId: InputDocument.prototype.buildDocumentNameWithSiteId,
    };
    const component = document.createElement('div');
    const helper = document.createElement('div');
    return InputDocument.prototype.createSelect.call(ctx, component, helper, documents, false);
  }

  // The handler key for CI/DNI/CE is prefixed by site-id (MLA_DNI), while the option
  // values are the raw SDK types (DNI) — the default must still be selected.
  test('selects the prefixed-key default (MLA → DNI)', () => {
    expect(buildSelect('MLA', ['CI', 'DNI', 'LC']).value).toBe('DNI');
  });

  test('selects the prefixed-key default (MLU → CI)', () => {
    expect(buildSelect('MLU', ['Otro', 'CI']).value).toBe('CI');
  });

  test('selects the non-prefixed default (MLB → CPF)', () => {
    expect(buildSelect('MLB', ['CNPJ', 'CPF']).value).toBe('CPF');
  });

  test('falls back to the first option when no default matches', () => {
    expect(buildSelect('MLA', ['LC', 'LE']).value).toBe('LC');
  });

  // An empty array is truthy, so without a length guard createSelect would
  // dereference select.options[0].value and throw, breaking the whole render.
  test('does not throw when the document list is empty (SDK-populated path)', () => {
    expect(() => buildSelect('MLB', [])).not.toThrow();
  });

  // The SDK may deliver the type in lower/mixed case; the site prefix must still
  // apply so the document keeps its per-site handler (not GenericHandler).
  test('selects the site-scoped default even when the type comes lowercase', () => {
    expect(buildSelect('MLA', ['ci', 'dni', 'lc']).value).toBe('dni');
  });
});

describe('InputDocument - setInputProperties (maxlength)', () => {
  // Runs the real setInputProperties against a stubbed select/input and returns
  // the maxlength it writes to the DOM.
  function maxlengthFor(siteId, rawType) {
    const select = document.createElement('select');
    const option = document.createElement('option');
    option.value = rawType;
    option.text = rawType;
    select.appendChild(option);
    select.value = rawType;

    const input = document.createElement('input');
    const ctx = {
      buildDocumentNameWithSiteId: InputDocument.prototype.buildDocumentNameWithSiteId,
      getPermissiveMaxLength: InputDocument.prototype.getPermissiveMaxLength,
    };
    InputDocument.prototype.setInputProperties.call(ctx, select, input, siteId);
    return Number(input.getAttribute('maxlength'));
  }

  describe('given a fixed-length document (CPF/CNPJ) or CI', () => {
    test.each([
      { siteId: 'MLB', rawType: 'CPF', expected: 14 },
      { siteId: 'MLB', rawType: 'CNPJ', expected: 18 },
      { siteId: 'MLA', rawType: 'CI', expected: 10 },
      { siteId: 'MLU', rawType: 'CI', expected: 11 },
    ])('when $rawType ($siteId) is selected, then maxlength stays $expected (no regression vs develop)', ({ siteId, rawType, expected }) => {
      expect(maxlengthFor(siteId, rawType)).toBe(expected);
    });
  });

  describe('given a variable-length document (else bucket)', () => {
    // develop accepts up to 20 raw digits in these fields; the short Figma
    // max_length_with_mask would block that. maxlength must fit 20 digits once
    // the mask adds separators — never the short Figma value.
    test.each([
      { siteId: 'MCO', rawType: 'CC', handler: 'CCHandler' },
      { siteId: 'MCO', rawType: 'CE', handler: 'MCO_CEHandler' },
      { siteId: 'MCO', rawType: 'NIT', handler: 'NITHandler' },
      { siteId: 'MLA', rawType: 'DNI', handler: 'MLA_DNIHandler' },
      { siteId: 'MPE', rawType: 'DNI', handler: 'MPE_DNIHandler' },
      { siteId: 'MLC', rawType: 'RUT', handler: 'RUTHandler' },
    ])('when $rawType ($siteId) is selected, then maxlength fits 20 digits, not the short Figma value', ({ siteId, rawType, handler }) => {
      const documentHandler = require('packages/narciso/components/input-document/document-handlers/' + handler);
      const maxlength = maxlengthFor(siteId, rawType);
      expect(maxlength).toBe(documentHandler.mask('9'.repeat(20)).length);
      expect(maxlength).toBeGreaterThan(documentHandler.CONFIG.max_length_with_mask);
    });
  });
});

describe('InputDocument - real-time validation (empty helper message)', () => {
  // Builds the DOM shape validateDocumentRealTime expects (input inside the
  // mp-input component, with input-helper/input-label as siblings under the
  // parent container) plus a ctx wiring the real prototype methods.
  function runRealTimeValidation(siteId, rawType, value) {
    const container = document.createElement('div');

    const helper = document.createElement('input-helper');
    const helperMessage = document.createElement('div');
    helperMessage.className = 'mp-helper-message';
    helperMessage.style.display = 'none';
    helper.appendChild(helperMessage);

    const label = document.createElement('input-label');
    label.appendChild(document.createElement('span'));

    const component = document.createElement('div');
    const input = document.createElement('input');
    component.appendChild(input);

    container.appendChild(helper);
    container.appendChild(label);
    container.appendChild(component);

    const select = document.createElement('select');
    const option = document.createElement('option');
    option.value = rawType;
    select.appendChild(option);
    select.value = rawType;

    const attrs = {
      'site-id': siteId,
      'helper-empty': 'Fill out this field.',
      'input-name': 'doc',
      'flag-error': 'doc-error',
    };
    const ctx = {
      getAttribute: (attr) => attrs[attr] ?? '',
      buildDocumentNameWithSiteId: InputDocument.prototype.buildDocumentNameWithSiteId,
      validateDocumentRealTime: InputDocument.prototype.validateDocumentRealTime,
      updateValidationState: InputDocument.prototype.updateValidationState,
      setValidState: InputDocument.prototype.setValidState,
      setInvalidState: InputDocument.prototype.setInvalidState,
      updateLabelState: InputDocument.prototype.updateLabelState,
      updateHelperErrorMessage: InputDocument.prototype.updateHelperErrorMessage,
    };

    input.value = value;
    ctx.validateDocumentRealTime(input, select, component);

    return { component, helperDisplay: helperMessage.style.display, helperText: helperMessage.innerHTML };
  }

  describe('given a required document left empty', () => {
    test('when MCO CC is cleared, then the empty helper message is shown alongside the red state', () => {
      const r = runRealTimeValidation('MCO', 'CC', '');
      expect(r.component.classList.contains('mp-error-2px')).toBe(true);
      expect(r.helperDisplay).toBe('flex');
      expect(r.helperText).toBe('Fill out this field.');
    });
  });

  describe('given a document that only rejects empty left empty', () => {
    test('when MLA DNI is cleared, then the empty helper message is shown (consistent across all documents)', () => {
      const r = runRealTimeValidation('MLA', 'DNI', '');
      expect(r.component.classList.contains('mp-error-2px')).toBe(true);
      expect(r.helperDisplay).toBe('flex');
      expect(r.helperText).toBe('Fill out this field.');
    });
  });
});
