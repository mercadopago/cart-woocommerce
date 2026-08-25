/**
 * The v2.1 variant view: composes the grouped-blocks saved-cards view with the account-money
 * decoration. Implements VariantViewPort by delegation (never inheritance — v2.1 must not
 * extend v2, ADR-001).
 */
import type { SavedMethodsRenderContext, VariantViewPort } from '@super-token/ports';
import type { VariantViewDeps } from '../VariantViewDeps';
import { V21SavedCardsView } from './SavedCardsView';
import { V21AccountMoneyDecoration } from './AccountMoneyDecoration';

export class V21View implements VariantViewPort {
  private readonly savedCardsView: V21SavedCardsView;
  private readonly accountMoneyDecoration: V21AccountMoneyDecoration;

  constructor(deps: VariantViewDeps) {
    this.savedCardsView = new V21SavedCardsView(deps);
    this.accountMoneyDecoration = new V21AccountMoneyDecoration(deps);
  }

  renderSavedPaymentMethods(context: SavedMethodsRenderContext): void {
    this.savedCardsView.render(context);
  }

  decorateSelection(row: HTMLElement): void {
    this.accountMoneyDecoration.decorate(row);
  }

  clearSelectionDecoration(container: HTMLElement): void {
    this.accountMoneyDecoration.clear(container);
  }

  reset(container: HTMLElement): void {
    this.savedCardsView.reset(container);
  }
}
