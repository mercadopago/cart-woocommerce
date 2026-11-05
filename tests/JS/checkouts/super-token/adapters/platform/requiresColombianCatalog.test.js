const { requiresColombianCatalog } = require('@super-token/adapters/platform/requiresColombianCatalog');
const { isLocalizedFlagEnabled } = require('@super-token/adapters/platform/localizedFlag');

describe('isLocalizedFlagEnabled', () => {
  it.each([true, 1, '1'])('Given %p, When read, Then the flag is enabled', (value) => {
    expect(isLocalizedFlagEnabled(value)).toBe(true);
  });

  it.each([false, 0, '0', '', 'true', undefined, null])('Given %p, When read, Then the flag is disabled', (value) => {
    expect(isLocalizedFlagEnabled(value)).toBe(false);
  });
});

describe('requiresColombianCatalog', () => {
  it.each(['MCO', 'mco'])('Given %s on a plugin without mco_store_language_copy, When checked, Then Super Token waits for the Colombian catalog', (siteId) => {
    expect(requiresColombianCatalog({ site_id: siteId })).toBe(true);
  });

  it.each([false, '', '0'])('Given MCO with mco_store_language_copy=%p, When checked, Then it still waits for the Colombian catalog', (flag) => {
    expect(requiresColombianCatalog({ site_id: 'MCO', mco_store_language_copy: flag })).toBe(true);
  });

  it.each([true, 1, '1'])('Given MCO with mco_store_language_copy=%p, When checked, Then Super Token is allowed', (flag) => {
    expect(requiresColombianCatalog({ site_id: 'MCO', mco_store_language_copy: flag })).toBe(false);
  });

  it.each(['MLA', 'MLB', 'MLC', 'MLM', 'MPE', 'MLU', undefined, 42])('Given %p without the flag, When checked, Then the gate does not apply', (siteId) => {
    expect(requiresColombianCatalog({ site_id: siteId })).toBe(false);
  });

  it('Given MCO with only the MLC store_language_copy flag (plugin 8.9.7), When checked, Then it still waits for the Colombian catalog', () => {
    expect(requiresColombianCatalog({ site_id: 'MCO', store_language_copy: '1' })).toBe(true);
  });
});
