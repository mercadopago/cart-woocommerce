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
  MutationObserver: global.MutationObserver,
  setTimeout: global.setTimeout,
  clearTimeout: global.clearTimeout,
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

describe('InputDocument - document option labels', () => {
  function buildStaticSelect(siteId, documents) {
    const attrs = {
      'site-id': siteId,
      'select-name': 'doc',
      'select-id': 'doc',
      'select-data-checkout': 'doc',
    };
    const ctx = {
      getAttribute: (attribute) => attrs[attribute] ?? '',
      createOption: InputDocument.prototype.createOption,
      buildDocumentNameWithSiteId: InputDocument.prototype.buildDocumentNameWithSiteId,
    };

    return InputDocument.prototype.createSelect.call(
      ctx,
      document.createElement('div'),
      document.createElement('div'),
      documents,
      false
    );
  }

  function buildSdkSelect(documents, selectedValue) {
    const select = document.createElement('select');

    documents.forEach(({ value, apiLabel }) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = apiLabel;
      select.appendChild(option);
    });

    if (selectedValue) {
      select.value = selectedValue;
    }

    return select;
  }

  const documentLabelsBySite = [
    {
      siteId: 'MLB',
      documents: [
        { value: 'CPF', apiLabel: 'CPF', expectedLabel: 'CPF' },
        { value: 'CNPJ', apiLabel: 'CNPJ', expectedLabel: 'CNPJ' },
      ],
    },
    {
      siteId: 'MLA',
      documents: [
        { value: 'DNI', apiLabel: 'Documento Nacional de Identidad', expectedLabel: 'DNI' },
        { value: 'CI', apiLabel: 'Cédula', expectedLabel: 'CI' },
        { value: 'LC', apiLabel: 'L.C.', expectedLabel: 'LC' },
        { value: 'LE', apiLabel: 'L.E.', expectedLabel: 'LE' },
        { value: 'Otro', apiLabel: 'Otro', expectedLabel: 'Otro' },
      ],
    },
    {
      siteId: 'MLU',
      documents: [
        { value: 'CI', apiLabel: 'Cédula de identidad', expectedLabel: 'CI' },
        { value: 'Otro', apiLabel: 'Otro', expectedLabel: 'Otro' },
      ],
    },
    {
      siteId: 'MLC',
      documents: [
        { value: 'RUT', apiLabel: 'R.U.T.', expectedLabel: 'RUT' },
        { value: 'Otro', apiLabel: 'Otro', expectedLabel: 'Otro' },
      ],
    },
    {
      siteId: 'MCO',
      documents: [
        { value: 'CC', apiLabel: 'Cédula de ciudadanía', expectedLabel: 'CC' },
        { value: 'CE', apiLabel: 'Cédula de extranjería', expectedLabel: 'CE' },
        { value: 'NIT', apiLabel: 'Número de identificación tributaria', expectedLabel: 'NIT' },
        { value: 'Otro', apiLabel: 'Otro', expectedLabel: 'Otro' },
      ],
    },
    {
      siteId: 'MPE',
      documents: [
        { value: 'DNI', apiLabel: 'Documento Nacional de Identidad', expectedLabel: 'DNI' },
        { value: 'CE', apiLabel: 'Carné de extranjería', expectedLabel: 'C.E' },
        { value: 'RUC', apiLabel: 'Registro Único de Contribuyentes', expectedLabel: 'RUC' },
        { value: 'Otro', apiLabel: 'Otro', expectedLabel: 'Otro' },
      ],
    },
  ];

  test.each(documentLabelsBySite)(
    'normalizes every $siteId document label without changing values, order or selection',
    ({ siteId, documents }) => {
      const selectedValue = documents[documents.length - 1].value;
      const select = buildSdkSelect(documents, selectedValue);
      const originalValues = Array.from(select.options).map((option) => option.value);

      InputDocument.prototype.normalizeDocumentOptionLabels(select, siteId);

      expect(Array.from(select.options).map((option) => option.textContent)).toEqual(
        documents.map(({ expectedLabel }) => expectedLabel)
      );
      expect(Array.from(select.options).map((option) => option.value)).toEqual(originalValues);
      expect(select.value).toBe(selectedValue);
    }
  );

  test('keeps MLM without document options', () => {
    const select = buildSdkSelect([]);

    expect(() => InputDocument.prototype.normalizeDocumentOptionLabels(select, 'MLM')).not.toThrow();
    expect(select.options).toHaveLength(0);
  });

  test('keeps the SDK text for an unknown future document type', () => {
    const select = buildSdkSelect([
      { value: 'PASSPORT', apiLabel: 'Passport', expectedLabel: 'Passport' },
    ], 'PASSPORT');

    InputDocument.prototype.normalizeDocumentOptionLabels(select, 'MLA');

    expect(select.options[0].textContent).toBe('Passport');
    expect(select.options[0].value).toBe('PASSPORT');
    expect(select.value).toBe('PASSPORT');
  });

  test('renders a static document label as text instead of HTML', () => {
    const select = document.createElement('select');
    const label = '<img src=x onerror=alert(1)>';

    InputDocument.prototype.createOption(select, label);

    expect(select.options[0].textContent).toBe(label);
    expect(select.options[0].querySelector('img')).toBeNull();
  });

  test('normalizes the SDK batch when the population observer fires', async () => {
    const select = buildSdkSelect([]);
    const input = document.createElement('input');
    const ctx = {
      selectObserver: null,
      selectObserverTimeout: null,
      isConnected: true,
      getAttribute: (attribute) => attribute === 'site-id' ? 'MLA' : '',
      cleanupSelectObserver: InputDocument.prototype.cleanupSelectObserver,
      normalizeDocumentOptionLabels: InputDocument.prototype.normalizeDocumentOptionLabels,
      buildDocumentNameWithSiteId: InputDocument.prototype.buildDocumentNameWithSiteId,
      setInputProperties: jest.fn(),
    };

    InputDocument.prototype.observeSelectPopulationFromSdk.call(ctx, select, input);

    const sdkBatch = document.createDocumentFragment();
    [
      { value: 'DNI', label: 'Documento Nacional de Identidad' },
      { value: 'CI', label: 'Cédula' },
      { value: 'LC', label: 'L.C.' },
    ].forEach(({ value, label }) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = label;
      sdkBatch.appendChild(option);
    });
    select.appendChild(sdkBatch);

    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(Array.from(select.options).map((option) => option.textContent)).toEqual(['DNI', 'CI', 'LC']);
    expect(select.value).toBe('DNI');
    expect(ctx.setInputProperties).toHaveBeenCalledWith(select, input, 'MLA');
    expect(ctx.selectObserver).toBeNull();
  });

  test('preserves labels from a plugin-owned static document list', () => {
    const select = buildStaticSelect('MLU', ['CI', 'OTRO']);

    expect(Array.from(select.options).map((option) => option.textContent)).toEqual(['CI', 'OTRO']);
    expect(Array.from(select.options).map((option) => option.value)).toEqual(['CI', 'OTRO']);
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
      updateInstruction: InputDocument.prototype.updateInstruction,
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
    // Mirrors createHelper: `input-id` is shared across gateways, the `id` is not.
    helper.setAttribute('input-id', 'mp-doc-number-helper');
    helper.setAttribute('id', 'mp-doc-number-helper-unique');
    const helperMessage = document.createElement('div');
    helperMessage.className = 'mp-helper-message';
    helperMessage.style.display = 'none';
    helper.appendChild(helperMessage);
    helper.updateMessage = (message) => {
      helper.setAttribute('message', message);
      helperMessage.textContent = message;
    };

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
      'instruction-range': 'Enter between {min} and {max} digits for your ID number.',
      'instruction-fixed': 'Enter the {digits} digits of your ID number.',
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
      setDocumentValidity: InputDocument.prototype.setDocumentValidity,
      elementId: InputDocument.prototype.elementId,
      hasInstruction: InputDocument.prototype.hasInstruction,
    };

    input.value = value;
    ctx.validateDocumentRealTime(input, select, component);

    return { component, input, helperDisplay: helperMessage.style.display, helperText: helperMessage.innerHTML };
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

  test('delegates document error messages to the safe input-helper API', () => {
    const helper = { updateMessage: jest.fn() };
    const message = '<img src=x onerror=alert(1)>';

    InputDocument.prototype.updateHelperErrorMessage(helper, message);

    expect(helper.updateMessage).toHaveBeenCalledWith(message);
  });

  describe('given a document rejected by its handler', () => {
    test('when validated, then the input is flagged invalid and points at both the instruction and the error message', () => {
      const r = runRealTimeValidation('MLB', 'CPF', '123');

      expect(r.input.getAttribute('aria-invalid')).toBe('true');
      expect(r.input.getAttribute('aria-describedby')).toBe('mp-doc-number-instruction mp-doc-number-helper-unique');
    });
  });

  describe('given a document accepted by its handler', () => {
    test('when validated, then the error reference is dropped but the instruction is kept', () => {
      const r = runRealTimeValidation('MLB', 'CPF', '11144477735');

      expect(r.input.getAttribute('aria-invalid')).toBe('false');
      expect(r.input.getAttribute('aria-describedby')).toBe('mp-doc-number-instruction');
    });
  });
});

