/**
 * Runtime composition: resolves the A/B variant, publishes the order+decorate seam (Phase 5) and,
 * once the SDK exists, builds and publishes the stateful runtime instances + the saved-methods
 * render (Phase 6). In the pre-cutover hybrid the legacy bundle may build the instances first; the
 * window.mpSuperTokenTriggerHandler guard makes this a no-op then.
 */
import { createVariantView, createVariantViewDeps } from '@super-token/adapters/view';
import type { SuperTokenViewParams, EmailListenerPort } from '@super-token/adapters/view';
import type { VariantViewPort } from '@super-token/ports';
import type { PaymentMethod, Installment, SuperTokenBundleParams } from '@super-token/types/external-globals';
import { SuperTokenPaymentMethods } from '@super-token/adapters/runtime/SuperTokenPaymentMethods';
import type { SuperTokenPaymentMethodsParams } from '@super-token/adapters/runtime/SuperTokenPaymentMethods';
import { SuperTokenAuthenticator } from '@super-token/adapters/runtime/SuperTokenAuthenticator';
import { SuperTokenErrorHandler } from '@super-token/adapters/runtime/SuperTokenErrorHandler';
import { SuperTokenEmailListener } from '@super-token/adapters/runtime/SuperTokenEmailListener';
import { SuperTokenTriggerHandler } from '@super-token/adapters/runtime/SuperTokenTriggerHandler';
import { SuperTokenDebounce } from '@super-token/adapters/runtime/SuperTokenDebounce';
import {
  CoreMonitorMetricsAdapter,
  createDomainConfig,
} from '@super-token/adapters/platform';
import type { SuperTokenDomainParams } from '@super-token/adapters/platform';
import { SUPER_TOKEN_JS_VERSION } from '@super-token/adapters/platform/constants';
import { PaymentMethodCatalog } from '@super-token/core/checkoutSession/PaymentMethodCatalog';
import { PaymentMethodRegistry } from '@super-token/core/paymentMethods/registry';
import { CreditCardMethod } from '@super-token/core/paymentMethods/CreditCardMethod';
import { ConsumerCreditsMethod } from '@super-token/core/paymentMethods/ConsumerCreditsMethod';
import type { InstallmentOption } from '@super-token/core/paymentMethods/BasePaymentMethodWithInstallments';
import type { SuperTokenInstances } from '@super-token/types/instances';
import { LegacyRenderSession } from '@super-token/adapters/session/LegacyRenderSession';
import type { LegacyRenderController } from '@super-token/adapters/session/LegacyRenderSession';
import * as globalBridge from '@super-token/adapters/legacy/globalBridge';
import { resolveSuperTokenVariant } from '@super-token/composition/variantRuntime';

// SDK-readiness gate, mirroring the legacy v2.1/mp-super-token.js: run once the SDK instance
// exists (immediately if already there, else on the ready event, with a bounded poll fallback for
// stores where the event fired before this module loaded — see checkout-resilience rules).
const MP_SDK_INSTANCE_READY_EVENT = 'mp_sdk_instance_ready';
const FALLBACK_POLL_INTERVAL_MS = 50;
const FALLBACK_POLL_MAX_WAIT_MS = 15000;

function whenSdkReady(run: () => void): void {
  if (window.mpSdkInstance) {
    run();
    return;
  }
  const poll = setInterval(() => {
    if (window.mpSdkInstance) {
      clearInterval(poll);
      run();
    }
  }, FALLBACK_POLL_INTERVAL_MS);
  // Clear the poll here too: without it, if the ready event fires first the interval keeps
  // ticking (no-ops) until the timeout, and run() could fire twice (event + a later poll tick).
  document.addEventListener(MP_SDK_INSTANCE_READY_EVENT, () => {
    clearInterval(poll);
    run();
  }, { once: true });
  setTimeout(() => clearInterval(poll), FALLBACK_POLL_MAX_WAIT_MS);
}

// window.mpCustomCheckoutHandler is assigned on mp-custom-checkout.js's own DOMContentLoaded
// listener — a signal with no causal relationship to the SDK-readiness gate above. In self-construct
// mode this composition runs as a plain synchronous script (no CDN fetch delay), so it can execute
// before that listener fires; a single point-in-time read races it and false-positives. Poll instead,
// mirroring the waitForHandler pattern already used for this same global in cart-update.helper.js.
const CUSTOM_HANDLER_POLL_INTERVAL_MS = 100;
const CUSTOM_HANDLER_MAX_WAIT_MS = 15000;

async function waitForCustomCheckoutHandler(): Promise<boolean> {
  const startedWaitingAt = Date.now();
  while (!window.mpCustomCheckoutHandler) {
    if (Date.now() - startedWaitingAt >= CUSTOM_HANDLER_MAX_WAIT_MS) {
      return false;
    }
    await new Promise<void>((resolve) => setTimeout(resolve, CUSTOM_HANDLER_POLL_INTERVAL_MS));
  }
  return true;
}

/**
 * Resolve the variant, then compose the order+decorate seam and the runtime instances. Called only
 * when the localized domain params are present; otherwise the legacy organizePaymentMethodsElements
 * keeps its inline reorder + normalize (the decoration would lack its copy/thumbnails).
 */
