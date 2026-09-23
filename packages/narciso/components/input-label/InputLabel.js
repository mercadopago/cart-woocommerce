class InputLabel extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createLabel());
  }

  createLabel() {
    // Must stay a <label>: a <div> gives no accessible name — see traps.md.
    const label = document.createElement('label');
    label.classList.add('mp-input-label');
    label.setAttribute('data-cy', 'input-label');

    const message = this.getAttribute('message');
    label.textContent = message;

    // Absent for the SDK iframe fields, which have no id of ours to point to.
    const forId = this.getAttribute('for');

    if (forId) {
      label.htmlFor = forId;
    }

    let isOptional = this.getAttribute('isOptional');

    if (typeof isOptional === 'string') {
      isOptional = isOptional !== 'false';
    }

    if (!isOptional) {
      const asterisco = document.createElement('b');
      asterisco.textContent = '*';
      asterisco.style = 'color: red';
      label.appendChild(asterisco);
    }

    return label;
  }
}

customElements.define('input-label', InputLabel);
