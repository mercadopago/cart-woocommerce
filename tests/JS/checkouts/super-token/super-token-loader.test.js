const vm = require('vm');
const fs = require('fs');
const { resolveAlias } = require('../../helpers/path-resolver');

const superTokenLoaderPath = resolveAlias('assets/js/checkouts/super-token-loader.js');

const CSS_ID = 'wc_mercadopago_supertoken_bundle_css';
const JS_ID = 'wc_mercadopago_supertoken_bundle_js';
const SUPER_TOKEN_STORAGE_BASE_URL = 'https://http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts';
const MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';

const DEFAULT_VALID_CONFIG = {
  active: true,
  default: 'v2',
  cookie_ttl_days: 30,
  variants: { 'v2': { weight: 100 }, 'v2.1': { weight: 0 } },
};

function makeFetchOk(config) {
  return jest.fn(() =>
    Promise.resolve({ ok: true, json: () => Promise.resolve(config) })
  );
}

function makeFetch4xx(status = 404) {
  return jest.fn(() => Promise.resolve({ ok: false, status }));
}

function makeFetchNetworkError() {
  return jest.fn(() => Promise.reject(new Error('network error')));
}

function makeFetchInvalidJson() {
  return jest.fn(() =>
    Promise.resolve({ ok: true, json: () => Promise.reject(new Error('bad json')) })
  );
}

/**
 * Creates a lightweight cookie simulation for use in vm contexts.
 * Supports get/set/clear via document.cookie assignment, mirroring real browser behaviour.
 */
function createCookieDocument(initialValue) {
  let jar = initialValue !== undefined ? `mp_st_variant=${initialValue}` : '';

  const cookieDocument = {
    head: global.document.head,
    body: global.document.body,
    getElementById: (id) => global.document.getElementById(id),
    createElement: (tag) => global.document.createElement(tag),
    get cookie() { return jar; },
    set cookie(value) {
      if (value.includes('expires=Thu, 01 Jan 1970')) {
        jar = '';
      } else {
        const [nameValue] = value.split(';');
        jar = nameValue;
      }
    },
  };

  return { cookieDocument, getCookie: () => jar };
}

function runLoaderScript(options = {}) {
  const { scriptsParams, sendBeacon, fetchFn, disableBeacon, cookieValue } = options;
  const fileContent = fs.readFileSync(superTokenLoaderPath, 'utf8');

  const beaconSpy = sendBeacon || jest.fn();
  const fetchSpy = fetchFn !== undefined ? fetchFn : jest.fn(() => Promise.resolve({ ok: false, status: 500 }));
  const { cookieDocument, getCookie } = createCookieDocument(cookieValue);

  const context = {
    window: { location: { href: 'https://example.com/checkout' } },
    document: cookieDocument,
    navigator: disableBeacon ? {} : { sendBeacon: beaconSpy },
    console: global.console,
    fetch: fetchSpy,
    Promise: global.Promise,
    Date: global.Date,
    Math: global.Math,
    setTimeout: global.setTimeout,
    clearTimeout: global.clearTimeout,
    JSON: global.JSON,
    Object: global.Object,
    RegExp: global.RegExp,
    Error: global.Error,
  };

  if (scriptsParams !== undefined) {
    context.wc_mercadopago_woocommerce_scripts_params = scriptsParams;
  }

  new vm.Script(fileContent).runInNewContext(context);

  return { beaconSpy, fetchSpy, getCookie };
}

/** Returns parsed payload of the Nth call to a given metric */
function getMetricPayload(beaconSpy, metricName, index = 0) {
  const calls = beaconSpy.mock.calls.filter(([url]) => url.endsWith('/' + metricName));
  return calls[index] ? JSON.parse(calls[index][1]) : null;
}

/**
 * Drains the Promise microtask queue completely.
 * Needed for chains like fetch().then().then().catch() inside Promise.race().
 * Uses multiple ticks to flush deeply nested .then() chains.
 */
