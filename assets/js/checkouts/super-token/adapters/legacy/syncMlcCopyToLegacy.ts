/** Keep older plugin controllers on the bundle's Chilean copy without replacing checkout state. */
import { createVariantViewDeps } from '@super-token/adapters/view/createVariantViewDeps';
import type { SuperTokenViewParams } from '@super-token/adapters/view';
import { MLC_COPY, usesLegacyMlcCopy } from '@super-token/adapters/platform/resolveMlcCopy';
import type { MlcCopyFlatKey, StoreLanguageCopyFlag } from '@super-token/adapters/platform/resolveMlcCopy';
import type { CustomCheckoutParams } from '@super-token/types/global';

type CopyRecord = Record<string, string | undefined>;

/** The localized params as the legacy sync reads and writes them (flat copy plus the nested groups). */
export type LegacyCopyParams = Record<string, unknown> & {
  site_id: string;
  store_language_copy?: StoreLanguageCopyFlag;
  input_title?: CopyRecord;
  placeholders?: CopyRecord;
  input_helper_message?: {
    installments?: CopyRecord;
    securityCode?: CopyRecord;
    [key: string]: unknown;
  };
};

/** Legacy controller (`window.mpSuperTokenPaymentMethods`, cast by the caller): only its copy fields are written. */
export type LegacyCopyFields = Record<string, unknown> & {
  variantView?: { savedCardsView?: { deps?: { copy?: Record<string, unknown> } } };
};

/** The legacy controller's SCREAMING_CASE copy field for each flat localized key. */
const CONTROLLER_COPY_FIELDS: Record<string, MlcCopyFlatKey> = {
  PAYMENT_METHODS_LIST_TEXT: 'payment_methods_list_text',
  PAYMENT_METHODS_LIST_ALT_TEXT: 'payment_methods_list_alt_text',
  SAVED_CARDS_TITLE: 'saved_cards_title',
  SAVED_CARD_TITLE: 'saved_card_title',
  MP_METHODS_TITLE: 'mp_methods_title',
  SAVED_PAYMENT_METHOD_TITLE: 'saved_payment_method_title',
  LAST_DIGITS_TEXT: 'last_digits_text',
  NEW_CARD_TEXT: 'new_card_text',
  ACCOUNT_MONEY_TEXT: 'account_money_text',
  ACCOUNT_MONEY_BALANCE_TEXT: 'account_money_balance_text',
  MERCADO_PAGO_CARD_NAME: 'mercado_pago_card_name',
  MERCADO_PAGO_CREDIT_CARD_NAME: 'mercado_pago_credit_card_name',
  INTEREST_FREE_PART_ONE_TEXT: 'interest_free_part_one_text',
  INTEREST_FREE_PART_TWO_TEXT: 'interest_free_part_two_text',
  SECURITY_CODE_INPUT_TITLE_TEXT: 'security_code_input_title_text',
  SECURITY_CODE_PLACEHOLDER_TEXT_3_DIGITS: 'security_code_placeholder_text_3_digits',
  SECURITY_CODE_PLACEHOLDER_TEXT_4_DIGITS: 'security_code_placeholder_text_4_digits',
  SECURITY_CODE_TOOLTIP_TEXT_3_DIGITS: 'security_code_tooltip_text_3_digits',
  SECURITY_CODE_TOOLTIP_TEXT_4_DIGITS: 'security_code_tooltip_text_4_digits',
  MERCADO_PAGO_PRIVACY_POLICY: 'mercadopago_privacy_policy',
  UPDATE_SECURITY_CODE_WITH_RETRY_ERROR_TEXT: 'update_security_code_with_retry_error_text',
  UPDATE_SECURITY_CODE_NO_RETRY_ERROR_TEXT: 'update_security_code_no_retry_error_text',
  AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT: 'authorize_payment_method_with_retry_error_text',
  AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT: 'authorize_payment_method_no_retry_error_text',
  SELECT_PAYMENT_METHOD_ERROR_TEXT: 'select_payment_method_error_text',
  SUBMIT_SUPER_TOKEN_GENERIC_ERROR_TEXT: 'update_security_code_with_retry_error_text',
};