describe('InputDocument - updateInstruction', () => {
  // Real handlers, never a hand-written CONFIG — an invented one hides the bug,
  // see traps.md.
  function instructionFor(handler, attributes = {}) {
    const attrs = {
      'instruction-range': 'Enter between {min} and {max} digits for your ID number.',
      'instruction-fixed': 'Enter the {digits} digits of your ID number.',
      ...attributes,
    };
    const instruction = document.createElement('span');
    const ctx = {
      instructionElement: instruction,
      getAttribute: (attr) => attrs[attr] ?? '',
    };

    InputDocument.prototype.updateInstruction.call(ctx, handler);

    return instruction.textContent;
  }

  function realHandler(name) {
    return require('packages/narciso/components/input-document/document-handlers/' + name);
  }

  describe('given a document that declares only max_length', () => {
    test.each([
      { name: 'CPFHandler', digits: 11 },
      { name: 'CNPJHandler', digits: 14 },
    ])('when $name is selected, then the instruction states $digits digits and never "undefined"', ({ name, digits }) => {
      const text = instructionFor(realHandler(name));

      expect(text).toBe(`Enter the ${digits} digits of your ID number.`);
      expect(text).not.toContain('undefined');
    });
  });

  describe('given a document that declares min_length equal to max_length', () => {
    test('when RUC is selected, then the instruction states the exact number of digits', () => {
      expect(instructionFor(realHandler('RUCHandler')))
        .toBe('Enter the 11 digits of your ID number.');
    });
  });

  describe('given a document that declares a real range', () => {
    test.each([
      { name: 'CCHandler', min: 5, max: 10 },
      { name: 'RUTHandler', min: 8, max: 9 },
      { name: 'NITHandler', min: 7, max: 16 },
    ])('when $name is selected, then the instruction states $min and $max', ({ name, min, max }) => {
      expect(instructionFor(realHandler(name)))
        .toBe(`Enter between ${min} and ${max} digits for your ID number.`);
    });
  });

  describe('given every shipped handler', () => {
    test('when the instruction is built, then no handler produces a sentence with "undefined"', () => {
      const names = [
        'CPFHandler', 'CNPJHandler', 'CCHandler', 'GenericHandler', 'LCHandler', 'LEHandler',
        'MCO_CEHandler', 'MLA_CIHandler', 'MLA_DNIHandler', 'MLU_CIHandler', 'MPE_CEHandler',
        'MPE_DNIHandler', 'NITHandler', 'RUCHandler', 'RUTHandler',
      ];

      names.forEach((name) => {
        expect(instructionFor(realHandler(name))).not.toContain('undefined');
      });
    });
  });

  describe('given a handler without length configuration', () => {
    test('when the instruction is built, then it stays empty instead of exposing a broken sentence', () => {
      expect(instructionFor({ CONFIG: {} })).toBe('');
    });
  });

  describe('given the component was never rendered', () => {
    test('when the instruction is built, then it does not throw', () => {
      const ctx = { getAttribute: () => '' };

      expect(() => InputDocument.prototype.updateInstruction.call(ctx, realHandler('CPFHandler')))
        .not.toThrow();
    });
  });
});

