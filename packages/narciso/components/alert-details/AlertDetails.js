class AlertDetails extends HTMLElement {
  static get observedAttributes() {
    return ['title', 'description', 'retryButtonText'];
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
    const alertDetails = this.createAlertDetails();
    const content = this.createCardContent();

    alertDetails.appendChild(content);

    this.appendChild(alertDetails);
  }

  createAlertDetails() {
    const testMode = document.createElement('div');
    testMode.classList.add('mp-alert-details-card');

    return testMode;
  }

  createCardContent() {
    const content = document.createElement('div');
    content.classList.add('mp-alert-details-card-content');

    const left = document.createElement('div');
    left.classList.add('mp-alert-details-card-content-left');

    const right = document.createElement('div');
    right.classList.add('mp-alert-details-card-content-right');

    content.appendChild(left);
    content.appendChild(right);

    const badge = this.createBadge();
    const title = this.createTitle();
    const description = this.createDescription();
    const retryButton = this.createRetryButton();

    left.appendChild(badge);
    right.appendChild(title);
    right.appendChild(description);
    right.appendChild(retryButton);

    return content;
  }

  createBadge() {
    const badge = document.createElement('div');
    badge.innerHTML = '!';
    badge.classList.add('mp-alert-details-badge');

    return badge;
  }

  createTitle() {
    const title = document.createElement('p');
    title.innerHTML = this.getAttribute('title');
    title.classList.add('mp-alert-details-title');

    return title;
  }

  createDescription() {
    const description = document.createElement('p');
    description.innerHTML = this.getAttribute('description');
    description.classList.add('mp-alert-details-description');

    return description;
  }

  createRetryButton() {
    const retryButtonText = this.getAttribute('retryButtonText');

    const retryButton = document.createElement('button');
    retryButton.classList.add('mp-alert-details-retry-button');
    retryButton.innerHTML = retryButtonText;
    retryButton.onclick = () => document.location.reload();

    return retryButton;
  }
}

customElements.define('alert-details', AlertDetails);
