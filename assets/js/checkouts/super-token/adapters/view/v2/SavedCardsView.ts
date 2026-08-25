/**
 * v2 saved-methods rendering: a single flat list with one global header (no e-mail, no
 * grouped blocks). Ported from the legacy v2 `organizePaymentMethodsElements` +
 * `addPaymentMethodsListHeader`.
 */
import type { SavedMethodsRenderContext } from '@super-token/ports';
import type { PaymentMethod } from '@super-token/types/external-globals';
import type { VariantViewDeps } from '../VariantViewDeps';
import { el } from '../shared/dom';
import { buildTypedRow } from '../shared/typedRow';
import type { RowPresentation } from '../shared/paymentMethodPresentation';
import { V2_STYLES } from './styles';

/** v2 row seam: no Mercado Pago credit-card special case, no account-money row class. */
const V2_ROW_PRESENTATION: RowPresentation = {
  mercadoPagoCreditCard: () => null,
  accountMoneyRowClasses: () => [],
};

export class V2SavedCardsView {
  constructor(private readonly deps: VariantViewDeps) {}

  render(context: SavedMethodsRenderContext): void {
    const { container, paymentMethods } = context;
    // Build each row through the shared per-type dispatch so v2 wires selection (and card
    // installments / credits) exactly like v2.1, differing only in the flat-list chrome below.
    const buildRow = (paymentMethod: PaymentMethod): HTMLElement =>
      buildTypedRow(paymentMethod, this.deps, V2_ROW_PRESENTATION, context);
    // Insert each row at the top in reverse so the first method ends up first, then prepend the
    // single list header above them all (faithful to the legacy insert order).
    [...paymentMethods].reverse().forEach((paymentMethod) => {
      container.insertBefore(buildRow(paymentMethod), container.firstChild);
    });
    container.insertBefore(this.buildListHeader(), container.firstChild);
  }

  reset(container: HTMLElement): void {
    container.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`)?.remove();
  }

  private buildListHeader(): HTMLElement {
    return el('header', {
      classes: [V2_STYLES.PAYMENT_METHODS_LIST_HEADER],
      children: [
        el('span', { text: this.deps.copy.paymentMethodsListText }),
        el('img', {
          classes: [V2_STYLES.PAYMENT_METHODS_LIST_HEADER_LOGO],
          attrs: { alt: 'Mercado Pago', src: this.deps.thumbnails.newMpLogoPath },
        }),
      ],
    });
  }
}
