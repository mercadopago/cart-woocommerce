const { MelidataAdapter } = require('@super-token/adapters/platform/MelidataAdapter');

function lastDispatchedEvent(dispatchSpy) {
  return dispatchSpy.mock.calls[dispatchSpy.mock.calls.length - 1][0];
}

function flushMicrotasks() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

describe('MelidataAdapter', () => {
  let dispatchSpy;

  beforeEach(() => {
    delete window.melidata;
    delete window.melidataReady;
    dispatchSpy = jest.spyOn(document, 'dispatchEvent').mockImplementation(() => true);
  });

  afterEach(() => {
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  it('Given MeliData is already loaded, When an error event is dispatched, Then it fires immediately with cleaned message and mercado_pago origin', () => {
    window.melidata = {};
    const adapter = new MelidataAdapter(jest.fn());

    adapter.dispatchMelidataErrorEvent('[mercado pago]: boom', 'post_submit');

    expect(dispatchSpy).toHaveBeenCalledTimes(1);
    const event = lastDispatchedEvent(dispatchSpy);
    expect(event.type).toBe('mp_checkout_error');
    expect(event.detail).toEqual({ message: 'boom', errorOrigin: 'post_submit_mercado_pago' });
  });

  it('Given MeliData is not ready, When events are dispatched, Then they are buffered and flushed in order once melidataReady resolves', async () => {
    let resolveReady;
    window.melidataReady = new Promise((resolve) => {
      resolveReady = resolve;
    });
    const adapter = new MelidataAdapter(jest.fn());

    adapter.dispatchMelidataErrorEvent('first', 'load_super_token');
    adapter.dispatchMelidataErrorEvent('second', 'load_super_token');
    expect(dispatchSpy).not.toHaveBeenCalled();

    resolveReady();
    await flushMicrotasks();

    expect(dispatchSpy).toHaveBeenCalledTimes(2);
    expect(dispatchSpy.mock.calls[0][0].detail.message).toBe('first');
    expect(dispatchSpy.mock.calls[1][0].detail.message).toBe('second');
  });

  it('Given neither MeliData nor melidataReady with the document already loaded, When an event is dispatched, Then it fails over immediately: timeout metric once + best-effort dispatch', () => {
    // Third readiness path: window.melidataReady never defined and `load` already fired,
    // so no future signal will arrive — the adapter must fail over synchronously instead
    // of registering a dead `load` listener (would silently drop checkout telemetry).
    Object.defineProperty(document, 'readyState', { configurable: true, get: () => 'complete' });
    const sendMetric = jest.fn();
    const adapter = new MelidataAdapter(sendMetric);

    adapter.dispatchMelidataErrorEvent('[mercado pago]: boom', 'load_super_token');

    expect(sendMetric).toHaveBeenCalledTimes(1);
    expect(sendMetric).toHaveBeenCalledWith('mp_melidata_load_timeout', 'true', 'buffered:1');
    expect(dispatchSpy).toHaveBeenCalledTimes(1);
    expect(lastDispatchedEvent(dispatchSpy).detail).toEqual({
      message: 'boom',
      errorOrigin: 'load_super_token_mercado_pago',
    });

    delete document.readyState;
  });

  it('Given MeliData fails to load, When melidataReady rejects, Then the timeout metric is sent once and buffered events are still dispatched best-effort', async () => {
    window.melidataReady = Promise.reject(new Error('cdn down'));
    const sendMetric = jest.fn();
    const adapter = new MelidataAdapter(sendMetric);

    adapter.dispatchMelidataErrorEvent('boom', 'post_submit');
    await flushMicrotasks();

    expect(sendMetric).toHaveBeenCalledTimes(1);
    expect(sendMetric).toHaveBeenCalledWith('mp_melidata_load_timeout', 'true', 'buffered:1');
    expect(dispatchSpy).toHaveBeenCalledTimes(1);
  });

  it('Given melidataReady never settles, When five seconds elapse, Then it reports the deadline once and flushes the FIFO buffer', () => {
    jest.useFakeTimers();
    window.melidataReady = new Promise(() => {});
    const sendMetric = jest.fn();
    const adapter = new MelidataAdapter(sendMetric);

    adapter.dispatchMelidataErrorEvent('first', 'post_submit');
    adapter.dispatchMelidataErrorEvent('second', 'post_submit');
    jest.advanceTimersByTime(4999);
    expect(dispatchSpy).not.toHaveBeenCalled();

    jest.advanceTimersByTime(1);

    expect(sendMetric).toHaveBeenCalledTimes(1);
    expect(sendMetric).toHaveBeenCalledWith('mp_melidata_load_timeout', 'true', 'buffered:2');
    expect(dispatchSpy.mock.calls.map((call) => call[0].detail.message)).toEqual(['first', 'second']);
  });

  it('Given readiness resolves before the deadline, When time advances later, Then no timeout metric is emitted', async () => {
    jest.useFakeTimers();
    let resolveReady;
    window.melidataReady = new Promise((resolve) => {
      resolveReady = resolve;
    });
    const sendMetric = jest.fn();
    const adapter = new MelidataAdapter(sendMetric);

    adapter.dispatchMelidataErrorEvent('first', 'post_submit');
    resolveReady();
    await Promise.resolve();
    await Promise.resolve();
    jest.advanceTimersByTime(5000);

    expect(sendMetric).not.toHaveBeenCalled();
    expect(dispatchSpy).toHaveBeenCalledTimes(1);
  });

  it('Given the deadline already failed, When melidataReady resolves late, Then it does not flush or report twice', async () => {
    jest.useFakeTimers();
    let resolveReady;
    window.melidataReady = new Promise((resolve) => {
      resolveReady = resolve;
    });
    const sendMetric = jest.fn();
    const adapter = new MelidataAdapter(sendMetric);

    adapter.dispatchMelidataErrorEvent('first', 'post_submit');
    jest.advanceTimersByTime(5000);
    resolveReady();
    await Promise.resolve();
    await Promise.resolve();

    expect(sendMetric).toHaveBeenCalledTimes(1);
    expect(dispatchSpy).toHaveBeenCalledTimes(1);
  });
});
