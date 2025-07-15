class InputField extends HTMLElement {
  helper = this.createHelper();
  input = this.createInput();

  connectedCallback() {
    this.build();
  }

  build() {
    const inputField = this.createInputField();
    this.appendChild(inputField);
  }

  createInputField() {
    const inputField = document.createElement('div');
    inputField.classList.add('mp-yape-input-field');
    inputField.setAttribute('data-cy', 'input-field-container');

    const label = this.createLabel(this.getAttribute('label-message'));

    inputField.appendChild(label);
    inputField.appendChild(this.input);
    inputField.appendChild(this.helper);
    return inputField;
  }

  createLabel(labelMessage) {
    const label = document.createElement('input-label');
    label.setAttribute('message', labelMessage);
    label.setAttribute('isOptional', 'true');

    return label;
  }

  validate() {
    if (this.input.value === '') {
      this.helper.firstElementChild.style.display = 'flex';
      document.getElementById('helper-input-field').childNodes[1].innerHTML = document.querySelector('input-field').getAttribute('empty-error-message');
      this.input.classList.add('mp-input-field-error');
      document.querySelector('[data-cy="input-label"]').style.setProperty('color', '#f23d4f', 'important');
    } else if (this.input.value.length < 11) {
      this.helper.firstElementChild.style.display = 'flex';
      document.getElementById('helper-input-field').childNodes[1].innerHTML = document.querySelector('input-field').getAttribute('invalid-error-message');
      this.input.classList.add('mp-input-field-error');
      document.querySelector('[data-cy="input-label"]').style.setProperty('color', '#f23d4f', 'important');
    } else {
      this.helper.firstElementChild.style.display = 'none';
      document.querySelector('[data-cy="input-label"]').style.setProperty('color', '#111111', 'important');
    }
  }

  createInput() {
    const mpInput = document.createElement('input');
    mpInput.classList.add('mp-yape-input');
    mpInput.setAttribute('id', 'checkout__yapePhoneNumber');
    mpInput.setAttribute('maxlength', '11');
    mpInput.setAttribute('placeholder', 'Ej.: 872 123 432');
    mpInput.setAttribute('aria-hidden', 'true');
    mpInput.setAttribute('data-cy', 'input-field');
    mpInput.setAttribute('name', 'phoneNumber');
    mpInput.setAttribute('required', 'true');

    mpInput.addEventListener('input', () => {
      const value = mpInput.value.replace(/\D/g, '');
      let maskedValue = '';

      for (let i = 0; i < value.length; i += 1) {
        maskedValue += value[i];
        if ((i + 1) % 3 === 0 && i !== value.length - 1) {
          maskedValue += ' ';
        }
      }

      mpInput.value = maskedValue;
    });

    mpInput.addEventListener('focus', () => {
      mpInput.classList.add('mp-input-field-focus');
      mpInput.classList.remove('mp-input-field-error');
      this.helper.firstElementChild.style.display = 'none';
    });

    mpInput.addEventListener('blur', () => {
      this.validate();
    });

    return mpInput;
  }

  createHelper() {
    const helper = document.createElement('input-helper');

    helper.setAttribute('isVisible', 'false');
    helper.setAttribute('type', 'error');
    helper.setAttribute('message', 'error');
    helper.setAttribute('input-id', 'helper-input-field');

    return helper;
  }
}

customElements.define('input-field', InputField);
