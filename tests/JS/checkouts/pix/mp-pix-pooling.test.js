const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');
const { createDomReadyHarness, timers, flushPromises } = require('../fixtures/payment-method-harness');
const source = resolveAlias('assets/js/checkouts/pix/mp-pix-pooling.js');

describe('Pix payment status polling', () => {
  let fetchStatus;
  let reload;
  const params = { order_id: 42, order_key: 'synthetic-order-key', nonce: 'synthetic-nonce', ajax_url: '/configured-status' };

  function start(config = params) {
    const lifecycle = createDomReadyHarness();
    loadFile(source, 'undefined', {
      ...timers(), document: lifecycle.document, window: { location: { reload } },
      fetch: fetchStatus, FormData,
      console: { debug: jest.fn() },
      ...(config === undefined ? {} : { wc_mercadopago_pix_pooling_params: config }),
    });
    lifecycle.runReady();
  }
  async function advance(ms) { jest.advanceTimersByTime(ms); await flushPromises(); }

  beforeEach(() => {
    jest.useFakeTimers();
    reload = jest.fn();
    fetchStatus = jest.fn().mockResolvedValue({ json: async () => ({ success: true, data: { status: 'pending' } }) });
  });
  afterEach(() => { jest.clearAllTimers(); jest.useRealTimers(); jest.restoreAllMocks(); });

  test('Given complete identity, When polling starts after 20s, Then the configured endpoint receives the order fields', async () => {
    start();
    await advance(19999);
    expect(fetchStatus).not.toHaveBeenCalled();
    await advance(1);
    const [url, request] = fetchStatus.mock.calls[0];
    expect(url).toBe('/configured-status');
    expect(request.method).toBe('POST');
    expect(Array.from(request.body.entries())).toEqual([
      ['order_id', '42'], ['order_key', 'synthetic-order-key'], ['nonce', 'synthetic-nonce'],
    ]);
    await advance(5000);
    expect(fetchStatus).toHaveBeenCalledTimes(2);
    expect(reload).not.toHaveBeenCalled();
  });

  test.each(['order_id', 'order_key', 'nonce'])('Given missing %s, When the page becomes ready, Then no timer or request is scheduled', async key => {
    start({ ...params, [key]: '' });
    await advance(40000);
    expect(fetchStatus).not.toHaveBeenCalled();
    expect(jest.getTimerCount()).toBe(0);
  });
  test('Given localization is absent, When the page becomes ready, Then polling does not start', () => {
    const lifecycle = createDomReadyHarness();
    loadFile(source, 'undefined', { ...timers(), document: lifecycle.document, fetch: fetchStatus });
    lifecycle.runReady();
    expect(jest.getTimerCount()).toBe(0);
    expect(fetchStatus).not.toHaveBeenCalled();
  });
  test('Given no custom URL, When the timer fires, Then the Woo endpoint fallback is used', async () => {
    start({ ...params, ajax_url: '' });
    await advance(20000);
    expect(fetchStatus.mock.calls[0][0]).toBe('/?wc-ajax=mp_pix_payment_status');
  });
  test.each([
    ['approved', { success: true, data: { status: 'approved' } }, 1],
    ['unsuccessful response', { success: false }, 0],
  ])('Given %s, When the response resolves, Then polling stops with the expected reload count', async (_name, response, reloads) => {
    fetchStatus.mockResolvedValue({ json: async () => response });
    start();
    await advance(20000);
    await advance(30000);
    expect(fetchStatus).toHaveBeenCalledTimes(1);
    expect(reload).toHaveBeenCalledTimes(reloads);
    expect(jest.getTimerCount()).toBe(0);
  });
  test.each(['network', 'JSON parse'])('Given a %s failure, When the next interval fires, Then it retries without reloading', async failure => {
    if (failure === 'network') fetchStatus.mockRejectedValueOnce(new Error('network unavailable'));
    else fetchStatus.mockResolvedValueOnce({ json: async () => { throw new Error('invalid JSON'); } });
    start();
    await advance(20000);
    await advance(5000);
    expect(fetchStatus).toHaveBeenCalledTimes(2);
    expect(reload).not.toHaveBeenCalled();
  });
  test('Given a successful response without status data, When polling continues, Then no approval is invented', async () => {
    fetchStatus.mockResolvedValue({ json: async () => ({ success: true }) });
    start();
    await advance(20000);
    await advance(5000);
    expect(fetchStatus).toHaveBeenCalledTimes(2);
    expect(reload).not.toHaveBeenCalled();
  });
  test('Given a permanently pending order, When the limit is reached, Then only 60 requests are sent', async () => {
    start();
    await advance(20000);
    for (let attempt = 1; attempt < 61; attempt++) await advance(5000);
    expect(fetchStatus).toHaveBeenCalledTimes(60);
    expect(jest.getTimerCount()).toBe(0);
    await advance(30000);
    expect(fetchStatus).toHaveBeenCalledTimes(60);
    expect(reload).not.toHaveBeenCalled();
  });
});
