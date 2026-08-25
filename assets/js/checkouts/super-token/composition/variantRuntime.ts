/**
 * A/B variant resolution for the composition root. Bundle/prod resolves the variant through
 * VariantConfigAdapter (remote config → cookie → weighted → fallback v2, with source:* telemetry);
 * dev/self-construct has no loader cookie, so it follows the localized MP_SUPER_TOKEN_VERSION (then
 * cookie, then v2). The same resolved variant drives both the core decoration and the rendered view.
 * Variant names, cookie and JS version live in adapters/platform/constants.ts.
 */
import { VariantConfigAdapter } from '@super-token/adapters/platform';
import { SUPER_TOKEN_VARIANT_COOKIE, V2_VARIANT } from '@super-token/adapters/platform/constants';

// Build-time A/B variant pin, injected by webpack DefinePlugin (PSW-4417). Empty in the unified
// mp-super-token/ bundle staged for TASK-013, which resolves the variant at runtime; set to 'v2' /
// 'v2.1' in the per-path retrocompat bundles the 8.9.3 loader fetches from v1/ and v2.1/.
declare const __ST_FIXED_VARIANT__: string;

// Dev/self-construct reads the A/B variant cookie as a fallback (bundle/prod resolves it through
// VariantConfigAdapter).
export function readVariantCookie(): string | null {
  const match = document.cookie.match(new RegExp('(^|;\\s*)' + SUPER_TOKEN_VARIANT_COOKIE + '=([^;]+)'));
  return match ? match[2] : null;
}

// Self-construct: dev mode (MP_SUPER_TOKEN_USE_BUNDLE=false, PHP sets self_construct=true) — the
// tree builds the stateful instances itself. Bundle mode leaves self_construct falsy; the selected
// refactored CDN bundle owns the same composition, with the path cutover still deferred to TASK-013.
export function isSelfConstruct(): boolean {
  return Boolean(
    (window.wc_mercadopago_supertoken_bundle_params as { self_construct?: boolean } | undefined)?.self_construct,
  );
}

export function resolveSuperTokenVariant(): Promise<string> {
  // A legacy retrocompat bundle (v1/, v2.1/) carries its variant frozen at build time, so it renders
  // the folder the older plugin's loader fetched — no runtime A/B resolution (which could diverge
  // from that folder). The runtime bundle leaves this empty and resolves below.
  if (__ST_FIXED_VARIANT__) {
    return Promise.resolve(__ST_FIXED_VARIANT__);
  }
  if (isSelfConstruct()) {
    const localized = (window.wc_mercadopago_supertoken_bundle_params as { super_token_version?: string } | undefined)?.super_token_version;
    return Promise.resolve(localized ?? readVariantCookie() ?? V2_VARIANT);
  }
  return new VariantConfigAdapter().resolve();
}
