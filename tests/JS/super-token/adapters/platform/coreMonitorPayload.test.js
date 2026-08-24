const {
  CORE_MONITOR_URL,
  sendToCoreMonitor,
} = require('@super-token/adapters/platform/coreMonitorPayload');

const buildPayload = (overrides = {}) => ({
  value: '1',
  message: 'ok',
  plugin_version: '8.9.1',
  platform: { name: 'woocommerce', uri: '/checkout', version: '9', url: 'https://shop.test' },
  details: { site_id: 'MLB', environment: 'prod', cust_id: '123' },
  ...overrides,
});

describe('sendToCoreMonitor', () => {
  let originalFetch;
  let originalSendBeacon;

  beforeEach(() => {
    originalFetch = global.fetch;
    originalSendBeacon = global.navigator.sendBeacon;
    global.fetch = jest.fn().mockResolvedValue(undefined);
  });

  afterEach(() => {
    global.fetch = originalFetch;
    global.navigator.sendBeacon = originalSendBeacon;
    jest.clearAllMocks();
  });

  it('Given the default transport, When sent, Then it POSTs the metric to CORE_MONITOR_URL/{metricName} as JSON', () => {
    const payload = buildPayload();
    sendToCoreMonitor('my_metric', payload);

    expect(global.fetch).toHaveBeenCalledTimes(1);
    const [url, init] = global.fetch.mock.calls[0];
    expect(url).toBe(`${CORE_MONITOR_URL}/my_metric`);
    expect(init.method).toBe('POST');
    expect(init.headers['Content-Type']).toBe('application/json');
    expect(JSON.parse(init.body)).toEqual(payload);
    expect(init.keepalive).toBe(false);
  });

  it('Given the beacon transport and an available sendBeacon, When sent, Then it uses sendBeacon with a JSON Blob and does not fetch', () => {
    global.navigator.sendBeacon = jest.fn().mockReturnValue(true);

    sendToCoreMonitor('ab_metric', buildPayload(), 'beacon');

    expect(global.navigator.sendBeacon).toHaveBeenCalledTimes(1);
    const [url, blob] = global.navigator.sendBeacon.mock.calls[0];
    expect(url).toBe(`${CORE_MONITOR_URL}/ab_metric`);
    expect(blob).toBeInstanceOf(Blob);
    expect(blob.type).toBe('application/json');
    expect(global.fetch).not.toHaveBeenCalled();
  });

  it('Given the beacon transport but sendBeacon is unavailable, When sent, Then it falls back to keepalive fetch', () => {
    global.navigator.sendBeacon = undefined;

    sendToCoreMonitor('ab_metric', buildPayload(), 'beacon');

    expect(global.fetch).toHaveBeenCalledTimes(1);
    expect(global.fetch.mock.calls[0][1].keepalive).toBe(true);
  });

  it('Given fetch rejects, When sent, Then the rejection is swallowed and nothing throws', () => {
    global.fetch = jest.fn().mockRejectedValue(new Error('network down'));
    expect(() => sendToCoreMonitor('my_metric', buildPayload())).not.toThrow();
  });

  it('Given fetch itself throws synchronously, When sent, Then the error is swallowed', () => {
    global.fetch = jest.fn(() => {
      throw new Error('boom');
    });
    expect(() => sendToCoreMonitor('my_metric', buildPayload())).not.toThrow();
  });
});