describe('InputDocument - markInvalidFromSubmit()', () => {
  // Mirrors what the component renders: the visible input plus the helper carrying a
  // per-instance id, both reachable from the custom element.
  function buildComponent({ comInstrucao = true, montado = true } = {}) {
    const attrs = {
      'select-id': 'form-checkout__identificationType',
      'instruction-fixed': comInstrucao ? 'Enter the {digits} digits of your ID number.' : '',
      'instruction-range': comInstrucao ? 'Enter between {min} and {max} digits.' : '',
    };

    const host = document.createElement('div');
    let input = null;
    let helper = null;

    if (montado) {
      input = document.createElement('input');
      input.className = 'mp-document';
      helper = document.createElement('input-helper');
      helper.setAttribute('id', 'form-checkout__identificationType-helper');
      host.appendChild(input);
      host.appendChild(helper);
    }

    const ctx = {
      querySelector: (sel) => host.querySelector(sel),
      getAttribute: (attr) => attrs[attr] ?? '',
      setDocumentValidity: InputDocument.prototype.setDocumentValidity,
      elementId: InputDocument.prototype.elementId,
      hasInstruction: InputDocument.prototype.hasInstruction,
    };

    return { ctx, input, chamar: () => InputDocument.prototype.markInvalidFromSubmit.call(ctx) };
  }

  describe('given the submit gate rejected the document', () => {
    test('when the component is asked to mark it, then the error message joins the description', () => {
      const { input, chamar } = buildComponent();

      chamar();

      expect(input.getAttribute('aria-invalid')).toBe('true');
      expect(input.getAttribute('aria-describedby')).toBe(
        'form-checkout__identificationType-instruction form-checkout__identificationType-helper'
      );
    });
  });

  describe('given a gateway that provides no instruction text', () => {
    test('when the component is asked to mark it, then only the error message is referenced', () => {
      const { input, chamar } = buildComponent({ comInstrucao: false });

      chamar();

      expect(input.getAttribute('aria-describedby')).toBe('form-checkout__identificationType-helper');
    });
  });

  describe('given the component was never rendered', () => {
    test('when the component is asked to mark it, then nothing is thrown', () => {
      const { chamar } = buildComponent({ montado: false });

      expect(() => chamar()).not.toThrow();
    });
  });
});