async function flushPromises() {
  for (let i = 0; i < 10; i++) {
    await Promise.resolve(); // eslint-disable-line no-await-in-loop
  }
}

describe('super-token-loader', () => {
  beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
    jest.useRealTimers();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  // ─── Asset injection (preserved behaviour) ────────────────────────────────────

  describe('asset injection', () => {
    test('Given valid cookie and active config, When loader runs, Then CSS and JS assets are injected once', async () => {
      runLoaderScript({
        cookieValue: 'v2.1',
        fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG),
        scriptsParams: { plugin_version: '1.2.3', theme: 'storefront', platform_version: '9.0.0', site_id: 'MLA', cust_id: '123' },
      });
      await flushPromises();

      const cssTag = document.getElementById(CSS_ID);
      const jsTag = document.getElementById(JS_ID);

      expect(cssTag).not.toBeNull();
      expect(cssTag.tagName).toBe('LINK');
      expect(jsTag).not.toBeNull();
      expect(jsTag.tagName).toBe('SCRIPT');
      expect(jsTag.defer).toBe(true);
    });

    test('Given valid cookie and active config, When loader runs twice, Then assets are not duplicated', async () => {
      runLoaderScript({ cookieValue: 'v2', fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });
      await flushPromises();
      runLoaderScript({ cookieValue: 'v2', fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });
      await flushPromises();

      expect(document.querySelectorAll(`#${CSS_ID}`)).toHaveLength(1);
      expect(document.querySelectorAll(`#${JS_ID}`)).toHaveLength(1);
    });

    test('Given JS asset load fails, When error handler runs, Then loader sends failure metric', async () => {
      const { beaconSpy } = runLoaderScript({
        cookieValue: 'v2',
        fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG),
        scriptsParams: { plugin_version: '1.2.3', theme: 'storefront', platform_version: '9.0.0', site_id: 'MLA', cust_id: '123' },
      });
      await flushPromises();

      document.getElementById(JS_ID).onerror();

      const payload = getMetricPayload(beaconSpy, 'load_super_token_bundle_js');
      expect(payload).toMatchObject({
        value: 'false',
        message: 'Unable to load super token bundle js on page',
        plugin_version: '1.2.3',
        platform: { name: 'woocommerce', uri: 'storefront', version: '9.0.0', url: 'https://example.com/checkout' },
        details: { site_id: 'MLA', environment: 'prod', cust_id: '123' },
      });
    });

    test('Given global params are missing, When success metric is emitted, Then payload uses safe fallback values', async () => {
      const { beaconSpy } = runLoaderScript({ cookieValue: 'v2', fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });
      await flushPromises();

      document.getElementById(CSS_ID).onload();

      const payload = getMetricPayload(beaconSpy, 'load_super_token_bundle_css');
      expect(payload.plugin_version).toBe('');
      expect(payload.platform.uri).toBe('');
      expect(payload.details.site_id).toBe('');
    });

    test('Given sendBeacon is unavailable, When metric is emitted, Then loader falls back to fetch for metrics', async () => {
      const metricFetch = jest.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve(DEFAULT_VALID_CONFIG) }));
      const { beaconSpy } = runLoaderScript({
        disableBeacon: true,
        cookieValue: 'v2',
        fetchFn: metricFetch,
        scriptsParams: { plugin_version: '2.0.0', theme: 'flavor', platform_version: '10.0.0', site_id: 'MLB', cust_id: '456' },
      });
      await flushPromises();

      document.getElementById(JS_ID).onerror();

      expect(beaconSpy).not.toHaveBeenCalled();
      const errorCall = metricFetch.mock.calls.find(([url]) => url.endsWith('/load_super_token_bundle_js'));
      expect(errorCall).toBeDefined();
      const [url, opts] = errorCall;
      expect(url).toBe(`${MONITOR_URL}/load_super_token_bundle_js`);
      expect(opts.method).toBe('POST');
      expect(opts.keepalive).toBe(true);
      expect(JSON.parse(opts.body).value).toBe('false');
    });

    test('Given fetch and sendBeacon are both unavailable, When metric is emitted, Then loader does not throw', () => {
      expect(() => {
        const { cookieDocument } = createCookieDocument('v2');
        const context = {
          window: { location: { href: 'https://example.com/checkout' } },
          document: cookieDocument,
          navigator: {},
          console: global.console,
          Promise: global.Promise,
          Date: global.Date,
          Math: global.Math,
          setTimeout: global.setTimeout,
          clearTimeout: global.clearTimeout,
          JSON: global.JSON,
          Object: global.Object,
          RegExp: global.RegExp,
          Error: global.Error,
        };
        new vm.Script(fs.readFileSync(superTokenLoaderPath, 'utf8')).runInNewContext(context);
        document.getElementById(CSS_ID)?.onerror?.();
      }).not.toThrow();
    });

    test('Given fetch is unavailable (sync throw), When loader runs, Then it still loads the fallback bundle (v2) and emits no variant assignment', () => {
      const beaconSpy = jest.fn();
      const { cookieDocument } = createCookieDocument();
      const context = {
        window: { location: { href: 'https://example.com/checkout' } },
        document: cookieDocument,
        navigator: { sendBeacon: beaconSpy },
        console: global.console,
        Promise: global.Promise,
        Date: global.Date,
        Math: global.Math,
        setTimeout: global.setTimeout,
        clearTimeout: global.clearTimeout,
        JSON: global.JSON,
        Object: global.Object,
        RegExp: global.RegExp,
        Error: global.Error,
        // fetch intentionally omitted → undefined in the vm context → synchronous ReferenceError
      };
      new vm.Script(fs.readFileSync(superTokenLoaderPath, 'utf8')).runInNewContext(context);

      // resilience: the fallback bundle (v2) is injected despite no fetch
      expect(document.getElementById(CSS_ID)).not.toBeNull();
      expect(document.getElementById(JS_ID)).not.toBeNull();
      // no A/B skew: these clients are NOT counted as a variant assignment
      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')).toBeNull();
    });
  });

  // ─── A/B: cookie path (returning visitor) ─────────────────────────────────────

  describe('A/B: cookie path (returning visitor)', () => {
    test('Given valid v2.1 cookie and active config, When loader runs, Then loads v2.1 bundle (source:cookie)', async () => {
      const { beaconSpy } = runLoaderScript({ cookieValue: 'v2.1', fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });
      await flushPromises();

      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v2.1/super-token.bundle.min.css`);
      expect(document.getElementById(JS_ID).getAttribute('src')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v2.1/super-token.bundle.min.js`);

      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2.1');
      expect(payload?.message).toBe('source:cookie');
    });

    test('Given valid v2 cookie and active config, When loader runs, Then loads from v1/ CDN folder (DD-3 mapping)', async () => {
      runLoaderScript({ cookieValue: 'v2', fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });
      await flushPromises();

      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v1/super-token.bundle.min.css`);
    });

    test('Given valid v2 cookie and active config, When loader runs, Then metric emits v2 (not v1 CDN folder name)', async () => {
      const { beaconSpy } = runLoaderScript({ cookieValue: 'v2', fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });
      await flushPromises();

      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:cookie');
    });

    test('Given valid cookie and active config, When loader runs, Then fetch_ab_config=success metric is emitted', async () => {
      // Loader always fetches — even for returning visitors — to support immediate kill switch propagation.
      const { beaconSpy } = runLoaderScript({ cookieValue: 'v2', fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });
      await flushPromises();

      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.value).toBe('success');
    });

    test('Given cookie with unknown variant (v9), When loader runs, Then clears cookie and proceeds to fetch', async () => {
      const { getCookie } = runLoaderScript({ cookieValue: 'v9', fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });

      await flushPromises();

      expect(getCookie()).not.toContain('v9');
    });

    test('Given cookie with fetch_failed value, When loader runs, Then clears and fetches again', async () => {
      const fetchSpy = makeFetchOk(DEFAULT_VALID_CONFIG);
      runLoaderScript({ cookieValue: 'fetch_failed', fetchFn: fetchSpy });

      await flushPromises();

      expect(fetchSpy).toHaveBeenCalled();
    });
  });

  // ─── A/B: first visit — fetch success ─────────────────────────────────────────

  describe('A/B: first visit — fetch success (active: true)', () => {
    test('Given active config, When loader runs, Then sets cookie and emits source:assigned', async () => {
      const { beaconSpy, getCookie } = runLoaderScript({ fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });

      await flushPromises();

      expect(getCookie()).toContain('mp_st_variant=');
      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.message).toBe('source:assigned');
    });

    test('Given cookie_ttl_days = 0, When assigned, Then uses default TTL without throwing', async () => {
      const { getCookie } = runLoaderScript({ fetchFn: makeFetchOk({ ...DEFAULT_VALID_CONFIG, cookie_ttl_days: 0 }) });

      await flushPromises();

      expect(getCookie()).toContain('mp_st_variant=');
    });

    test('Given cookie_ttl_days = -1, When assigned, Then uses default TTL without throwing', async () => {
      const { getCookie } = runLoaderScript({ fetchFn: makeFetchOk({ ...DEFAULT_VALID_CONFIG, cookie_ttl_days: -1 }) });

      await flushPromises();

      expect(getCookie()).toContain('mp_st_variant=');
    });

    test('Given config with variants: null, When loader runs, Then source:config_invalid + fallback (v2) without throwing', async () => {
      const config = { active: true, default: 'v2', cookie_ttl_days: 30, variants: null };
      const { beaconSpy, getCookie } = runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      expect(getCookie()).toBe('');
      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:config_invalid');
    });

    test('Given config with variants missing, When loader runs, Then source:config_invalid + fallback (v2) without throwing', async () => {
      const config = { active: true, default: 'v2', cookie_ttl_days: 30 }; // no variants field
      const { beaconSpy, getCookie } = runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      expect(getCookie()).toBe('');
      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:config_invalid');
    });

    test('Given config with unknown variant from selectVariantByWeight, When assigned, Then source:config_invalid + fallback (v2) + no cookie', async () => {
      const config = { active: true, default: 'v2', cookie_ttl_days: 30, variants: { 'v2.1.0': { weight: 100 } } };
      const { beaconSpy, getCookie } = runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      expect(getCookie()).toBe('');
      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:config_invalid');
    });

    test('Given valid fetch, When success, Then emits fetch_ab_config=success and fetch_ab_config_loading_time', async () => {
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });

      await flushPromises();

      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.value).toBe('success');
      const loadingTime = getMetricPayload(beaconSpy, 'fetch_ab_config_loading_time')?.value;
      // value is always sent as a string (the Datadog monitor endpoint rejects numeric values with 418)
      expect(typeof loadingTime).toBe('string');
      expect(Number(loadingTime)).toBeGreaterThanOrEqual(0);
    });

    test('Given a metric with a numeric value, When emitted, Then payload value and message are coerced to strings (418 guard)', async () => {
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchOk(DEFAULT_VALID_CONFIG) });

      await flushPromises();

      // fetch_ab_config_loading_time is the only metric built from a numeric source (elapsedMs)
      const payload = getMetricPayload(beaconSpy, 'fetch_ab_config_loading_time');
      expect(typeof payload.value).toBe('string');
      expect(typeof payload.message).toBe('string');
    });

    test('Given the loader runs, When it fetches the A/B config, Then the URL uses the .js extension (CDN WAF blocks .json)', async () => {
      const fetchFn = makeFetchOk(DEFAULT_VALID_CONFIG);
      runLoaderScript({ fetchFn });

      await flushPromises();

      const fetchedUrl = fetchFn.mock.calls[0][0];
      expect(fetchedUrl).toContain('config/super-token-variants.js');
      expect(fetchedUrl).not.toContain('super-token-variants.json');
    });

    test('Given weight 100 on v2.1 and Math.random stubbed, When assigned, Then cookie value matches assigned variant', async () => {
      jest.spyOn(Math, 'random').mockReturnValue(0.5);
      const config = { active: true, default: 'v2', cookie_ttl_days: 30, variants: { 'v2': { weight: 0 }, 'v2.1': { weight: 100 } } };

      const { getCookie } = runLoaderScript({ fetchFn: makeFetchOk(config) });
      await flushPromises();

      expect(getCookie()).toBe('mp_st_variant=v2.1');
    });

    test('Given config with unknown variant, When source:config_invalid, Then loads fallback bundle from v1/ folder', async () => {
      const config = { active: true, default: 'v2', cookie_ttl_days: 30, variants: { 'v2.1.0': { weight: 100 } } };
      runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v1/super-token.bundle.min.css`);
    });

    test('Given weight 100 on v2.1 and Math.random stubbed, When assigned, Then bundle CSS/JS point to v2.1/ CDN folder', async () => {
      jest.spyOn(Math, 'random').mockReturnValue(0.5);
      const config = { active: true, default: 'v2', cookie_ttl_days: 30, variants: { 'v2': { weight: 0 }, 'v2.1': { weight: 100 } } };

      runLoaderScript({ fetchFn: makeFetchOk(config) });
      await flushPromises();

      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v2.1/super-token.bundle.min.css`);
      expect(document.getElementById(JS_ID).getAttribute('src')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v2.1/super-token.bundle.min.js`);
    });

    test('Given weight 100 on v2.1 and Math.random stubbed, When loader runs, Then picks v2.1', async () => {
      jest.spyOn(Math, 'random').mockReturnValue(0.5);
      const config = { active: true, default: 'v2', cookie_ttl_days: 30, variants: { 'v2': { weight: 0 }, 'v2.1': { weight: 100 } } };

      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchOk(config) });
      await flushPromises();

      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2.1');
      expect(payload?.message).toBe('source:assigned');
    });

    test('Given all weights zero, When selectVariantByWeight runs, Then returns SUPER_TOKEN_FALLBACK_VARIANT (v2)', async () => {
      const config = { active: true, default: 'v2', cookie_ttl_days: 30, variants: { 'v2': { weight: 0 }, 'v2.1': { weight: 0 } } };
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.value).toBe('v2');
    });
  });

  // ─── A/B: kill switch (active: false) ──────────────────────────────────────────

  describe('A/B: active field validation', () => {
    test('Given config with active field missing, When loader runs, Then source:config_invalid (not kill_switch)', async () => {
      // active absent → typeof undefined !== 'boolean' → config_invalid, not kill_switch
      const config = { default: 'v2', cookie_ttl_days: 30, variants: { 'v2': { weight: 100 } } };
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:config_invalid');
      expect(payload?.message).not.toBe('source:kill_switch');
    });

    test('Given config with active: null, When loader runs, Then source:config_invalid', async () => {
      const config = { active: null, default: 'v2', cookie_ttl_days: 30, variants: { 'v2': { weight: 100 } } };
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:config_invalid');
    });

    test('Given config with active: 0 (falsy number), When loader runs, Then source:config_invalid (not kill_switch)', async () => {
      // 0 is falsy but not boolean — previously would have triggered kill switch silently
      const config = { active: 0, default: 'v2', cookie_ttl_days: 30, variants: { 'v2': { weight: 100 } } };
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:config_invalid');
      expect(payload?.message).not.toBe('source:kill_switch');
    });

    test('Given config with active: "false" (string), When loader runs, Then source:config_invalid', async () => {
      // "false" is truthy — previously was a silent no-op (not kill_switch, but also not flagged)
      const config = { active: 'false', default: 'v2', cookie_ttl_days: 30, variants: { 'v2': { weight: 100 } } };
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:config_invalid');
    });

    test('Given config with invalid active field, When loader runs, Then loads fallback bundle from v1/', async () => {
      const config = { default: 'v2', cookie_ttl_days: 30, variants: {} }; // active absent
      runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v1/super-token.bundle.min.css`);
    });
  });

  describe('A/B: kill switch (active: false)', () => {
    test('Given active:false and no existing cookie, When loader runs, Then clears any assigned cookie', async () => {
      const { getCookie } = runLoaderScript({
        fetchFn: makeFetchOk({ active: false, default: 'v2', cookie_ttl_days: 30, variants: {} }),
      });

      await flushPromises();

      expect(getCookie()).toBe('');
    });

    test('Given active:false and existing valid cookie, When loader runs, Then kill switch clears cookie and loads default', async () => {
      // Loader always fetches — kill switch now affects ALL visitors, including returning ones.
      const config = { active: false, default: 'v2', cookie_ttl_days: 30, variants: {} };
      const { beaconSpy, getCookie } = runLoaderScript({
        cookieValue: 'v2.1',
        fetchFn: makeFetchOk(config),
      });
      await flushPromises();

      expect(getCookie()).toBe('');
      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:kill_switch');
    });

    test('Given active:false with valid default, When loader runs, Then clears cookie and loads default variant', async () => {
      const { beaconSpy, getCookie } = runLoaderScript({ fetchFn: makeFetchOk({ active: false, default: 'v2', cookie_ttl_days: 30, variants: {} }) });

      await flushPromises();

      expect(getCookie()).toBe('');
      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:kill_switch');
    });

    test('Given active:false with valid default v2, When kill_switch, Then loads bundle from v1/ CDN folder', async () => {
      const config = { active: false, default: 'v2', cookie_ttl_days: 30, variants: {} };
      runLoaderScript({ fetchFn: makeFetchOk(config) });

      await flushPromises();

      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v1/super-token.bundle.min.css`);
    });

    test('Given active:false with unknown default (v9), When loader runs, Then loads SUPER_TOKEN_FALLBACK_VARIANT (v2)', async () => {
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchOk({ active: false, default: 'v9', cookie_ttl_days: 30, variants: {} }) });

      await flushPromises();

      const payload = getMetricPayload(beaconSpy, 'super_token_ab_variant');
      expect(payload?.value).toBe('v2');
      expect(payload?.message).toBe('source:kill_switch');
    });
  });

  // ─── A/B: fetch failure scenarios ──────────────────────────────────────────────

  describe('A/B: fetch failures', () => {
    test('Given fetch returns 4xx, When loader runs, Then source:fetch_failed + cookie fetch_failed + bundle v2', async () => {
      const { beaconSpy, getCookie } = runLoaderScript({ fetchFn: makeFetch4xx(404) });

      await flushPromises();

      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.message).toBe('source:fetch_failed');
      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.value).toBe('error');
      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.message).toBe('http:404');
      expect(getCookie()).toContain('mp_st_variant=fetch_failed');
      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v1/super-token.bundle.min.css`);
    });

    test('Given fetch throws network/CORS error, When loader runs, Then source:fetch_failed + network_or_cors', async () => {
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchNetworkError() });

      await flushPromises();

      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.message).toBe('source:fetch_failed');
      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.message).toBe('network_or_cors');
      // fallback bundle must load from v1/ (v2 control variant)
      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v1/super-token.bundle.min.css`);
    });

    test('Given fetch fails, When cookie fetch_failed is set, Then TTL is approximately 2 hours (not years)', async () => {
      const beforeMs = Date.now();
      let capturedCookieString = '';

      // Intercept the full document.cookie set call to read the expires= value
      const { cookieDocument } = createCookieDocument();
      const originalSet = Object.getOwnPropertyDescriptor(cookieDocument, 'cookie').set;
      Object.defineProperty(cookieDocument, 'cookie', {
        get: Object.getOwnPropertyDescriptor(cookieDocument, 'cookie').get,
        set: (value) => {
          if (value.includes('fetch_failed')) capturedCookieString = value;
          originalSet.call(cookieDocument, value);
        },
        configurable: true,
      });

      const fileContent = require('fs').readFileSync(superTokenLoaderPath, 'utf8');
      const context = {
        window: { location: { href: 'https://example.com/checkout' } },
        document: cookieDocument,
        navigator: { sendBeacon: jest.fn() },
        console: global.console,
        fetch: makeFetchNetworkError(),
        Promise: global.Promise, Date: global.Date, Math: global.Math,
        setTimeout: global.setTimeout, clearTimeout: global.clearTimeout,
        JSON: global.JSON, Object: global.Object, RegExp: global.RegExp, Error: global.Error,
      };
      new (require('vm').Script)(fileContent).runInNewContext(context);
      await flushPromises();

      // Extract expires= value from the captured cookie string
      const expiresMatch = capturedCookieString.match(/expires=([^;]+)/);
      expect(expiresMatch).not.toBeNull();
      const expiryMs = new Date(expiresMatch[1]).getTime();

      // Should be ~2h from now — verify it's between 1h and 3h (not year 21739)
      const twoHoursMs = 2 * 60 * 60 * 1000;
      expect(expiryMs - beforeMs).toBeGreaterThan(twoHoursMs * 0.5);
      expect(expiryMs - beforeMs).toBeLessThan(twoHoursMs * 1.5);
    });

    test('Given fetch returns invalid JSON, When loader runs, Then source:fetch_failed + invalid_json', async () => {
      const { beaconSpy } = runLoaderScript({ fetchFn: makeFetchInvalidJson() });

      await flushPromises();

      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.value).toBe('error');
      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.message).toBe('invalid_json');
      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.message).toBe('source:fetch_failed');
    });

    test('Given fetch times out (3s), When loader runs, Then timeout metric + fallback v2 + cookie fetch_failed', async () => {
      jest.useFakeTimers();

      let resolveHanging;
      const hangingFetch = new Promise((resolve) => { resolveHanging = resolve; });
      const fetchSpy = jest.fn(() => hangingFetch);

      const { beaconSpy, getCookie } = runLoaderScript({ fetchFn: fetchSpy });

      jest.advanceTimersByTime(3100);
      await flushPromises();

      jest.useRealTimers();

      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.value).toBe('v2');
      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.message).toBe('source:fetch_failed');
      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.value).toBe('timeout');
      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.message).toMatch(/^elapsed_ms:/);
      expect(getCookie()).toContain('mp_st_variant=fetch_failed');

      resolveHanging(null);
    });

    test('Given timeout fires before 4xx response arrives, When loader runs, Then only timeout metric is emitted (not http:4xx)', async () => {
      jest.useFakeTimers();

      let resolveWith4xx;
      const slow4xxFetch = new Promise((resolve) => {
        resolveWith4xx = () => resolve({ ok: false, status: 404 });
      });
      const { beaconSpy } = runLoaderScript({ fetchFn: jest.fn(() => slow4xxFetch) });

      jest.advanceTimersByTime(3100); // timeout fires first
      await flushPromises();
      resolveWith4xx(); // 4xx arrives late
      await flushPromises();

      jest.useRealTimers();

      const configCalls = beaconSpy.mock.calls.filter(([url]) => url.endsWith('/fetch_ab_config'));
      expect(configCalls).toHaveLength(1);
      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.value).toBe('timeout');
    });

    test('Given timeout fires before invalid JSON arrives, When loader runs, Then only timeout metric is emitted (not invalid_json)', async () => {
      jest.useFakeTimers();

      let resolveWithInvalidJson;
      const slowInvalidFetch = new Promise((resolve) => {
        resolveWithInvalidJson = () => resolve({ ok: true, json: () => Promise.reject(new Error('bad json')) });
      });
      const { beaconSpy } = runLoaderScript({ fetchFn: jest.fn(() => slowInvalidFetch) });

      jest.advanceTimersByTime(3100); // timeout fires first
      await flushPromises();
      resolveWithInvalidJson(); // invalid JSON arrives late
      await flushPromises();

      jest.useRealTimers();

      const configCalls = beaconSpy.mock.calls.filter(([url]) => url.endsWith('/fetch_ab_config'));
      expect(configCalls).toHaveLength(1);
      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.value).toBe('timeout');
    });

    test('Given timeout fires before network rejects, When loader runs, Then only timeout metric is emitted (not network_or_cors)', async () => {
      jest.useFakeTimers();

      let rejectFetch;
      const fetchSpy = jest.fn(() => new Promise((_, reject) => { rejectFetch = reject; }));

      const { beaconSpy } = runLoaderScript({ fetchFn: fetchSpy });

      jest.advanceTimersByTime(3100); // timeout fires first
      await flushPromises();
      rejectFetch(new Error('network')); // network rejects after timeout
      await flushPromises();

      jest.useRealTimers();

      const configMetricCalls = beaconSpy.mock.calls.filter(([url]) => url.endsWith('/fetch_ab_config'));
      expect(configMetricCalls).toHaveLength(1);
      expect(getMetricPayload(beaconSpy, 'fetch_ab_config')?.value).toBe('timeout');
    });
  });

  // ─── A/B: fetch failure resilience (valid cookie preserved) ────────────────────

  describe('A/B: fetch failure resilience — valid cookie as fallback', () => {
    test('Given fetch 4xx and valid v2.1 cookie, When loader runs, Then source:cookie loads v2.1 (not fetch_failed)', async () => {
      const { beaconSpy, getCookie } = runLoaderScript({ cookieValue: 'v2.1', fetchFn: makeFetch4xx(404) });
      await flushPromises();

      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.value).toBe('v2.1');
      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.message).toBe('source:cookie');
      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v2.1/super-token.bundle.min.css`);
      // cookie must NOT be overwritten with fetch_failed
      expect(getCookie()).toBe('mp_st_variant=v2.1');
    });

    test('Given network error and valid v2 cookie, When loader runs, Then source:cookie loads from v1/ (not fetch_failed)', async () => {
      const { beaconSpy, getCookie } = runLoaderScript({ cookieValue: 'v2', fetchFn: makeFetchNetworkError() });
      await flushPromises();

      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.message).toBe('source:cookie');
      expect(document.getElementById(CSS_ID).getAttribute('href')).toBe(`${SUPER_TOKEN_STORAGE_BASE_URL}/v1/super-token.bundle.min.css`);
      expect(getCookie()).toBe('mp_st_variant=v2');
    });

    test('Given invalid JSON and valid cookie, When loader runs, Then source:cookie (not source:fetch_failed)', async () => {
      const { beaconSpy, getCookie } = runLoaderScript({ cookieValue: 'v2.1', fetchFn: makeFetchInvalidJson() });
      await flushPromises();

      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.message).toBe('source:cookie');
      expect(getCookie()).toBe('mp_st_variant=v2.1');
    });

    test('Given fetch timeout and valid cookie, When loader runs, Then source:cookie (fetch_failed cookie NOT set)', async () => {
      jest.useFakeTimers();

      let resolveHanging;
      const hangingFetch = new Promise((resolve) => { resolveHanging = resolve; });
      const { beaconSpy, getCookie } = runLoaderScript({ cookieValue: 'v2', fetchFn: jest.fn(() => hangingFetch) });

      jest.advanceTimersByTime(3100);
      await flushPromises();
      jest.useRealTimers();

      expect(getMetricPayload(beaconSpy, 'super_token_ab_variant')?.message).toBe('source:cookie');
      // fetch_failed cookie must NOT be set — existing cookie preserved
      expect(getCookie()).toBe('mp_st_variant=v2');

      resolveHanging(null);
    });

    test('Given fetch fails and valid cookie exists, When loader runs, Then fetch_failed cookie is never set', async () => {
      const { getCookie } = runLoaderScript({ cookieValue: 'v2', fetchFn: makeFetch4xx(503) });
      await flushPromises();

      // cookie must remain the valid variant, not overwritten with fetch_failed
      expect(getCookie()).toBe('mp_st_variant=v2');
      expect(getCookie()).not.toContain('fetch_failed');
    });
  });
});
