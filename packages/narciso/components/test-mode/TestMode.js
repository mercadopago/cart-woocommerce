class TestMode extends HTMLElement {
  connectedCallback() {
    this.build();
  }

  build() {
    const testMode = this.createTestMode();
    const header = this.createCardHeader();
    const content = this.createCardContent();

    testMode.appendChild(header);
    testMode.appendChild(content);

    this.appendChild(testMode);
  }

  createTestMode() {
    const testMode = document.createElement('div');
    testMode.classList.add('mp-test-mode-card');
    testMode.setAttribute('data-cy', 'test-mode-card');

    return testMode;
  }

  createCardContent() {
    const content = document.createElement('div');
    content.classList.add('mp-test-mode-card-content');

    const description = document.createElement('p');
    description.innerHTML = this.getAttribute('description');
    description.classList.add('mp-test-mode-description');
    description.setAttribute('data-cy', 'test-mode-description');
    content.appendChild(description);

    const linkText = this.getAttribute('link-text');
    const linkUrl = this.getAttribute('link-src');

    const anchor = document.createElement('a');
    anchor.classList.add('mp-test-mode-link');
    anchor.innerHTML = linkText;
    anchor.href = linkUrl;
    anchor.target = 'blank';

    description.appendChild(anchor);

    return content;
  }

  createCardHeader() {
    const cardHeader = document.createElement('div');
    cardHeader.classList.add('mp-test-mode-card-content');

    const badge = this.createBadge();
    const title = this.createTitle();

    cardHeader.appendChild(badge);
    cardHeader.appendChild(title);

    return cardHeader;
  }

  createBadge() {
    const badge = document.createElement('div');
    badge.innerHTML = '!';
    badge.classList.add('mp-test-mode-badge');

    return badge;
  }

  createTitle() {
    const title = document.createElement('p');
    title.innerHTML = this.getAttribute('title');
    title.classList.add('mp-test-mode-title');
    title.setAttribute('data-cy', 'test-mode-title');

    return title;
  }
}

customElements.define('test-mode', TestMode);
