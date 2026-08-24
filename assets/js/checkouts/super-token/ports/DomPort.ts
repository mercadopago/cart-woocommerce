/**
 * Port: WooCommerce checkout DOM — operations meaningful in the WooCommerce /
 * WordPress checkout context.
 *
 * Contract only — no implementation. All methods are named after what they do
 * in the checkout, not after the native DOM call they happen to use. Generic
 * DOM primitives (querySelector, getElementById, addEventListener…) are not
 * included: they are already available on every Element and Document and wrapping
 * them provides no testability or safety value.
 *
 * Representative subset — completed as the consumers migrate (TASK-009). The
 * concrete adapter lands in TASK-005.
 */
export interface DomPort {
  /** True when running inside the WooCommerce Classic Checkout (Fluid-Checkout-safe). */
  isClassicCheckout(): boolean;
  /** True when running inside the WooCommerce Blocks Checkout. */
  isBlocksCheckout(): boolean;

  getCheckoutLoader(): HTMLElement | null;
  moveLoaderToPaymentMethodsList(loader: HTMLElement, paymentMethodsList: Element): void;
  removeLoaderFromPaymentMethodsList(loader: HTMLElement, checkoutContainer: Element): void;

  closeAccordion(accordionContent: Element, accordionElement: HTMLElement): void;
  selectNewCardAccordion(
    accordionElement: HTMLElement,
    accordionContent: Element,
    accordionHeader: Element,
  ): void;

  selectPaymentMethod(paymentMethodElement: Element): void;
  deselectAllPaymentMethods(): void;

  showSecurityCodeError(element: Element, text: string): void;
  clearSecurityCodeError(element: Element): void;
}
