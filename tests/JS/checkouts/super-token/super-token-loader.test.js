const vm = require('vm');
const fs = require('fs');
const { resolveAlias } = require('../../helpers/path-resolver');

const superTokenLoaderPath = resolveAlias('assets/js/checkouts/super-token-loader.js');

function runLoaderScript(options = {}) {
  const { scriptsParams, sendBeacon, fetchFn, disableBeacon } = options;
  const fileContent = fs.readFileSync(superTokenLoaderPath, 'utf8');
  const beaconSpy = sendBeacon || jest.fn();
  const fetchSpy = fetchFn || jest.fn(() => Promise.resolve());
  const context = {
    window: {
      location: {
        href: 'https://example.com/checkout',
      },
    },
    document: global.document,
    navigator: disableBeacon ? {} : { sendBeacon: beaconSpy },
    console: global.console,
    fetch: fetchSpy,
  };

  if (scriptsParams !== undefined) {
    context.wc_mercadopago_woocommerce_scripts_params = scriptsParams;
  }

  const script = new vm.Script(fileContent);
  script.runInNewContext(context);

  return { beaconSpy, fetchSpy };
}

describe('super-token-loader', () => {
  const cssId = 'wc_mercadopago_supertoken_bundle_css';
  const jsId = 'wc_mercadopago_supertoken_bundle_js';

  beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
  });

  test('Given script executes, When loader runs, Then CSS and JS assets are injected once', () => {
    runLoaderScript({
      scriptsParams: {
        plugin_version: '1.2.3',
        theme: 'storefront',
        platform_version: '9.0.0',
        site_id: 'MLA',
        cust_id: '123',
      },
    });

    const cssTag = document.getElementById(cssId);
    const jsTag = document.getElementById(jsId);

    expect(cssTag).not.toBeNull();
    expect(cssTag.tagName).toBe('LINK');
    expect(jsTag).not.toBeNull();
    expect(jsTag.tagName).toBe('SCRIPT');
    expect(jsTag.defer).toBe(true);
  });

  test('Given script executes twice, When assets already exist, Then loader does not duplicate injected tags', () => {
    runLoaderScript();
    runLoaderScript();

    expect(document.querySelectorAll(`#${cssId}`)).toHaveLength(1);
    expect(document.querySelectorAll(`#${jsId}`)).toHaveLength(1);
  });

  test('Given JS asset load fails, When error handler runs, Then loader sends failure metric payload', () => {
    const { beaconSpy } = runLoaderScript({
      scriptsParams: {
        plugin_version: '1.2.3',
        theme: 'storefront',
        platform_version: '9.0.0',
        site_id: 'MLA',
        cust_id: '123',
      },
    });

    const jsTag = document.getElementById(jsId);
    jsTag.onerror();

    expect(beaconSpy).toHaveBeenCalledTimes(1);
    expect(beaconSpy).toHaveBeenCalledWith(
      'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/load_super_token_bundle_js',
      expect.any(String)
    );

    const payload = JSON.parse(beaconSpy.mock.calls[0][1]);
    expect(payload).toMatchObject({
      value: 'false',
      message: 'Unable to load super token bundle js on page',
      plugin_version: '1.2.3',
      platform: {
        name: 'woocommerce',
        uri: 'storefront',
        version: '9.0.0',
        url: 'https://example.com/checkout',
      },
      details: {
        site_id: 'MLA',
        environment: 'prod',
        cust_id: '123',
      },
    });
  });

  test('Given global params are missing, When success metric is emitted, Then payload uses safe fallback values', () => {
    const { beaconSpy } = runLoaderScript();

    const cssTag = document.getElementById(cssId);
    cssTag.onload();

    const payload = JSON.parse(beaconSpy.mock.calls[0][1]);
    expect(payload.plugin_version).toBe('');
    expect(payload.platform.uri).toBe('');
    expect(payload.platform.version).toBe('');
    expect(payload.details.site_id).toBe('');
    expect(payload.details.cust_id).toBe('');
  });

  test('Given sendBeacon is unavailable, When metric is emitted, Then loader falls back to fetch with correct options', () => {
    const { fetchSpy, beaconSpy } = runLoaderScript({
      disableBeacon: true,
      scriptsParams: {
        plugin_version: '2.0.0',
        theme: 'flavor',
        platform_version: '10.0.0',
        site_id: 'MLB',
        cust_id: '456',
      },
    });

    const jsTag = document.getElementById(jsId);
    jsTag.onerror();

    expect(beaconSpy).not.toHaveBeenCalled();
    expect(fetchSpy).toHaveBeenCalledTimes(1);

    const [url, opts] = fetchSpy.mock.calls[0];
    expect(url).toBe(
      'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/load_super_token_bundle_js'
    );
    expect(opts.method).toBe('POST');
    expect(opts.keepalive).toBe(true);
    expect(opts.headers).toEqual({ 'Content-Type': 'application/json' });

    const payload = JSON.parse(opts.body);
    expect(payload).toMatchObject({
      value: 'false',
      message: 'Unable to load super token bundle js on page',
      plugin_version: '2.0.0',
      details: { site_id: 'MLB', cust_id: '456' },
    });
  });

  test('Given both sendBeacon and fetch are unavailable, When metric is emitted, Then loader does not throw', () => {
    expect(() => {
      runLoaderScript({
        disableBeacon: true,
        fetchFn: undefined,
      });

      // Remove fetch from context by running with a custom context that lacks it
      const fileContent = fs.readFileSync(superTokenLoaderPath, 'utf8');
      const context = {
        window: { location: { href: 'https://example.com/checkout' } },
        document: global.document,
        navigator: {},
        console: global.console,
      };
      const script = new vm.Script(fileContent);
      script.runInNewContext(context);

      const cssTag = document.getElementById(cssId);
      if (cssTag && cssTag.onerror) {
        cssTag.onerror();
      }
    }).not.toThrow();
  });
});
