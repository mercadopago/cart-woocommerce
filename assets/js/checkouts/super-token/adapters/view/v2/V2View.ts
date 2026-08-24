/**
 * The v2 variant view: composes the flat-list saved-cards view with the no-op
 * account-money decoration. Implements VariantViewPort by delegation (never inheritance —
 * v2.1 must not extend v2, ADR-001).
 */
import type { SavedMethodsRenderContext, VariantViewPort } from '@super-token/ports';
import type { VariantViewDeps } from '../VariantViewDeps';
import { V2SavedCardsView } from './SavedCardsView';
import { V2AccountMoneyDecoration } from './AccountMoneyDecoration';

export class V2View implements VariantViewPort {
  private readonly savedCardsView: V2SavedCardsView;
  private readonly accountMoneyDecoration: V2AccountMoneyDecoration;

  constructor(deps: VariantViewDeps) {
    this.savedCardsView = new V2SavedCardsView(deps);
    this.accountMoneyDecoration = new V2AccountMoneyDecoration();
  }

  renderSavedPaymentMethods(context: SavedMethodsRenderContext): void {
    this.savedCardsView.render(context);
  }

  decorateSelection(): void {
    this.accountMoneyDecoration.decorate();
  }

  clearSelectionDecoration(): void {
    this.accountMoneyDecoration.clear();
  }

  reset(container: HTMLElement): void {
    this.savedCardsView.reset(container);
  }
}
