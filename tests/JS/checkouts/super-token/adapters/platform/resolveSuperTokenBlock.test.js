const { resolveSuperTokenBlock } = require('@super-token/adapters/platform/resolveSuperTokenBlock');

describe('resolveSuperTokenBlock', () => {
  it.each([true, '1'])('Given MCO with the Colombian catalog (mco_store_language_copy=%p), When resolved, Then Super Token can load', (flag) => {
    expect(resolveSuperTokenBlock({ site_id: 'MCO', mco_store_language_copy: flag })).toBeNull();
  });

  it('Given MCO without the Colombian catalog, When resolved, Then the outdated plugin is the reason', () => {
    expect(resolveSuperTokenBlock({ site_id: 'MCO' })).toEqual({ reason: 'outdated_plugin' });
  });

  it.each(['MLA', 'MLB', 'MLC', 'MLM'])('Given %s, When resolved, Then Super Token can load', (siteId) => {
    expect(resolveSuperTokenBlock({ site_id: siteId })).toBeNull();
  });
});
