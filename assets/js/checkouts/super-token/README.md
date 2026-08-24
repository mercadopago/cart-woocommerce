# Super Token — Hexagonal architecture (frontend)

This folder is the ES module + TypeScript foundation for the Super Token checkout
scripts. It replaces the previous model of global-scoped classes (`class
MPSuperToken*` attached to `window.*`, concatenated in load order) with an
explicit, injectable, testable structure.

> **Status (PSW-4277 — refactor is the only runtime; legacy deleted, toggle removed):** the whole
> flow is ported. In dev/self-construct mode (`MP_SUPER_TOKEN_USE_BUNDLE=false`,
> the default here) `bootstrap.ts` builds and publishes the Super Token instances
> from this tree; in bundle mode it is the single CDN bundle. Rollback is CDN-first.
> The legacy `v2/`, `v2.1/` and `shared/` folders — the stateful classes, the
> saved-card rendering/selection, `mp-super-token.js` and the shared validators —
> have been **deleted**; their behavior lives in `adapters/runtime/`,
> `adapters/view/`, `useCases/` and `adapters/validation/`. Styles are one compiled
> file per A/B variant (`super-token-<variant>.min.css`, built from the SCSS
> entries by `main.js`); the served variant follows `MP_SUPER_TOKEN_VERSION` and
> `bootstrap.ts` renders that same variant in dev, so DOM and CSS always match.
>
> **Production 8.9.3 keeps the retrocompatible A/B loader and CDN paths**, but each
> path already serves this refactored runtime with its variant frozen (`v1/` → v2,
> `v2.1/` → v2.1). The unified `mp-super-token/` bundle remains staged for the
> deferred **TASK-013** path cutover. Both variants use the shared typed-row builder,
> including saved-method selection.

## Layers and the dependency rule

Dependencies always point **inward**, toward the center. The domain never imports
the DOM, the SDK or `window.*`.

```
adapters/  ──────►  useCases/  ──────►  core/
(platform,          (application         (pure domain,
 checkout,           orchestration)       no platform)
 view)                    │
   ▲                      ▼
   └──────────────  ports/ (interfaces)  ◄── adapters implement these
```

| Folder | Role | Depends on | Filled by |
| --- | --- | --- | --- |
| `core/` | Pure domain (models, error codes, rules) | nothing | TASK-006 |
| `useCases/` | Application orchestration through ports | `core/`, `ports/` | TASK-006 |
| `ports/` | Interfaces (contracts) — the seams | nothing | **this task** |
| `adapters/platform/` | SDK / metrics / DOM implementations | `ports/` | TASK-005 |
| `adapters/checkout/` | Classic & Blocks wiring | `useCases/`, `ports/` | TASK-009 |
| `adapters/view/` | Variant views + `VariantViewFactory` | `ports/`, `core/` | TASK-008 |
| `adapters/legacy/` | Transitional `window.*` bridge (driven adapter) | `types/` | **this task** |
| `composition/` | Composition wiring — `bootstrap.ts` delegates here | `useCases/`, `adapters/` | PSW-4372 |

## Composition (`composition/`)

`bootstrap.ts` (below) is a thin orchestrator holding no logic; it delegates the wiring to four
concern-scoped modules under `composition/` (PSW-4372):

- `legacyDelegationSeams.ts` — publishes the `window.mpSuperToken*` delegation seams
  (`publishLegacyDelegationSeams()`).
- `variantRuntime.ts` — resolves the A/B variant (`resolveSuperTokenVariant()`; dev localized-version
  path vs bundle `VariantConfigAdapter`).
- `runtimeComposition.ts` — `composeRuntime()`: the order+decorate seam and, once the SDK is ready,
  builds and publishes the stateful runtime instances + saved-methods render, then injects them into
  the Classic event handler (`setSuperTokenDependencies`) and publishes to `window.*` via the bridge.
- `initializationResilience.ts` — `startInitializationResilience()`: SDK-readiness watcher +
  init-health checker.

A single decision point that builds every dependency and injects it (the former `composeSuperToken()`
seam) can consolidate here once the legacy CDN bundle is removed (**TASK-013 / PSW-4277**); kept as
these focused factories until then (no speculative provider framework).

## Activation entrypoint (`bootstrap.ts`)

`bootstrap.ts` is the **runtime composition root**. It is the webpack entry
(`npm run build:super-token:webpack` → `build/super-token/bootstrap.ts.js`); in dev/self-construct
`CustomGateway` enqueues that local build, and in bundle mode the same entry is the single CDN
bundle. It is the only Super Token runtime after the PSW-4277 cutover (toggle removed).

