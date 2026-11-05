# Super Token — canonical v2.1 runtime

Updated for PSW-4853, 2026-10-29. The experiment is closed in the new code.
`bootstrap.ts` composes the same v2.1 experience in local and CDN modes. Neither
cookies, remote config nor `super_token_version` select a view. There is no v2
view, weighted assignment, build-time pin or retry into the old UI.

## Boundaries

- `core/` contains payment-method classification, decoration, ordering and checkout state.
- `useCases/` coordinates authorization, CVV/ESC, invalidation and checkout finalization.
- `ports/` defines the existing SDK, checkout and view contracts.
- `adapters/platform/` provides SDK/metrics/DOM adapters and localized parameter mapping.
- `adapters/view/v2.1/` renders grouped saved methods using shared interactive rows.
- `adapters/legacy/globalBridge.ts` preserves the `window.mpSuperToken*` API for installed plugins.
- `composition/runtimeComposition.ts` publishes the decorator, waits for the SDK and builds
  instances once. It preserves the Promise boundary before initialization resilience starts.

Classic, Blocks and Order Pay continue to use the existing bridge and validation gates.
Do not remove generation checks, ESC/CVV handling or event-handler dependency injection.

## Compatibility

`resolveLegacyPresentationCopy.ts` fills only missing/blank presentation strings introduced
in plugin 8.8.0: saved cards, saved card, other methods, saved payment method, sufficient
balance and Mercado Pago credit card. It prioritizes the plugin catalog locale, then WordPress's `<html lang>` (Portuguese,
Spanish with Argentine regional copy, or English source strings for unsupported languages).
Supplied translations take precedence. Missing MP logo paths retain the SDK thumbnail;
no URL to an asset absent from an old plugin is invented. Existing Chilean legacy-copy
and store-language flags remain supported.

## Build and distribution

```sh
npm run build:super-token:webpack
npm run build:super-token:bundle
npm run build:integrity
```

One webpack compilation produces `build/super-token/bootstrap.ts.js`. `main.js` stages
`assets/js/checkouts/super-token/super-token.bundle.js` and one stamped CSS file.
Copy these into the Scripts repo's `src/scripts/super-token/v2.1/` canonical sources
(the CSS handoff is named `super-token.bundle.css` there). Do not copy retired v2 handoffs.
The Scripts build optimizes once and emits identical JS/CSS at `v1/` and `v2.1/`;
`mp-super-token/` remains a generated alias reserved for PSW-4277; it is not published
in production in this release (pending NGINX route).

The new plugin loader directly loads `v1/`, the permanent production folder. Publish
and verify the CDN update there before releasing this plugin.
Already installed loaders keep their config/cookie/fallback behavior; both possible CDN
addresses now contain v2.1. Keep `config/super-token-variants.js` for those loaders,
with `active:false` and `default:"v2"`. CDN publication is independent of a public
plugin release. Plugins still using local scripts cannot be migrated through CDN alone.
The transitional CSS retains v2 selectors for independently cached old JS; it does not
restore a v2 implementation. Browser cache combinations still require visual homologation.

Runtime JS and CSS stamp: `1.2.12`; this is separate from the plugin/package release version.
The new runtime sends Datadog `ab_variant: "not_available"`; `js_version` identifies its
code. External Melidata collection and old loader events can still reflect old cookies
or lack a version. Do not reinterpret them as proof of which UI rendered.

## Validation

Per-variant SCSS build entries and generated CSS are retired; equivalence checks compile
reference fixtures in memory from the shared rules and retained partials.

Run `npm run type-check`, the Super Token Jest suites, the build and
`npm run verify:super-token:scss`. The Scripts tests execute the final minified aliases
and assert identical output bytes. Before publication, validate Classic, Blocks, Order Pay,
locale divergence, old/new plugin and CDN combinations, CSS/JS cache mixing and rollback.
The local PSW-4853 checkpoint does not publish assets or create a PR.

PSW-4853 review fixes: the new loader clears the legacy experiment cookie without reading
config, and cookie restrictions cannot block assets. Legacy MLC copy is resolved before
missing presentation text is filled. The fallback locale order is plugin catalog locale,
HTML language, then English; it never uses the credential country.

Production contract (PSW-4853): `v1/` permanently serves canonical v2.1; `v2.1/` is a
temporary identical copy kept for approximately 30 days and until the installed-loader
`v2.1`/`source:cookie` metric reaches zero. Keep `mp-super-token/` in generated output,
without production publication in this release; its future PSW-4277 cutover needs an
NGINX route. Future A/B winners are promoted to `v1/`; only challengers get new folders.

The runtime safeguards CDN CSS with `ensureSuperTokenCss`: five seconds for the original
link, one versioned corrective link in the same folder and five seconds for its result.
The metric value is the found stamp, `empty` or `timeout`; `fixed` requires the new stamp
after load. Messages include the original folder: `fixed folder:v1` or
`fix_failed folder:v2.1`, for example. Composition and rendering never await it.