describe('InputDocument - changing the document type clears the accessible state too', () => {
  // Builds the field through createInput, so the real `change` listener is wired,
  // and returns the pieces needed to assert the state before and after the switch.
  function buildField(siteId, documents) {
    const attrs = {
      'site-id': siteId,
      documents: JSON.stringify(documents),
      validate: 'true',
      'select-name': 'doc_type',
      'select-id': 'doc_type',
      'select-data-checkout': 'doc_type',
      'input-name': 'doc_number',
      'input-data-checkout': 'doc_number',
      'helper-empty': 'Fill out this field.',
      'helper-invalid': 'Enter a valid document.',
      'helper-wrong': 'Enter the complete document.',
      'instruction-fixed': 'Enter the {digits} digits of your ID number.',
      'instruction-range': 'Enter between {min} and {max} digits for your ID number.',
    };

    const proto = InputDocument.prototype;
    const ctx = {
      getAttribute: (attr) => attrs[attr] ?? '',
      createVerticalLine: proto.createVerticalLine,
      createSelect: proto.createSelect,
      createOption: proto.createOption,
      createDocument: proto.createDocument,
      clearErrorStates: proto.clearErrorStates,
      setDocumentValidity: proto.setDocumentValidity,
      setInputProperties: proto.setInputProperties,
      getPermissiveMaxLength: proto.getPermissiveMaxLength,
      setMaskInputDocument: proto.setMaskInputDocument,
      observeSelectPopulationFromSdk: () => {},
      normalizeDocumentOptionLabels: proto.normalizeDocumentOptionLabels,
      updateInstruction: proto.updateInstruction,
      createInstruction: proto.createInstruction,
      querySelector: () => null,
      handleInputFocus: proto.handleInputFocus,
      handleInputFocusOut: proto.handleInputFocusOut,
      handleNonEmptyInput: proto.handleNonEmptyInput,
      validateDocumentRealTime: proto.validateDocumentRealTime,
      updateValidationState: proto.updateValidationState,
      setValidState: proto.setValidState,
      setInvalidState: proto.setInvalidState,
      updateLabelState: proto.updateLabelState,
      updateHelperErrorMessage: proto.updateHelperErrorMessage,
      buildDocumentNameWithSiteId: proto.buildDocumentNameWithSiteId,
      elementId: proto.elementId,
      hasInstruction: proto.hasInstruction,
    };

    const helper = document.createElement('input-helper');
    helper.setAttribute('input-id', 'mp-doc-number-helper');
    helper.setAttribute('id', 'doc_type-helper');
    const helperMessage = document.createElement('div');
    helperMessage.className = 'mp-helper-message';
    helper.appendChild(helperMessage);
    helper.updateMessage = (message) => { helperMessage.textContent = message; };

    const label = document.createElement('input-label');
    label.appendChild(document.createElement('span'));

    const hidden = document.createElement('input');
    const mpInput = proto.createInput.call(ctx, helper, hidden, label);

    // Same shape createInputDocument builds: the helper and the label are siblings
    // of the field container, which is how validateDocumentRealTime finds them.
    const container = document.createElement('div');
    container.appendChild(label);
    container.appendChild(mpInput);
    container.appendChild(hidden);
    container.appendChild(helper);

    const input = mpInput.querySelector('input[data-cy=input-document]');
    const select = mpInput.querySelector('select');

    return { container, mpInput, input, select, helper, label };
  }

  const ler = ({ mpInput, input }) => ({
    'aria-invalid': input.getAttribute('aria-invalid'),
    'aria-describedby': input.getAttribute('aria-describedby'),
    classesDeErro: [...mpInput.classList].filter((c) => c.startsWith('mp-error')),
  });

  describe('given the buyer typed an invalid document and then switched the type', () => {
    test('when the type changes, then the field is no longer announced as invalid', () => {
      const campo = buildField('MLB', ['CPF', 'CNPJ']);

      campo.input.value = '111';
      campo.input.dispatchEvent(new Event('input'));

      expect(ler(campo)['aria-invalid']).toBe('true');
      expect(ler(campo)['aria-describedby']).toContain('doc_type-helper');

      campo.select.value = 'CNPJ';
      campo.select.dispatchEvent(new Event('change'));

      const depois = ler(campo);
      expect(depois['aria-invalid']).toBe('false');
      expect(depois['aria-describedby']).not.toContain('doc_type-helper');
      expect(depois.classesDeErro).toEqual([]);
    });
  });

  describe('given the field was showing the focused error state', () => {
    test('when the type changes, then the red state is cleared as well', () => {
      const campo = buildField('MLB', ['CPF', 'CNPJ']);

      campo.input.value = '111';
      campo.input.dispatchEvent(new Event('input'));
      campo.mpInput.classList.remove('mp-error');
      campo.mpInput.classList.add('mp-error-2px');

      campo.select.value = 'CNPJ';
      campo.select.dispatchEvent(new Event('change'));

      expect(ler(campo).classesDeErro).toEqual([]);
    });
  });
});

