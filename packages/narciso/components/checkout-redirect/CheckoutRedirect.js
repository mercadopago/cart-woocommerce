class CheckoutRedirect extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-checkout-redirect-container');
    container.setAttribute('data-cy', 'checkout-redirect-container');

    container.appendChild(this.createImage());
    container.appendChild(this.createText());

    return container;
  }

  createImage() {
    const image = document.createElement('img');

    image.classList.add('mp-checkout-redirect-image');
    image.src = this.getAttribute('src');
    image.alt = this.getAttribute('alt');

    return image;
  }

  createText() {
    const text = document.createElement('p');

    text.classList.add('mp-checkout-redirect-text');
    text.innerHTML = this.getAttribute('text');

    return text;
  }
}

customElements.define('checkout-redirect', CheckoutRedirect);
