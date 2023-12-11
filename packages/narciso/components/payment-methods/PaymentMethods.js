class PaymentMethods extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const paymentMethods = JSON.parse(this.getAttribute('methods'));

    const container = document.createElement('div');
    container.classList.add('mp-payment-methods-container');

    paymentMethods.forEach((method) => {
      container.appendChild(this.createPaymentMethodType(method));
    });

    return container;
  }

  createPaymentMethodType(method) {
    const { title, label, payment_methods: paymentMethods } = method;

    const paymentType = document.createElement('div');
    paymentType.classList.add('mp-payment-method-type-container');

    if (paymentMethods && paymentMethods.length !== 0) {
      paymentType.appendChild(this.createHeader(title, label));
      paymentType.appendChild(this.createContent(paymentMethods));
    }

    return paymentType;
  }

  createHeader(title, label) {
    const header = document.createElement('div');
    header.classList.add('mp-payment-methods-header');

    if (title) {
      header.appendChild(this.createTitle(title));
    }

    if (label) {
      header.appendChild(this.createBadge(label));
    }

    return header;
  }

  createTitle(value) {
    const title = document.createElement('p');

    title.classList.add('mp-payment-methods-title');
    title.innerHTML = value;

    return title;
  }

  createBadge(value) {
    const badge = document.createElement('div');
    const text = document.createElement('span');

    text.classList.add('mp-payment-methods-badge-text');
    text.innerHTML = value;

    badge.classList.add('mp-payment-methods-badge');
    badge.appendChild(text);

    return badge;
  }

  createContent(paymentMethods) {
    const content = document.createElement('div');
    content.classList.add('mp-payment-methods-content');

    paymentMethods.forEach((paymentMethod) => {
      content.appendChild(this.createImage(paymentMethod));
    });

    return content;
  }

  createImage(paymentMethod) {
    const { src, alt } = paymentMethod;
    const image = document.createElement('payment-method-logo');

    image.setAttribute('src', src);
    image.setAttribute('alt', alt);

    return image;
  }
}

customElements.define('payment-methods', PaymentMethods);
