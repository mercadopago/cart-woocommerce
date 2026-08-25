const DocumentHandlerFactory = require('./document-handlers/DocumentHandlerFactory');

class InputDocument extends HTMLElement {
  selectObserver = null;
  selectObserverTimeout = null;

  connectedCallback() {
    this.build();
  }

  disconnectedCallback() {
    this.cleanupSelectObserver();
  }

  cleanupSelectObserver() {
    if (this.selectObserver) {
      this.selectObserver.disconnect();
      this.selectObserver = null;
    }
    if (this.selectObserverTimeout) {
      clearTimeout(this.selectObserverTimeout);
      this.selectObserverTimeout = null;
    }
  }

  build() {
    const inputDocument = this.createInputDocument();
    this.appendChild(inputDocument);
  }

  createInputDocument() {
    const inputDocument = document.createElement('div');
    inputDocument.classList.add('mp-input-document');
    inputDocument.setAttribute('data-cy', 'input-document-container');

    const label = this.createLabel(this.getAttribute('label-message'));
    const helper = this.createHelper(this.getAttribute('helper-empty'));
    const hidden = this.createHiddenField(this.getAttribute('hidden-id'));
    const input = this.createInput(helper, hidden, label);

    inputDocument.appendChild(label);
    inputDocument.appendChild(input);
    inputDocument.appendChild(hidden);
    inputDocument.appendChild(helper);
    return inputDocument;
  }

  createLabel(labelMessage) {
    const label = document.createElement('input-label');
    label.setAttribute('message', labelMessage);
    label.setAttribute('isOptional', 'false');

    return label;
  }

  createInput(helper, hidden, label) {
    const mpInput = document.createElement('div');
    mpInput.classList.add('mp-input');
    mpInput.setAttribute('id', 'form-checkout__identificationNumber-container');

    const documents = JSON.parse(this.getAttribute('documents'));
    const validate = this.getAttribute('validate');
    const verticalLine = this.createVerticalLine();
    const select = this.createSelect(mpInput, helper, documents, validate);
    const mpDocument = this.createDocument(mpInput, select, helper, label);

    select.addEventListener('change', () => {
      mpInput.classList.remove('mp-focus');
      mpInput.classList.remove('mp-error');
      // Clear the error helper when the document type changes
      helper.firstElementChild.style.display = 'none';

      this.setInputProperties(select, mpDocument, this.getAttribute('site-id'));

      this.setMaskInputDocument(select, mpDocument, hidden);

      // Reset the label state when the document type changes
      this.updateLabelState(label, false);
    });

    mpInput.appendChild(select);
    mpInput.appendChild(verticalLine);
    mpInput.appendChild(mpDocument);

    this.setMaskInputDocument(select, mpDocument, hidden);

    if (!documents || documents.length === 0) {
      this.observeSelectPopulationFromSdk(select, mpDocument);
    }

    return mpInput;
  }

  buildDocumentNameWithSiteId(documentName, siteId) {
    const documentsInTwoCountries = ['CE', 'DNI', 'CI'];

    const normalizedDocName = documentName.replace(/[^a-zA-Z0-9]/g, '');
    // Match case-insensitively: the SDK may deliver the type in lower/mixed case
    // (e.g. "dni"), and without the prefix the doc would fall back to GenericHandler
    // and silently lose the per-site mask/validation.
    const prefix = siteId && documentsInTwoCountries.includes(normalizedDocName.toUpperCase()) ? `${siteId}_` : '';

    return `${prefix}${normalizedDocName}`.toUpperCase();
  }

  setInputProperties(select, mpDocument, siteId) {
    const documentName = this.buildDocumentNameWithSiteId(select.value, siteId);
    const handler = DocumentHandlerFactory.getHandler(documentName);

    mpDocument.value = '';
    mpDocument.setAttribute('maxlength', this.getPermissiveMaxLength(select.value, handler));
    mpDocument.setAttribute('placeholder', handler.CONFIG.placeholder);
  }

  // maxlength must stay as permissive as develop, never the short Figma value.
  // Develop's raw digit caps (never blocked): CPF 11, CNPJ 14, CI 8; everything
  // else 20. Rendering that cap through the handler mask keeps the added
  // separators from shrinking the digit allowance — see traps.md.
  getPermissiveMaxLength(rawType, handler) {
    const digitCap = { CPF: 11, CNPJ: 14, CI: 8 }[rawType] ?? 20;
    return handler.mask('9'.repeat(digitCap)).length;
  }

