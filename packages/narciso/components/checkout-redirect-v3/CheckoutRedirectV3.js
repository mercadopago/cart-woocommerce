class CheckoutRedirectV3 extends HTMLElement {
  tabNumber = 5

  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-checkout-redirect-v3-container');
    container.setAttribute('data-cy', 'checkout-redirect-v3-container');

    container.appendChild(this.createTitleContainer());
    container.appendChild(this.createDescription());

    return container;
  }

  createTitleContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-checkout-redirect-v3-title-container');

    container.appendChild(this.createMPLogoImage());
    container.appendChild(this.createTitle());

    return container;
  }

  createMPLogoImage() {
    const image = document.createElement('img');

    image.classList.add('mp-checkout-redirect-v3-mp-logo-image');
    image.setAttribute('aria-hidden', 'true');
    image.src = this.getAttribute('src');
    image.alt = this.getAttribute('alt');

    return image;
  }

  createTitle() {
    const text = document.createElement('p');

    text.classList.add('mp-checkout-redirect-v3-title');
    text.innerHTML = this.getAttribute('title');
    text.tabIndex = this.tabNumber;
    this.tabNumber++;

    return text;
  }

  createDescription() {
    const text = document.createElement('p');

    text.classList.add('mp-checkout-redirect-v3-description');
    text.innerHTML = this.getAttribute('description');
    text.tabIndex = this.tabNumber;
    this.tabNumber++;

    return text;
  }
}

customElements.define('checkout-redirect-v3', CheckoutRedirectV3);
