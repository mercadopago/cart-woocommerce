const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { getStoreSiteId, isSharedLaneEnvironment } = require('../helpers/site-guard');

describe('site guard', () => {
  it('uses the immutable config only for the shared staging/homol runner', () => {
    const environment = {
      CHECKOUT: 'classic',
      E2E_ENVIRONMENT: 'staging',
      E2E_REMOTE_MAIN_ONLY: '1',
      WP_EXTERNAL_STORE: '1',
    };
    assert.equal(isSharedLaneEnvironment(environment), true);
    assert.equal(getStoreSiteId(environment, {
      getRuntimeTargetFromEnvironment: () => ({ site: 'MLB' }),
    }), 'MLB');
  });

  it('preserves HTTP identity detection for personal Super Token stores', () => {
    const environment = {
      SHOP_URL: 'https://developer-prod.ppolimpo.io',
      WP_EXTERNAL_STORE: '1',
    };
    assert.equal(isSharedLaneEnvironment(environment), false);
    assert.equal(getStoreSiteId(environment, {
      fetchStoreSiteIdViaHttp: (url) => (
        url === environment.SHOP_URL ? 'MLB' : ''
      ),
      wpGetOption: () => {
        throw new Error('personal external stores must not invoke local WP-CLI');
      },
    }), 'MLB');
  });

  it('fails instead of silently skipping when external identity is unknown', () => {
    const environment = {
      SHOP_URL: 'https://developer-prod.ppolimpo.io',
      WP_EXTERNAL_STORE: '1',
    };
    assert.throws(() => getStoreSiteId(environment, {
      fetchStoreSiteIdViaHttp: () => {
        throw new Error('[E2E] site_id ausente');
      },
    }), /site_id ausente/);
  });
});
