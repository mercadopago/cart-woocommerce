class InputWithTooltip extends HTMLElement {
  static get observedAttributes() {
    return ['label', 'placeholder', 'value', 'tooltip', 'validation-regexp', 'error-message', 'tooltip-position', 'tooltip-label'];
  }

  constructor() {
    super();
    this._value = '';
    this._showError = false;
    this._inputEl = null;
    this._onDocumentClick = this._onDocumentClick.bind(this);
  }

  connectedCallback() {
    this._value = this.getAttribute('value') || '';
    this.render();
  }

  disconnectedCallback() {
    document.removeEventListener('click', this._onDocumentClick);
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (name === 'value' && this._inputEl && this._inputEl.value !== newValue) {
      this._inputEl.value = newValue || '';
      this._value = newValue || '';
    }
    if (name !== 'value') {
      this.render();
    }
  }

  get value() {
    return this._inputEl ? this._inputEl.value : this._value;
  }

  set value(val) {
    this.setAttribute('value', val);
    if (this._inputEl) this._inputEl.value = val;
    this._value = val;
  }

  _validate(val) {
    const regexp = this.getAttribute('validation-regexp');
    if (!regexp || !val) return true;
    try {
      return new RegExp(regexp).test(val);
    } catch {
      return true;
    }
  }

  _onInput(e) {
    this._value = e.target.value;
    this.setAttribute('value', this._value);
    if (this._showError) {
      this._showError = false;
      this._updateErrorState();
    }
  }

  _onChange(e) {
    const val = e.target.value;
    if (val && !this._validate(val)) {
      this._showError = true;
    } else {
      this._showError = false;
    }
    this._updateErrorState();
    this.dispatchEvent(new CustomEvent('change', { detail: { value: val, valid: !this._showError } }));
  }

  _onDocumentClick(e) {
    if (!this.contains(e.target)) {
      this._hideTooltip();
    }
  }

  _toggleTooltip() {
    const tooltipEl = this.querySelector('.mp-input-with-tooltip-tooltip-content');
    if (tooltipEl.classList.contains('show')) {
      this._hideTooltip();
    } else {
      this._showTooltip();
    }
  }

  _showTooltip() {
    const tooltipEl = this.querySelector('.mp-input-with-tooltip-tooltip-content');
    tooltipEl.classList.add('show');
    document.addEventListener('click', this._onDocumentClick);
  }

  _hideTooltip() {
    const tooltipEl = this.querySelector('.mp-input-with-tooltip-tooltip-content');
    tooltipEl.classList.remove('show');
    document.removeEventListener('click', this._onDocumentClick);
  }

  _updateErrorState() {
    const container = this.querySelector('.mp-input-with-tooltip-input-container');
    const label = this.querySelector('.mp-input-with-tooltip-label');
    if (container) {
      if (this._showError) {
        container.classList.add('error');
        label.classList.add('error');
      } else {
        container.classList.remove('error');
        label.classList.remove('error');
      }
    }
    const helper = this.querySelector('.mp-input-with-tooltip-helper-error');
    if (helper) {
      helper.style.display = this._showError ? 'flex' : 'none';
    }
  }

  render() {
    const label = this.getAttribute('label') || '';
    const placeholder = this.getAttribute('placeholder') || '';
    const tooltip = this.getAttribute('tooltip') || '';
    const tooltipLabel = this.getAttribute('tooltip-label') || 'Mais informações';
    const errorMessage = this.getAttribute('error-message') || 'Valor inválido';
    const value = this._value || '';
    const showError = this._showError;
    const tooltipPosition = this.getAttribute('tooltip-position') || 'right';

    this.innerHTML = `
      <label
        tabindex="0"
        for="mp-input-with-tooltip-input"
        class="mp-input-with-tooltip-label"
      >${label}</label>
      <div class="mp-input-with-tooltip-input-container${showError ? ' error' : ''}">
        <input
          id="mp-input-with-tooltip-input"
          class="mp-input-with-tooltip-input"
          placeholder="${placeholder}"
          value="${value}"
        />
        <span
          class="mp-input-with-tooltip-tooltip-icon"
          tabindex="0"
          aria-label="${tooltipLabel}"
          role="button"
        >?</span>
        <div
          aria-describedby="mp-input-with-tooltip-tooltip-content"
          class="mp-input-with-tooltip-tooltip-content mp-input-with-tooltip-tooltip-${tooltipPosition}"
        >
          <span
            id="mp-input-with-tooltip-tooltip-content"
            tabindex="0"
            role="tooltip"
          >
            ${tooltip}
          </span>
        </div>
      </div>
      <div
        id="mp-input-with-tooltip-helper-error"
        tabindex="0"
        class="mp-input-with-tooltip-helper-error"
        style="display:${showError ? 'block' : 'none'}"
      >
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="12" height="12" rx="6" fill="#CC1818"/>
          <path d="M6.72725 2.90918H5.27271L5.45452 6.90918H6.54543L6.72725 2.90918Z" fill="white"/>
          <path d="M5.99998 7.63645C6.40164 7.63645 6.72725 7.96206 6.72725 8.36373C6.72725 8.76539 6.40164 9.091 5.99998 9.091C5.59832 9.091 5.27271 8.76539 5.27271 8.36373C5.27271 7.96206 5.59832 7.63645 5.99998 7.63645Z" fill="white"/>
        </svg>
        ${errorMessage}
      </div>
    `;
    this._inputEl = this.querySelector('input');
    this._inputEl.value = value;
    this._inputEl.addEventListener('input', this._onInput.bind(this));
    this._inputEl.addEventListener('change', this._onChange.bind(this));

    // Tooltip
    const icon = this.querySelector('.mp-input-with-tooltip-tooltip-icon');
    icon.addEventListener('click', () => {
      this._toggleTooltip();
    });
    icon.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        this._toggleTooltip();
      }
    });
  }
}
customElements.define('input-with-tooltip', InputWithTooltip);
