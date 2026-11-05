import { isLocalizedFlagEnabled } from '@super-token/adapters/platform/localizedFlag';

/**
 * True only for MCO on a plugin that does not send `mco_store_language_copy`. The CDN
 * bundle is shared by every installed plugin version, and plugins without the `es_CO` catalog (up to
 * 8.9.7) would show the Argentine or Mexican copy ("Meses") to Colombian buyers, so those stores stay
 * on the Custom Checkout. Same pattern as `store_language_copy` for MLC (see resolveMlcCopy), except
 * that the bundle carries no Colombian copy: MCO launched only with plugins that ship it.
 */
export function requiresColombianCatalog(params: { site_id?: unknown; mco_store_language_copy?: unknown }): boolean {
  return (
    !isLocalizedFlagEnabled(params.mco_store_language_copy) &&
    typeof params.site_id === 'string' &&
    params.site_id.toUpperCase() === 'MCO'
  );
}
