/**
 * Port: variant-specific rendering of the saved-payment-methods UI — the seam that
 * differs between A/B variants (today the `v2` and `v2.1` folders). The concrete
 * views live in `adapters/view/{v2,v2.1}` and are resolved once by the
 * `VariantViewFactory` (TASK-008, ADR-005).
 *
 * The seam has four behaviours because v2 and v2.1 diverge at four points of the
 * saved-methods lifecycle: the initial render, the decoration applied when a method
 * is selected, its removal on deselect, and the variant chrome removed on reset. A
 * variant with no behaviour at a given point (e.g. v2 has no account-money
 * decoration) implements it as a no-op, so consumers never branch on the variant.
 */
import type {
  Installment,
  PaymentMethod,
  RawSdkCreditsContractController,
} from '@super-token/types/external-globals';
import type { InstallmentOption } from '@super-token/core/paymentMethods/BasePaymentMethodWithInstallments';

/** Parameters for the SDK `renderCreditsContract` call (payment-methods.js:2097-2106). */
export interface CreditsContractParameters {
  fastPaymentToken: string;
  pricingId: string;
  pseudotoken: string;
  customization: { textColor: string; textSize: string; linkColor: string };
}

/**
 * Primitives the view needs when it builds an interactive row itself (behaviour wiring), forwarded
 * to the still-legacy controller — the scaffold that shrinks as each row type is ported into the
 * tree. `onSelectPaymentMethod` mirrors the legacy `onSelectSuperTokenPaymentMethod` (which already
 * delegates to the refactored selection seam); the installment primitives wrap the platform globals
 * (CheckoutPage / MPCheckoutFieldsDispatcher / sendMetric) the tree must not touch directly.
 */
export interface RenderRowSession {
  onSelectPaymentMethod(row: HTMLElement, paymentMethod: PaymentMethod): void;
  /** Refreshes the tax-info line for the chosen installment (CheckoutPage.updateTaxInfoForSelect). */
  updateInstallmentsTaxInfo(selectedValue: string, taxInfoElementId: string, installments: Installment[]): void;
  /** Dispatches the installments-filled field event and records the installments-filled metric. */
  installmentSelected(methodType: string): void;
  /** Reports (once) that MPCheckoutFieldsDispatcher is unavailable at installments setup. */
  reportInstallmentDispatcherMissing(context: string): void;

  // ─── Consumer credits (async SDK contract + state + metrics) ───
  /** The fast-payment token (legacy controller state) used as the contract parameter. */
  getFastPaymentToken(): string;
  /** Renders the SDK credits contract into the element, resolving its controller. */
  renderCreditsContract(
    elementId: string,
    parameters: CreditsContractParameters,
  ): Promise<RawSdkCreditsContractController>;
  /** Updates the contract for the chosen installment (records the error metric on failure). */
  updateCreditsContract(controller: RawSdkCreditsContractController, installments: string): void;
  /** Dispatches the installments-filled field event (without the metric — credits records it later). */
  dispatchInstallmentsFilledField(): void;
  recordCreditsContractRendered(success: boolean, error?: unknown): void;
  recordOpenCreditsInfoModal(linkText: string): void;
  recordConsumerCreditsHint(success: boolean, error?: unknown): void;
  recordConsumerCreditsDueDate(success: boolean, error?: unknown): void;
}

export interface SavedMethodsRenderContext {
  /** The custom-checkout container the saved methods are rendered into. */
  container: HTMLElement;
  /** Account payment methods, already reordered by the core PaymentMethodCatalog. */
  paymentMethods: PaymentMethod[];
  /**
   * Transitional row factory for the row types not yet ported into the tree: the composition root
   * injects the legacy `createPaymentMethodElement` here so the view drives grouping/blocks/header
   * while those rows stay legacy. Absent once every row type is ported.
   */
  buildRow?(paymentMethod: PaymentMethod): HTMLElement;
  /**
   * Behaviour primitives for the row types the view builds itself (account money, cards).
   * Absent when the view is not driving any interactive row.
   */
  rowSession?: RenderRowSession;
  /**
   * The installment `<select>` options for a card, composed from the domain core by the
   * composition root (the view has no domain config). Absent when the tree is not driving cards.
   */
  installmentOptions?(paymentMethod: PaymentMethod): InstallmentOption[];
  /**
   * The consumer-credits legal hint for a selected installment, composed from the domain core
   * (ConsumerCreditsMethod). Absent when the tree is not driving consumer credits.
   */
  consumerCreditsHint?(installment: Installment): string;
}

export interface VariantViewPort {
  /** Renders the saved-methods UI (list or grouped blocks + headers) into the container. */
  renderSavedPaymentMethods(context: SavedMethodsRenderContext): void;
  /** Variant chrome added when a row is selected (v2.1: account-money balance line; v2: no-op). */
  decorateSelection(row: HTMLElement): void;
  /** Undoes the selection decoration on deselect (v2.1: removes the balance line; v2: no-op). */
  clearSelectionDecoration(container: HTMLElement): void;
  /** Removes the variant-specific chrome on reset (v2: list header; v2.1: the blocks). */
  reset(container: HTMLElement): void;
}
