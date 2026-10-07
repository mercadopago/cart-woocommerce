/** Keep older plugin controllers on the bundle's Chilean copy without replacing checkout state. */
import type { SuperTokenDomainParams } from '@super-token/adapters/platform/createDomainConfig';
import type { CustomCheckoutParams } from '@super-token/types/global';

/** Legacy controller's SCREAMING_CASE copy fields (`window.mpSuperTokenPaymentMethods`, cast by the caller). */
export type LegacyCopyFields = Partial<Record<
  | 'ACCOUNT_MONEY_TEXT'
  | 'ACCOUNT_MONEY_BALANCE_TEXT'
  | 'INTEREST_FREE_PART_ONE_TEXT'
  | 'INTEREST_FREE_PART_TWO_TEXT'
  | 'INSTALLMENTS_INTEREST_FREE_OPTION_TEXT'
  | 'BANK_INTEREST_HINT_TEXT',
  string
>>;

/** The account-money row's only marker shared by v2 and v2.1 — v2 never adds the
 *  `.mp-super-token-account-money-row` class (its `RowPresentation.extraClasses` is empty). */
const ACCOUNT_MONEY_ROW_SELECTOR = '[data-type="account_money"]';

/** Plugin catalogs carry the raw `&nbsp;` entity; pre-refactor `innerHTML` renderers decode it to
 *  a real NBSP (U+00A0) in the DOM. Collapse both forms to a plain space so either side matches. */
function normalizeNbsp(text: string): string {
  return text.replace(/&nbsp;|\u00a0/g, ' ');
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

/**
 * Composition-edge callers (`runtimeComposition.ts`) read `window.mpSuperTokenPaymentMethods` and
 * `window.wc_mercadopago_custom_checkout_params` and pass them in — this adapter stays free of
 * `window.*`, matching the `legacyDelegationSeams.ts` convention.
 */
export function syncMlcCopyToLegacy(
  original: SuperTokenDomainParams,
  localized: SuperTokenDomainParams,
  controller: LegacyCopyFields | undefined,
  customParams: CustomCheckoutParams | undefined,
): void {
  // The resolver returns the same object for every non-MLC site.
  if (original === localized || typeof original.site_id !== 'string' || original.site_id.toUpperCase() !== 'MLC') {
    return;
  }

  const previousAccountMoney = original.account_money_text;
  const previousBalance = original.account_money_balance_text;
  const previousInterestFreePartOne = original.interest_free_part_one_text;
  const previousInterestFreePartTwo = original.interest_free_part_two_text;
  const previousInterestFreeOption = original.input_helper_message?.installments?.interest_free_option_text;
  const bankHint = localized.input_helper_message?.installments?.bank_interest_hint_text;

  // Old controllers can be constructed after this bundle runs, so retain the original global
  // object's identity while writing only the MLC copy fields, never unrelated plugin parameters.
  original.account_money_text = localized.account_money_text;
  original.account_money_balance_text = localized.account_money_balance_text;
  original.interest_free_part_one_text = localized.interest_free_part_one_text;
  original.interest_free_part_two_text = localized.interest_free_part_two_text;
  original.interest_free_option_text = localized.interest_free_option_text;
  original.input_helper_message = localized.input_helper_message;

  if (customParams && bankHint) {
    const helper = customParams.input_helper_message ?? {};
    customParams.input_helper_message = {
      ...helper,
      installments: {
        ...helper.installments,
        bank_interest_hint_text: bankHint,
      },
    };
  }

  // Old controllers capture these values in class fields when constructed. Keep the existing
  // instance and its payment state; the guarded runtime must never create a second controller.
  if (controller) {
    controller.ACCOUNT_MONEY_TEXT = localized.account_money_text;
    controller.ACCOUNT_MONEY_BALANCE_TEXT = localized.account_money_balance_text;
    controller.INTEREST_FREE_PART_ONE_TEXT = localized.interest_free_part_one_text;
    controller.INTEREST_FREE_PART_TWO_TEXT = localized.interest_free_part_two_text;
    controller.INSTALLMENTS_INTEREST_FREE_OPTION_TEXT =
      localized.input_helper_message?.installments?.interest_free_option_text;
    controller.BANK_INTEREST_HINT_TEXT = bankHint;
  }

  // If the older runtime rendered before the CDN bundle arrived, refresh only known copy nodes.
  // textContent avoids interpreting plugin catalog strings as HTML.
  replaceVisibleText(`${ACCOUNT_MONEY_ROW_SELECTOR} .mp-super-token-payment-method__title`,
    previousAccountMoney, localized.account_money_text);
  replaceVisibleText('.mp-super-token-am-balance-text', previousBalance,
    localized.account_money_balance_text);
  replaceVisibleText('.mp-super-token-payment-method__value-prop', previousInterestFreePartOne,
    localized.interest_free_part_one_text);
  replaceVisibleText('.mp-super-token-payment-method__value-prop', previousInterestFreePartTwo,
    localized.interest_free_part_two_text);
  replaceVisibleText('select[id^="mp-super-token-installments-select-"] option', previousInterestFreeOption,
    localized.input_helper_message?.installments?.interest_free_option_text);

  // Both Super Token's own renderer (cardRow.ts) and the Custom checkout's equivalent remove this
  // node outright for a non-qualifying installment rather than leaving it empty, so presence alone
  // already means the current selection qualifies (hasBankInterestDisclaimer === true) — refresh it.
  // hideAllPaymentMethodDetails() only hides a deselected card's details (PAYMENT_METHOD_HIDE), it
  // never removes them, so a previously selected card's hint can still be in the DOM (hidden) beside
  // the active card's own hint — update every match, not just the first in document order.
  if (bankHint) {
    document.querySelectorAll<HTMLElement>('.mp-installments-bank-interest-hint').forEach((hint) => {
      hint.textContent = `*${bankHint}`;
    });
  }
  // v2.1 restores `aria-label` from `data-base-aria-label` on every deselect
  // (V21AccountMoneyDecoration.clear()); keep both in sync or the next selection cycle reverts to
  // the stale copy. The base label never includes the balance line (only appended while selected).
  document.querySelectorAll<HTMLElement>(ACCOUNT_MONEY_ROW_SELECTOR).forEach((row) => {
    const label = row.getAttribute('aria-label');
    if (label) {
      let updated = label;
      if (previousAccountMoney) {
        updated = replaceNormalized(updated, previousAccountMoney, localized.account_money_text) ?? updated;
      }
      if (previousBalance && localized.account_money_balance_text) {
        updated = replaceNormalized(updated, previousBalance, localized.account_money_balance_text) ?? updated;
      }
      if (updated !== label) {
        row.setAttribute('aria-label', updated);
      }
    }

    const baseLabel = row.dataset.baseAriaLabel;
    if (baseLabel && previousAccountMoney) {
      const updatedBaseLabel = replaceNormalized(baseLabel, previousAccountMoney, localized.account_money_text);
      if (updatedBaseLabel !== null && updatedBaseLabel !== baseLabel) {
        row.dataset.baseAriaLabel = updatedBaseLabel;
      }
    }
  });
}
