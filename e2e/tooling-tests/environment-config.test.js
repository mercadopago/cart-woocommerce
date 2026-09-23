const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const baseConfig = require('../config/environments.json');
const {
  assertHttpReady,
  getEnvironmentTarget,
  getRuntimeTargetFromEnvironment,
  preflightExternalTarget,
  resolveConfigPath,
  validateConfig,
} = require('../helpers/environment-config');
const { wpOption } = require('../helpers/wp-env');

function cloneConfig() {
  return JSON.parse(JSON.stringify(baseConfig));
}

describe('environment config', () => {
  it('returns the exact allowlisted target for the selected lane', () => {
    const config = validateConfig(cloneConfig());
    const target = getEnvironmentTarget(config, 'staging', 'mlb', 'blocks');

    assert.equal(target.environment, 'staging');
    assert.equal(target.site, 'MLB');
    assert.equal(target.checkout, 'blocks');
    assert.equal(target.checkoutUrl, 'https://e2e-staging-mlb.ppolimpo.io/checkout-blocks/');
    assert.equal(target.identityUrl, 'https://e2e-staging-mlb.ppolimpo.io/checkout-classic/');
  });

  it('configures staging and homol for all seven sites', () => {
    const config = validateConfig(cloneConfig());
    for (const site of ['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE']) {
      assert.equal(getEnvironmentTarget(config, 'staging', site, 'classic').site, site);
      assert.equal(getEnvironmentTarget(config, 'homol', site, 'blocks').site, site);
    }
  });

  it('fails closed when a lane omits one supported site', () => {
    const config = cloneConfig();
    delete config.environments.homol.sites.MPE;

    assert.throws(() => validateConfig(config), /sete sites/);
  });

  it('requires a bounded heartbeat well below the remote lease TTL', () => {
    const missing = cloneConfig();
    delete missing.lock.heartbeatSeconds;
    assert.throws(() => validateConfig(missing), /heartbeatSeconds/);

    const unsafe = cloneConfig();
    unsafe.lock.heartbeatSeconds = unsafe.lock.ttlSeconds / 2;
    assert.throws(() => validateConfig(unsafe), /abaixo de um terco/);
  });

  it('rejects hosts outside the static allowlist', () => {
    const config = cloneConfig();
    config.environments.staging.sites.MLB.shopUrl = 'https://127.0.0.1/shop/';

    assert.throws(() => validateConfig(config), /URL nao permitida/);
  });

  it('rejects shop and checkout from different origins', () => {
    const config = cloneConfig();
    config.environments.staging.sites.MLB.checkoutUrls.classic =
      'https://e2e-homol-mlb.ppolimpo.io/checkout-classic/';

    assert.throws(() => validateConfig(config), /mesma origem/);
  });

  it('rejects an allowlisted origin assigned to the wrong site', () => {
    const config = cloneConfig();
    config.environments.staging.sites.MLA.shopUrl = 'https://e2e-staging-mlb.ppolimpo.io/shop/';
    config.environments.staging.sites.MLA.checkoutUrls.classic =
      'https://e2e-staging-mlb.ppolimpo.io/checkout-classic/';
    config.environments.staging.sites.MLA.checkoutUrls.blocks =
      'https://e2e-staging-mlb.ppolimpo.io/checkout-blocks/';

    assert.throws(() => validateConfig(config), /Origem inesperada/);
  });

  it('pins every configured container to its exact lane and site', () => {
    const config = cloneConfig();
    config.environments.staging.sites.MLB.containerName = 'wp-homol-mlb';

    assert.throws(() => validateConfig(config), /Container invalido em staging\/MLB/);
  });

  it('rejects config paths outside e2e/config', () => {
    assert.throws(() => resolveConfigPath('../package.json'), /dentro de e2e\/config/);
  });

  it('fails when runtime URLs differ from the versioned target', () => {
    const previous = { ...process.env };
    process.env.E2E_ENVIRONMENT = 'staging';
    process.env.SITE = 'MLB';
    process.env.CHECKOUT = 'classic';
    process.env.SHOP_URL = 'https://e2e-staging-mlb.ppolimpo.io/shop/';
    process.env.CHECKOUT_URL = 'https://e2e-staging-mlb.ppolimpo.io/checkout-blocks/';
    try {
      assert.throws(() => getRuntimeTargetFromEnvironment(), /divergem/);
    } finally {
      process.env = previous;
    }
  });

  it('blocks local WP mutations for an external store', () => {
    const previous = process.env.WP_EXTERNAL_STORE;
    process.env.WP_EXTERNAL_STORE = '1';
    try {
      assert.throws(() => wpOption('option', 'value'), /proibidos/);
    } finally {
      if (previous === undefined) delete process.env.WP_EXTERNAL_STORE;
      else process.env.WP_EXTERNAL_STORE = previous;
    }
  });

  it('preflights both URLs and confirms the expected site id', async () => {
    const target = getEnvironmentTarget(validateConfig(cloneConfig()), 'staging', 'MLB', 'classic');
    const previousFetch = global.fetch;
    const redirectModes = [];
    global.fetch = async (url, options) => ({
      status: 200,
      ok: true,
      url,
      headers: { get: () => null },
      text: async () => {
        redirectModes.push(options.redirect);
        return '<script>window.x={"site_id":"MLB"}</script>';
      },
    });
    try {
      await preflightExternalTarget(target);
      assert.deepEqual(redirectModes, ['manual']);
    } finally {
      global.fetch = previousFetch;
    }
  });

  it('retries one transient HTTP preflight failure before invalidating a lane', async () => {
    const previousFetch = global.fetch;
    let calls = 0;
    global.fetch = async (url) => {
      calls += 1;
      if (calls === 1) throw new Error('temporary network failure');
      return {
        status: 200,
        url,
        headers: { get: () => null },
      };
    };
    try {
      await assertHttpReady('https://e2e-staging-mlb.ppolimpo.io/shop/', 'shop', { retryDelayMs: 0 });
      assert.equal(calls, 2);
    } finally {
      global.fetch = previousFetch;
    }
  });

  it('fails preflight when the remote store exposes another site id', async () => {
    const target = getEnvironmentTarget(validateConfig(cloneConfig()), 'staging', 'MLB', 'classic');
    const previousFetch = global.fetch;
    global.fetch = async (url, options) => ({
      status: 200,
      ok: true,
      url,
      headers: { get: () => null },
      text: async () => options.redirect === 'manual' ? '<script>window.x={"site_id":"MLA"}</script>' : '',
    });
    try {
      await assert.rejects(() => preflightExternalTarget(target), /nao corresponde/);
    } finally {
      global.fetch = previousFetch;
    }
  });

  it('follows only bounded same-origin redirects while confirming site identity', async () => {
    const target = getEnvironmentTarget(validateConfig(cloneConfig()), 'staging', 'MLB', 'classic');
    const previousFetch = global.fetch;
    let identityCalls = 0;
    global.fetch = async (url) => {
      if (String(url).endsWith('/checkout-classic/')) {
        identityCalls += 1;
        return {
          status: 302,
          ok: false,
          url,
          headers: { get: () => '/identity/' },
          text: async () => '',
        };
      }
      if (String(url).endsWith('/identity/')) {
        identityCalls += 1;
        return {
          status: 200,
          ok: true,
          url,
          headers: { get: () => null },
          text: async () => '<script>window.x={"site_id":"MLB"}</script>',
        };
      }
      return {
        status: 200,
        ok: true,
        url,
        headers: { get: () => null },
        text: async () => '',
      };
    };
    try {
      await preflightExternalTarget(target);
      assert.equal(identityCalls, 3);
    } finally {
      global.fetch = previousFetch;
    }
  });

  it('rejects cross-origin redirects while confirming site identity', async () => {
    const target = getEnvironmentTarget(validateConfig(cloneConfig()), 'staging', 'MLB', 'classic');
    const previousFetch = global.fetch;
    let checkoutCalls = 0;
    global.fetch = async (url) => {
      if (String(url) === target.checkoutUrl) checkoutCalls += 1;
      const isIdentityRequest = String(url) === target.checkoutUrl && checkoutCalls > 1;
      return {
        status: isIdentityRequest ? 302 : 200,
        ok: !isIdentityRequest,
        url,
        headers: { get: () => isIdentityRequest ? 'https://attacker.example/identity/' : null },
        text: async () => '',
      };
    };
    try {
      await assert.rejects(() => preflightExternalTarget(target), /destino nao permitido/);
    } finally {
      global.fetch = previousFetch;
    }
  });
});
