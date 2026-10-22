/** The language a store is expected to use for each credential site. */
const EXPECTED_STORE_LANGUAGE_BY_SITE: Record<string, string> = {
  MLA: 'es-ar',
  MLB: 'pt-br',
  MLC: 'es-cl',
  MCO: 'es-co',
  MLM: 'es-mx',
  MPE: 'es-pe',
  MLU: 'es-uy',
};

const UNKNOWN_STORE_LANGUAGE = 'unknown';

/**
 * The store language when it differs from the one expected for the credential site, or null when
 * it matches or the site is unknown. Reads the `<html lang>` WordPress prints (e.g. "es-ES"), which
 * every plugin version renders, so the divergence is measured for installs that never updated.
 */
export function divergentStoreLanguage(siteId: unknown, htmlLang: string | null | undefined): string | null {
  const expected = typeof siteId === 'string' ? EXPECTED_STORE_LANGUAGE_BY_SITE[siteId.toUpperCase()] : undefined;
  if (!expected) {
    return null;
  }

  const storeLanguage = (htmlLang ?? '').trim().toLowerCase();
  if (storeLanguage === expected) {
    return null;
  }

  return storeLanguage || UNKNOWN_STORE_LANGUAGE;
}
