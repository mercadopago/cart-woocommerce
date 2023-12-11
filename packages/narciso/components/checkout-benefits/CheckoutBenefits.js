class CheckoutBenefits extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-checkout-benefits-container');

    container.appendChild(this.createTitle());
    container.appendChild(this.createList());

    return container;
  }

  createTitle() {
    const title = document.createElement('p');

    title.classList.add('mp-checkout-benefits-title');
    title.innerHTML = this.getAttribute('title');

    const titleAlign = this.getAttribute('title-align');

    if (titleAlign === 'center') {
      title.style.setProperty('text-align', 'center', 'important');
    }

    if (titleAlign === 'left') {
      title.style.setProperty('text-align', 'left', 'important');
    }
    return title;
  }

  createList() {
    const items = JSON.parse(this.getAttribute('items'));

    const list = document.createElement('div');
    list.classList.add('mp-checkout-benefits-list');

    items.forEach((item, index) => {
      list.appendChild(this.createItem(item, index));
    });

    return list;
  }

  createItem(item, index) {
    const div = document.createElement('div');
    div.classList.add('mp-checkout-benefits-item');

    const mode = this.getAttribute('list-mode');
    if (mode === 'count') {
      div.appendChild(this.createCountList(index));
      div.appendChild(this.createSimpleText(item));
      return div;
    }

    if (mode === 'bullet') {
      div.appendChild(this.createBulletList());
      div.appendChild(this.createSimpleText(item));
      return div;
    }

    if (mode === 'image' && typeof item === 'object') {
      div.appendChild(this.createImageList(item));
      div.appendChild(this.createCompositeText(item));
      return div;
    }

    return div;
  }

  createSimpleText(value) {
    const text = document.createElement('span');
    text.innerHTML = value;

    return text;
  }

  createCompositeText({ title, subtitle }) {
    const pTitle = document.createElement('p');
    pTitle.classList.add('mp-checkout-benefits-item-title');
    pTitle.innerHTML = title;

    const pSubtitle = document.createElement('p');
    pSubtitle.classList.add('mp-checkout-benefits-item-subtitle');
    pSubtitle.innerHTML = subtitle;

    const span = document.createElement('span');
    span.appendChild(pTitle);
    span.appendChild(pSubtitle);

    return span;
  }

  createCountList(stepCounter) {
    const p = document.createElement('p');
    p.innerText = 1 + stepCounter;
    p.classList.add('mp-checkout-benefits-count-list-item');

    const div = document.createElement('div');
    div.classList.add('mp-checkout-benefits-count-list-div');
    div.appendChild(p);
    return div;
  }

  createBulletList() {
    const container = document.createElement('div');
    container.classList.add('mp-checkout-benefits-tick-mark-container');

    const mark = document.createElement('div');
    mark.classList.add('mp-checkout-benefits-tick-mark');

    container.appendChild(mark);

    return container;
  }

  createImageList({ image }) {
    const div = document.createElement('div');
    div.classList.add('mp-checkout-benefits-image-list');

    div.appendChild(this.createImage(image));

    return div;
  }

  createImage({ src, alt }) {
    const image = document.createElement('img');
    image.classList.add('mp-checkout-benefits-image');

    image.setAttribute('src', src);
    image.setAttribute('alt', alt);

    return image;
  }
}

customElements.define('checkout-benefits', CheckoutBenefits);