Who **owns** the stateful instances depends on the mode:

- **Bundle mode** (`MP_SUPER_TOKEN_USE_BUNDLE=true`, production today): the A/B loader fetches one
  refactored, variant-frozen CDN bundle, whose composition root builds and publishes the instances.
- **Self-construct mode** (`MP_SUPER_TOKEN_USE_BUNDLE=false`, dev/homolog): the legacy classes
  are not enqueued, so `bootstrap.ts` **builds** the instances itself and publishes them.

> `bootstrap.ts` has grown large as each PSW-4276 slice added its use-case wiring inline;
> decomposing it into per-concern factories is tracked under **PSW-4277**.

### `bootstrap.ts` and `window.*` — read before "just import the class"

New domain/adapter code never touches `window.*`. But `bootstrap.ts` is the composition-root
**edge**, so it does read `window`, in two distinct buckets:

1. **External globals — legitimate, stays.** `window.mpSdkInstance` (the Mercado Pago SDK, an
   external global), `window.wc_mercadopago_supertoken_bundle_params` (the `wp_localize_script`
   config) and the legacy Classic handler (`window.mpEventHandler` / `window.mpCustomCheckoutHandler`).
   These are the browser/WordPress boundary, not domain state.
2. **The runtime instances — `window.mpSuperToken*` — transitional.** `bootstrap.ts` reaches the
   trigger handler / payment methods / authenticator / metrics / error handler through `window`,
   **not** by importing the class and `new`-ing it. Reason: the reused instance must be the **same
   one** the still-legacy checkout uses (it holds the SDK authenticator handle, the load generation,
   the selected method) — a fresh `new` would be a **desynced** instance. So the coupling is about
   **instance identity during the hybrid**, not class access. These reads disappear once the legacy
   bundle is removed (**TASK-013 / PSW-4277**): the tree will build the instances and pass them
   directly, with no `window` round-trip.

Loading it does two things:

1. **Finalization (Phase 1 / TASK-009).** Publishes `finalizeClassic`/`finalizeBlocks`
   (the single `FinalizeSuperTokenPayment` + `ClassicCheckout`/`BlocksCheckout`) to
   `window.mpSuperTokenFinalize{Classic,Blocks}` via the bridge. The still-legacy
   `event-handler.js` (Classic) and `custom.block.js` (Blocks) **delegate** their
   previously-duplicated inline finalization to it, with a **fallback** to the legacy
   inline path if the bundle is absent.
2. **Init resilience (Phase 2 / TASK-010).** Constructs `SdkReadinessWatcher` +
   `InitializationHealthChecker` **at entry** (so `startedAt` matches the legacy
   module-load baseline) and owns the init observability — the equivalent code was
   removed from the legacy `mp-super-token.js` to avoid double reporting.

Rendering, selection and the `Select`/`Load`/`ResetFlow` use cases are now driven
by this tree in dev mode (the legacy controller was ported into `adapters/runtime/`
and deleted). The CDN cutover that also replaces the production bundle is TASK-013.

## The transitional bridge (`adapters/legacy/globalBridge.ts`)

`publish()` mirrors the composed instances onto the same `window.mpSuperToken*`
names used today, so integrators that still read those globals (Classic
`event-handler.js` fallback, Blocks `custom.block.js`) keep working during the
migration. **It is the only place new code writes to `window.*`.** Tracked debt
(TECH-4): removed once traffic on the old bundle is residual and no external
consumer depends on those globals — no fixed date.

## Path alias

Import across layers with the `@super-token/*` alias (configured in `tsconfig.json`,
`webpack.config.js` and `jest.config.js`) instead of `../../..`. Same-folder and
descending imports stay relative.

## Adding a payment method or an A/B variant

- **New payment method:** model it in `core/`, add the use case in `useCases/`, and
  render it through the `VariantViewPort`. No new `window.*`.
- **New A/B variant:** add a `VariantViewPort` implementation under `adapters/view/`
  and register it in the `VariantViewFactory`. The variant is resolved at runtime
  (ADR-005, single bundle) — no separate folder per variant.

## Testing in isolation

The domain and use cases are plain modules with injected ports, so they run under
Jest with mock ports — no DOM, no SDK, no `window.*`. TypeScript `.ts` files are
transformed by `babel-jest` (`@babel/preset-typescript`) scoped to the test run;
the legacy global-class files keep using the `vm/loadFile` helper. Type contracts
are enforced by `npm run type-check` (`tsc --noEmit`).