  observeSelectPopulationFromSdk(select, mpDocument) {
    this.cleanupSelectObserver();

    const observer = new MutationObserver(() => {
      if (!this.isConnected || select.options.length === 0) {
        return;
      }

      const siteId = this.getAttribute('site-id');
      const defaultKey = DocumentHandlerFactory.getDefaultCountryHandler(siteId);

      // getDefaultCountryHandler returns the internal handler key, which for
      // site-scoped documents (CE/DNI/CI) is prefixed (e.g. "MLA_DNI"), while the
      // <select> options carry the raw SDK value ("DNI"). Match each option through
      // buildDocumentNameWithSiteId so the default is selected regardless of prefix.
      const defaultOption = defaultKey && Array.from(select.options).find(
        opt => this.buildDocumentNameWithSiteId(opt.value, siteId) === defaultKey
      );

      select.value = defaultOption ? defaultOption.value : select.options[0].value;

      this.setInputProperties(select, mpDocument, this.getAttribute('site-id'));
      this.cleanupSelectObserver();
    });

    this.selectObserver = observer;

    observer.observe(select, {
      childList: true,
      subtree: false
    });

    // Fallback cleanup only if the SDK never populates the select. The observer
    // self-disconnects once it applies the default/maxlength, so this cap must
    // outlast a slow SDK/API load (the e2e waits up to 15s) — 2s was too short and
    // left the field with a generic maxlength / no default on slow loads.
    this.selectObserverTimeout = setTimeout(() => {
      if (this.isConnected) {
        this.cleanupSelectObserver();
      }
    }, 15000);
  }

  createSelect(component, helper, documents, validate) {
    const select = document.createElement('select');

    select.classList.add('mp-document-select');
    select.setAttribute('name', this.getAttribute('select-name'));
    select.setAttribute('id', this.getAttribute('select-id'));
    select.setAttribute('data-checkout', this.getAttribute('select-data-checkout'));
    select.setAttribute('data-cy', 'select-document');

    if (documents && documents.length > 0) {
      documents.forEach((doc) => {
        this.createOption(select, doc);
      });

      const siteId = this.getAttribute('site-id');
      const defaultKey = DocumentHandlerFactory.getDefaultCountryHandler(siteId);
      const defaultDoc = defaultKey && documents.find(
        doc => this.buildDocumentNameWithSiteId(doc, siteId) === defaultKey
      );

      select.value = defaultDoc || select.options[0].value;
    }

    if (validate) {
      select.addEventListener('focus', () => {
        component.classList.add('mp-focus');
        helper.firstElementChild.style.display = 'none';
      });

      select.addEventListener('focusout', () => {
        component.classList.remove('mp-focus');
        helper.firstElementChild.style.display = 'none';
      });
    }

    return select;
  }

  createOption(select, doc) {
    const option = document.createElement('option');

    option.innerHTML = doc;
    option.value = doc;

    select.appendChild(option);
  }

  createHiddenField(id) {
    const field = document.createElement('input');
    field.setAttribute('type', "hidden");
    field.setAttribute('id', id);

    return field;
  }

  createVerticalLine() {
    const verticalLine = document.createElement('div');
    verticalLine.classList.add('mp-vertical-line');

    return verticalLine;
  }

  setMaskInputDocument(select, input, hidden) {
    const masks = {
      CPF: (value) =>
        value
          .replace(/\D+/g, '')
          .replace(/(\d{3})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d{1,2})/, '$1-$2')
          .replace(/(-\d{2})\d+?$/, '$1'),
      CNPJ: (value) =>
        value
          .replace(/[^A-Za-z0-9]+/g, '')
          .replace(/^([A-Za-z0-9]{2})([A-Za-z0-9])/, '$1.$2')
          .replace(/^([A-Za-z0-9]{2})\.([A-Za-z0-9]{3})([A-Za-z0-9])/, '$1.$2.$3')
          .replace(/\.([A-Za-z0-9]{3})([A-Za-z0-9])/, '.$1/$2')
          .replace(/([A-Za-z0-9]{4})([0-9])/, '$1-$2')
          .replace(/(-[0-9]{2})[A-Za-z0-9]+?$/, '$1'),
      CI: (value) => value.replace(/\D+/g, ''),
    };

