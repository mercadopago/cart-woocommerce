import type { DomPort } from '@super-token/ports';

/**
 * Platform adapter: the WooCommerce checkout DOM (RN-3). Implements `DomPort`
 * with operations that are meaningful in the WooCommerce / WordPress checkout
 * context — not generic wrappers of native DOM APIs.
 *
 * `isClassicCheckout` uses the Fluid-Checkout-safe detection pattern
 * (checkout-resilience.md): checking the Blocks registry for registered methods
 * is the only reliable signal in hybrid Classic+Blocks environments.
 *
 * Additional safe-sink helpers (`setText`, `dangerouslySetHtml`, `setImageSource`,
 * `escapeHtml`) are exposed as concrete methods for the view layer (TASK-008)
 * but are not part of the DomPort contract: use cases interact through the
 * named WooCommerce operations, not through low-level DOM accessors.
 *
 * SEC-3 (XSS, CWE-79): user/SDK-controlled strings (card `name`, `thumbnail`,
 * `last_four_digits`, SDK error messages) must go through the safe sinks
 * defined below; `dangerouslySetHtml` is reserved for static markup templates only.
 */
export class WooDomAdapter implements DomPort {
  private readonly SAFE_URL = /^(https?:\/\/|\/\/|\/|\.\/|\.\.\/)/i;

  // Class names mirrored exactly from SUPER_TOKEN_STYLES in
  // v2/entities/super-token-payment-methods.js (lines 40, 43).
  private readonly PAYMENT_METHOD_SELECTED_CLASS = 'mp-super-token-payment-method__selected';
  private readonly ACCORDION_CONTENT_OPEN_CLASS = 'mp-super-token-payment-method__accordion-content--open';
  private readonly LOADER_SELECTOR = '.mp-checkout-custom-load';
  // 'error' is the canonical class used by the legacy code on label/container/input (lines 1608-1621).
  private readonly ERROR_CLASS = 'error';

  // ─── DomPort — checkout-type detection ──────────────────────────────────────

  /**
   * Returns true for Classic Checkout, false for Blocks.
   * Uses the Fluid-Checkout-safe heuristic: Classic is identified by the
   * absence of registered Blocks payment methods, not by DOM selectors which
   * are unreliable in hybrid environments.
   */
  isClassicCheckout(): boolean {
    if (typeof window.wc === 'undefined') return true;
    const registeredMethods = window.wc.wcBlocksRegistry?.getPaymentMethods?.() ?? {};
    return Object.keys(registeredMethods).length === 0;
  }

  isBlocksCheckout(): boolean {
    return !this.isClassicCheckout();
  }

  // ─── DomPort — checkout loader ───────────────────────────────────────────────

  getCheckoutLoader(): HTMLElement | null {
    return document.querySelector(this.LOADER_SELECTOR);
  }

  moveLoaderToPaymentMethodsList(loader: HTMLElement, paymentMethodsList: Element): void {
    paymentMethodsList.parentElement?.appendChild(loader);
  }

  removeLoaderFromPaymentMethodsList(loader: HTMLElement, checkoutContainer: Element): void {
    checkoutContainer.appendChild(loader);
  }

  // ─── DomPort — accordion ─────────────────────────────────────────────────────

  // TASK-009 wiring note: the legacy callers (payment-methods.js:457-463, :484-487) guard
  // these elements with `if (accordionContent)` and `if (accordionElement)` + console.warn.
  // The adapter relies on TypeScript non-null at the adapter boundary; when TASK-009 wires
  // the real DOM calls, the caller must preserve those null-guards to avoid null-deref in
  // an uncontrolled checkout environment (checkout-resilience rule).

  closeAccordion(accordionContent: Element, accordionElement: HTMLElement): void {
    accordionContent.classList.remove(this.ACCORDION_CONTENT_OPEN_CLASS);
    accordionElement.style.height = '48px';
  }

  selectNewCardAccordion(
    accordionElement: HTMLElement,
    accordionContent: Element,
    accordionHeader: Element,
  ): void {
    accordionElement.classList.add(this.PAYMENT_METHOD_SELECTED_CLASS);
    accordionElement.style.height = '48px';
    accordionHeader.setAttribute('aria-selected', 'true');

    setTimeout(() => {
      accordionContent.classList.add(this.ACCORDION_CONTENT_OPEN_CLASS);
      requestAnimationFrame(() => {
        accordionElement.style.height = 'auto';
        accordionElement.style.overflow = 'visible';
      });
    }, 10);
  }

  // ─── DomPort — payment method selection ─────────────────────────────────────

  selectPaymentMethod(paymentMethodElement: Element): void {
    paymentMethodElement.classList.add(this.PAYMENT_METHOD_SELECTED_CLASS);
    paymentMethodElement.setAttribute('aria-selected', 'true');
  }

  deselectAllPaymentMethods(): void {
    document
      .querySelectorAll(`.${this.PAYMENT_METHOD_SELECTED_CLASS}`)
      .forEach((element) => {
        element.classList.remove(this.PAYMENT_METHOD_SELECTED_CLASS);
        element.setAttribute('aria-selected', 'false');
      });
  }

  // ─── DomPort — security-code error display ───────────────────────────────────

  showSecurityCodeError(element: Element, text: string): void {
    this.setText(element, text);
    element.classList.add(this.ERROR_CLASS);
  }

  clearSecurityCodeError(element: Element): void {
    element.textContent = '';
    element.classList.remove(this.ERROR_CLASS);
  }

  // ─── Safe sinks for the view layer (not in DomPort contract) ────────────────
  // Used by TASK-008 when element builders are migrated into the adapter tree.
  // SEC-3: user/SDK-controlled data must use setText or setImageSource.

  /** Use for any user/SDK-controlled string — prevents innerHTML XSS. */
  setText(element: Element, text: unknown): void {
    element.textContent = String(text);
  }

  /** Use only for static markup templates — never pass interpolated user data. */
  dangerouslySetHtml(element: Element, staticHtml: string): void {
    element.innerHTML = staticHtml;
  }

  /** Rejects active-content URL schemes (javascript:, data:, vbscript:). */
  setImageSource(image: HTMLImageElement, src: unknown): void {
    if (typeof src === 'string' && this.SAFE_URL.test(src.trim())) {
      image.setAttribute('src', src);
    }
  }

  escapeHtml(str: unknown): string {
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
}
