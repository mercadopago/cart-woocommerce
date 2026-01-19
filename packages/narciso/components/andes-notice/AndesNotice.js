class AndesNotice extends HTMLElement {
  connectedCallback() {
    this.render();
  }

  getBadgeIcon(type = 'warning') {
    const icons = {
      error: 'X',
      warning: '!',
    };
    return icons[type];
  }

  render() {
    const description = this.getAttribute('description') || '';
    const type = this.getAttribute('type') || 'warning';
    const badgeIcon = this.getBadgeIcon(type);

    this.innerHTML = `
      <div class="mp-andes-notice-card ${type}">
        <div class="mp-andes-notice-card-content">
          <div class="mp-andes-notice-badge ${type}">${badgeIcon}</div>
          <span class="mp-andes-notice-description">
            ${description}
          </span>
        </div>
      </div>
    `;
  }
}

customElements.define('andes-notice', AndesNotice);
