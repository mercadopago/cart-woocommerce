/**
 * Single source of truth for the Super Token bundle's setup constants: variant names, the A/B
 * cookie, the injected JS version and the CDN location the A/B config is read from.
 *
 * Scope note: the loader (`assets/js/checkouts/super-token-loader.js`, a standalone CDN asset) and the PHP
 * gateway are separate runtimes that cannot import from this module — they keep their own minimal
 * copies of the values they need (documented as the single source for each runtime).
 */

/**
 * Injected into the init telemetry as `js_version`. Kept in sync with the CDN bundle's version.
 */
export const SUPER_TOKEN_JS_VERSION = '1.2.5';

export const V2_VARIANT = 'v2';
export const V21_VARIANT = 'v2.1';

/** Applied whenever the A/B resolution cannot produce a valid variant. */
export const SUPER_TOKEN_FALLBACK_VARIANT = V2_VARIANT;

/** The only variants the runtime knows how to render; anything else falls back. */
export const SUPER_TOKEN_ALLOWED_VARIANTS: Record<string, boolean> = {
  [V2_VARIANT]: true,
  [V21_VARIANT]: true,
};

/** Cookie the A/B assignment is remembered on (written by VariantConfigAdapter, read for metrics). */
export const SUPER_TOKEN_VARIANT_COOKIE = 'mp_st_variant';

/** `v1` = production storage segment | `homol` = homologação. */
export const SUPER_TOKEN_BUNDLE_ENV = 'v1';
export const SUPER_TOKEN_STORAGE_BASE_URL =
  `https://http2.mlstatic.com/storage/${SUPER_TOKEN_BUNDLE_ENV}/mercadopago/woocommerce/scripts`;
export const SUPER_TOKEN_AB_CONFIG_URL = `${SUPER_TOKEN_STORAGE_BASE_URL}/config/super-token-variants.js`;
