import type { AccountPaymentMethod } from '@super-token/types/external-globals';

export interface CoordinateAccountPaymentMethodsContext {
  amount: string | null;
  buyerEmail: string;
  fetch: (signal: AbortSignal) => Promise<AccountPaymentMethod[] | null>;
}

interface CachedRequest {
  key: string;
  promise: Promise<AccountPaymentMethod[] | null>;
}

/**
 * Single-flight and single-result cache for the buyer/amount identity currently
 * active in checkout. It retains only one entry and never logs or exposes its key.
 */
export class CoordinateAccountPaymentMethods {
  private cached: CachedRequest | null = null;

  private requestTail: Promise<void> = Promise.resolve();

  private readonly activeControllers = new Set<AbortController>();

  execute(ctx: CoordinateAccountPaymentMethodsContext): Promise<AccountPaymentMethod[] | null> {
    const key = JSON.stringify([ctx.amount, ctx.buyerEmail]);

    if (this.cached?.key === key) {
      return this.cached.promise.then((methods) => methods?.slice() ?? null);
    }

    // Different checkout identities must not mutate the shared authenticator in
    // parallel. Queue them while still sharing one promise for equal identities.
    // An explicit invalidation aborts this generation and replaces requestTail so
    // a stalled SDK promise cannot retain every later checkout identity.
    const controller = new AbortController();
    this.activeControllers.add(controller);
    let entry: CachedRequest;
    const promise = this.requestTail
      .catch(() => undefined)
      .then(() => controller.signal.aborted ? null : ctx.fetch(controller.signal))
      .then((methods) => !controller.signal.aborted && Array.isArray(methods) ? methods.slice() : null)
      .then((methods) => {
        if (!methods && this.cached === entry) {
          this.cached = null;
        }
        return methods;
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) {
          return null;
        }
        if (this.cached === entry) {
          this.cached = null;
        }
        throw error;
      })
      .finally(() => {
        this.activeControllers.delete(controller);
      });

    entry = { key, promise };
    this.cached = entry;
    this.requestTail = promise.then(
      () => undefined,
      () => undefined,
    );
    return promise.then((methods) => methods?.slice() ?? null);
  }

  invalidate(): void {
    this.cached = null;
    this.activeControllers.forEach((controller) => controller.abort());
    this.activeControllers.clear();
    this.requestTail = Promise.resolve();
  }
}
