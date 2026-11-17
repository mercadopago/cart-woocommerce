import type { AccountPaymentMethod } from '@super-token/types/external-globals';

export interface CoordinateAccountPaymentMethodsContext {
  amount: string | null;
  buyerEmail: string;
  fetch: (signal: AbortSignal) => Promise<AccountPaymentMethod[] | null>;
  onTimeout?: () => void;
}

/** Same safety cap as the Smart Pix eligibility: an SDK step that never settles cannot hold the checkout. */
export const ACCOUNT_PAYMENT_METHODS_TIMEOUT_MS = 30000;

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

  constructor(private readonly timeoutMs = ACCOUNT_PAYMENT_METHODS_TIMEOUT_MS) {}

  execute(ctx: CoordinateAccountPaymentMethodsContext): Promise<AccountPaymentMethod[] | null> {
    const key = JSON.stringify([ctx.amount, ctx.buyerEmail]);

    if (this.cached?.key === key) {
      return this.cached.promise.then((methods) => methods?.slice() ?? null);
    }

    // Different checkout identities must not mutate the shared authenticator in
    // parallel. Queue them while still sharing one promise for equal identities.
    // An explicit invalidation or the safety cap aborts this generation and
    // resolves its waiters with null, so a stalled SDK promise can neither keep
    // them pending nor retain every later checkout identity.
    const controller = new AbortController();
    this.activeControllers.add(controller);
    const timeout = setTimeout(() => {
      reportTimeout(ctx);
      controller.abort();
    }, this.timeoutMs);
    let entry: CachedRequest;
    const request = this.requestTail
      .catch(() => undefined)
      .then(() => controller.signal.aborted ? null : ctx.fetch(controller.signal));
    // Once abandoned, the SDK promise may still reject; nobody awaits it any more.
    request.catch(() => undefined);
    const promise = Promise.race([request, resolveNullOnAbort(controller.signal)])
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
        clearTimeout(timeout);
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

function resolveNullOnAbort(signal: AbortSignal): Promise<null> {
  return new Promise((resolve) => {
    signal.addEventListener('abort', () => resolve(null), { once: true });
  });
}

function reportTimeout(ctx: CoordinateAccountPaymentMethodsContext): void {
  try {
    ctx.onTimeout?.();
  } catch {
    // Telemetry cannot keep the waiters pending.
  }
}
