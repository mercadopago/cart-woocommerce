class InputSelect extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-input-select-container');
    container.appendChild(this.createLabel());
    container.appendChild(this.createInput());
    container.appendChild(this.createHelper());
    return container;
  }

  createInput() {
    const input = document.createElement('div');
    input.classList.add('mp-input-select-input');

    input.appendChild(this.createSelect());

    return input;
  }

  createSelect() {
    const select = document.createElement('select');
    const id = this.getAttribute('name');

    select.classList.add('mp-input-select-select');
    select.setAttribute('id', id);
    select.setAttribute('name', id);

    const options = this.getAttribute('options') && JSON.parse(this.getAttribute('options'));

    //include default option
    if (this.getAttribute('default-option')) {
      const optionDefault = document.createElement('option');
      optionDefault.setAttribute('selected', 'selected');
      optionDefault.setAttribute('hidden', 'hidden');
      optionDefault.innerHTML = this.getAttribute('default-option');
      select.appendChild(optionDefault);
    }


    if (options && options.length !== 0) {
      options.forEach((option) => {
        select.appendChild(this.createOption(option));
      });
    }

    return select;
  }

  createOption(value) {
    const option = document.createElement('option');

    option.innerHTML = value.description;
    option.value = value.id;

    return option;
  }

  createLabel() {
    const label = document.createElement('input-label');
    const optional = this.getAttribute('optional');

    label.setAttribute('message', this.getAttribute('label'));

    if (optional === 'false') {
      label.setAttribute('isOptional', optional);
    } else {
      label.setAttribute('isOptional', 'true');
    }

    return label;
  }

  createHelper() {
    const helper = document.createElement('input-helper');

    helper.setAttribute('isVisible', false);
    helper.setAttribute('message', this.getAttribute('helper-message'));
    helper.setAttribute('input-id', 'mp-doc-number-helper');

    return helper;
}

  createHiddenField(id) {
    const field = document.createElement('input');
    field.setAttribute('type', "hidden");
    field.setAttribute('id', id);

    return field;
  }

}

customElements.define('input-select', InputSelect);