/** The account-money row's only marker shared by v2 and v2.1 — v2 never adds the
 *  `.mp-super-token-account-money-row` class (its `RowPresentation.extraClasses` is empty). */
const ACCOUNT_MONEY_ROW_SELECTOR = '[data-type="account_money"]';
const PRIVACY_FOOTER_ID = 'mp-super-token-privacy-policy-footer';
const MLC_PRIVACY_POLICY_URL = 'https://www.mercadopago.cl/privacidad';

/** Plugin catalogs carry the raw `&nbsp;` entity; pre-refactor `innerHTML` renderers decode it to
 *  a real NBSP (U+00A0) in the DOM. Collapse both forms to a plain space so either side matches. */
function normalizeNbsp(text: string): string {
  return text.replace(/&nbsp;| /g, ' ');
}

/** Substring replace that treats `&nbsp;` and a real NBSP as a plain space on both sides. Null when no match. */
function replaceNormalized(text: string, previous: string, next: string): string | null {
  const normalizedText = normalizeNbsp(text);
  const normalizedPrevious = normalizeNbsp(previous);
  if (!normalizedText.includes(normalizedPrevious)) {
    return null;
  }
  return normalizedText.replace(normalizedPrevious, normalizeNbsp(next));
}

function replaceVisibleText(selector: string, previous: string | undefined, next: string | undefined): void {
  if (!previous || !next || previous === next) {
    return;
  }
  document.querySelectorAll(selector).forEach((element) => {
    const current = element.textContent;
    const updated = current && replaceNormalized(current, previous, next);
    if (updated !== null && updated !== undefined) {
      element.textContent = updated;
    }
  });
}

function replaceVisibleAttribute(
  selector: string,
  attribute: string,
  previous: string | undefined,
  next: string | undefined,
): void {
  if (!previous || !next || previous === next) {
    return;
  }
  document.querySelectorAll(selector).forEach((element) => {
    const current = element.getAttribute(attribute);
    const updated = current && replaceNormalized(current, previous, next);
    if (updated !== null && updated !== undefined) {
      element.setAttribute(attribute, updated);
    }
  });
}

/** Built with DOM APIs so no plugin-supplied markup is parsed. */
export function setMlcPrivacyFooter(footer: HTMLElement): void {
  const span = document.createElement('span');
  const link = document.createElement('a');
  link.href = MLC_PRIVACY_POLICY_URL;
  link.target = '_blank';
  link.rel = 'noopener noreferrer';
  link.textContent = 'cómo cuidamos tu privacidad';
  span.append('Conoce ', link, '.');
  footer.replaceChildren(span);
}

function syncController(controller: LegacyCopyFields, localized: LegacyCopyParams): void {
  Object.entries(CONTROLLER_COPY_FIELDS).forEach(([field, key]) => {
    controller[field] = localized[key];
  });
  controller.INSTALLMENTS_INPUT_TITLE = localized.input_title?.installments;
  controller.INSTALLMENTS_PLACEHOLDER = localized.placeholders?.installments;
  controller.INSTALLMENTS_REQUIRED_MESSAGE = localized.input_helper_message?.installments?.required;
  controller.INSTALLMENTS_INTEREST_FREE_OPTION_TEXT =
    localized.input_helper_message?.installments?.interest_free_option_text;
  controller.BANK_INTEREST_HINT_TEXT = localized.input_helper_message?.installments?.bank_interest_hint_text;
  controller.SECURITY_CODE_ERROR_MESSAGES = localized.input_helper_message?.securityCode;

  // Refactored views retain their own copy object even when the controller is reused.
  const viewCopy = controller.variantView?.savedCardsView?.deps?.copy;
  if (viewCopy) {
    Object.assign(viewCopy, createVariantViewDeps(localized as unknown as SuperTokenViewParams, null).copy);
  }
}

