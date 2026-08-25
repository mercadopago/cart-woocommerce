/**
 * Per-type saved-method row dispatch, shared by every variant view. Both v2 and v2.1 build the
 * SAME interactive row for a given method type — only the surrounding chrome (flat list vs.
 * grouped blocks, header/e-mail) and the `RowPresentation` differ. Keeping the dispatch in one
 * place is deliberate: the variants previously diverged here (v2.1 was ported to interactive rows,
 * v2 was left presentation-only), which broke selection under the v2 variant once the legacy
 * `createPaymentMethodElement` (`context.buildRow`) was dropped.
 *
 * When the behaviour primitives (`rowSession` + the domain composers) are present, the tree builds
 * the row itself and wires selection. When they are absent (unit tests, or a not-yet-ported row
 * type) it falls back to the injected legacy factory or, failing that, the presentation-only row.
 */
import type { SavedMethodsRenderContext } from '@super-token/ports';
import type { PaymentMethod } from '@super-token/types/external-globals';
import {
  isAccountMoney,
  isConsumerCredits,
  isCreditCard,
  isDebitCard,
  isPrepaidCard,
} from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import type { VariantViewDeps } from '../VariantViewDeps';
import { buildPaymentMethodRow } from './paymentMethodRow';
import { buildInteractiveRow } from './interactiveRow';
import { buildCardRow } from './cardRow';
import { buildConsumerCreditsRow } from './consumerCreditsRow';
import type { RowPresentation } from './paymentMethodPresentation';

function isCard(paymentMethod: PaymentMethod): boolean {
  return isCreditCard(paymentMethod) || isDebitCard(paymentMethod) || isPrepaidCard(paymentMethod);
}

export function buildTypedRow(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  presentation: RowPresentation,
  context: SavedMethodsRenderContext,
): HTMLElement {
  if (isAccountMoney(paymentMethod) && context.rowSession) {
    return buildInteractiveRow(paymentMethod, deps, presentation, context.rowSession);
  }

  if (isCard(paymentMethod) && context.rowSession && context.installmentOptions) {
    return buildCardRow(paymentMethod, deps, presentation, context.rowSession, context.installmentOptions);
  }

  if (
    isConsumerCredits(paymentMethod) &&
    context.rowSession &&
    context.installmentOptions &&
    context.consumerCreditsHint
  ) {
    return buildConsumerCreditsRow(
      paymentMethod,
      deps,
      presentation,
      context.rowSession,
      context.installmentOptions,
      context.consumerCreditsHint,
    );
  }

  return context.buildRow
    ? context.buildRow(paymentMethod)
    : buildPaymentMethodRow(paymentMethod, deps, presentation);
}