export function composeRuntime(
  domainParams: SuperTokenDomainParams,
  recompose: { current: () => void },
  metrics: CoreMonitorMetricsAdapter,
): void {
  const viewParams = domainParams as unknown as SuperTokenViewParams;

  const composeWithVariant = (variant: string): void => {
    const domainConfig = createDomainConfig(domainParams, variant);
    const catalog = new PaymentMethodCatalog(domainConfig.paymentMethodsOrder);
    const registry = new PaymentMethodRegistry(domainConfig);

    const orderAndDecorate = (paymentMethods: PaymentMethod[]): PaymentMethod[] =>
      registry.decorateAccountPaymentMethods(catalog.reorderAccountPaymentMethods(paymentMethods));

    globalBridge.publishOrderAndDecorate({ orderAndDecorate });

    // The installment `<select>` options + consumer-credits hint come from the domain core (the
    // view has no domain config).
    const creditCardMethod = new CreditCardMethod(domainConfig);
    const consumerCreditsMethod = new ConsumerCreditsMethod(domainConfig);
    const installmentOptions = (paymentMethod: PaymentMethod): InstallmentOption[] =>
      'installments' in paymentMethod && paymentMethod.installments
        ? creditCardMethod.normalizedInstallments(paymentMethod.installments)
        : [];
    const consumerCreditsHint = (installment: Installment): string =>
      consumerCreditsMethod.buildConsumerCreditsHint(installment);

    // Build + publish the TS instances once the SDK exists — the tree is the runtime for both
    // variants (createVariantView falls back to v2). In the pre-cutover hybrid the legacy bundle
    // may build them first; the window.mpSuperTokenTriggerHandler guard makes this a no-op then.
    const buildAndPublishInstances = (): void => {
      const sdk = window.mpSdkInstance;
      // Idempotent: the ready event + poll fallback can both fire; the built-guard makes the
      // second a no-op (mirrors the legacy superTokenAlreadyBuilt guard).
      if (!sdk || window.mpSuperTokenTriggerHandler) {
        return;
      }

      const bundleParams = window.wc_mercadopago_supertoken_bundle_params as unknown as
        SuperTokenPaymentMethodsParams & { platform_id: string };
      const entityMetrics = new CoreMonitorMetricsAdapter(
        sdk,
        SUPER_TOKEN_JS_VERSION,
        window.wc_mercadopago_supertoken_bundle_params as SuperTokenBundleParams,
      );
      const emailListener = new SuperTokenEmailListener(new SuperTokenDebounce());

      // Lazy + memoized: created on first render and reused, so the e-mail header listener
      // (guarded per view instance) registers exactly once across re-renders.
      let view: VariantViewPort | undefined;
      // Referenced lazily by the render closure (assigned right after, before any render runs),
      // so the closure can be a constructor argument without a construction-order cycle.
      let paymentMethods: SuperTokenPaymentMethods;

      const renderSavedMethods = (container: HTMLElement, methods: PaymentMethod[]): void => {
        // One stylesheet serves both variants; the root's data-variant scopes each variant's rules.
        container.setAttribute('data-variant', variant);
        if (!view) {
          view = createVariantView(variant, createVariantViewDeps(viewParams, emailListener as EmailListenerPort));
        }
        // The view builds every row itself now (createPaymentMethodElement is dropped), so buildRow
        // is omitted; the render session only supplies the interactive-row behaviour primitives.
        const session = new LegacyRenderSession(paymentMethods as unknown as LegacyRenderController);
        view.renderSavedPaymentMethods({
          container,
          paymentMethods: orderAndDecorate(methods),
          rowSession: session,
          installmentOptions,
          consumerCreditsHint,
        });
      };

      paymentMethods = new SuperTokenPaymentMethods(sdk, entityMetrics, bundleParams, renderSavedMethods, emailListener);
      const authenticator = new SuperTokenAuthenticator(sdk, paymentMethods, entityMetrics, bundleParams.platform_id);
      const errorHandler = new SuperTokenErrorHandler(paymentMethods, entityMetrics);
      const triggerHandler = new SuperTokenTriggerHandler(
        authenticator,
        emailListener,
        paymentMethods,
        errorHandler,
        entityMetrics,
        bundleParams.current_user_email,
      );

      const instances = {
        triggerHandler,
        authenticator,
        paymentMethods,
        metrics: entityMetrics,
        errorHandler,
      } as unknown as SuperTokenInstances;

      globalBridge.publish(instances);
      if (typeof window.mpEventHandler?.setSuperTokenDependencies === 'function') {
        window.mpEventHandler.setSuperTokenDependencies(instances);
      }

      // Kept from the legacy build: the custom-checkout-handler-missing init signal (the SDK-ready
      // and init-health signals are already owned by the resilience watcher/checker below). Polled,
      // not a single read — see waitForCustomCheckoutHandler above for why a point-in-time check
      // false-positives here. Fire-and-forget: reporting is a side effect, not a composition gate.
      void waitForCustomCheckoutHandler().then((handlerFound) => {
        if (!handlerFound) {
          entityMetrics.sendMetric('MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS', 'mp_super_token_init', 'mp_super_token_init_error');
        }
      });
    };

    recompose.current = buildAndPublishInstances;
    // whenSdkReady may invoke this synchronously or from the async poll / ready-event paths. A throw
    // on the async paths escapes the composeRuntime().catch below, leaving the composition failure
    // invisible, so guard the invocation here to keep every path instrumented. recompose.current
    // stays unwrapped so the resilience recovery path keeps its own super_token_recovery_compose_failed.
    whenSdkReady((): void => {
      try {
        buildAndPublishInstances();
      } catch (error: unknown) {
        const message = error instanceof Error ? error.message : String(error);
        metrics.sendMetric('super_token_compose_failed', 'mp_super_token_init', message);
      }
    });
  };

  resolveSuperTokenVariant()
    .then(composeWithVariant)
    .catch((error: unknown) => {
      const message = error instanceof Error ? error.message : String(error);
      metrics.sendMetric('super_token_compose_failed', 'mp_super_token_init', message);
    });
}
