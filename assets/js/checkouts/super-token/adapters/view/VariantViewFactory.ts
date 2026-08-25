/**
 * The single place that maps a resolved A/B variant string to its concrete view (RN-4).
 * An unknown variant falls back to v2 — the safe baseline. This is the only variant
 * decision in the tree; consumers depend on VariantViewPort, never on the string.
 */
import type { VariantViewPort } from '@super-token/ports';
import type { VariantViewDeps } from './VariantViewDeps';
import { V2View } from './v2/V2View';
import { V21View } from './v2.1/V21View';

const FALLBACK_VARIANT = 'v2';

const VARIANT_VIEWS: Record<string, (deps: VariantViewDeps) => VariantViewPort> = {
  v2: (deps) => new V2View(deps),
  'v2.1': (deps) => new V21View(deps),
};

export function createVariantView(variant: string, deps: VariantViewDeps): VariantViewPort {
  const create = VARIANT_VIEWS[variant] ?? VARIANT_VIEWS[FALLBACK_VARIANT];
  return create(deps);
}
