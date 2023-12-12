class InputHelper extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createHelper());
  }

  createHelper() {
    const helper = document.createElement('div');

    helper.classList.add('mp-helper');
    helper.setAttribute('id', this.getAttribute('input-id'));
    helper.setAttribute('data-cy', 'helper-container');

    this.validateVisibility(helper);

    const icon = this.createIcon();
    const message = this.getAttribute('message');
    const helperMessage = this.createHelperMessage(message);

    helper.appendChild(icon);
    helper.appendChild(helperMessage);

    return helper;
  }

  createIcon() {
    const icon = document.createElement('div');

    icon.innerHTML = '!';
    icon.classList.add('mp-helper-icon');

    return icon;
  }

  createHelperMessage(message) {
    const helperMessage = document.createElement('div');

    helperMessage.innerHTML = message;
    helperMessage.classList.add('mp-helper-message');
    helperMessage.setAttribute('data-cy', 'helper-message');

    return helperMessage;
  }

  validateVisibility(helper) {
    let isVisible = this.getAttribute('isVisible');

    if (typeof isVisible === 'string') {
      isVisible = isVisible !== 'false';
    }

    helper.style.display = isVisible ? 'flex' : 'none';
  }
}

customElements.define('input-helper', InputHelper);
