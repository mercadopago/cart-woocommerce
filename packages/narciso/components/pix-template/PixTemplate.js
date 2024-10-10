class PixTemplate extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    this.appendChild(this.createContainer());
  }

  createContainer() {
    const container = document.createElement('div');
    container.classList.add('mp-pix-template-container');
    container.setAttribute('data-cy', 'pix-template-container');

    container.appendChild(this.createImage());
    container.appendChild(this.createTitle());
    container.appendChild(this.createSubtitle());

    return container;
  }

  createTitle() {
    const title = document.createElement('p');

    title.classList.add('mp-pix-template-title');
    title.innerText = this.getAttribute('title');

    return title;
  }

  createSubtitle() {
    const subtitle = document.createElement('p');

    subtitle.classList.add('mp-pix-template-subtitle');
    subtitle.innerText = this.getAttribute('subtitle');

    return subtitle;
  }

  createImage() {
    const image = document.createElement('img');

    image.classList.add('mp-pix-template-image');
    image.src = this.getAttribute('src');
    image.alt = this.getAttribute('alt');

    return image;
  }
}

customElements.define('pix-template', PixTemplate);