function refreshRenderedCopy(previous: LegacyCopyParams, localized: LegacyCopyParams): void {
  const titleSelector = '.mp-super-token-block__title, .mp-payment-methods-header span';
  const titleKeys: MlcCopyFlatKey[] = [
    'payment_methods_list_text',
    'saved_cards_title',
    'saved_card_title',
    'mp_methods_title',
    'saved_payment_method_title',
  ];
  const text = (params: LegacyCopyParams, key: MlcCopyFlatKey): string | undefined =>
    params[key] as string | undefined;

  titleKeys.forEach((key) => {
    replaceVisibleText(titleSelector, text(previous, key), text(localized, key));
    replaceVisibleAttribute('.mp-super-token-block', 'aria-label', text(previous, key), text(localized, key));
  });
  replaceVisibleAttribute('#mp-checkout-super-token-root', 'aria-label',
    text(previous, 'payment_methods_list_alt_text'), text(localized, 'payment_methods_list_alt_text'));
  replaceVisibleText('.mp-super-token-payment-method__accordion-title',
    text(previous, 'new_card_text'), text(localized, 'new_card_text'));
  replaceVisibleAttribute('.mp-super-token-payment-method__accordion-header', 'aria-label',
    text(previous, 'new_card_text'), text(localized, 'new_card_text'));
  (['mercado_pago_card_name', 'mercado_pago_credit_card_name', 'account_money_text'] as MlcCopyFlatKey[]).forEach((key) => {
    replaceVisibleText('.mp-super-token-payment-method__title', text(previous, key), text(localized, key));
  });
  replaceVisibleText('.mp-super-token-am-balance-text',
    text(previous, 'account_money_balance_text'), text(localized, 'account_money_balance_text'));
  replaceVisibleText('.mp-super-token-payment-method__value-prop',
    text(previous, 'interest_free_part_one_text'), text(localized, 'interest_free_part_one_text'));
  replaceVisibleText('.mp-super-token-payment-method__value-prop',
    text(previous, 'interest_free_part_two_text'), text(localized, 'interest_free_part_two_text'));

  const previousInstallments = previous.input_helper_message?.installments ?? {};
  const localizedInstallments = localized.input_helper_message?.installments ?? {};
  replaceVisibleText('label[for^="mp-super-token-installments-select-"]',
    previous.input_title?.installments, localized.input_title?.installments);
  replaceVisibleText('select[id^="mp-super-token-installments-select-"] option',
    previous.placeholders?.installments, localized.placeholders?.installments);
  replaceVisibleText('select[id^="mp-super-token-installments-select-"] option',
    previousInstallments.interest_free_option_text, localizedInstallments.interest_free_option_text);
  replaceVisibleAttribute('input-helper[input-id^="mp-super-token-installments-error-"]', 'message',
    previousInstallments.required, localizedInstallments.required);

  replaceVisibleText('.mp-super-token-security-code-label',
    text(previous, 'security_code_input_title_text'), text(localized, 'security_code_input_title_text'));
  (['security_code_tooltip_text_3_digits', 'security_code_tooltip_text_4_digits'] as MlcCopyFlatKey[]).forEach((key) => {
    replaceVisibleAttribute('.mp-super-token-security-code-tooltip', 'aria-label', text(previous, key), text(localized, key));
    replaceVisibleAttribute('.mp-super-token-security-code-tooltip', 'data-tooltip', text(previous, key), text(localized, key));
  });
  const previousSecurityCode = previous.input_helper_message?.securityCode ?? {};
  const localizedSecurityCode = localized.input_helper_message?.securityCode ?? {};
  (['invalid_type', 'invalid_length'] as const).forEach((key) => {
    replaceVisibleText('.mp-super-token-security-code-error-message', previousSecurityCode[key], localizedSecurityCode[key]);
  });
  ([
    'update_security_code_with_retry_error_text',
    'update_security_code_no_retry_error_text',
    'authorize_payment_method_with_retry_error_text',
    'authorize_payment_method_no_retry_error_text',
    'select_payment_method_error_text',
  ] as MlcCopyFlatKey[]).forEach((key) => {
    replaceVisibleAttribute('#mp-fast-payments-error', 'description', text(previous, key), text(localized, key));
  });

  // Both renderers remove a non-qualifying installment's hint outright, so any match is current.
  const bankHint = localizedInstallments.bank_interest_hint_text;
  if (bankHint) {
    document.querySelectorAll<HTMLElement>('.mp-installments-bank-interest-hint').forEach((hint) => {
      hint.textContent = `*${bankHint}`;
    });
  }
  const privacyFooter = document.getElementById(PRIVACY_FOOTER_ID);
  if (privacyFooter) {
    setMlcPrivacyFooter(privacyFooter);
  }

  // v2.1 restores `aria-label` from `data-base-aria-label` on every deselect, so refresh both.
  document.querySelectorAll<HTMLElement>(ACCOUNT_MONEY_ROW_SELECTOR).forEach((row) => {
    (['account_money_text', 'account_money_balance_text'] as MlcCopyFlatKey[]).forEach((key) => {
      const oldValue = text(previous, key);
      const newValue = text(localized, key);
      if (!oldValue || !newValue) {
        return;
      }
      const label = row.getAttribute('aria-label');
      const updatedLabel = label && replaceNormalized(label, oldValue, newValue);
      if (updatedLabel !== null && updatedLabel !== undefined) {
        row.setAttribute('aria-label', updatedLabel);
      }
      const baseLabel = row.dataset.baseAriaLabel;
      const updatedBaseLabel = baseLabel && replaceNormalized(baseLabel, oldValue, newValue);
      if (updatedBaseLabel !== null && updatedBaseLabel !== undefined) {
        row.dataset.baseAriaLabel = updatedBaseLabel;
      }
    });
  });
}

