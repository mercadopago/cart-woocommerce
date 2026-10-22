const { divergentStoreLanguage } = require('@super-token/adapters/platform/storeLanguage');

describe('divergentStoreLanguage', () => {
  it.each([
    ['MLA', 'es-AR'],
    ['MLB', 'pt-BR'],
    ['MLC', 'es-CL'],
    ['MCO', 'es-CO'],
    ['mlm', 'es-MX'],
    ['MPE', 'es-PE'],
    ['MLU', 'es-UY'],
  ])('Given %s with its expected language %s, When checked, Then no divergence is returned', (siteId, htmlLang) => {
    expect(divergentStoreLanguage(siteId, htmlLang)).toBeNull();
  });

  it.each([
    ['MLC', 'es-ES', 'es-es'],
    ['MLA', 'en-US', 'en-us'],
    ['MLM', ' es ', 'es'],
    ['MLB', '', 'unknown'],
    ['MLB', undefined, 'unknown'],
  ])('Given %s with store language %p, When checked, Then %p is returned', (siteId, htmlLang, expected) => {
    expect(divergentStoreLanguage(siteId, htmlLang)).toBe(expected);
  });

  it.each([['MLV'], [undefined], [42]])('Given an unknown site %p, When checked, Then no divergence is returned', (siteId) => {
    expect(divergentStoreLanguage(siteId, 'es-ES')).toBeNull();
  });
});
