const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const {
  loadSharedCapabilities,
  skipIfUnsupportedCapability,
} = require('../helpers/capability-guard');

describe('shared capability guard', () => {
  it('declares every site and records the unsupported MLB PIX seller', () => {
    const capabilities = loadSharedCapabilities();
    assert.deepEqual(
      new Set(Object.keys(capabilities.sites)),
      new Set(['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE'])
    );
    assert.equal(capabilities.sites.MLB.pix, false);
  });

  it('turns a known missing external capability into an explicit skip', () => {
    const previous = process.env.WP_EXTERNAL_STORE;
    process.env.WP_EXTERNAL_STORE = '1';
    let skipped = false;
    try {
      skipIfUnsupportedCapability({ skip: (condition) => { skipped = condition; } }, 'MLB', 'pix');
      assert.equal(skipped, true);
    } finally {
      if (previous === undefined) delete process.env.WP_EXTERNAL_STORE;
      else process.env.WP_EXTERNAL_STORE = previous;
    }
  });
});
