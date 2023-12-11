class InputRadio extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-input-radio-container');

    container.appendChild(this.createRadio());
    container.appendChild(this.createLabel());

    return container;
  }

  createRadio() {
    const radio = document.createElement('input');
    const dataRate = this.getAttribute('dataRate');

    radio.classList.add('mp-input-radio-radio');
    radio.type = 'radio';
    radio.id = this.getAttribute('identification');
    radio.name = this.getAttribute('name');
    radio.value = this.getAttribute('value');
    radio.setAttribute('data-cy', 'input-radio');

    if (dataRate) {
      radio.setAttribute('dataRate', dataRate);
    }

    return radio;
  }

  createLabel() {
    const label = document.createElement('label');

    label.classList.add('mp-input-radio-label');
    label.htmlFor = this.getAttribute('identification');

    return label;
  }
}

customElements.define('input-radio', InputRadio);
