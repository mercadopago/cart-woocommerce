class TermsAndConditions extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-terms-and-conditions-container');
    container.setAttribute('data-cy', 'terms-and-conditions-container');

    container.appendChild(this.createText());
    container.appendChild(this.createLink());

    return container;
  }

  createText() {
    const text = document.createElement('span');

    text.classList.add('mp-terms-and-conditions-text');
    text.innerHTML = this.getAttribute('description');

    return text;
  }

  createLink() {
    const link = document.createElement('a');

    link.classList.add('mp-terms-and-conditions-link');
    link.innerHTML = this.getAttribute('link-text');
    link.href = this.getAttribute('link-src');
    link.target = 'blank';

    return link;
  }
}

customElements.define('terms-and-conditions', TermsAndConditions);
