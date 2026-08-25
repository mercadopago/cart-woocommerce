import type { MetricsPort } from '@super-token/ports';
import type { SuperTokenInstances } from '@super-token/types/instances';
import { CARD_FORM_MOUNTED_EVENT } from './SdkReadinessWatcher';

export const INIT_CHECK_SESSION_KEY = 'mp_super_token_init_checked';

export interface InitializationHealthCheckerDeps {
  metrics: MetricsPort;
  /** Returns the instances the composition root built, or null if not yet composed. */
  getInstances: () => SuperTokenInstances | null;
  readSdkInstance?: () => unknown;
  session?: Pick<Storage, 'getItem' | 'setItem'>;
}

/**
 * Checks the health of the Super Token initialization after the card form mounts,
 * and reports the outcome through the MetricsPort exactly once per session.
 *
 * Validates against the composition-root instances (not `window.*`), using a
 * fail-fast chain: SDK present → all classes present → trigger handler listening →
 * success. The first failure found is reported and remaining checks are skipped.
 *
 * The sessionStorage dedup key ensures the check fires at most once per page load,
 * regardless of how many times the form mounts.
 */
export class InitializationHealthChecker {
  private readonly metrics: MetricsPort;
  private readonly getInstances: () => SuperTokenInstances | null;
  private readonly readSdkInstance: () => unknown;
  private readonly session: Pick<Storage, 'getItem' | 'setItem'>;

  constructor(deps: InitializationHealthCheckerDeps) {
    this.metrics = deps.metrics;
    this.getInstances = deps.getInstances;
    this.readSdkInstance = deps.readSdkInstance ?? (() => window.mpSdkInstance);
    this.session = deps.session ?? window.sessionStorage;
  }

  /**
   * Registers a listener that calls check() when the card form mounts.
   *
   * Intended wiring point for the composition root (TASK-013):
   *   checker.registerFormMountedCheck();
   */
  registerFormMountedCheck(): void {
    document.addEventListener(CARD_FORM_MOUNTED_EVENT, () =>
      this.check(CARD_FORM_MOUNTED_EVENT),
    );
  }

  /**
   * Runs the initialization health check.
   * `dispatchedFrom` identifies the event or trigger that called this method
   * (e.g. `'mp_card_form_mounted'`); it is included in every metric message.
   *
   * The sessionStorage dedup flag is only consumed on conclusive outcomes
   * (trigger not listening, success, unexpected error). When the SDK or instances
   * are absent the flag is NOT set, so a subsequent call can re-evaluate once the
   * composition root has had a chance to compose (e.g. after SdkReadinessWatcher
   * recovers on the same event). This prevents a false-permanent failure when the
   * health check runs before the recovery watcher on the same mp_card_form_mounted
   * dispatch — see adapters/platform/README.md TASK-013 pre-conditions.
   */
  check(dispatchedFrom: string): void {
    const origin = dispatchedFrom || 'unknown';

    if (this.session.getItem(INIT_CHECK_SESSION_KEY) === 'true') {
      return;
    }

    let conclusive = false;

    try {
      if (!this.readSdkInstance()) {
        this.metrics.mpSdkInstanceNotExists(origin);
        return;
      }

      const instances = this.getInstances();
      if (!this.allInstancesArePresent(instances)) {
        this.metrics.superTokenClassesNotExist(this.buildMissingSummary(instances), origin);
        return;
      }

      conclusive = true;

      if (!instances.triggerHandler.isAlreadyListeningForm) {
        this.metrics.superTokenTriggerHandlerNotListening(origin);
        return;
      }

      this.metrics.superTokenInitializationSuccess(origin);
    } catch (error) {
      conclusive = true;
      this.metrics.superTokenInitializationError(error, origin);
    } finally {
      if (conclusive) {
        this.session.setItem(INIT_CHECK_SESSION_KEY, 'true');
      }
    }
  }

  private allInstancesArePresent(
    instances: SuperTokenInstances | null,
  ): instances is SuperTokenInstances {
    return Boolean(
      instances &&
        instances.metrics &&
        instances.paymentMethods &&
        instances.authenticator &&
        instances.errorHandler &&
        instances.triggerHandler,
    );
  }

  private buildMissingSummary(instances: SuperTokenInstances | null): string {
    return (
      `${instances?.metrics ? '' : 'Metrics class did not load. '}` +
      `${instances?.paymentMethods ? '' : 'Payment Methods class did not load. '}` +
      `${instances?.authenticator ? '' : 'Authenticator class did not load. '}` +
      `${instances?.errorHandler ? '' : 'Error Handler class did not load. '}` +
      `${instances?.triggerHandler ? '' : 'Trigger Handler class did not load.'}`
    );
  }
}