describe('InputDocument - createLabel', () => {
  function buildLabel(selectId) {
    const attrs = { 'select-id': selectId };
    const ctx = {
      getAttribute: (attr) => attrs[attr] ?? '',
      elementId: InputDocument.prototype.elementId,
      hasInstruction: InputDocument.prototype.hasInstruction,
    };

    return InputDocument.prototype.createLabel.call(ctx, 'Tipo de documento');
  }

  describe('given a document component with a select id', () => {
    test('when the label is created, then it is associated with the document type select', () => {
      const label = buildLabel('form-checkout__identificationType');

      expect(label.getAttribute('for')).toBe('form-checkout__identificationType');
    });
  });

  describe('given a document component without a select id', () => {
    test('when the label is created, then no association is set', () => {
      expect(buildLabel('').hasAttribute('for')).toBe(false);
    });
  });
});

describe('InputDocument - element ids across instances', () => {
  function idsFor(selectId) {
    const attrs = { 'select-id': selectId };
    const ctx = {
      getAttribute: (attr) => attrs[attr] ?? '',
      elementId: InputDocument.prototype.elementId,
      hasInstruction: InputDocument.prototype.hasInstruction,
    };

    return {
      label: ctx.elementId('label'),
      instruction: ctx.elementId('instruction'),
    };
  }

  describe('given two gateways rendering the component on the same page', () => {
    // Classic renders every payment box, so Custom and Ticket coexist in the DOM.
    // Duplicate ids would make aria-describedby resolve to whichever came first.
    test('when both mount, then their ids do not collide', () => {
      const custom = idsFor('form-checkout__identificationType');
      const ticket = idsFor('doc_type');

      expect(custom.instruction).not.toBe(ticket.instruction);
      expect(custom.label).not.toBe(ticket.label);
    });
  });

  describe('given a component without a select id', () => {
    test('when the ids are built, then it falls back to a stable prefix', () => {
      expect(idsFor('').instruction).toBe('mp-doc-number-instruction');
    });
  });
});

