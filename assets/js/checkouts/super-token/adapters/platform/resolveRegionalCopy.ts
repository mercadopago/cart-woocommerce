import { LEGACY_COPY_VALUES, REGIONAL_COPY } from './regionalCopy';
import type { LegacyCopyParams } from '../legacy/syncCopyToLegacy';

export type RegionalSite = keyof typeof REGIONAL_COPY;

function language(value: unknown): string {
  return typeof value === 'string' ? value.trim().toLowerCase().replace(/_/g, '-') : '';
}

/** Older gettext SDK locales and the generic copy flag do not identify the store catalog. */
export function regionalCopySite(
  params: { site_id?: unknown; store_language_copy?: unknown; store_locale?: unknown },
  htmlLanguage?: string,
  sdkLocale?: unknown,
): RegionalSite | undefined {
  const site = typeof params.site_id === 'string' ? params.site_id.toUpperCase() : '';
  if (site !== 'MLU' && site !== 'MPE') return undefined;
  const expected = site === 'MLU' ? 'es-uy' : 'es-pe';
  const explicitLanguage = language(params.store_locale);
  if (explicitLanguage) return explicitLanguage === expected ? site : undefined;
  const catalogLanguage = language(sdkLocale);
  if (catalogLanguage === expected) return site;
  const oldGettextFallback = catalogLanguage === 'es-ar' || catalogLanguage === 'es-mx';
  if ((!catalogLanguage || oldGettextFallback) && language(htmlLanguage) === expected) return site;
  return undefined;
}

function normalize(value: string): string {
  return value.replace(/&nbsp;|\u00a0/g, ' ');
}

function resolveGroup(
  supplied: Record<string, unknown> | undefined,
  approved: Readonly<Record<string, string>>,
  prefix = '',
): Record<string, unknown> {
  const result = { ...supplied };
  Object.entries(approved).forEach(([key, next]) => {
    const current = result[key];
    const missing = current === undefined || current === null || current === '';
    const known = typeof current === 'string' && LEGACY_COPY_VALUES[prefix + key]
      .some((value) => normalize(value) === normalize(current));
    if (missing || known) result[key] = next;
  });
  return result;
}

/** Only known legacy copy or absent copy is replaced; merchant-specific text remains authoritative. */
export function resolveRegionalCopy<T extends { site_id: string }>(
  params: T,
  htmlLanguage?: string,
  sdkLocale?: unknown,
): T {
  const site = regionalCopySite(params, htmlLanguage, sdkLocale);
  if (!site) return params;
  const source = params as unknown as LegacyCopyParams;
  const copy = REGIONAL_COPY[site];
  const helper = source.input_helper_message ?? {};
  return {
    ...params,
    ...resolveGroup(source, copy.flat),
    input_title: resolveGroup(source.input_title, copy.inputTitle, 'input_title.'),
    placeholders: resolveGroup(source.placeholders, copy.placeholders, 'placeholders.'),
    input_helper_message: {
      ...helper,
      installments: resolveGroup(helper.installments, copy.installments, 'input_helper_message.installments.'),
      securityCode: resolveGroup(helper.securityCode, copy.securityCode, 'input_helper_message.securityCode.'),
    },
  } as T;
}

/** Known predecessors also repair a cached controller/DOM when the current params are already correct. */
export function regionalCopyPredecessors(params: LegacyCopyParams): LegacyCopyParams[] {
  const entries = Object.entries(LEGACY_COPY_VALUES).filter(([path, values]) => {
    const parts = path.split('.');
    let current: unknown = params[path];
    if (parts[0] === 'input_title') current = params.input_title?.[parts[1]];
    else if (parts[0] === 'placeholders') current = params.placeholders?.[parts[1]];
    else if (parts[0] === 'input_helper_message') {
      current = params.input_helper_message?.[parts[1] as 'installments' | 'securityCode']?.[parts[2]];
    }
    return typeof current === 'string' && values.some((value) => normalize(value) === normalize(current as string));
  }).map(([path, values]) => [path, [...values].sort((left, right) => right.length - left.length)] as const);
  const count = Math.max(0, ...entries.map(([, values]) => values.length));
  return Array.from({ length: count }, (_, index) => {
    const inputTitle: Record<string, string> = {};
    const placeholders: Record<string, string> = {};
    const installments: Record<string, string> = {};
    const securityCode: Record<string, string> = {};
    const previous: LegacyCopyParams = {
      site_id: params.site_id,
      input_title: inputTitle, placeholders,
      input_helper_message: { installments, securityCode },
    };
    entries.forEach(([path, values]) => {
      const value = values[index];
      if (value === undefined) return;
      const parts = path.split('.');
      if (parts[0] === 'input_title') inputTitle[parts[1]] = value;
      else if (parts[0] === 'placeholders') placeholders[parts[1]] = value;
      else if (parts[0] === 'input_helper_message') {
        const group = parts[1] === 'installments' ? installments : securityCode;
        group[parts[2]] = value;
      } else previous[path] = value;
    });
    return previous;
  });
}
