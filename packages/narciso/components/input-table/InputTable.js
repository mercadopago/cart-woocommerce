class InputTable extends HTMLElement {
  static get observedAttributes() {
    return ['columns', 'name', 'button-name', 'bank-interest-text'];
  }

  constructor() {
    super();
    this.index = 0;
    this.limit = 5;
    this.offset = this.limit;
    this.columns = null;
    this.total = 0;
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

  setColumns() {
    this.columns = JSON.parse(this.getAttribute('columns'));
    return this;
  }

  setTotal() {
    this.total = this.columns.length;
    return this;
  }

  createContainer() {
    const container = document.createElement('div');

    this.setColumns();

    if (this.columns) {
      this.setTotal();

      container.classList.add('mp-input-table-container');
      container.setAttribute('data-cy', 'input-table-container');
      container.appendChild(this.createList());
      container.appendChild(this.createBankInterestDisclaimer());
    }

    return container;
  }

  createList() {
    const list = document.createElement('div');
    list.classList.add('mp-input-table-list');
    list.setAttribute('data-cy', 'input-table-list');

    const link = this.createLink();
    link.onclick = () => this.appendItems(this.columns, list, link, true);

    this.appendItems(this.columns, list, link, false);
    list.appendChild(link);

    return list;
  }

  createItem(columns) {
    const item = document.createElement('div');

    item.classList.add('mp-input-table-item');
    item.appendChild(this.createLabel(columns));

    return item;
  }

  createLabel(columns) {
    const { id, value, rowText, rowObs, highlight, img, alt, dataRate } = columns;

    const name = this.getAttribute('name');
    const label = document.createElement('div');
    label.classList.add('mp-input-table-label');

    label.appendChild(this.createOption(id, name, value, rowText, img, alt, dataRate));

    if (rowObs) {
      label.appendChild(this.createRowObs(rowObs, highlight));
    }

    label.onclick = () => {
      document.getElementById(id).checked = true;
    };

    return label;
  }

  createOption(id, name, value, rowText, img, alt, dataRate) {
    const option = document.createElement('div');

    option.classList.add('mp-input-table-option');
    option.appendChild(this.createRadio(id, name, value, dataRate));

    img
      ? option.appendChild(this.createRowTextWithImg(rowText, img, alt))
      : option.appendChild(this.createRowText(rowText));

    return option;
  }

  createRadio(id, name, value, dataRate) {
    const radio = document.createElement('input-radio');

    radio.setAttribute('name', name);
    radio.setAttribute('value', value);
    radio.setAttribute('identification', id);
    radio.setAttribute('dataRate', dataRate);

    return radio;
  }

  createRowText(value) {
    const rowText = document.createElement('span');

    rowText.classList.add('mp-input-table-row-text');
    rowText.innerHTML = value;

    return rowText;
  }

  createRowTextWithImg(value, img, alt) {
    const rowText = document.createElement('span');
    const image = document.createElement('payment-method-logo');

    image.setAttribute('src', img);
    image.setAttribute('alt', alt);
    image.style.marginRight = '10px';

    rowText.classList.add('mp-input-table-row-text-image');
    rowText.innerHTML = value;
    rowText.appendChild(image);

    return rowText;
  }

  createRowObs(value, isHighlight) {
    const rowObs = document.createElement('span');

    isHighlight
      ? rowObs.classList.add('mp-input-table-row-obs-highlight')
      : rowObs.classList.add('mp-input-table-row-obs');

    rowObs.innerHTML = value;

    return rowObs;
  }

  createLink() {
    const container = document.createElement('div');
    container.classList.add('mp-input-table-container-link');

    const link = document.createElement('a');
    link.setAttribute('id', 'more-options')
    link.classList.add('mp-input-table-link');
    link.innerHTML = this.getAttribute('button-name');

    container.appendChild(link);

    return container;
  }

  createBankInterestDisclaimer() {
    const container = document.createElement('div');
    container.classList.add('mp-input-table-bank-interest-container');

    const text = document.createElement('p');
    text.classList.add('mp-input-table-bank-interest-text');
    text.innerText = this.getAttribute('bank-interest-text');

    container.appendChild(text);

    return container;
  }

  appendItems(columns, list, link, before) {
    this.validateLimit();

    for (let i = this.index; i < this.limit; i += 1) {
      if (before) {
        list.insertBefore(this.createItem(columns[i]), link);
      } else {
        list.appendChild(this.createItem(columns[i]));
      }
    }

    if (this.limit >= this.total) {
      link.style.setProperty('display', 'none', 'important');
    }

    this.index += this.offset;
    this.limit += this.offset;

    this.validateLimit();
  }

  validateLimit() {
    if (this.limit > this.total) {
      this.limit = this.total;
    }
  }
}

customElements.define('input-table', InputTable);
