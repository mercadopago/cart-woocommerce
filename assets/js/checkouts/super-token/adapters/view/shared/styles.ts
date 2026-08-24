/**
 * CSS class names shared by both variant views — the saved-method row and the list
 * container. Literal in the legacy `SUPER_TOKEN_STYLES` (identical across v2/v2.1),
 * so they live in the code, not in the injected config. Variant-only classes live in
 * `../v2/styles.ts` and `../v2.1/styles.ts`.
 */
export const SHARED_STYLES = {
  PAYMENT_METHOD: 'mp-super-token-payment-method',
  PAYMENT_METHOD_HEADER: 'mp-super-token-payment-method__header',
  PAYMENT_METHOD_THUMBNAIL: 'mp-super-token-payment-method__thumbnail',
  PAYMENT_METHOD_CONTENT: 'mp-super-token-payment-method__content',
  PAYMENT_METHOD_CONTENT_TITLE: 'mp-super-token-payment-method__content-title',
  PAYMENT_METHOD_TITLE: 'mp-super-token-payment-method__title',
  PAYMENT_METHOD_LAST_FOUR_DIGITS: 'mp-super-token-payment-method__last-four-digits',
  PAYMENT_METHOD_VALUE_PROP: 'mp-super-token-payment-method__value-prop',
  PAYMENT_METHOD_SELECTED: 'mp-super-token-payment-method__selected',
  // Detail accordion (installments + security code), hidden until the row is selected.
  PAYMENT_METHOD_DETAILS: 'mp-super-token-payment-method__details',
  PAYMENT_METHOD_HIDE: 'mp-super-token-hide',
  METHOD_DETAILS_WRAPPER: 'mp-super-token-method-details-wrapper',
  // Installments select.
  INSTALLMENTS_SELECT_CONTAINER: 'mp-checkout-custom-installments-select-container',
  INPUT_LABEL: 'mp-input-label',
  SELECT_INPUT: 'mp-custom-checkout-select-input',
  INSTALLMENTS_TAX_INFO: 'mp-installments-tax-info',
  INSTALLMENTS_ERROR: 'mp-super-token-error',
  INSTALLMENTS_LABEL_ERROR: 'mp-super-token-label-error',
  // Security-code field container (the SDK mounts the CVV input into SECURITY_CODE_INPUT).
  SECURITY_CODE_CONTAINER: 'mp-super-token-security-code-container',
  SECURITY_CODE_LABEL: 'mp-super-token-security-code-label',
  SECURITY_CODE_INPUT: 'mp-super-token-security-code-input',
  SECURITY_CODE_TOOLTIP: 'mp-super-token-security-code-tooltip',
  INPUT_TOOLTIP_HELPER_ERROR: 'mp-input-with-tooltip-helper-error',
  SECURITY_CODE_ERROR_MESSAGE: 'mp-super-token-security-code-error-message',
} as const;
