/**
 * Boolean flags the plugin sends through `wp_localize_script()`. WordPress stringifies scalar
 * values, so PHP `true` reaches JavaScript as `'1'` and `false` as an empty string; `true` and `1`
 * still appear when a test or the self-construct entry passes the params directly.
 */
export function isLocalizedFlagEnabled(value: unknown): boolean {
  return value === true || value === 1 || value === '1';
}
