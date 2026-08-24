const vm = require('vm');
const fs = require('fs');
const { resolveAlias } = require('../../helpers/path-resolver');

const superTokenLoaderPath = resolveAlias('assets/js/checkouts/super-token-loader.js');

const CSS_ID = 'wc_mercadopago_supertoken_bundle_css';
const JS_ID = 'wc_mercadopago_supertoken_bundle_js';
const BUNDLE_BASE_URL = 'https://http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts/v1';
const MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';

function runLoaderScript(options = {}) {
  const { scriptsParams, sendBeacon, disableBeacon, disableFetch, location } = options;
  const fileContent = fs.readFileSync(superTokenLoaderPath, 'utf8');

  const beaconSpy = sendBeacon || jest.fn();
  const fetchSpy = jest.fn();

  const context = {
    window: {
      location: location || {
        href: 'https://example.com/checkout',
        origin: 'https://example.com',
        pathname: '/checkout',
      },
    },
    document: global.document,
    navigator: disableBeacon ? {} : { sendBeacon: beaconSpy },
    console: global.console,
    JSON: global.JSON,
    Object: global.Object,
    Error: global.Error,
    setTimeout: global.setTimeout,
    clearTimeout: global.clearTimeout,
  };

  if (!disableFetch) {
    context.fetch = fetchSpy;
  }
  if (scriptsParams !== undefined) {
    context.wc_mercadopago_woocommerce_scripts_params = scriptsParams;
  }

  new vm.Script(fileContent).runInNewContext(context);

  return { beaconSpy, fetchSpy };
}

/** Returns parsed payload of the Nth call to a given metric */
function getMetricPayload(beaconSpy, metricName, index = 0) {
  const calls = beaconSpy.mock.calls.filter(([url]) => url.endsWith('/' + metricName));
  return calls[index] ? JSON.parse(calls[index][1]) : null;
}

describe('super-token-loader', () => {
  beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe('asset injection', () => {
    test('Given the loader runs, When it loads the bundle, Then CSS and JS point to the fallback variant CDN folder', () => {
      runLoaderScript();

      const cssTag = document.getElementById(CSS_ID);
      const jsTag = document.getElementById(JS_ID);

      expect(cssTag).not.toBeNull();
      expect(cssTag.tagName).toBe('LINK');
      expect(cssTag.getAttribute('href')).toBe(`${BUNDLE_BASE_URL}/super-token.bundle.min.css`);

      expect(jsTag).not.toBeNull();
      expect(jsTag.tagName).toBe('SCRIPT');
      expect(jsTag.getAttribute('src')).toBe(`${BUNDLE_BASE_URL}/super-token.bundle.min.js`);
      expect(jsTag.defer).toBe(true);
    });

    test('Given the loader runs twice, When the ids already exist, Then the assets are not duplicated', () => {
      runLoaderScript();
      runLoaderScript();

      expect(document.querySelectorAll(`#${CSS_ID}`)).toHaveLength(1);
      expect(document.querySelectorAll(`#${JS_ID}`)).toHaveLength(1);
    });
  });

  describe('load metrics', () => {
    test('Given the JS asset fails to load, When onerror fires, Then a failure metric is sent with the full payload', () => {
      const { beaconSpy } = runLoaderScript({
        scriptsParams: { plugin_version: '1.2.3', theme: 'storefront', platform_version: '9.0.0', site_id: 'MLA', cust_id: '123' },
      });

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

    test('Given the CSS asset loads, When onload fires and params are missing, Then a success metric is sent with safe fallbacks', () => {
      const { beaconSpy } = runLoaderScript();

      document.getElementById(CSS_ID).onload();

      const payload = getMetricPayload(beaconSpy, 'load_super_token_bundle_css');
      expect(payload.value).toBe('true');
      expect(payload.plugin_version).toBe('');
      expect(payload.platform.uri).toBe('');
      expect(payload.details.site_id).toBe('');
    });

    test('Given an Order Pay URL contains a key, When a metric is emitted, Then query and fragment are excluded', () => {
      const { beaconSpy } = runLoaderScript({
        location: {
          href: 'https://example.com/checkout/order-pay/42/?key=wc_order_secret#payment',
          origin: 'https://example.com',
          pathname: '/checkout/order-pay/42/',
        },
      });

      document.getElementById(CSS_ID).onload();

      const payload = getMetricPayload(beaconSpy, 'load_super_token_bundle_css');
      expect(payload.platform.url).toBe('https://example.com/checkout/order-pay/42/');
      expect(payload.platform.url).not.toContain('wc_order_secret');
    });

    test('Given sendBeacon is unavailable, When a metric is emitted, Then the loader falls back to fetch (keepalive POST)', () => {
      const { beaconSpy, fetchSpy } = runLoaderScript({
        disableBeacon: true,
        scriptsParams: { plugin_version: '2.0.0', theme: 'flavor', platform_version: '10.0.0', site_id: 'MLB', cust_id: '456' },
      });

      document.getElementById(JS_ID).onerror();

      expect(beaconSpy).not.toHaveBeenCalled();
      const errorCall = fetchSpy.mock.calls.find(([url]) => url.endsWith('/load_super_token_bundle_js'));
      expect(errorCall).toBeDefined();
      const [url, opts] = errorCall;
      expect(url).toBe(`${MONITOR_URL}/load_super_token_bundle_js`);
      expect(opts.method).toBe('POST');
      expect(opts.keepalive).toBe(true);
      expect(JSON.parse(opts.body).value).toBe('false');
    });

    test('Given neither sendBeacon nor fetch is available, When a metric is emitted, Then the loader does not throw', () => {
      expect(() => {
        runLoaderScript({ disableBeacon: true, disableFetch: true });
        document.getElementById(CSS_ID)?.onerror?.();
      }).not.toThrow();
    });
  });
});
