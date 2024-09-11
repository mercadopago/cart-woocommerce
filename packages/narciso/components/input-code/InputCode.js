class InputCode extends HTMLElement {
  helper = this.createHelper();
  inputContainer = this.createInputContainer();

  connectedCallback() {
    this.build();
  }

  build() {
    const container = document.createElement('div');
    container.classList.add('mp-yape-input-code-container');

    const labelContainer = document.createElement('div');
    labelContainer.classList.add('mp-yape-label-container');

    const label = document.createElement('label');
    label.setAttribute('id', 'yape-input-code-label');
    label.textContent = this.getAttribute('label') || 'Código de aprobación';

    const iconWrapper = document.createElement('div');
    iconWrapper.classList.add('mp-yape-icon-wrapper');

    const icon = document.createElement('img');
    icon.src = this.getAttribute('src');
    icon.alt = 'Icone de ajuda';
    icon.classList.add('mp-yape-icon');

    const tooltip = document.createElement('div');
    tooltip.classList.add('mp-yape-tooltip');
    tooltip.textContent = this.getAttribute('tooltip-text') || 'Encuéntralo en el menú de la app de Yape.';

    iconWrapper.appendChild(icon);
    iconWrapper.appendChild(tooltip);

    labelContainer.appendChild(label);
    labelContainer.appendChild(iconWrapper);
    container.appendChild(labelContainer);
    this.inputs = [];
    for (let i = 0; i < 6; i += 1) {
      const input = document.createElement('input');
      input.type = 'text';
      input.maxLength = '1';
      input.classList.add('mp-yape-code-input');
      input.setAttribute('input-index', i);
      this.inputs.push(input);

      input.addEventListener('input', (event) => {
        if (!/\d/.test(event.target.value)) {
          event.target.value = '';
        } else if (event.target.value.length === 1 && i < 5) {
          this.inputs[i + 1].focus();
        }

        this.updateClassesOnInput();
      });

      input.addEventListener('keydown', (event) => {
        const index = parseInt(event.target.getAttribute('input-index'));
        if (event.key === 'ArrowLeft' && index > 0) {
          this.inputs[index - 1].focus();
        } else if (event.key === 'ArrowRight' && index < 5) {
          this.inputs[index + 1].focus();
        } else if (event.key === 'Backspace' && index > 0 && !event.target.value) {
          this.inputs[index - 1].focus();
        }
      });

      if (i === 0) {
        input.addEventListener('paste', (event) => this.handlePaste(event));
      }

      input.addEventListener('focus', () => {
        input.classList.add('mp-input-code-focus');
        input.classList.remove('mp-input-code-error');
        this.helper.firstElementChild.style.display = 'none';
        document.getElementById('yape-input-code-label').style.color = '#111111';
      });

      input.addEventListener('blur', () => {
        setTimeout(() => {
          this.validate();
        }, 100);
      });

      this.inputContainer.appendChild(input);
    }

    container.appendChild(this.inputContainer);
    this.appendChild(container);
    this.appendChild(this.helper);

    this.applyStyles();
  }

  validate() {
    this.checkForErrors(this.helper, this.inputContainer);
  }

  handlePaste(event) {
    event.preventDefault();
    if (!Array.isArray(this.inputs)) {
      return;
    }

    const pasteData = (event.clipboardData || window.clipboardData).getData('text') || '';
    const digits = pasteData.split('').filter(char => /\d/.test(char));

    if (digits.length > 0) {
      digits.forEach((digit, index) => {
        if (index < this.inputs.length) {
          this.inputs[index].value = digit;
        }
      });
    }

    this.updateClassesOnInput();
  }

  updateClassesOnInput() {

    this.inputs.forEach(input => {
      input.classList.remove('mp-input-code-error');
      input.classList.add('mp-input-code-focus');
    });
  }

  checkForErrors(helper, inputContainer) {
    const inputs = inputContainer.querySelectorAll('input');
    const allFilled = Array.from(inputs).every(input => input.value !== '');
    const anyFilled = Array.from(inputs).some(input => input.value !== '');

    if (!document.activeElement.classList.contains('mp-yape-code-input')) {
      if (!allFilled) {
        inputs.forEach(input => input.classList.add('mp-input-code-error'));
        helper.firstElementChild.style.display = 'flex';
        document.getElementById('yape-input-code-label').style.color = '#f23d4f';
        if (anyFilled) {
          document.getElementById('helper-approval-code').childNodes[1].innerHTML = document.querySelector('input-code').getAttribute('invalid-error-message');
        } else {
          document.getElementById('helper-approval-code').childNodes[1].innerHTML = document.querySelector('input-code').getAttribute('empty-error-message');
        }
      }
    }
  }

  applyStyles() {
    const style = document.createElement('style');
    style.textContent = `
      .mp-yape-input-code-container {
        display: flex !important;
        flex-direction: column !important;
        font-family: 'Proxima Nova', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif !important;
      }

      .mp-yape-label-container {
        display: flex !important;
        align-items: flex-start !important;
        margin-bottom: -8px !important;
        font-style: normal !important;
        font-weight: 400 !important;
        height: 30px !important;
        line-height: 18px !important;
        font-size: 14px !important;
        padding-left: 6px !important
      }

      .mp-yape-icon-wrapper {
        position: relative !important;
        display: flex !important;
        align-items: center !important;
      }

      .mp-yape-icon {
        max-width: 20px !important;
        margin-left: 4px !important;
        cursor: pointer !important;
      }

      .mp-yape-tooltip {
        display: none !important;
        position: absolute !important;
        top: -55px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        background-color: white !important;
        padding: 8px !important;
        border-radius: 8px !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
        z-index: 100 !important;
        white-space: nowrap !important;
        pointer-events: none !important;
        font-family: 'Proxima Nova', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif !important;
        font-size: 14px !important;
        font-weight: 400 !important;
        line-height: 28px !important;
        text-align: left !important;

      }

      .mp-yape-tooltip::after {
        content: "";
        position: absolute;
        bottom: -8px;
        left: 51%;
        transform: translateX(-70%);
        border-width: 8px 8px 0 8px;
        border-style: solid;
        z-index: 101;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        clip-path: polygon(100% 0%, 0% 0%, 50% 100%);
        color: white;
      }

      .mp-yape-icon-wrapper:hover .mp-yape-tooltip {
        display: block !important;
      }

      .mp-yape-input-container {
        display: flex !important;
        gap: 8px !important;
      }

      .mp-yape-code-input {
        width: 38px !important;
        height: 53px !important;
        text-align: center !important;
        font-size: 24px !important;
        border: 1px solid #ccc !important;
        border-radius: 4px !important;
        font-size: 16px !important;
        line-height: 20px !important;
        font-family: 'Proxima Nova', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif !important;
        font-weight: 400 !important;
      }

      .mp-yape-code-input:nth-child(4) {
        margin-left: 10px !important;
      }

      .mp-input-code-focus {
        border: 1px solid #7f54b3 !important;
      }

      .mp-input-code-error {
        border: 1px solid #f23d4f !important;
      }
    `;
    this.appendChild(style);
  }

  createInputContainer() {
    const inputContainer = document.createElement('div');
    inputContainer.classList.add('mp-yape-input-container');
    return inputContainer;
  }

  createHelper() {
    const helper = document.createElement('input-helper');
    helper.setAttribute('isVisible', false);
    helper.setAttribute('message', 'error');
    helper.setAttribute('input-id', 'helper-approval-code');
    return helper;
  }
}

customElements.define('input-code', InputCode);
