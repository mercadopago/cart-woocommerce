class PaymentMethodLogoSlider extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-payment-method-logo-slider-container');
    container.appendChild(this.createContent());

    return container;
  }

  createContent() {
    const methods = JSON.parse(this.getAttribute('methods'));

    const content = document.createElement('div');
    content.classList.add('mp-payment-method-logo-slider-content');
    content.appendChild(this.createImage(methods[0]));

    const selector = content.firstChild;
    this.createSlider(selector, methods);

    return content;
  }

  createImage({ src, alt }) {
    const image = document.createElement('payment-method-logo');

    image.setAttribute('src', src);
    image.setAttribute('alt', alt);

    return image;
  }

  createSlider(content, methods, index = 0) {
    content.setAttribute('src', methods[index].src);
    content.setAttribute('alt', methods[index].alt);

    index = (index < methods.length-1) ? index + 1 : 0;

    setTimeout(() => {
      this.createSlider(content, methods, index)
    }, 2000);
  }
}

customElements.define('payment-method-logo-slider', PaymentMethodLogoSlider);
