/**
 * @jest-environment jsdom
 * @jest-environment-options {"url": "https://localhost/"}
 */
// The variant cookie is written with `;Secure`; an https context is required for
// jsdom to persist it, matching the production checkout (always https).
// jsdom's Blob does not implement .text(); use a minimal double that captures the
// parts/type so the beacon body (now a Blob, not a raw string) can be inspected.
class FakeBlob {
  constructor(parts, options) {
    this._text = (parts || []).join('');
    this.type = (options && options.type) || '';
    this.size = this._text.length;
  }
  async text() { return this._text; }
}
global.Blob = FakeBlob;

const { VariantConfigAdapter } = require('@super-token/adapters/platform/VariantConfigAdapter');

const VARIANT_METRIC = 'super_token_ab_variant';

async function beaconFor(metricName) {
  const call = navigator.sendBeacon.mock.calls.find((args) => String(args[0]).endsWith(`/${metricName}`));
  if (!call) return null;
  // The body is now a Blob (application/json) instead of a raw string, so read its text.
  const raw = typeof call[1] === 'string' ? call[1] : await call[1].text();
  return { url: call[0], type: typeof call[1] === 'string' ? undefined : call[1].type, payload: JSON.parse(raw) };
}

function mockConfigResponse(config) {
  global.fetch = jest.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(config) });
}

function clearVariantCookie() {
  document.cookie = 'mp_st_variant=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
}

describe('VariantConfigAdapter', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    window.history.replaceState({}, '', '/');
    clearVariantCookie();
    Object.defineProperty(window.navigator, 'sendBeacon', { value: jest.fn(), configurable: true, writable: true });
    window.wc_mercadopago_woocommerce_scripts_params = { plugin_version: '8.9.0', site_id: 'MLB', cust_id: 'C1', theme: 'storefront' };
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
    delete window.wc_mercadopago_woocommerce_scripts_params;
    clearVariantCookie();
  });

  it('Given active config with no cookie, When resolve runs, Then it assigns the weighted variant and stores the cookie', async () => {
    mockConfigResponse({ active: true, variants: { v2: { weight: 0 }, 'v2.1': { weight: 1 } }, cookie_ttl_days: 10 });
    const adapter = new VariantConfigAdapter();

    const variant = await adapter.resolve();

    expect(variant).toBe('v2.1');
    expect(document.cookie).toContain('mp_st_variant=v2.1');
    expect((await beaconFor(VARIANT_METRIC)).payload.message).toBe('source:assigned');
  });

  it('Given a metric is beaconed, When resolve runs, Then the beacon body is a Blob typed application/json (not text/plain)', async () => {
    mockConfigResponse({ active: true, variants: { v2: { weight: 1 } } });
    const adapter = new VariantConfigAdapter();

    await adapter.resolve();

    const beacon = await beaconFor(VARIANT_METRIC);
    expect(navigator.sendBeacon.mock.calls[0][1]).toBeInstanceOf(Blob);
    expect(beacon.type).toBe('application/json');
  });

  it('Given an Order Pay URL contains a key, When a metric is beaconed, Then query and fragment are excluded', async () => {
    window.history.replaceState({}, '', '/checkout/order-pay/42/?key=wc_order_secret#payment');
    mockConfigResponse({ active: true, variants: { v2: { weight: 1 } } });
    const adapter = new VariantConfigAdapter();

    await adapter.resolve();

    const beacon = await beaconFor(VARIANT_METRIC);
    expect(beacon.payload.platform.url).toBe('https://localhost/checkout/order-pay/42/');
    expect(beacon.payload.platform.url).not.toContain('wc_order_secret');
  });

  it('Given the kill switch (active:false), When resolve runs, Then it clears the cookie and returns the default variant', async () => {
    document.cookie = 'mp_st_variant=v2.1';
    mockConfigResponse({ active: false, default: 'v2.1' });
    const adapter = new VariantConfigAdapter();

    const variant = await adapter.resolve();

    expect(variant).toBe('v2.1');
    expect(document.cookie).not.toContain('mp_st_variant=v2.1');
    expect((await beaconFor(VARIANT_METRIC)).payload.message).toBe('source:kill_switch');
  });

  it('Given the config fetch fails with a valid cookie, When resolve runs, Then it falls back to the cookie variant', async () => {
    document.cookie = 'mp_st_variant=v2.1';
    global.fetch = jest.fn().mockRejectedValue(new Error('network'));
    const adapter = new VariantConfigAdapter();

    const variant = await adapter.resolve();

    expect(variant).toBe('v2.1');
    expect((await beaconFor(VARIANT_METRIC)).payload.message).toBe('source:cookie');
  });

  it('Given the config fetch fails with no cookie, When resolve runs, Then it returns the fallback and marks the fetch_failed cookie', async () => {
    global.fetch = jest.fn().mockRejectedValue(new Error('network'));
    const adapter = new VariantConfigAdapter();

    const variant = await adapter.resolve();

    expect(variant).toBe('v2');
    expect(document.cookie).toContain('mp_st_variant=fetch_failed');
    expect((await beaconFor(VARIANT_METRIC)).payload.message).toBe('source:fetch_failed');
  });

  it('Given a malformed config (active not boolean), When resolve runs, Then it returns the fallback variant', async () => {
    mockConfigResponse({ active: 'yes' });
    const adapter = new VariantConfigAdapter();

    const variant = await adapter.resolve();

    expect(variant).toBe('v2');
    expect((await beaconFor(VARIANT_METRIC)).payload.message).toBe('source:config_invalid');
  });

  it('Given active config with a valid cookie, When resolve runs, Then it reuses the cookie without reassigning', async () => {
    document.cookie = 'mp_st_variant=v2';
    mockConfigResponse({ active: true, variants: { v2: { weight: 1 }, 'v2.1': { weight: 1 } } });
    const adapter = new VariantConfigAdapter();

    const variant = await adapter.resolve();

    expect(variant).toBe('v2');
    expect((await beaconFor(VARIANT_METRIC)).payload.message).toBe('source:cookie');
  });
});
