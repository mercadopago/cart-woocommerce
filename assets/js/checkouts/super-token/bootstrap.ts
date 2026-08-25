/**
 * Bundle entrypoint for the refactored Super Token runtime — the only runtime after the cutover.
 * In dev/self-construct mode this file is built locally (build/super-token/bootstrap.ts.js) and
 * enqueued by CustomGateway; in bundle mode it is the single CDN bundle. Emergency rollback is
 * CDN-first (republish the previous bundle), no longer a per-store toggle.
 *
 * This is a thin orchestrator: it wires the three composition concerns and holds no logic itself.
 * Loading it (1) publishes every legacy-delegation seam to window.mpSuperToken* through the
 * transitional bridge so the still-legacy JS classes delegate each orchestration step to the TS use
 * cases, (2) resolves the A/B variant and — once the SDK exists — builds and publishes the stateful
 * runtime instances + saved-methods render, and (3) starts the SDK-readiness/init-health resilience.
 *
 * In the pre-cutover hybrid the legacy `mp-super-token.js` still builds the stateful classes and
 * mirrors them to `window.mpSuperToken*`; the runtime composition reuses those instances through
 * typed session adapters (guarded so it never rebuilds them) and the delegation seams keep a
 * fallback path so stores stay safe if a seam fails.
 */
import { CoreMonitorMetricsAdapter } from '@super-token/adapters/platform';
import type { SuperTokenDomainParams } from '@super-token/adapters/platform';
import { SUPER_TOKEN_JS_VERSION } from '@super-token/adapters/platform/constants';
import type { SuperTokenBundleParams } from '@super-token/types/external-globals';
import { publishLegacyDelegationSeams } from '@super-token/composition/legacyDelegationSeams';
import { composeRuntime } from '@super-token/composition/runtimeComposition';
import { startInitializationResilience } from '@super-token/composition/initializationResilience';

// Publish the legacy-delegation seams (synchronous, both modes).
publishLegacyDelegationSeams();

// Order + decorate (Phase 5) + render (Phase 6). Platform edge: read the localized params once.
// When they are absent the seam is not published, so the legacy organizePaymentMethodsElements
// keeps its inline reorder + normalize (the decoration would otherwise lack its copy/thumbnails).
const domainParams = window.wc_mercadopago_supertoken_bundle_params as unknown as
  | SuperTokenDomainParams
  | undefined;

// Shared late-bound composition trigger. `buildAndPublishInstances` is a closure created only once
// the A/B variant resolves (async), so it cannot be referenced when the resilience watcher starts at
// module load. composeRuntime binds it here; the watcher's card-form recovery path re-invokes it for
// a late SDK that missed the poll window. Idempotent via the trigger-handler guard (PSW-4277).
const recompose: { current: () => void } = { current: () => {} };

// One Core Monitor adapter shared by the runtime composition (compose failures) and the init
// resilience (SDK-readiness + recovery). Lazy SDK read so it works before the SDK exists.
const PARAMS_FALLBACK: SuperTokenBundleParams = {
  plugin_version: '',
  platform_version: '',
  site_id: '',
  cust_id: '',
  location: '',
  platform_id: '',
};
const metrics = new CoreMonitorMetricsAdapter(
  () => window.mpSdkInstance,
  SUPER_TOKEN_JS_VERSION,
  window.wc_mercadopago_supertoken_bundle_params ?? PARAMS_FALLBACK,
);

if (domainParams) {
  composeRuntime(domainParams, recompose, metrics);
}

// Initialization resilience (Phase 2): runs synchronously after the (async) runtime composition,
// mirroring the original module order.
startInitializationResilience(recompose, metrics);
