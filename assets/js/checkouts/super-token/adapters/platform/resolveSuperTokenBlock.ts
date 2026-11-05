import { requiresColombianCatalog } from '@super-token/adapters/platform/requiresColombianCatalog';
import type { SuperTokenBlock } from '@super-token/useCases/LoadSuperToken';

/**
 * Why Super Token stays off for this store, or null when it can load. Only the catalog gates it:
 * every store receives the canonical v2.1 runtime since the A/B test ended (PSW-4853), so there is
 * no variant left to check.
 */
export function resolveSuperTokenBlock(
  params: { site_id?: unknown; mco_store_language_copy?: unknown },
): SuperTokenBlock | null {
  if (requiresColombianCatalog(params)) {
    return { reason: 'outdated_plugin' };
  }
  return null;
}
