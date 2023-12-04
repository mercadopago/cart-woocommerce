class PaymentMethodLogo extends HTMLElement {
  static get observedAttributes() {
    return ['src', 'alt'];
  }

  connectedCallback() {
    this.build();
  }

  attributeChangedCallback() {
    if (this.firstElementChild) {
      this.removeChild(this.firstElementChild);
      this.build();
    }
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const content = document.createElement('div');

    content.classList.add('mp-payment-method-logo-container');
    content.appendChild(this.createImage());

    return content;
  }

  createImage() {
    const image = document.createElement('img');
    image.classList.add('mp-payment-method-logo-image');

    image.alt = this.getAttribute('alt');
    image.src = this.getAttribute('src');
    image.onerror = (e) => e.target?.parentNode?.parentNode?.parentNode?.removeChild(e.target.parentNode.parentNode);

    return image;
  }
}

customElements.define('payment-method-logo', PaymentMethodLogo);
