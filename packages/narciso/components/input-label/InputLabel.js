class InputLabel extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createLabel());
  }

  createLabel() {
    const label = document.createElement('div');
    label.classList.add('mp-input-label');
    label.setAttribute('data-cy', 'input-label');

    const message = this.getAttribute('message');
    label.innerHTML = message;

    let isOptional = this.getAttribute('isOptional');

    if (typeof isOptional === 'string') {
      isOptional = isOptional !== 'false';
    }

    if (!isOptional) {
      const asterisco = document.createElement('b');
      asterisco.innerHTML = '*';
      asterisco.style = 'color: red';
      label.appendChild(asterisco);
    }

    return label;
  }
}

customElements.define('input-label', InputLabel);
