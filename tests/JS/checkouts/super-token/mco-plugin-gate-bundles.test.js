const { createBundleRuntime } = require('../../helpers/bundle-runtime');

// Literal module paths let Jest discover this suite with --findRelatedTests.
// The canonical handoff (assets/js/checkouts/super-token/super-token.bundle.js) is a gitignored byte copy
// of build/super-token/bootstrap.ts.js staged by build:super-token:bundle, so CI only has the build output.
const BUNDLES = [
  ['local', () => jest.requireActual('../../../../build/super-token/bootstrap.ts.js')],
];

// The CDN bundle serves every plugin version: Colombian stores need the es_CO catalog, which only
// plugins sending mco_store_language_copy ship. Since the A/B test ended (PSW-4853) every store gets
// the canonical v2.1 runtime, so no variant can keep MCO off.
describe.each(BUNDLES)('MCO gate in the generated %s bundle', (bundle, loadBundle) => {
  const SKIPPED_VARIANT_METRIC = 'super_token_skipped_variant';
  let runtime;

  const isAbConfig = (url) => String(url).endsWith('/config/super-token-variants.js');
  const isMetric = (url, name) => String(url).endsWith(`/datadog/big/${name}`);

  beforeEach(() => {
    runtime = createBundleRuntime();
    window.mpSdkInstance = { authenticator: jest.fn() };
    global.fetch = jest.fn(() => Promise.resolve({ ok: true, json: async () => ({}) }));
  });

  afterEach(() => {
    runtime.cleanup();
    delete window.mpSdkInstance;
    delete global.fetch;
    document.cookie = 'mp_st_variant=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
  });

  async function loadTriggerHandler(params) {
    window.wc_mercadopago_supertoken_bundle_params = {
      intl: 'es-CO',
      currency: 'COP',
      store_language_copy: true,
      self_construct: true,
      // The saved-methods controller reads these groups when the trigger handler is built.
      input_helper_message: { installments: {}, securityCode: {} },
      input_title: { installments: '' },
      placeholders: {},
      ...params,
    };
    jest.isolateModules(loadBundle);
    await runtime.waitForReady();
    for (let elapsed = 0; !window.mpSuperTokenTriggerHandler && elapsed < 1000; elapsed += 10) {
      await jest.advanceTimersByTimeAsync(10);
    }
    expect(window.mpSuperTokenTriggerHandler).toBeDefined();
    return window.mpSuperTokenTriggerHandler;
  }

  test('Given MCO on a plugin without mco_store_language_copy (8.9.7), When loaded, Then Super Token stays off and the SDK is not called', async () => {
    const handler = await loadTriggerHandler({ site_id: 'MCO' });

    expect(handler.getBlock()).toEqual({ reason: 'outdated_plugin' });
    await handler.loadSuperToken('50000');
    expect(handler.currentAmount).toBe('');
    expect(window.mpSdkInstance.authenticator).not.toHaveBeenCalled();
  });

  test.each([true, '1'])('Given MCO on a plugin sending mco_store_language_copy=%p, When loaded, Then Super Token goes through with an integer amount', async (flag) => {
    const handler = await loadTriggerHandler({ site_id: 'MCO', mco_store_language_copy: flag });

    expect(handler.getBlock()).toBeNull();
    // The load goes on to the e-mail listener, which needs jQuery (absent from this jsdom page); the
    // integer amount is stored before that step, which is enough to show the gate let it through.
    await handler.loadSuperToken('50000.00').catch(() => {});
    expect(handler.currentAmount).toBe('50000');
  });

  test('Given a returning buyer with the legacy v2 cookie, When an MCO store with the catalog loads, Then the cookie does not keep it off nor fetch the A/B config', async () => {
    document.cookie = 'mp_st_variant=v2; path=/';

    const handler = await loadTriggerHandler({ site_id: 'MCO', mco_store_language_copy: '1' });

    expect(handler.getBlock()).toBeNull();
    await handler.loadSuperToken('50000.00').catch(() => {});
    expect(handler.currentAmount).toBe('50000');
    expect(global.fetch.mock.calls.some(([url]) => isAbConfig(url))).toBe(false);
    expect(global.fetch.mock.calls.some(([url]) => isMetric(url, SKIPPED_VARIANT_METRIC))).toBe(false);
  });

  test.each(['MLA', 'MLB', 'MLC', 'MLM'])('Given %s without the MCO flag, When loaded, Then the gate does not apply', async (siteId) => {
    const handler = await loadTriggerHandler({ site_id: siteId });

    expect(handler.getBlock()).toBeNull();
  });
});
