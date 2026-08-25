# adapters/view/

Variant views: concrete implementations of `VariantViewPort` for each A/B variant
(`v2` and `v2.1`) plus the `VariantViewFactory` that picks one at runtime — the single
variant decision point (unknown variant → `v2` fallback, RN-4). This is where the two
variant folders **collapse** into one code path with runtime variant resolution (ADR-005).

Layout:
- `VariantViewFactory.ts` — `createVariantView(variant, deps)` maps a resolved variant string to its view.
- `VariantViewDeps.ts` — the view-local config the factory injects (copy, thumbnails, siteId, e-mail listener).
- `shared/` — `PaymentMethodRow` (the row skeleton, rendered via DOM APIs — no `innerHTML`, XSS-safe by construction), `paymentMethodPresentation` (name/thumbnail resolution + the `RowPresentation` variant seam), `styles`.
- `v2/`, `v2.1/` — each a `*View` that implements `VariantViewPort` by **composing** a `SavedCardsView` + an `AccountMoneyDecoration` (never inheritance — v2.1 must not extend v2).

Populated by **TASK-008** (variant). Depends on `ports/` and `core/`.

Scope boundary: the views render the saved-methods UI and its variant chrome only. Behaviour
wiring (selection/installments/details/metrics), the composition-root wiring, and the single
CDN bundle are **not** here — they land in TASK-009 (checkout) and TASK-013 (bundle). The tree
is therefore not yet reachable from the shipped bundle.
