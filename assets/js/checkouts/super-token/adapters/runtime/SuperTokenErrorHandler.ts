/**
 * Ported `MPSuperTokenErrorHandler` (v2.1/errors/super-token-error-handler.js) — the published
 * `window.mpSuperTokenErrorHandler` instance the Classic (`event-handler.js`) and Blocks
 * (`custom.block.js`) checkout consumers call at submit time, and the `errorHandler` dependency
 * the `ClassicCheckout`/`BlocksCheckout` finalizers receive.
 *
 * It exposes the single external method `handleError`, delegating the parse → metric → display
 * sequence to the `HandleError` use case (which already owns that logic) and reusing
 * `LegacyErrorHandlerSession` to adapt its two collaborators — the metrics instance and the
 * payment-methods controller — into the use case's session port.
 *
 * Part of the port-then-flip deletion of `v2/`/`v2.1/`: inert until the flip (not yet constructed
 * or published at runtime), unit-tested for parity with the legacy class. At the flip the bundle
 * bootstrap constructs it with the ported TS `paymentMethods`/`metrics` instances and publishes it
 * through `globalBridge.publish`.
 */
import { HandleError } from '@super-token/useCases/HandleError';
import { LegacyErrorHandlerSession } from '@super-token/adapters/session/LegacyErrorHandlerSession';
import type {
  LegacyErrorMetrics,
  LegacyErrorPaymentMethods,
} from '@super-token/adapters/session/LegacyErrorHandlerSession';

export class SuperTokenErrorHandler {
  private readonly handleErrorUseCase = new HandleError();

  constructor(
    private readonly paymentMethods: LegacyErrorPaymentMethods,
    private readonly metrics: LegacyErrorMetrics,
  ) {}

  handleError(exception: unknown): string {
    return this.handleErrorUseCase.execute({
      session: new LegacyErrorHandlerSession(this.metrics, this.paymentMethods),
      exception,
    });
  }
}