describe('InputDocument - accessible name of the number input', () => {
  describe('given the visible label names a compound control', () => {
    // The label's `for` focuses the type select, so the number input has no label
    // of its own — without aria-labelledby its name would be the mask placeholder.
    test('when the document input is created, then it borrows the visible label', () => {
      const attrs = {
        'select-id': 'form-checkout__identificationType',
        'input-name': 'identificationNumber',
        'input-data-checkout': 'doc_number',
        'site-id': 'MLB',
        'instruction-fixed': 'Enter the {digits} digits of your ID number.',
      };
      const ctx = {
        getAttribute: (attr) => attrs[attr] ?? '',
        elementId: InputDocument.prototype.elementId,
        hasInstruction: InputDocument.prototype.hasInstruction,
      hasInstruction: InputDocument.prototype.hasInstruction,
        createInstruction: InputDocument.prototype.createInstruction,
        updateInstruction: InputDocument.prototype.updateInstruction,
        setInputProperties: InputDocument.prototype.setInputProperties,
        setMaskInputDocument: () => {},
        validateDocumentRealTime: () => {},
        buildDocumentNameWithSiteId: InputDocument.prototype.buildDocumentNameWithSiteId,
        getPermissiveMaxLength: InputDocument.prototype.getPermissiveMaxLength,
        handleInputFocus: () => {},
        handleInputFocusOut: () => {},
      };
      ctx.createInstruction.call(ctx);

      const select = document.createElement('select');
      const option = document.createElement('option');
      option.value = 'CPF';
      select.appendChild(option);
      select.value = 'CPF';

      const input = InputDocument.prototype.createDocument.call(
        ctx,
        document.createElement('div'),
        select,
        document.createElement('div'),
        document.createElement('input-label')
      );

      expect(input.getAttribute('aria-labelledby')).toBe('form-checkout__identificationType-label');
      expect(input.getAttribute('aria-describedby')).toBe('form-checkout__identificationType-instruction');
    });
  });
});

