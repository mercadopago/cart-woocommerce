/** Preserve the established pre-flag Chilean policy and its Custom checkout bank-hint bridge. */
import { MLC_COPY, usesLegacyMlcCopy } from '@super-token/adapters/platform/resolveMlcCopy';
import type { CustomCheckoutParams } from '@super-token/types/global';
import { setCopyPrivacyFooter, syncCopyToLegacy } from './syncCopyToLegacy';
import type { LegacyCopyParams, LegacyCopyFields } from './syncCopyToLegacy';
export type { LegacyCopyParams, LegacyCopyFields } from './syncCopyToLegacy';

const PRIVACY = { url: 'https://www.mercadopago.cl/privacidad', prefix: 'Conoce' };

export function setMlcPrivacyFooter(footer: HTMLElement): void {
  setCopyPrivacyFooter(footer, PRIVACY);
}

export function syncMlcCopyToLegacy(
  original: LegacyCopyParams,
  localized: LegacyCopyParams,
  controller: LegacyCopyFields | undefined,
  customParams: CustomCheckoutParams | undefined,
): void {
  if (original === localized || !usesLegacyMlcCopy(original)) return;
  syncCopyToLegacy(original, localized, controller, Object.keys(MLC_COPY.flat));
  const footer = document.getElementById('mp-super-token-privacy-policy-footer');
  if (footer) setMlcPrivacyFooter(footer);
  const bankHint = localized.input_helper_message?.installments?.bank_interest_hint_text;
  if (customParams && bankHint) {
    const helper = customParams.input_helper_message ?? {};
    customParams.input_helper_message = {
      ...helper,
      installments: { ...helper.installments, bank_interest_hint_text: bankHint },
    };
  }
}
