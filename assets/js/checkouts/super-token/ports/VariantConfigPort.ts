/**
 * Port: resolves which A/B variant this visitor gets, as a logical string
 * (`'v2'` | `'v2.1'` | ...).
 *
 * Contract only — no implementation. This is the *config* seam (cookie / remote
 * config / kill switch / weighted assignment / fallback), distinct from the
 * `VariantViewPort` *view* seam (TASK-008). It does NOT pick or render a view and
 * does NOT load the CDN bundle — it only returns the resolved variant string.
 * The concrete adapter lands in TASK-005; runtime single-bundle resolution is
 * ADR-005 (TASK-008/013).
 */
export interface VariantConfigPort {
  resolve(): Promise<string>;
}
