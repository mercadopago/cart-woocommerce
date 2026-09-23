class InputHelper extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createHelper());
  }

  createHelper() {
    const helper = document.createElement('div');
    const type = this.getAttribute('type') || 'error';

    helper.classList.add('mp-helper');
    helper.setAttribute('id', this.getAttribute('input-id'));
    helper.setAttribute('data-cy', 'helper-container');
    // Only an error interrupts: alert is assertive, so using it for supporting
    // text made the hint cut off whatever was being announced — see traps.md.
    helper.setAttribute('role', type === 'error' ? 'alert' : 'status');

    this.validateVisibility(helper);

    const message = this.getAttribute('message');
    const helperMessage = this.createHelperMessage(message, type);

    if (type === 'error') {
      const icon = this.createIcon();
      helper.appendChild(icon);
    }

    helper.appendChild(helperMessage);

    return helper;
  }

  createIcon() {
    const icon = document.createElement('div');

    icon.innerHTML = `
    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect width="12" height="12" rx="6" fill="#CC1818"/><path d="M6.72725 2.90918H5.27271L5.45452 6.90918H6.54543L6.72725 2.90918Z" fill="white"/>
      <path d="M5.99998 7.63645C6.40164 7.63645 6.72725 7.96206 6.72725 8.36373C6.72725 8.76539 6.40164 9.091 5.99998 9.091C5.59832 9.091 5.27271 8.76539 5.27271 8.36373C5.27271 7.96206 5.59832 7.63645 5.99998 7.63645Z" fill="white"/>
    </svg>`;
    icon.classList.add('mp-helper-icon');
    icon.setAttribute('tabindex', '-1');
    icon.setAttribute('aria-hidden', 'true');

    return icon;
  }

  createHelperMessage(message, type) {
    const helperMessage = document.createElement('div');

    helperMessage.textContent = message;
    helperMessage.classList.add('mp-helper-message');
    helperMessage.classList.add(type);
    helperMessage.setAttribute('data-cy', 'helper-message');
    // No aria-hidden/tabindex: this is the only content of the role="alert"
    // container, so hiding it silenced the alert — see traps.md.

    return helperMessage;
  }

  updateMessage(message) {
    this.setAttribute('message', message);
    this.querySelector('.mp-helper-message').textContent = message;
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
