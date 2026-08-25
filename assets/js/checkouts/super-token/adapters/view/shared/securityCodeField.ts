/**
 * The security-code (CVV) field container for a saved card — an empty mount point the SDK fills
 * on selection (mountSecurityCodeField, still legacy), plus its label, tooltip and error slot.
 * Ported from the legacy `buildSecurityCodeInnerHTML` (payment-methods.js:1420-1470); returns null
 * when the card does not require a CVV (`security_code_settings.mode !== 'mandatory'`).
 *
 * Built with `el` (textContent/setAttribute), so the SDK-provided token only ever lands in id
 * attributes, never as markup. The decorative error icon is a fixed SVG with no interpolation.
 */
import type { PaymentMethod } from '@super-token/types/external-globals';
import { securityCodeIsRequired } from '@super-token/core/checkoutSession/PaymentMethodEligibility';
import { el } from './dom';
import { SHARED_STYLES } from './styles';
import type { VariantViewDeps } from '../VariantViewDeps';

const THREE_DIGITS = 3;

// Fixed decorative error icon (no data interpolation) — parsed once and cloned per field.
const ERROR_ICON_SVG =
  '<svg aria-hidden="true" tabindex="-1" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">' +
  '<rect width="12" height="12" rx="6" fill="#CC1818"/>' +
  '<path d="M6.72725 2.90918H5.27271L5.45452 6.90918H6.54543L6.72725 2.90918Z" fill="white"/>' +
  '<path d="M5.99998 7.63645C6.40164 7.63645 6.72725 7.96206 6.72725 8.36373C6.72725 8.76539 6.40164 9.091 5.99998 9.091C5.59832 9.091 5.27271 8.76539 5.27271 8.36373C5.27271 7.96206 5.59832 7.63645 5.99998 7.63645Z" fill="white"/>' +
  '</svg>';

function errorIcon(): Node {
  const template = document.createElement('template');
  template.innerHTML = ERROR_ICON_SVG;
  return template.content.firstElementChild as Node;
}

export function buildSecurityCodeField(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
): HTMLElement | null {
  if (!('security_code_settings' in paymentMethod)) {
    return null;
  }
  const settings = paymentMethod.security_code_settings;
  if (!securityCodeIsRequired(settings)) {
    return null;
  }

  const token = paymentMethod.token;
  const tooltipText =
    settings?.length === THREE_DIGITS ? deps.copy.securityCodeTooltip3Digits : deps.copy.securityCodeTooltip4Digits;

  return el('div', {
    classes: [SHARED_STYLES.SECURITY_CODE_CONTAINER],
    attrs: { id: `mp-super-token-security-code-container-${token}` },
    children: [
      el('label', {
        classes: [SHARED_STYLES.SECURITY_CODE_LABEL],
        attrs: { tabindex: '0' },
        text: deps.copy.securityCodeInputTitle,
      }),
      el('div', {
        classes: [SHARED_STYLES.SECURITY_CODE_INPUT],
        attrs: { id: `mp-super-token-security-code-input-${token}` },
      }),
      el('span', {
        classes: [SHARED_STYLES.SECURITY_CODE_TOOLTIP],
        attrs: {
          tabindex: '0',
          'aria-label': tooltipText,
          role: 'tooltip',
          'data-tooltip': tooltipText,
          style: 'display: none !important;',
        },
        text: '?',
      }),
      el('div', {
        classes: [SHARED_STYLES.INPUT_TOOLTIP_HELPER_ERROR],
        attrs: { id: 'mp-input-with-tooltip-helper-error', tabindex: '0', role: 'alert' },
        children: [
          errorIcon(),
          el('span', {
            classes: [SHARED_STYLES.SECURITY_CODE_ERROR_MESSAGE],
            attrs: { id: 'mp-super-token-security-code-error-message', 'aria-hidden': 'true', tabindex: '-1' },
          }),
        ],
      }),
    ],
  });
}
