# adapters/view/

PSW-4853: `v2.1/V21View.ts` is the single `VariantViewPort` implementation.
`runtimeComposition.ts` constructs it directly; the v2 implementation and selection
factory were removed. The port name stays compatible with existing adapters.

`createVariantViewDeps.ts` maps localized params; the composition first supplies
legacy missing strings through `resolveLegacyPresentationCopy.ts`. Shared rows retain
selection, installments, CVV, keyboard access and safe DOM sinks. The root marker
`data-variant="v2.1"` scopes the stylesheet; it is not an experiment assignment.