/**
 * Composition-edge callers (`runtimeComposition.ts`) read `window.mpSuperTokenPaymentMethods` and
 * `window.wc_mercadopago_custom_checkout_params` and pass them in — this adapter stays free of
 * `window.*`, matching the `legacyDelegationSeams.ts` convention.
 */
export function syncMlcCopyToLegacy(
  original: LegacyCopyParams,
  localized: LegacyCopyParams,
  controller: LegacyCopyFields | undefined,
  customParams: CustomCheckoutParams | undefined,
): void {
  // The resolver returns the same object whenever the legacy override does not apply.
  if (original === localized || !usesLegacyMlcCopy(original)) {
    return;
  }

  const previous: LegacyCopyParams = {
    ...original,
    input_title: { ...original.input_title },
    placeholders: { ...original.placeholders },
    input_helper_message: {
      ...original.input_helper_message,
      installments: { ...original.input_helper_message?.installments },
      securityCode: { ...original.input_helper_message?.securityCode },
    },
  };

  // Old controllers can be constructed after this bundle runs, so retain the original global
  // object's identity while writing only the copy fields, never unrelated plugin parameters.
  Object.assign(original, MLC_COPY.flat);
  original.input_title = localized.input_title;
  original.placeholders = localized.placeholders;
  original.input_helper_message = localized.input_helper_message;

  const bankHint = localized.input_helper_message?.installments?.bank_interest_hint_text;
  if (customParams && bankHint) {
    const helper = customParams.input_helper_message ?? {};
    customParams.input_helper_message = {
      ...helper,
      installments: { ...helper.installments, bank_interest_hint_text: bankHint },
    };
  }

  if (controller) {
    syncController(controller, localized);
  }

  refreshRenderedCopy(previous, localized);
}
