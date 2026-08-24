const { WooDomAdapter } = require('@super-token/adapters/platform/WooDomAdapter');

describe('WooDomAdapter', () => {
  let dom;

  beforeEach(() => {
    dom = new WooDomAdapter();
    document.body.innerHTML = '';
    delete window.wc;
  });

  // Checkout type detection (Fluid-Checkout-safe) ───────────────────────────────

  it('Given window.wc is undefined, When isClassicCheckout is called, Then it returns true (safe default for WP without Blocks)', () => {
    expect(dom.isClassicCheckout()).toBe(true);
    expect(dom.isBlocksCheckout()).toBe(false);
  });

  it('Given wc.wcBlocksRegistry has registered payment methods, When isClassicCheckout is called, Then it returns false (Blocks checkout)', () => {
    window.wc = { wcBlocksRegistry: { getPaymentMethods: () => ({ 'woo-mercado-pago-custom': {} }) } };

    expect(dom.isClassicCheckout()).toBe(false);
    expect(dom.isBlocksCheckout()).toBe(true);
  });

  it('Given wc.wcBlocksRegistry has no registered methods, When isClassicCheckout is called, Then it returns true (Classic checkout)', () => {
    window.wc = { wcBlocksRegistry: { getPaymentMethods: () => ({}) } };

    expect(dom.isClassicCheckout()).toBe(true);
  });

  // Safe sinks (SEC-3) ─────────────────────────────────────────────────────────

  it('Given attacker-controlled text, When setText is used, Then the markup is not parsed into DOM (XSS prevented)', () => {
    const element = document.createElement('div');

    dom.setText(element, '<img src=x onerror="alert(1)">');

    expect(element.querySelector('img')).toBeNull();
    expect(element.textContent).toBe('<img src=x onerror="alert(1)">');
  });

  it('Given an unsafe image URL (javascript:), When setImageSource is used, Then the src is rejected', () => {
    const image = document.createElement('img');

    dom.setImageSource(image, 'javascript:alert(1)');

    expect(image.getAttribute('src')).toBeNull();
  });

  it('Given a safe https URL, When setImageSource is used, Then the src is accepted', () => {
    const image = document.createElement('img');

    dom.setImageSource(image, 'https://http2.mlstatic.com/card.png');

    expect(image.getAttribute('src')).toBe('https://http2.mlstatic.com/card.png');
  });

  it('Given a string with HTML metacharacters, When escapeHtml is called, Then they are entity-encoded', () => {
    expect(dom.escapeHtml(`<b>"'`)).toBe('&lt;b&gt;&quot;&#39;');
  });

  // Checkout loader ─────────────────────────────────────────────────────────────

  it('Given a loader element and payment-methods list, When moveLoaderToPaymentMethodsList is called, Then the loader is appended to the list parent', () => {
    const parent = document.createElement('div');
    const list = document.createElement('ul');
    parent.appendChild(list);
    const loader = document.createElement('div');
    loader.className = 'mp-checkout-custom-load';
    document.body.appendChild(parent);

    dom.moveLoaderToPaymentMethodsList(loader, list);

    expect(loader.parentElement).toBe(parent);
  });

  // Accordion ───────────────────────────────────────────────────────────────────

  it('Given an accordion, When closeAccordion is called, Then the canonical open class is removed and height is set to 48px', () => {
    // Class name canonical: SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION_CONTENT_OPEN
    // in v2/entities/super-token-payment-methods.js:43
    const OPEN_CLASS = 'mp-super-token-payment-method__accordion-content--open';
    const accordionContent = document.createElement('div');
    accordionContent.classList.add(OPEN_CLASS);
    const accordionElement = document.createElement('div');
    accordionElement.style.height = '200px';

    dom.closeAccordion(accordionContent, accordionElement);

    expect(accordionContent.classList.contains(OPEN_CLASS)).toBe(false);
    expect(accordionElement.style.height).toBe('48px');
  });

  it('Given an accordion, When selectNewCardAccordion is called, Then selected state applies immediately and the content opens only after the timer', () => {
    // Timer-driven: setTimeout(10ms) syncs the open class with the height:auto animation.
    // Class names canonical: SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED / _ACCORDION_CONTENT_OPEN.
    jest.useFakeTimers();
    const SELECTED_CLASS = 'mp-super-token-payment-method__selected';
    const OPEN_CLASS = 'mp-super-token-payment-method__accordion-content--open';
    const accordionElement = document.createElement('div');
    const accordionContent = document.createElement('div');
    const accordionHeader = document.createElement('div');

    dom.selectNewCardAccordion(accordionElement, accordionContent, accordionHeader);

    expect(accordionElement.classList.contains(SELECTED_CLASS)).toBe(true);
    expect(accordionElement.style.height).toBe('48px');
    expect(accordionHeader.getAttribute('aria-selected')).toBe('true');
    expect(accordionContent.classList.contains(OPEN_CLASS)).toBe(false);

    jest.runAllTimers();

    expect(accordionContent.classList.contains(OPEN_CLASS)).toBe(true);
    jest.useRealTimers();
  });

  // Payment method selection ────────────────────────────────────────────────────

  it('Given a payment method element, When selectPaymentMethod is called, Then the canonical selected class and aria-selected are applied', () => {
    // Class name canonical: SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED
    // in v2/entities/super-token-payment-methods.js:40
    const SELECTED_CLASS = 'mp-super-token-payment-method__selected';
    const element = document.createElement('div');

    dom.selectPaymentMethod(element);

    expect(element.classList.contains(SELECTED_CLASS)).toBe(true);
    expect(element.getAttribute('aria-selected')).toBe('true');
  });

  it('Given selected payment methods in the document, When deselectAllPaymentMethods is called, Then all lose their canonical selected state', () => {
    const SELECTED_CLASS = 'mp-super-token-payment-method__selected';
    ['a', 'b'].forEach((id) => {
      const el = document.createElement('div');
      el.id = id;
      el.classList.add(SELECTED_CLASS);
      el.setAttribute('aria-selected', 'true');
      document.body.appendChild(el);
    });

    dom.deselectAllPaymentMethods();

    document.querySelectorAll('#a, #b').forEach((el) => {
      expect(el.classList.contains(SELECTED_CLASS)).toBe(false);
      expect(el.getAttribute('aria-selected')).toBe('false');
    });
  });

  // Security code error ─────────────────────────────────────────────────────────

  it('Given an SDK error message, When showSecurityCodeError is called, Then the text is set safely and the canonical error class is added (XSS prevented)', () => {
    // Class name canonical: 'error' — used by toggleSecurityCodeErrorMessage in
    // v2/entities/super-token-payment-methods.js:1619-1621 on label/container/input.
    const element = document.createElement('span');

    dom.showSecurityCodeError(element, '<script>xss</script>');

    expect(element.querySelector('script')).toBeNull();
    expect(element.textContent).toBe('<script>xss</script>');
    expect(element.classList.contains('error')).toBe(true);
  });

  it('Given an active error, When clearSecurityCodeError is called, Then text and the canonical error class are cleared', () => {
    const element = document.createElement('span');
    element.textContent = 'CVV inválido';
    element.classList.add('error');

    dom.clearSecurityCodeError(element);

    expect(element.textContent).toBe('');
    expect(element.classList.contains('error')).toBe(false);
  });
});
