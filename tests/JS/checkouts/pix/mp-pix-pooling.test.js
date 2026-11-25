const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');
const { createDomReadyHarness, timers, flushPromises } = require('../fixtures/payment-method-harness');
const source = resolveAlias('assets/js/checkouts/pix/mp-pix-pooling.js');

describe('Pix payment status polling', () => {
  let fetchStatus;
  let reload;
  const params = { order_id: 42, order_key: 'synthetic-order-key', nonce: 'synthetic-nonce', ajax_url: '/configured-status' };

  function start(config = params, extraGlobals = {}) {
    const lifecycle = createDomReadyHarness();
    loadFile(source, 'undefined', {
      ...timers(), document: lifecycle.document, window: { location: { reload } },
      fetch: fetchStatus, FormData,
      console: { debug: jest.fn() }, ...extraGlobals,
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

  describe('single request in flight per page', () => {
    function deferred() {
      let resolve;
      const promise = new Promise(res => { resolve = res; });
      return { promise, resolve };
    }

    test('Given a slow request, When more ticks fire, Then no overlapping request is dispatched', async () => {
      const slow = deferred();
      fetchStatus.mockReturnValueOnce(slow.promise);
      start();
      await advance(20000);
      expect(fetchStatus).toHaveBeenCalledTimes(1);
      await advance(15000);
      expect(fetchStatus).toHaveBeenCalledTimes(1);
      slow.resolve({ json: async () => ({ success: true, data: { status: 'pending' } }) });
      await flushPromises();
      await advance(5000);
      expect(fetchStatus).toHaveBeenCalledTimes(2);
    });

    test('Given a request that never answers, When the timeout elapses, Then it is aborted and polling continues', async () => {
      fetchStatus.mockImplementationOnce((url, request) => new Promise((resolve, reject) => {
        request.signal.addEventListener('abort', () => reject(new Error('aborted')));
      }));
      start(params, { AbortController });
      await advance(20000);
      expect(fetchStatus).toHaveBeenCalledTimes(1);
      await advance(14999);
      expect(fetchStatus).toHaveBeenCalledTimes(1); // still waiting, ticks are skipped
      await advance(1);
      await advance(5000);
      expect(fetchStatus).toHaveBeenCalledTimes(2); // inFlight was released by the abort
      expect(reload).not.toHaveBeenCalled();
    });

    test('Given a backend that never answers, When the total polling window elapses, Then polling stops after 10 minutes', async () => {
      fetchStatus.mockReturnValue(new Promise(() => {}));
      start();
      await advance(20000);
      expect(fetchStatus).toHaveBeenCalledTimes(1);
      await advance(5000 * 125); // more ticks than the 120-tick (10 min) window
      expect(fetchStatus).toHaveBeenCalledTimes(1);
      expect(jest.getTimerCount()).toBe(0); // the interval was cleared
      expect(reload).not.toHaveBeenCalled();
    });

    test('Given AbortController is unavailable, When polling, Then requests are sent without a signal', async () => {
      start();
      await advance(20000);
      expect(fetchStatus.mock.calls[0][1].signal).toBeUndefined();
    });

    test('Given headers arrived but the JSON body is pending, When ticks fire, Then the next request waits for the body', async () => {
      const body = deferred();
      fetchStatus.mockResolvedValueOnce({ json: () => body.promise });
      start();
      await advance(20000);
      await advance(15000);
      expect(fetchStatus).toHaveBeenCalledTimes(1);
      body.resolve({ success: true, data: { status: 'pending' } });
      await flushPromises();
      await advance(5000);
      expect(fetchStatus).toHaveBeenCalledTimes(2);
    });

    test('Given skipped ticks, When the limit is reached, Then only dispatched requests consume attempts', async () => {
      const slow = deferred();
      fetchStatus.mockReturnValueOnce(slow.promise);
      start();
      await advance(20000);
      await advance(30000);
      slow.resolve({ json: async () => ({ success: true, data: { status: 'pending' } }) });
      await flushPromises();
      for (let tick = 0; tick < 59; tick++) await advance(5000);
      expect(fetchStatus).toHaveBeenCalledTimes(60);
      await advance(30000);
      expect(fetchStatus).toHaveBeenCalledTimes(60);
    });

    test('Given a recoverable failure marked retry, When the next tick fires, Then polling continues', async () => {
      fetchStatus.mockResolvedValueOnce({ json: async () => ({ success: false, data: { retry: true } }) });
      start();
      await advance(20000);
      await advance(5000);
      expect(fetchStatus).toHaveBeenCalledTimes(2);
      expect(reload).not.toHaveBeenCalled();
    });

    test('Given a rejected request, When the next tick fires, Then the in-flight guard was released', async () => {
      fetchStatus.mockRejectedValueOnce(new Error('network unavailable'));
      start();
      await advance(20000);
      await advance(5000);
      expect(fetchStatus).toHaveBeenCalledTimes(2);
    });

    test('Given approval resolves after the polling already finished, When it arrives late, Then it reloads once and starts nothing', async () => {
      const first = deferred();
      fetchStatus.mockReturnValueOnce(first.promise);
      start();
      await advance(20000);
      first.resolve({ json: async () => ({ success: true, data: { status: 'approved' } }) });
      await flushPromises();
      await advance(30000);
      expect(reload).toHaveBeenCalledTimes(1);
      expect(fetchStatus).toHaveBeenCalledTimes(1);
      expect(jest.getTimerCount()).toBe(0);
    });
  });
});