describe('InputDocument - gateways that do not provide the instruction texts', () => {
  // Only the Custom checkout template passes instruction-range/instruction-fixed.
  // PSE and Ticket share this component and must not reference an empty description.
  function buildFor(attrs) {
    const ctx = {
      getAttribute: (attr) => attrs[attr] ?? '',
      elementId: InputDocument.prototype.elementId,
      hasInstruction: InputDocument.prototype.hasInstruction,
      createInstruction: InputDocument.prototype.createInstruction,
      updateInstruction: InputDocument.prototype.updateInstruction,
      setInputProperties: InputDocument.prototype.setInputProperties,
      setMaskInputDocument: () => {},
      validateDocumentRealTime: () => {},
      buildDocumentNameWithSiteId: InputDocument.prototype.buildDocumentNameWithSiteId,
      getPermissiveMaxLength: InputDocument.prototype.getPermissiveMaxLength,
      handleInputFocus: () => {},
      handleInputFocusOut: () => {},
    };
    const instruction = ctx.createInstruction.call(ctx);

    const select = document.createElement('select');
    const option = document.createElement('option');
    option.value = 'CPF';
    select.appendChild(option);
    select.value = 'CPF';

    const input = InputDocument.prototype.createDocument.call(
      ctx,
      document.createElement('div'),
      select,
      document.createElement('div'),
      document.createElement('input-label')
    );

    return { ctx, instruction, input };
  }

  const semTextos = { 'select-id': 'doc_type', 'input-name': 'doc', 'site-id': 'MCO' };
  const comTextos = { ...semTextos, 'instruction-fixed': 'Enter the {digits} digits of your ID number.' };

  describe('given a gateway without the instruction texts', () => {
    test('when the field is built, then no instruction element is created', () => {
      expect(buildFor(semTextos).instruction).toBeNull();
    });

    test('when the field is built, then it is not described by anything', () => {
      expect(buildFor(semTextos).input.hasAttribute('aria-describedby')).toBe(false);
    });

    test('when the state changes, then no empty description is attached', () => {
      const { ctx, input } = buildFor(semTextos);
      const helper = document.createElement('input-helper');
      helper.setAttribute('id', 'doc_type-helper');

      InputDocument.prototype.setDocumentValidity.call(ctx, input, helper, false);

      expect(input.hasAttribute('aria-describedby')).toBe(false);
    });

    test('when the state changes to invalid, then only the error message is referenced', () => {
      const { ctx, input } = buildFor(semTextos);
      const helper = document.createElement('input-helper');
      helper.setAttribute('id', 'doc_type-helper');

      InputDocument.prototype.setDocumentValidity.call(ctx, input, helper, true);

      expect(input.getAttribute('aria-describedby')).toBe('doc_type-helper');
    });
  });

  describe('given a gateway that provides the instruction texts', () => {
    test('when the field is built, then the description is attached as before', () => {
      const { instruction, input } = buildFor(comTextos);

      expect(instruction).not.toBeNull();
      expect(input.getAttribute('aria-describedby')).toBe('doc_type-instruction');
    });
  });

  describe('given any gateway', () => {
    test('when the field is built, then the label association is kept regardless', () => {
      expect(buildFor(semTextos).input.getAttribute('aria-labelledby')).toBe('doc_type-label');
      expect(buildFor(comTextos).input.getAttribute('aria-labelledby')).toBe('doc_type-label');
    });
  });
});

describe('InputDocument - required state', () => {
  // The red asterisk in the label is a plain character to a screen reader, so the
  // required state has to be declared on the controls themselves.
  function buildFor() {
    const attrs = {
      'select-id': 'form-checkout__identificationType',
      'select-name': 'docType',
      'select-data-checkout': 'docType',
      'input-name': 'identificationNumber',
      'site-id': 'MLB',
      'instruction-fixed': 'Enter the {digits} digits of your ID number.',
    };
    const ctx = {
      getAttribute: (attr) => attrs[attr] ?? '',
      elementId: InputDocument.prototype.elementId,
      hasInstruction: InputDocument.prototype.hasInstruction,
      createInstruction: InputDocument.prototype.createInstruction,
      updateInstruction: InputDocument.prototype.updateInstruction,
      setInputProperties: InputDocument.prototype.setInputProperties,
      createOption: InputDocument.prototype.createOption,
      buildDocumentNameWithSiteId: InputDocument.prototype.buildDocumentNameWithSiteId,
      getPermissiveMaxLength: InputDocument.prototype.getPermissiveMaxLength,
      setMaskInputDocument: () => {},
      validateDocumentRealTime: () => {},
      handleInputFocus: () => {},
      handleInputFocusOut: () => {},
    };
    ctx.createInstruction.call(ctx);

    const select = InputDocument.prototype.createSelect.call(
      ctx,
      document.createElement('div'),
      document.createElement('div'),
      ['CPF', 'CNPJ'],
      false
    );

    const input = InputDocument.prototype.createDocument.call(
      ctx,
      document.createElement('div'),
      select,
      document.createElement('div'),
      document.createElement('input-label')
    );

    return { select, input };
  }

  describe('given the document field, which is always required', () => {
    test('when the type select is created, then it declares the required state', () => {
      expect(buildFor().select.getAttribute('aria-required')).toBe('true');
    });

    test('when the number input is created, then it declares the required state', () => {
      expect(buildFor().input.getAttribute('aria-required')).toBe('true');
    });
  });
});
