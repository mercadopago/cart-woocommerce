/** Synchronize presentation copy without replacing payment state, controls or listeners. */
import { createVariantViewDeps } from '@super-token/adapters/view/createVariantViewDeps';
import type { SuperTokenViewParams } from '@super-token/adapters/view';
import type { StoreLanguageCopyFlag } from '@super-token/adapters/platform/resolveMlcCopy';

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
const CONTROLLER_COPY_FIELDS: Record<string, string> = {
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
  ACCOUNT_MONEY_AVAILABLE_TEXT: 'account_money_available_text',
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
export interface PrivacyCopy { url: string; prefix: string }


/** Plugin catalogs carry the raw `&nbsp;` entity; pre-refactor `innerHTML` renderers decode it to
 *  a real NBSP (U+00A0) in the DOM. Collapse both forms to a plain space so either side matches. */
function normalizeNbsp(text: string): string {
  return text.replace(/&nbsp;| /g, ' ');
}

/** Substring replace that treats `&nbsp;` and a real NBSP as a plain space on both sides. Null when no match. */
function replaceNormalized(text: string, previous: string, next: string): string | null {
  const normalizedText = normalizeNbsp(text);
  const normalizedPrevious = normalizeNbsp(previous);
  if (!normalizedText.includes(normalizedPrevious) || normalizedText.includes(normalizeNbsp(next))) {
    return null;
  }
  return normalizedText.replace(normalizedPrevious, next.replace(/&nbsp;/g, '\u00a0'));
}

function copyElements(root: ParentNode, selector: string): HTMLElement[] {
  const elements = Array.from(root.querySelectorAll<HTMLElement>(selector));
  if (root instanceof HTMLElement && root.matches(selector)) elements.unshift(root);
  return elements;
}

function replaceVisibleText(selector: string, previous: string | undefined, next: string | undefined, root: ParentNode): void {
  if (!previous || !next || previous === next) {
    return;
  }
  copyElements(root, selector).forEach((element) => {
    // Preserve nested icons, accessible spans and controls owned by the existing row.
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    let node = walker.nextNode();
    while (node) {
      const updated = replaceNormalized(node.textContent ?? '', previous, next);
      if (updated !== null) node.textContent = updated;
      node = walker.nextNode();
    }
  });
}

function replaceVisibleAttribute(
  selector: string,
  attribute: string,
  previous: string | undefined,
  next: string | undefined,
  root: ParentNode,
): void {
  if (!previous || !next || previous === next) {
    return;
  }
  copyElements(root, selector).forEach((element) => {
    const current = element.getAttribute(attribute);
    const updated = current && replaceNormalized(current, previous, next);
    if (updated !== null && updated !== undefined) {
      element.setAttribute(attribute, updated);
    }
  });
}

/** Built with DOM APIs so no plugin-supplied markup is parsed. */
export function setCopyPrivacyFooter(footer: HTMLElement, privacy: PrivacyCopy): void {
  const existingLink = footer.querySelector('a');
  const span = existingLink?.parentElement ?? document.createElement('span');
  const link = existingLink ?? document.createElement('a');
  link.href = privacy.url;
  link.target = '_blank';
  link.rel = 'noopener noreferrer';
  link.textContent = 'cómo cuidamos tu privacidad';
  if (existingLink && span.childNodes.length === 3
    && span.firstChild?.nodeType === Node.TEXT_NODE && span.lastChild?.nodeType === Node.TEXT_NODE) {
    span.firstChild.textContent = `${privacy.prefix} `;
    span.lastChild.textContent = '.';
  } else {
    span.replaceChildren(`${privacy.prefix} `, link, '.');
    footer.replaceChildren(span);
  }
}

function syncController(controller: LegacyCopyFields, localized: LegacyCopyParams, flatKeys: readonly string[]): void {
  Object.entries(CONTROLLER_COPY_FIELDS).forEach(([field, key]) => {
    if (flatKeys.includes(key)) controller[field] = localized[key];
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

type CopyReplacement = { previous: string | undefined; apply: () => void };

function refreshRenderedCopy(
  previous: LegacyCopyParams,
  localized: LegacyCopyParams,
  root: ParentNode,
  replacements: CopyReplacement[],
): void {
  const replaceText = (selector: string, before: string | undefined, after: string | undefined): void => {
    replacements.push({ previous: before, apply: () => replaceVisibleText(selector, before, after, root) });
  };
  const replaceAttribute = (selector: string, attribute: string, before: string | undefined, after: string | undefined): void => {
    replacements.push({ previous: before, apply: () => replaceVisibleAttribute(selector, attribute, before, after, root) });
  };
  const titleSelector = '.mp-super-token-block__title, .mp-payment-methods-header span';
  const titleKeys: string[] = [
    'payment_methods_list_text',
    'saved_cards_title',
    'saved_card_title',
    'mp_methods_title',
    'saved_payment_method_title',
  ];
  const text = (params: LegacyCopyParams, key: string): string | undefined =>
    params[key] as string | undefined;

  titleKeys.forEach((key) => {
    replaceText(titleSelector, text(previous, key), text(localized, key));
    replaceAttribute('.mp-super-token-block', 'aria-label', text(previous, key), text(localized, key));
  });
  replaceAttribute('#mp-checkout-super-token-root', 'aria-label',
    text(previous, 'payment_methods_list_alt_text'), text(localized, 'payment_methods_list_alt_text'));
  replaceText('.mp-super-token-payment-method__accordion-title',
    text(previous, 'new_card_text'), text(localized, 'new_card_text'));
  replaceAttribute('.mp-super-token-payment-method__accordion-header', 'aria-label',
    text(previous, 'new_card_text'), text(localized, 'new_card_text'));
  (['mercado_pago_card_name', 'mercado_pago_credit_card_name', 'account_money_text'] as string[]).forEach((key) => {
    replaceText('.mp-super-token-payment-method__title', text(previous, key), text(localized, key));
  });
  replaceText('.mp-super-token-am-balance-text',
    text(previous, 'account_money_balance_text'), text(localized, 'account_money_balance_text'));
  replaceText('.mp-super-token-payment-method__value-prop',
    text(previous, 'interest_free_part_one_text'), text(localized, 'interest_free_part_one_text'));
  replaceText('.mp-super-token-payment-method__value-prop',
    text(previous, 'interest_free_part_two_text'), text(localized, 'interest_free_part_two_text'));

  const previousInstallments = previous.input_helper_message?.installments ?? {};
  const localizedInstallments = localized.input_helper_message?.installments ?? {};
  replaceText('label[for^="mp-super-token-installments-select-"]',
    previous.input_title?.installments, localized.input_title?.installments);
  replaceText('select[id^="mp-super-token-installments-select-"] option',
    previous.placeholders?.installments, localized.placeholders?.installments);
  replaceText('select[id^="mp-super-token-installments-select-"] option',
    previousInstallments.interest_free_option_text, localizedInstallments.interest_free_option_text);
  replaceAttribute('input-helper[input-id^="mp-super-token-installments-error-"]', 'message',
    previousInstallments.required, localizedInstallments.required);

  replaceText('.mp-super-token-security-code-label',
    text(previous, 'security_code_input_title_text'), text(localized, 'security_code_input_title_text'));
  (['security_code_tooltip_text_3_digits', 'security_code_tooltip_text_4_digits'] as string[]).forEach((key) => {
    replaceAttribute('.mp-super-token-security-code-tooltip', 'aria-label', text(previous, key), text(localized, key));
    replaceAttribute('.mp-super-token-security-code-tooltip', 'data-tooltip', text(previous, key), text(localized, key));
  });
  const previousSecurityCode = previous.input_helper_message?.securityCode ?? {};
  const localizedSecurityCode = localized.input_helper_message?.securityCode ?? {};
  (['invalid_type', 'invalid_length'] as const).forEach((key) => {
    replaceText('.mp-super-token-security-code-error-message', previousSecurityCode[key], localizedSecurityCode[key]);
  });
  ([
    'update_security_code_with_retry_error_text',
    'update_security_code_no_retry_error_text',
    'authorize_payment_method_with_retry_error_text',
    'authorize_payment_method_no_retry_error_text',
    'select_payment_method_error_text',
  ] as string[]).forEach((key) => {
    replaceAttribute('#mp-fast-payments-error', 'description', text(previous, key), text(localized, key));
  });

  // Both renderers remove a non-qualifying installment's hint outright, so any match is current.
  const bankHint = localizedInstallments.bank_interest_hint_text;
  if (bankHint) {
    root.querySelectorAll<HTMLElement>(root === document ? '.mp-installments-bank-interest-hint' : '[data-type] .mp-installments-bank-interest-hint').forEach((hint) => {
      hint.textContent = `*${bankHint}`;
    });
  }
  // Cached rows retain both their current and base accessible names.
  const rowCopyFields = {
    [ACCOUNT_MONEY_ROW_SELECTOR]: ['account_money_text', 'account_money_balance_text'],
    '[data-type="credit_card"], [data-type="debit_card"], [data-type="prepaid_card"]': [
      'mercado_pago_card_name', 'mercado_pago_credit_card_name', 'last_digits_text',
      'interest_free_part_one_text', 'interest_free_part_two_text',
    ],
  };
  Object.entries(rowCopyFields).forEach(([selector, keys]) => {
    keys.forEach((key) => {
      replaceAttribute(selector, 'aria-label', text(previous, key), text(localized, key));
      replaceAttribute(selector, 'data-base-aria-label', text(previous, key), text(localized, key));
    });
  });
}


export function syncCopyToLegacy(
  original: LegacyCopyParams,
  localized: LegacyCopyParams,
  controller: LegacyCopyFields | undefined,
  flatKeys: readonly string[],
  options: { root?: ParentNode; privacy?: PrivacyCopy; predecessors?: LegacyCopyParams[] } = {},
): void {
  const previous = { ...original };
  const controllerCopy: LegacyCopyParams = {
    site_id: original.site_id,
    input_title: { installments: controller?.INSTALLMENTS_INPUT_TITLE as string | undefined },
    placeholders: { installments: controller?.INSTALLMENTS_PLACEHOLDER as string | undefined },
    input_helper_message: {
      installments: {
        required: controller?.INSTALLMENTS_REQUIRED_MESSAGE as string | undefined,
        interest_free_option_text: controller?.INSTALLMENTS_INTEREST_FREE_OPTION_TEXT as string | undefined,
      },
      securityCode: controller?.SECURITY_CODE_ERROR_MESSAGES as CopyRecord | undefined,
    },
  };
  Object.entries(CONTROLLER_COPY_FIELDS).forEach(([field, key]) => {
    controllerCopy[key] = controller?.[field];
  });
  flatKeys.forEach((key) => { original[key] = localized[key]; });
  original.input_title = localized.input_title;
  original.placeholders = localized.placeholders;
  original.input_helper_message = localized.input_helper_message;
  if (controller) syncController(controller, localized, flatKeys);
  const root = options.root ?? document;
  const replacements: CopyReplacement[] = [];
  [previous, controllerCopy, ...(options.predecessors ?? [])].forEach((before) => {
    refreshRenderedCopy(before, localized, root, replacements);
  });
  // All consumers contribute candidates before a shorter label can partially replace a longer one.
  replacements.sort((left, right) => normalizeNbsp(right.previous ?? '').length
    - normalizeNbsp(left.previous ?? '').length).forEach((replacement) => replacement.apply());
  if (options.privacy) {
    const footer = root.querySelector<HTMLElement>(`#${PRIVACY_FOOTER_ID}`);
    if (footer && (normalizeNbsp(footer.textContent ?? '') !== `${options.privacy.prefix} cómo cuidamos tu privacidad.`
      || footer.querySelector('a')?.getAttribute('href') !== options.privacy.url)) {
      setCopyPrivacyFooter(footer, options.privacy);
    }
  }
}
