/**
 * Builds the presentational DOM of a single saved-method row, shared by both variant
 * views. Rendered through the `el` helper (textContent/setAttribute only, no `innerHTML`),
 * so user- and SDK-provided values (name, thumbnail) can never break out into markup
 * (RN-3, SEC-3).
 *
 * Scope: the visual skeleton only (thumbnail, title, last-four, value-prop, classes) — the
 * part where variants differ, via `RowPresentation`. Behaviour wiring (click/keydown,
 * installments dropdown, card detail accordion, metrics) is attached by the checkout
 * orchestrator (TASK-009), not here.
 *
 * `deps.siteId` is expected already normalized to uppercase by the composition point
 * (`createVariantView`), so no per-call `.toUpperCase()` is needed.
 */
import type { PaymentMethod } from '@super-token/types/external-globals';
import {
  isConsumerCredits,
  isCreditCard,
  paymentMethodIdentifier,
} from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { BRAZIL_ACCRONYM } from '@super-token/core/constants';
import type { VariantViewDeps } from '../VariantViewDeps';
import { el } from './dom';
import { SHARED_STYLES } from './styles';
import { installmentsWithoutFee, resolvePaymentMethodView } from './paymentMethodPresentation';
import type { RowPresentation } from './paymentMethodPresentation';

const TEMPORARY_ID_BYTES = 8;
const TEMPORARY_ID_LENGTH = 13;
const MLB_INSTALLMENT_SUFFIX = 'x';

function temporaryId(): string {
  return Array.from(crypto.getRandomValues(new Uint8Array(TEMPORARY_ID_BYTES)), (byte) =>
    byte.toString(36),
  )
    .join('')
    .substring(0, TEMPORARY_ID_LENGTH);
}

function installmentSuffix(deps: VariantViewDeps): string {
  return deps.siteId === BRAZIL_ACCRONYM ? MLB_INSTALLMENT_SUFFIX : '';
}

function valuePropText(freeInstallments: number, deps: VariantViewDeps): string {
  return `${deps.copy.interestFreePartOneText} ${freeInstallments}${installmentSuffix(deps)} ${deps.copy.interestFreePartTwoText}`;
}

function buildAriaLabel(
  name: string,
  lastFour: string | null,
  freeInstallments: number,
  deps: VariantViewDeps,
): string {
  const cleanName = (name ?? '').replace(/&nbsp;|\u00a0/g, ' ');
  const lastFourText = lastFour ? ` ${deps.copy.lastDigitsText} ${lastFour}` : '';
  const installmentsText = freeInstallments > 1 ? ` ${valuePropText(freeInstallments, deps)}` : '';
  return `${cleanName}${lastFourText}${installmentsText}`;
}

export function buildPaymentMethodRow(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  presentation: RowPresentation,
): HTMLElement {
  const model = resolvePaymentMethodView(paymentMethod, deps, presentation);
  const lastFour = model.suppressLastFour
    ? null
    : 'card' in paymentMethod
      ? paymentMethod.card?.card_number?.last_four_digits ?? null
      : null;
  const freeInstallments = installmentsWithoutFee(paymentMethod);
  const showValueProp =
    (isCreditCard(paymentMethod) || isConsumerCredits(paymentMethod)) && freeInstallments > 1;
  const id = paymentMethod?.id ? paymentMethodIdentifier(paymentMethod) : temporaryId();
  const ariaLabel = buildAriaLabel(model.name, lastFour, showValueProp ? freeInstallments : 0, deps);

  const row = el('article', {
    classes: [SHARED_STYLES.PAYMENT_METHOD, ...model.extraClasses],
    attrs: { id, 'aria-label': ariaLabel, tabindex: '0', role: 'option', 'aria-selected': 'false' },
    dataset: { type: paymentMethod?.type, id, baseAriaLabel: ariaLabel },
    children: [
      el('section', {
        classes: [SHARED_STYLES.PAYMENT_METHOD_HEADER],
        children: [
          el('figure', {
            classes: [SHARED_STYLES.PAYMENT_METHOD_THUMBNAIL],
            children: [
              el('img', { attrs: { src: model.thumbnail ?? '', alt: '', 'aria-hidden': 'true' } }),
            ],
          }),
          el('article', {
            classes: [SHARED_STYLES.PAYMENT_METHOD_CONTENT],
            children: [
              el('section', {
                classes: [SHARED_STYLES.PAYMENT_METHOD_CONTENT_TITLE],
                children: [
                  el('span', { classes: [SHARED_STYLES.PAYMENT_METHOD_TITLE], text: model.name }),
                  lastFour
                    ? el('span', {
                        classes: [SHARED_STYLES.PAYMENT_METHOD_LAST_FOUR_DIGITS],
                        text: `**** ${lastFour}`,
                      })
                    : null,
                ],
              }),
              showValueProp
                ? el('span', {
                    classes: [SHARED_STYLES.PAYMENT_METHOD_VALUE_PROP],
                    attrs: { 'aria-hidden': 'true' },
                    text: valuePropText(freeInstallments, deps),
                  })
                : null,
            ],
          }),
        ],
      }),
    ],
  });

  return row;
}
