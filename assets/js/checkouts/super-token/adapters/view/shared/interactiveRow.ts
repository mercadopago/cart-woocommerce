/**
 * Builds an interactive saved-method row entirely in the tree: the presentation skeleton
 * (`buildPaymentMethodRow`) plus the selection wiring (click / Space / Enter), forwarding the
 * selection itself to the injected session (the legacy `onSelectSuperTokenPaymentMethod`, which
 * already delegates to the refactored selection seam). This replaces the legacy
 * `createPaymentMethodElement` row for the types ported into the tree — currently account money,
 * which has no detail accordion, installments or security-code field. Card and consumer-credits
 * rows (which add those details) are ported in the following slices.
 *
 * Wiring mirrors payment-methods.js:2019-2027 exactly (`click`; `keydown` on 'Space'/'Enter' with
 * preventDefault).
 */
import type { RenderRowSession } from '@super-token/ports';
import type { PaymentMethod } from '@super-token/types/external-globals';
import type { VariantViewDeps } from '../VariantViewDeps';
import { buildPaymentMethodRow } from './paymentMethodRow';
import type { RowPresentation } from './paymentMethodPresentation';

export function buildInteractiveRow(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  presentation: RowPresentation,
  session: RenderRowSession,
): HTMLElement {
  const row = buildPaymentMethodRow(paymentMethod, deps, presentation);

  row.addEventListener('click', () => session.onSelectPaymentMethod(row, paymentMethod));
  row.addEventListener('keydown', (event) => {
    if (event.code === 'Space' || event.key === 'Enter') {
      event.preventDefault();
      session.onSelectPaymentMethod(row, paymentMethod);
    }
  });

  return row;
}
