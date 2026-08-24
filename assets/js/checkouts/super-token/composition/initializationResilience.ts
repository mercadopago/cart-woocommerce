/**
 * Initialization resilience (Phase 2): SDK-readiness reporting and post-mount health check.
 * The watcher reports SDK readiness (super_token_sdk_loaded / super_token_init_source) — which it
 * now owns after the equivalent code was stripped from the legacy mp-super-token.js — and the
 * checker validates the composed instances after the card form mounts.
 */
import {
  CoreMonitorMetricsAdapter,
  SdkReadinessWatcher,
  InitializationHealthChecker,
  CARD_FORM_MOUNTED_EVENT,
} from '@super-token/adapters/platform';
import type { SuperTokenInstances } from '@super-token/types/instances';

// The health check validates the composed instances. In the hybrid they are the legacy globals
// the CDN bundle builds; return null until they exist so the checker can re-evaluate later.
function readLegacyInstances(): SuperTokenInstances | null {
  const { mpSuperTokenTriggerHandler, mpSuperTokenAuthenticator, mpSuperTokenPaymentMethods, mpSuperTokenMetrics, mpSuperTokenErrorHandler } = window;
  if (!mpSuperTokenTriggerHandler) {
    return null;
  }
  return {
    triggerHandler: mpSuperTokenTriggerHandler,
    authenticator: mpSuperTokenAuthenticator as SuperTokenInstances['authenticator'],
    paymentMethods: mpSuperTokenPaymentMethods as SuperTokenInstances['paymentMethods'],
    metrics: mpSuperTokenMetrics as SuperTokenInstances['metrics'],
    errorHandler: mpSuperTokenErrorHandler as SuperTokenInstances['errorHandler'],
  };
}

/**
 * Wire the SDK-readiness watcher and the init health checker. Platform edge: the one place allowed
 * to read the localized params and the (still-legacy) instances, keeping the domain free of window.*.
 */
export function startInitializationResilience(
  recompose: { current: () => void },
  metrics: CoreMonitorMetricsAdapter,
): void {
  const watcher = new SdkReadinessWatcher({ metrics });
  const checker = new InitializationHealthChecker({ metrics, getInstances: readLegacyInstances });

  // Single combined listener — recovery MUST run before the health check so a late-arriving SDK is
  // composed before the checker reads the instances (adapters/platform/README.md pre-condition).
  document.addEventListener(CARD_FORM_MOUNTED_EVENT, () => {
    watcher.recoverIfSdkIsNowAvailable();
    checker.check(CARD_FORM_MOUNTED_EVENT);
  });

  // In bundle mode the trigger-handler guard makes recomposition a no-op (the legacy bundle composes
  // the stateful classes), but in self-construct — and after the cutover — the tree composes, so the
  // watcher's card-form recovery path must re-run the composition for a late SDK that arrived after
  // the poll window closed. The watcher also owns the SDK-readiness signals.
  watcher.start(() => {
    try {
      recompose.current();
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      metrics.sendMetric('super_token_recovery_compose_failed', 'mp_super_token_init', message);
    }
  });
}
