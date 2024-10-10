class CheckoutBenefitsList extends HTMLElement {
  tabNumber = 1

  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-checkout-benefits-list-container');

    container.appendChild(this.createTitle());
    container.appendChild(this.createList());

    return container;
  }

  createTitle() {
    const title = document.createElement('p');

    title.classList.add('mp-checkout-benefits-list-title');
    title.innerHTML = this.getAttribute('title');
    title.tabIndex = this.tabNumber;
    this.tabNumber++;
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
    div.classList.add('mp-checkout-benefits-list-item');

    div.appendChild(this.createCountItem(index));
    div.appendChild(this.createSimpleText(item));
    return div;
  }

  createSimpleText(value) {
    const text = document.createElement('span');
    text.innerHTML = value;
    text.tabIndex = this.tabNumber;
    this.tabNumber++;

    return text;
  }

  createCountItem(stepCounter) {
    const p = document.createElement('p');
    p.innerText = (1 + stepCounter) + ".";
    p.classList.add('mp-checkout-benefits-list-count-list-item');

    return p;
  }
}

customElements.define('checkout-benefits-list', CheckoutBenefitsList);
