# adapters/platform/

Concrete implementations of the platform ports — the outward edge that talks to
`window.mpSdkInstance`, the Core Monitor / MeliData telemetry and the WooCommerce
DOM. This is the only layer allowed to read those platform globals; the domain
stays free of `window.*`.

Implemented by **TASK-005** (PSW-4269):

- `MpSdkAdapter` → `PaymentSdkPort` — single point that touches the MP JS SDK
  (injected, never read from `window.*` here); pure delegation, never reads card
  data into JS state (SEC-1/PCI).
- `CoreMonitorMetricsAdapter` → `MetricsPort` — full `MPSuperTokenMetrics` surface,
  moved 1:1 (identical telemetry: same metric names, URL and payload). Delivers via
  `fetch`; owns `sendMetric`/`normalizeErrorMessage` as private internals.
- `MelidataAdapter` — MeliData error events. Event-driven: buffers events while
  `window.melidata` (external CDN client) is not ready and flushes them once
  `window.melidataReady` resolves, instead of waiting/polling per event. Owned by
  `CoreMonitorMetricsAdapter`.
- `WooDomAdapter` → `DomPort` — the reusable DOM vocabulary (query, create/insert,
  class/attr/style, tree moves, events, timers, `escapeHtml`) plus safe sinks
  (`setText`, `setImageSource`) that make the future view XSS-safe by construction
  (SEC-3). The legacy element builders are view concerns (TASK-008), not moved here.
- `VariantConfigAdapter` → `VariantConfigPort` — resolves the A/B variant string
  (cookie / remote config / kill switch / weighted / fallback), ported from
  `super-token-loader.js`. Returns the variant only; it does not load the bundle
  or pick a view.
- `createPlatformAdapters` — step 1 of the composition root: builds all adapters,
  reading `window.mpSdkInstance` once. Consumed by the domain in TASK-006.

Added by **TASK-010** (PSW-4274):

- `SdkReadinessWatcher` — waits for `window.mpSdkInstance` using three tiers (already
  ready → `mp_sdk_instance_ready` event → 50ms fallback poll capped at 15s) and calls
  `compose()` exactly once. Adds a recovery path via `registerFormMountedRecovery()`:
  when the card form mounts after the poll window, it re-checks the SDK and composes
  with source `card_form_recovery` if it is now available. Reports `super_token_sdk_loaded`
  and `super_token_init_source` (with `elapsed_ms`) through `MetricsPort`.
- `InitializationHealthChecker` — validates that all composition-root instances are
  present and that the trigger handler is listening, after the card form mounts. Reports
  exactly once per page load via `sessionStorage` dedup. All init signals go through
  `MetricsPort` (metric names preserved 1:1 from the legacy `mp-super-token.js`).

Intended wiring for the composition root (TASK-013):
```ts
watcher.start(compose);
watcher.registerFormMountedRecovery();
checker.registerFormMountedCheck();
```

TASK-013 pre-conditions to verify before wiring:
- **Listener registration order (critical)**: both `registerFormMountedRecovery()` and
  `registerFormMountedCheck()` listen to `mp_card_form_mounted`. Recovery MUST be registered
  first so it composes the instances synchronously before the health check reads them. If the
  order is reversed and the SDK arrives late, the checker sees empty instances, reports a
  false `SUPER_TOKEN_CLASSES_NOT_EXISTS`, and — even though the `conclusive` flag protects
  against consuming the dedup for missing-state results — the real `SUPER_TOKEN_INITIALIZATION_SUCCESS`
  is only emitted if the form mounts again. Prefer a single combined listener in the
  composition root to make the order explicit and not rely on registration sequence:
  ```ts
  document.addEventListener('mp_card_form_mounted', () => {
    watcher.recoverIfSdkIsNowAvailable();   // first: compose if SDK arrived late
    checker.check('mp_card_form_mounted');  // then: validate the composed state
  });
  ```
- **Metric sink**: the four init-check signals now go to Core Monitor instead of the legacy
  plugin-global `window.sendMetric`. Confirm PSW-4167 dashboard queries read from Core Monitor
  and align with the PSW-4334 merge gate before enabling.
- **elapsed_ms baseline**: `SdkReadinessWatcher` captures `startedAt` in the constructor.
  Construct the watcher at bundle entrypoint (not lazily) so the 15s `sdk_event_recovered`
  threshold matches the legacy module-load baseline.

Depends on: `ports/` (implements them). Nothing inward depends on this layer.
