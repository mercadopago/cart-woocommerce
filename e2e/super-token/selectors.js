// Seletores do checkout e do Super Token (lib v2.1). O ST renderiza dentro de iframes
// do MP, então as asserções são sempre por elemento — nunca por texto do body.
export const SELECTORS = {
  email: 'form[name="checkout"] input[type="email"], #email, #billing_email',
  customCheckoutRadio:
    "#radio-control-wc-payment-method-options-woo-mercado-pago-custom, #payment_method_woo-mercado-pago-custom",
  paymentMethodRadio: 'input[name^="radio-control-wc-payment-method-options"], input[name="payment_method"]',
  savedCardsList: ".mp-super-token-payment-methods-list",
  savedCard: ".mp-super-token-payment-method",
  mpIframe: "iframe[src*=mercadopago], iframe[src*=mercadolibre]",
  securityCode: ".mp-super-token-security-code-input",
  errorNotice: "#mp-fast-payments-error",
  authorizedPseudotoken: "#authorized_pseudotoken",
  terms: "#terms",
  couponInput: '#coupon_code, .wc-block-components-totals-coupon__input-container input',
  couponApply: 'button[name="apply_coupon"], .wc-block-components-totals-coupon__button',
  checkoutOverlay: '.woocommerce-checkout .blockUI.blockOverlay, form.checkout .blockUI.blockOverlay',
};
