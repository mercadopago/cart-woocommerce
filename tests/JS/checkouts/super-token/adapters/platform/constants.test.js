const { SUPER_TOKEN_JS_VERSION } = require('@super-token/adapters/platform/constants');

describe('Super Token platform constants', () => {
  it('keeps the distributed JS runtime version explicit', () => {
    expect(SUPER_TOKEN_JS_VERSION).toBe('1.2.6');
  });
});
