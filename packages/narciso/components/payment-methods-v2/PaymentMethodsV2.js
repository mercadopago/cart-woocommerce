class PaymentMethodsV2 extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-payment-methods-v2-container');

    container.appendChild(this.createContent());

    return container;
  }

  createContent() {
    const content = document.createElement('div');
    content.classList.add('mp-payment-methods-v2-content');

    content.appendChild(this.createTitle());
    content.appendChild(this.createList());

    return content;
  }

  createTitle() {
    const title = document.createElement('p');

    title.classList.add('mp-payment-methods-v2-title');
    title.innerHTML = this.getAttribute('title');

    return title;
  }

  createList() {
    const list = document.createElement('div');
    list.classList.add('mp-payment-methods-v2-list');

    return this.handleMethodsList(list);
  }

  handleMethodsList(list) {
    const methods = JSON.parse(this.getAttribute('methods'));
    let hasSlider = false;

    methods.forEach((method, index) => {
      if (index <= 9 || methods.length === 11) {
        list.appendChild(this.createLogo(method));
      } else {
        hasSlider = true;
      }
    });

    if (hasSlider) {
      const remainingMethods = Object.entries(methods).slice(10).map(remainingMethod => remainingMethod[1]);
      list.appendChild(this.createSlider(JSON.stringify(remainingMethods)));
    }

    return list;
  }

  createLogo({ src, alt }) {
    const image = document.createElement('payment-method-logo');

    image.setAttribute('src', src);
    image.setAttribute('alt', alt);

    return image;
  }

  createSlider(methods) {
    const slider = document.createElement('payment-method-logo-slider');

    slider.setAttribute('methods', methods);

    return slider;
  }
}

customElements.define('payment-methods-v2', PaymentMethodsV2);
