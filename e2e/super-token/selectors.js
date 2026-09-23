// Seletores do checkout e do Super Token (lib v2.1). O ST renderiza dentro de iframes
// do MP, então as asserções são sempre por elemento — nunca por texto do body.
export const SELECTORS = {
  email: 'form[name="checkout"] input[type="email"], #email, #billing_email',
  customCheckoutRadio:
    "#radio-control-wc-payment-method-options-woo-mercado-pago-custom, #payment_method_woo-mercado-pago-custom",
  paymentMethodRadio: 'input[name^="radio-control-wc-payment-method-options"], input[name="payment_method"]',
  savedCardsList: ".mp-super-token-payment-methods-list",
  savedCard: ".mp-super-token-payment-method",
  // Meios alternativos (E2E-2, PSW-4265): o "novo cartão" é um accordion (não um article[data-type]).
  newCardAccordion: ".mp-super-token-payment-method__accordion-header",
  mpIframe: "iframe[src*=mercadopago], iframe[src*=mercadolibre]",
  securityCode: ".mp-super-token-security-code-input",
  errorNotice: "#mp-fast-payments-error",
  authorizedPseudotoken: "#authorized_pseudotoken",
  terms: "#terms",
  // Classic (#coupon_code) + Blocks. No Blocks o input já apareceu como __input (dentro do __form)
  // e como __input-container em outras versões do WC — manter ambos cobre as variações.
  couponInput: '#coupon_code, .wc-block-components-totals-coupon__form input[type="text"], .wc-block-components-totals-coupon__input-container input',
  couponApply: 'button[name="apply_coupon"], .wc-block-components-totals-coupon__button',
  // Blocks colapsa o cupom atrás de um "Adicionar cupom" (panel button escopado ao container do cupom).
  couponToggleBlocks: '.wc-block-components-totals-coupon .wc-block-components-panel__button',
  // O tema renderiza o cupom em 2 lugares (resumo mobile + sidebar desktop), um com display:none.
  // Mirar só o VISÍVEL evita o .first() cair na cópia oculta (o painel abre mas nada é digitado).
  couponInputVisible: '#coupon_code:visible, .wc-block-components-totals-coupon__form input[type="text"]:visible, .wc-block-components-totals-coupon__input-container input:visible',
  couponApplyVisible: 'button[name="apply_coupon"]:visible, .wc-block-components-totals-coupon__button:visible',
  checkoutOverlay: '.woocommerce-checkout .blockUI.blockOverlay, form.checkout .blockUI.blockOverlay',
};
