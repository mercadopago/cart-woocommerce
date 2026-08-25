/**
 * Fetching and rendering the buyer's saved payment methods — the inner load step of the legacy
 * `fetchAndRenderSuperTokenPaymentMethods` (super-token-trigger-handler.js:243-275). Owns the
 * e-mail gate (capture → validate → skip metrics), the fetching flag, and the load-generation
 * guard around the async fetch, so a load cancelled or superseded mid-flight renders nothing.
 *
 * The RAW methods are handed to the renderer unchanged: ordering and decoration stay in the
 * render path (organizePaymentMethodsElements → the order+decorate seam), its single owner. The
 * fetch is grounded in the legacy `mpSuperTokenAuthenticator.getAccountPaymentMethods`, which
 * already delegates to the refactored load seam (Phase 7), so this step composes on it rather
 * than re-wrapping the SDK auth flow. Fail-safe: every step returns without throwing, so a
 * failed load never blocks the checkout.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';

/** Subset of the legacy trigger handler (and the collaborators it holds) the fetch+render step drives. */
export interface FetchAndRenderSession {
  getBuyerEmail(): string | null | undefined;
  isValidEmail(email: string): boolean;
  setFetching(isFetching: boolean): void;
  getLoadGeneration(): number;
  currentAmount(): string | null;
  fetchAccountPaymentMethods(amount: string | null, buyerEmail: string): Promise<PaymentMethod[] | null>;
  // Synchronous in the legacy controller (renders + self-reports via errorToRenderAccountPaymentMethods
  // in its own try/catch) — no promise to await or drop; typed void to match that real contract.
  renderAccountPaymentMethods(paymentMethods: PaymentMethod[], amount: string | null): void;
}

/** Subset of `MPSuperTokenMetrics` emitted while gating the load on the buyer e-mail. */
export interface FetchAndRenderMetrics {
  skippedNoEmail(): void;
  skippedInvalidEmail(): void;
  emailCaptured(): void;
}

export interface FetchAndRenderContext {
  session: FetchAndRenderSession;
  metrics: FetchAndRenderMetrics;
}

export class FetchAndRenderPaymentMethods {
  async execute(ctx: FetchAndRenderContext): Promise<void> {
    const { session, metrics } = ctx;

    const buyerEmail = session.getBuyerEmail();
    if (!buyerEmail) {
      metrics.skippedNoEmail();
      return;
    }

    // The SDK rejects invalid e-mails; validate before fetching to avoid provider errors.
    if (!session.isValidEmail(buyerEmail)) {
      metrics.skippedInvalidEmail();
      return;
    }

    metrics.emailCaptured();
    session.setFetching(true);

    const generation = session.getLoadGeneration();
    let paymentMethods: PaymentMethod[] | null;
    try {
      paymentMethods = await session.fetchAccountPaymentMethods(session.currentAmount(), buyerEmail);
    } catch (_) {
      // Transient fetch failure: only release the flag if we still own this load, so a newer
      // generation that superseded us keeps ownership. Without this the flag stays stuck at true
      // and LoadSuperToken discards every reload without an amount/e-mail change — no recovery.
      if (session.getLoadGeneration() === generation) {
        session.setFetching(false);
      }
      return;
    }
    // A newer load (or a cancel) bumped the generation while we awaited — drop this stale result.
    if (session.getLoadGeneration() !== generation) {
      return;
    }

    session.setFetching(false);

    if (!paymentMethods || !paymentMethods.length) {
      return;
    }

    session.renderAccountPaymentMethods(paymentMethods, session.currentAmount());
  }
}
