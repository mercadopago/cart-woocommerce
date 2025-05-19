class CheckoutNotice extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() {
    this.render();
  }

  render() {
    const container = document.createElement('div');
    container.classList.add('mp-info-notification');

    const content = document.createElement('div');
    content.classList.add('content');

    const icon = document.createElement('img');
    icon.src = this.getAttribute('src');
    icon.alt = 'Icone de informação';
    icon.classList.add('icon');

    const messageContainer = document.createElement('div');
    messageContainer.classList.add('message');
    messageContainer.textContent = this.getAttribute('message') || 'Mensagem de informação';

    content.appendChild(icon);
    content.appendChild(messageContainer);
    container.appendChild(content);

    const footer = document.createElement('div');
    footer.classList.add('footer');

    const footerIcon = document.createElement('img');
    footerIcon.src = this.getAttribute('icon');
    footerIcon.alt = 'Footer Icon';
    footerIcon.classList.add('footer-icon');

    footer.appendChild(footerIcon);
    footer.appendChild(document.createTextNode(this.getAttribute('footer-text') || 'Procesado por Mercado Pago'));

    this.shadowRoot.append(container);
    this.shadowRoot.append(footer);

    this.applyStyles();
  }

  applyStyles() {
    const style = document.createElement('style');
    style.textContent = `
      .mp-info-notification {
        display: flex !important;
        flex-direction: column !important;
        padding: 12px !important;
        background-color: #f7f7f7 !important;
        border-radius: 8px !important;
        border-left: 4px solid var(--andes-accent-color) !important;
        margin-bottom: 16px !important;
      }

      .content {
        display: flex !important;
        align-items: center !important;
      }

      .icon {
        max-width: 16px !important;
        max-height: 16px !important;
        margin-right: 8px !important;
      }

      @media (max-width: 600px) {
        .icon {
          margin-bottom: 15px !important;
        }
      }

      @media (max-width: 300px) {
        .icon {
          margin-bottom: 35px !important;
        }
      }

      .message {
        flex-grow: 1 !important;
        color: #333 !important;
        padding: 10px !important;
        font-family: 'Proxima Nova', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif !important;
        font-size: 14px !important;
        font-weight: 400 !important;
        line-height: 18px !important;
        text-align: left !important;
      }

      .footer {
        display: flex !important;
        align-items: center !important;
        color: #999 !important;
        margin-top: 24px !important;
        font-family: 'Proxima Nova', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        line-height: 15px !important;
        text-align: left !important;
      }

      .footer-icon {
        max-width: 20px !important;
        margin-right: 8px !important;
      }
    `;
    this.shadowRoot.appendChild(style);
  }
}

customElements.define('checkout-notice', CheckoutNotice);