    input.addEventListener('input', (e) => {
      const documentName = this.buildDocumentNameWithSiteId(select.value, this.getAttribute('site-id'));
      const handler = DocumentHandlerFactory.getHandler(documentName);

      if (handler && handler.mask) {
        e.target.value = handler.mask(e.target.value);
      }

      if (hidden) {
        const value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        hidden.value = value;
      }

      // Real-time validation while typing
      this.validateDocumentRealTime(e.target, select, input.parentElement);
    });
  }

  validateDocumentRealTime(input, select, component) {
    const helper = component.parentElement.querySelector('input-helper');
    const label = component.parentElement.querySelector('input-label');

    const documentName = this.buildDocumentNameWithSiteId(select.value, this.getAttribute('site-id'));
    const isValid = DocumentHandlerFactory.validate(documentName, input.value);

    if (!isValid.result) {
      this.updateHelperErrorMessage(helper, this.getAttribute(`helper-${isValid.type}`));
    }

    this.updateValidationState(isValid.result, input, component, helper, label);
  }

  updateValidationState(isValid, input, component, helper, label) {
    if (isValid) {
      this.setValidState(input, component, helper, label);
    } else {
      this.setInvalidState(input, component, helper, label);
    }
  }

  setValidState(input, component, helper, label) {
    component.classList.remove('mp-error', 'mp-error-2px');
    helper.firstElementChild.style.display = 'none';
    input.setAttribute('name', this.getAttribute('input-name'));
    this.updateLabelState(label, false);
  }

  setInvalidState(input, component, helper, label) {
    if (input.value.trim() === '') {
      component.classList.add('mp-error-2px');
      // Show the empty helper alongside the red state so the error is never
      // silent: the message mirrors the red border/label and clears together
      // with them on blur. Empty-on-submit gating stays out of this component.
      helper.firstElementChild.style.display = 'flex';
      input.setAttribute('name', this.getAttribute('input-name'));
      this.updateLabelState(label, true);
    } else {
      component.classList.add('mp-error-2px');
      helper.firstElementChild.style.display = 'flex';
      input.setAttribute('name', this.getAttribute('flag-error'));
      this.updateLabelState(label, true);
    }
  }

  createDocument(component, select, helper, label) {
    const input = document.createElement('input');

    if (this.getAttribute('input-id')) {
      input.setAttribute('id', this.getAttribute('input-id'));
    }

    input.setAttribute('name', this.getAttribute('input-name'));
    input.setAttribute('data-checkout', this.getAttribute('input-data-checkout'));
    input.setAttribute('data-cy', 'input-document');
    input.classList.add('mp-document');
    input.type = 'text';
    input.inputMode = 'text';
    this.setInputProperties(select, input, this.getAttribute('site-id'));

    input.addEventListener('focus', () => {
      this.handleInputFocus(component, helper, input, label);
    });

    input.addEventListener('focusout', () => {
      this.handleInputFocusOut(component, helper, input, label);
    });

    return input;
  }

  handleInputFocus(component, helper, input, label) {
    if (component.classList.contains('mp-error')) {
      component.classList.remove('mp-error');
      component.classList.add('mp-error-2px');
      return;
    }
    component.classList.remove('mp-error-2px');
    component.classList.add('mp-focus');
    helper.firstElementChild.style.display = 'none';
    input.setAttribute('name', this.getAttribute('input-name'));
    this.updateLabelState(label, false);
  }

  handleInputFocusOut(component, helper, input, label) {
    component.classList.remove('mp-focus');

    if (input.value.trim() === '') {
      this.clearErrorStates(component, helper, label);
    } else {
      this.handleNonEmptyInput(component);
    }
  }

  clearErrorStates(component, helper, label) {
    component.classList.remove('mp-error-2px', 'mp-error');
    if (helper) {
      helper.firstElementChild.style.display = 'none';
    }
    this.updateLabelState(label, false);
  }

  handleNonEmptyInput(component) {
    if (component.classList.contains('mp-error-2px')) {
      component.classList.remove('mp-error-2px');
      component.classList.add('mp-error');
    }
  }

  updateLabelState(label, isError) {
    if (isError) {
      label.firstElementChild.classList.add('mp-label-error');
    } else {
      label.firstElementChild.classList.remove('mp-label-error');
    }
  }

  updateHelperErrorMessage(helper, message) {
    helper.setAttribute('message', message);
    helper.querySelector('.mp-helper-message').innerHTML = message;
  }

  createHelper(helperMessage) {
    const helper = document.createElement('input-helper');

    helper.setAttribute('isVisible', false);
    helper.setAttribute('type', 'error');
    helper.setAttribute('message', helperMessage);
    helper.setAttribute('input-id', 'mp-doc-number-helper');

    return helper;
  }
}

customElements.define('input-document', InputDocument);
