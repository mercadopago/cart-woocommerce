class InputDocument extends HTMLElement {
  connectedCallback() {
    this.build();
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
    const helper = this.createHelper(this.getAttribute('helper-message'));
    const hidden = this.createHiddenField(this.getAttribute('hidden-id'));
    const input = this.createInput(helper, hidden);

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

  createInput(helper, hidden) {
    const mpInput = document.createElement('div');
    mpInput.classList.add('mp-input');
    mpInput.setAttribute('id', 'form-checkout__identificationNumber-container');

    const documents = JSON.parse(this.getAttribute('documents'));
    const validate = this.getAttribute('validate');
    const verticalLine = this.createVerticalLine();
    const select = this.createSelect(mpInput, helper, documents, validate);
    const mpDocument = this.createDocument(mpInput, select, helper);

    select.addEventListener('change', () => {
      mpInput.classList.remove('mp-focus');
      mpInput.classList.remove('mp-error');

      this.setInpuProperties(select, mpDocument);

      this.setMaskInputDocument(select, mpDocument, hidden);
    });

    mpInput.appendChild(select);
    mpInput.appendChild(verticalLine);
    mpInput.appendChild(mpDocument);

    this.setMaskInputDocument(select, mpDocument, hidden);

    return mpInput;
  }

  setInpuProperties(select, mpDocument) {
    if (select.value === 'CPF') {
      mpDocument.value = '';
      mpDocument.setAttribute('maxlength', '14');
      mpDocument.setAttribute('placeholder', '999.999.999-99');
    } else if (select.value === 'CNPJ') {
      mpDocument.value = '';
      mpDocument.setAttribute('maxlength', '18');
      mpDocument.setAttribute('placeholder', '99.999.999/0001-99');
    } else if (select.value === 'CI') {
      mpDocument.value = '';
      mpDocument.setAttribute('maxlength', '8');
      mpDocument.setAttribute('placeholder', '99999999');
    } else {
      mpDocument.value = '';
      mpDocument.setAttribute('maxlength', '20');
      mpDocument.setAttribute('placeholder', '');
    }
  }

  createSelect(component, helper, documents, validate) {
    const select = document.createElement('select');

    select.classList.add('mp-document-select');
    select.setAttribute('name', this.getAttribute('select-name'));
    select.setAttribute('id', this.getAttribute('select-id'));
    select.setAttribute('data-checkout', this.getAttribute('select-data-checkout'));
    select.setAttribute('data-cy', 'select-document');

    if (documents) {
      documents.forEach((doc) => {
        this.createOption(select, doc);
      });
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

  isValidCPF(cpf) {
    if (typeof cpf !== 'string') {
      return false;
    }

    cpf = cpf.replace(/[\s.-]*/gim, '');

    if (
      !cpf ||
      cpf.length !== 11 ||
      cpf === '00000000000' ||
      cpf === '11111111111' ||
      cpf === '22222222222' ||
      cpf === '33333333333' ||
      cpf === '44444444444' ||
      cpf === '55555555555' ||
      cpf === '66666666666' ||
      cpf === '77777777777' ||
      cpf === '88888888888' ||
      cpf === '99999999999'
    ) {
      return false;
    }

    let soma = 0;
    let resto;

    for (let i = 1; i <= 9; i += 1) {
      soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
    }

    resto = (soma * 10) % 11;

    if (resto === 10 || resto === 11) {
      resto = 0;
    }

    if (resto !== parseInt(cpf.substring(9, 10))) {
      return false;
    }

    soma = 0;

    for (let i = 1; i <= 10; i += 1) {
      soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
    }

    resto = (soma * 10) % 11;

    if (resto === 10 || resto === 11) {
      resto = 0;
    }

    if (resto !== parseInt(cpf.substring(10, 11))) {
      return false;
    }

    return true;
  }

  isValidCNPJ(cnpj) {
    cnpj = cnpj.replace(/[^\d]+/g, '');

    if (cnpj === '') {
      return false;
    }

    if (cnpj.length !== 14) {
      return false;
    }

    // Elimina CNPJs invalidos conhecidos
    if (
      cnpj === '00000000000000' ||
      cnpj === '11111111111111' ||
      cnpj === '22222222222222' ||
      cnpj === '33333333333333' ||
      cnpj === '44444444444444' ||
      cnpj === '55555555555555' ||
      cnpj === '66666666666666' ||
      cnpj === '77777777777777' ||
      cnpj === '88888888888888' ||
      cnpj === '99999999999999'
    ) {
      return false;
    }

    // Valida DVs
    let tamanho = cnpj.length - 2;
    let numeros = cnpj.substring(0, tamanho);

    const digitos = cnpj.substring(tamanho);

    let soma = 0;
    let pos = tamanho - 7;

    for (let i = tamanho; i >= 1; i -= 1) {
      soma += numeros.charAt(tamanho - i) * pos--;

      if (pos < 2) {
        pos = 9;
      }
    }

    let resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);

    if (resultado !== Number(digitos.charAt(0))) {
      return false;
    }

    tamanho += 1;
    numeros = cnpj.substring(0, tamanho);
    soma = 0;
    pos = tamanho - 7;

    for (let i = tamanho; i >= 1; i -= 1) {
      soma += numeros.charAt(tamanho - i) * pos--;

      if (pos < 2) {
        pos = 9;
      }
    }

    resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);

    if (resultado !== Number(digitos.charAt(1))) {
      return false;
    }

    return true;
  }

  isValidCI(ci) {
    let x = 0;
    let y = 0;
    let docCI = 0;
    const dig = ci[ci.length - 1];

    if (ci.length <= 6) {
      for (y = ci.length; y < 7; y += 1) {
        ci = `0${ci}`;
      }
    }

    for (y = 0; y < 7; y += 1) {
      x += (parseInt('2987634'[y], 10) * parseInt(ci[y], 10)) % 10;
    }

    if (x % 10 === 0) {
      docCI = 0;
    } else {
      docCI = 10 - (x % 10);
    }

    return dig === docCI.toString();
  }

  isValidCC(cc) {
    return ((typeof cc === 'string') && cc.length > 0) ;
  }

  isValidCE(ce) {
    return ((typeof ce === 'string') && ce.length > 0) ;
  }

  isValidNIT(nit) {
    return ((typeof nit === 'string') && nit.length > 0) ;
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
          .replace(/\D+/g, '')
          .replace(/(\d{2})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d)/, '$1/$2')
          .replace(/(\d{4})(\d)/, '$1-$2')
          .replace(/(-\d{2})\d+?$/, '$1'),
      CI: (value) => value.replace(/\D+/g, ''),
    };

    input.addEventListener('input', (e) => {
      if (typeof masks[select.value] !== 'undefined') {
        e.target.value = masks[select.value](e.target.value);
      }
      if (hidden) {
        const value = e.target.value.replace(/[^\w\s]/gi, '');
        hidden.value = value;
      }
    });
  }

  createDocument(component, select, helper) {
    const input = document.createElement('input');
    input.setAttribute('name', this.getAttribute('input-name'));
    input.setAttribute('data-checkout', this.getAttribute('input-data-checkout'));
    input.setAttribute('data-cy', 'input-document');
    input.classList.add('mp-document');
    input.type = 'text';
    input.inputMode = 'text';
    this.setInpuProperties(select, input);

    input.addEventListener('focus', () => {
      component.classList.add('mp-focus');
      component.classList.remove('mp-error');
      helper.firstElementChild.style.display = 'none';
    });

    input.addEventListener('focusout', () => {
      component.classList.remove('mp-focus');

      const validateDocument = {
        CPF: (value) => this.isValidCPF(value),
        CNPJ: (value) => this.isValidCNPJ(value),
        CI: (value) => this.isValidCI(value),
        CC: (value) => this.isValidCC(value),
        CE: (value) => this.isValidCE(value),
        NIT: (value) => this.isValidNIT(value),
      };

      if (typeof validateDocument[select.value] !== 'undefined') {
        if (validateDocument[select.value](input.value)) {
          component.classList.remove('mp-error');
          helper.firstElementChild.style.display = 'none';
          input.setAttribute('name', this.getAttribute('input-name'));
        } else {
          component.classList.add('mp-error');
          helper.firstElementChild.style.display = 'flex';
          input.setAttribute('name', this.getAttribute('flag-error'));
        }
      }
    });

    return input;
  }

  createHelper(helperMessage) {
    const helper = document.createElement('input-helper');

    helper.setAttribute('isVisible', false);
    helper.setAttribute('message', helperMessage);
    helper.setAttribute('input-id', 'mp-doc-number-helper');

    return helper;
  }
}

customElements.define('input-document', InputDocument);
