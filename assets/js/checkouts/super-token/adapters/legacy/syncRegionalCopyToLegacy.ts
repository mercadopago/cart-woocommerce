import { REGIONAL_COPY, REGIONAL_PRIVACY } from '../platform/regionalCopy';
import { regionalCopyPredecessors } from '../platform/resolveRegionalCopy';
import type { RegionalSite } from '../platform/resolveRegionalCopy';
import { syncCopyToLegacy } from './syncCopyToLegacy';
import type { LegacyCopyFields, LegacyCopyParams } from './syncCopyToLegacy';

/** No Custom checkout bridge: only Fast Payments owns these parameters and rendered labels. */
export function syncRegionalCopyToLegacy(
  original: LegacyCopyParams,
  localized: LegacyCopyParams,
  controller: LegacyCopyFields | undefined,
  site: RegionalSite,
): void {
  const copy = REGIONAL_COPY[site];
  const hasApprovedPrivacy = localized.mercadopago_privacy_policy === copy.flat.mercadopago_privacy_policy;
  const privacy = hasApprovedPrivacy ? REGIONAL_PRIVACY[site] : undefined;
  const root = document.getElementById('mp-checkout-super-token-root');
  syncCopyToLegacy(original, localized, controller, Object.keys(copy.flat), {
    // An empty fragment prevents a pre-render sync from touching unrelated Custom controls.
    root: root ?? document.createDocumentFragment(),
    privacy,
    predecessors: regionalCopyPredecessors(localized),
  });
}
