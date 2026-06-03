const { resolveAlias } = require('../helpers/path-resolver');
const { loadFile } = require('../helpers/load-file');

const DISPATCHER_PATH = resolveAlias('assets/js/checkouts/mp-checkout-error-dispatcher.js');

function loadCustomEventDispatcher(opts = {}) {
  return loadFile(DISPATCHER_PATH, 'MPCustomEventDispatcher', {
    jQuery: jest.fn(),
    CustomEvent: global.CustomEvent,
    MutationObserver: global.MutationObserver,
    Event: global.Event,
    fetch: opts.fetch || (() => ({ catch: () => {} })),
    setTimeout: (cb, ms) => global.setTimeout(cb, ms),
    clearTimeout: (id) => global.clearTimeout(id),
  });
}

function loadAllInOneContext() {
  // Returns the dispatcher AND the handlers in a SINGLE evaluation, so that the
  // handlers' calls to `MPCustomEventDispatcher.dispatchWhenReady(...)` resolve
  // to the SAME class instance we can spy on.
  const onHandlers = {};
  const jq = jest.fn(() => ({
    on: jest.fn((eventName, cb) => {
      onHandlers[eventName] = cb;
    }),
  }));
  const fileContent = require('fs').readFileSync(DISPATCHER_PATH, 'utf8');
  const vm = require('vm');
  const ctx = {
    window: global.window,
    document: global.document,
    console: global.console,
    jQuery: jq,
    CustomEvent: global.CustomEvent,
    MutationObserver: global.MutationObserver,
    Event: global.Event,
    fetch: global.fetch,
    setTimeout: global.setTimeout,
    clearTimeout: global.clearTimeout,
    Promise: global.Promise,
  };
  const exposed = fileContent
    + '\n({ MPCustomEventDispatcher, MPClassicCheckoutErrorHandler, MPBlocksCheckoutErrorHandler, MPOrderPayCheckoutErrorHandler, MPDispatchedErrorTracker });';
  return { ...new vm.Script(exposed).runInNewContext(ctx), onHandlers };
}

describe('MPCustomEventDispatcher.waitForMelidata()', () => {
  let MPCustomEventDispatcher;

  beforeEach(() => {
    MPCustomEventDispatcher = loadCustomEventDispatcher();
    delete window.melidata;
    delete window.melidataReady;
  });

  afterEach(() => {
    delete window.melidata;
    delete window.melidataReady;
    jest.restoreAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Branch 1: window.melidata já existe
  // ---------------------------------------------------------------------------
  test('TC-WFM-01: resolve imediatamente quando window.melidata está definido', async () => {
    window.melidata = { track: jest.fn() };

    await expect(MPCustomEventDispatcher.waitForMelidata()).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // Branch 2: melidataReady é uma Promise (thenable)
  // ---------------------------------------------------------------------------
  test('TC-WFM-02: encadeia .then(resolve) quando melidataReady é uma Promise', async () => {
    window.melidataReady = Promise.resolve();

    await expect(MPCustomEventDispatcher.waitForMelidata()).resolves.toBeUndefined();
  });

  test('TC-WFM-02b: resolve mesmo se melidataReady rejeitar (.catch absorve)', async () => {
    // .catch(resolve) chama resolve(error) — a Promise resolve com o Error como valor,
    // não rejeita. O comportamento importante é que o checkout não trava.
    window.melidataReady = Promise.reject(new Error('melidata failed'));

    await expect(MPCustomEventDispatcher.waitForMelidata()).resolves.toBeInstanceOf(Error);
  });

  // ---------------------------------------------------------------------------
  // Branch 3: melidataReady é truthy mas não é uma Promise
  // ---------------------------------------------------------------------------
  test('TC-WFM-03: resolve imediatamente quando melidataReady=true (truthy, não-thenable)', async () => {
    window.melidataReady = true;

    await expect(MPCustomEventDispatcher.waitForMelidata()).resolves.toBeUndefined();
  });

  test('TC-WFM-03b: resolve imediatamente quando melidataReady=1 (número truthy)', async () => {
    window.melidataReady = 1;

    await expect(MPCustomEventDispatcher.waitForMelidata()).resolves.toBeUndefined();
  });

  test('TC-WFM-03c: não tenta chamar .then() em melidataReady truthy não-thenable', async () => {
    window.melidataReady = true;

    const promise = MPCustomEventDispatcher.waitForMelidata();
    await expect(promise).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // Branch 4: document.readyState === 'complete' (melidata ausente)
  // ---------------------------------------------------------------------------
  test('TC-WFM-04: resolve imediatamente quando readyState=complete e melidata ausente', async () => {
    Object.defineProperty(document, 'readyState', { value: 'complete', configurable: true });

    await expect(MPCustomEventDispatcher.waitForMelidata()).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // Branch 5: aguarda evento load (nenhuma condição anterior satisfeita)
  // ---------------------------------------------------------------------------
  test('TC-WFM-05: resolve ao disparar window load quando melidata ainda não existe', async () => {
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });

    const promise = MPCustomEventDispatcher.waitForMelidata();

    window.dispatchEvent(new Event('load'));

    await expect(promise).resolves.toBeUndefined();
  });

  test('TC-WFM-05b: ao disparar load com melidataReady Promise pendente, encadeia .then(resolve)', async () => {
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });

    const promise = MPCustomEventDispatcher.waitForMelidata();

    window.melidataReady = Promise.resolve();
    window.dispatchEvent(new Event('load'));

    await expect(promise).resolves.toBeUndefined();
  });
});

describe('MPCustomEventDispatcher.dispatchWhenReady()', () => {
  let MPCustomEventDispatcher;
  let dispatchSpy;
  let fetchMock;

  beforeEach(() => {
    delete window.melidata;
    delete window.melidataReady;
    fetchMock = jest.fn(() => ({ catch: () => {} }));
    MPCustomEventDispatcher = loadCustomEventDispatcher({ fetch: fetchMock });
    dispatchSpy = jest.spyOn(document, 'dispatchEvent').mockImplementation(() => true);
  });

  afterEach(() => {
    delete window.melidata;
    delete window.melidataReady;
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Happy path: melidata already loaded
  // ---------------------------------------------------------------------------
  test('TC-DWR-01: dispatches via microtask when window.melidata is already present', async () => {
    window.melidata = { track: jest.fn() };

    MPCustomEventDispatcher.dispatchWhenReady('mp_checkout_error', { message: 'x' });

    // Resolve all microtasks
    await Promise.resolve();
    await Promise.resolve();

    expect(dispatchSpy).toHaveBeenCalledTimes(1);
    const event = dispatchSpy.mock.calls[0][0];
    expect(event.type).toBe('mp_checkout_error');
    expect(event.detail).toEqual({ message: 'x' });
  });

  // ---------------------------------------------------------------------------
  // Timeout path: melidata never loads → sendTimeoutMetric fires AND dispatch still occurs
  // ---------------------------------------------------------------------------
  test('TC-DWR-02: after 5000ms timeout, sendTimeoutMetric is called and dispatch still occurs', async () => {
    jest.useFakeTimers();
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });

    MPCustomEventDispatcher.dispatchWhenReady('mp_checkout_error', { message: 'timeout-case' });

    // Advance past the timeout
    jest.advanceTimersByTime(5000);

    // Drain microtasks so the Promise.race().then() resolves
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const fetchCall = fetchMock.mock.calls[0];
    expect(fetchCall[0]).toMatch(/\/big\/mp_melidata_load_timeout$/);
    expect(fetchCall[1].method).toBe('POST');
    expect(fetchCall[1].keepalive).toBe(true);
    const body = JSON.parse(fetchCall[1].body);
    expect(body.value).toBe('true');
    expect(body.message).toBe('mp_checkout_error');

    expect(dispatchSpy).toHaveBeenCalledTimes(1);
    expect(dispatchSpy.mock.calls[0][0].type).toBe('mp_checkout_error');
  });

  // ---------------------------------------------------------------------------
  // TC-DWR-01b: timeout is cancelled when melidata loads before 5s — no false-positive metric
  // ---------------------------------------------------------------------------
  test('TC-DWR-01b: sendTimeoutMetric is NOT called when melidata loads before the timeout', async () => {
    jest.useFakeTimers();
    window.melidata = { track: jest.fn() };

    MPCustomEventDispatcher.dispatchWhenReady('mp_checkout_error', { message: 'fast' });

    await Promise.resolve();
    await Promise.resolve();

    // Advance past the timeout window — metric must NOT fire
    jest.advanceTimersByTime(5000);

    expect(fetchMock).not.toHaveBeenCalled();
    expect(dispatchSpy).toHaveBeenCalledTimes(1);
  });

  // ---------------------------------------------------------------------------
  // sendTimeoutMetric is best-effort: a thrown fetch must not propagate
  // ---------------------------------------------------------------------------
  test('TC-DWR-03: sendTimeoutMetric swallows synchronous fetch errors', () => {
    // Reload the dispatcher with a fetch that throws synchronously
    const throwingFetch = jest.fn(() => { throw new Error('network adapter unavailable'); });
    const Dispatcher = loadCustomEventDispatcher({ fetch: throwingFetch });

    expect(() => Dispatcher.sendTimeoutMetric('mp_checkout_error')).not.toThrow();
    expect(throwingFetch).toHaveBeenCalledTimes(1);
  });

  test('TC-DWR-04: sendTimeoutMetric swallows rejected fetch promises', () => {
    // Reload the dispatcher with a fetch that returns a rejected promise
    const rejectingFetch = jest.fn(() => Promise.reject(new Error('blocked by adblocker')));
    const Dispatcher = loadCustomEventDispatcher({ fetch: rejectingFetch });

    expect(() => Dispatcher.sendTimeoutMetric('mp_checkout_error')).not.toThrow();
    expect(rejectingFetch).toHaveBeenCalledTimes(1);
  });
});

describe('Handler integration with dispatchWhenReady', () => {
  let dispatchSpy;

  beforeEach(() => {
    delete window.melidata;
    delete window.melidataReady;
    dispatchSpy = jest.spyOn(document, 'dispatchEvent').mockImplementation(() => true);
    jest.spyOn(document, 'querySelector').mockImplementation((sel) => {
      // Make MPClassicCheckoutErrorHandler.handle() proceed past its form guard
      if (sel === 'form[name=checkout]') return { tagName: 'FORM' };
      if (sel === '.woocommerce-error') return { textContent: 'card declined', querySelector: () => null };
      return null;
    });
  });

  afterEach(() => {
    delete window.melidata;
    delete window.melidataReady;
    jest.restoreAllMocks();
  });

  test('TC-INT-01: MPClassicCheckoutErrorHandler.handle() dispatches via dispatchWhenReady', async () => {
    window.melidata = { track: jest.fn() }; // ready immediately → microtask path

    const { MPClassicCheckoutErrorHandler, onHandlers } = loadAllInOneContext();
    const handler = new MPClassicCheckoutErrorHandler();
    handler.handle();

    // The handler registered a jQuery 'checkout_error' listener — fire it.
    expect(typeof onHandlers.checkout_error).toBe('function');
    onHandlers.checkout_error(null, '<li>card declined</li>');

    await Promise.resolve();
    await Promise.resolve();

    expect(dispatchSpy).toHaveBeenCalled();
    const event = dispatchSpy.mock.calls.find(c => c[0].type === 'mp_checkout_error');
    expect(event).toBeDefined();
    expect(event[0].detail.message).toBe('card declined');
    expect(['post_submit_woocommerce', 'post_submit_mercado_pago']).toContain(event[0].detail.errorOrigin);
  });

  test('TC-INT-02: MPBlocksCheckoutErrorHandler.handle() dispatches via dispatchWhenReady', async () => {
    window.melidata = { track: jest.fn() }; // ready immediately → microtask path

    const { MPBlocksCheckoutErrorHandler } = loadAllInOneContext();
    const handler = new MPBlocksCheckoutErrorHandler();

    handler.handle({
      processingResponse: {
        paymentDetails: { message: 'card_declined' },
      },
    });

    await Promise.resolve();
    await Promise.resolve();

    const event = dispatchSpy.mock.calls.find(c => c[0]?.type === 'mp_checkout_error');
    expect(event).toBeDefined();
    expect(event[0].detail.message).toBe('card_declined');
    expect(event[0].detail.errorOrigin).toBe('post_submit_mercado_pago');
  });

  // ---------------------------------------------------------------------------
  // TC-INT-03: MPOrderPayCheckoutErrorHandler — async path + race window
  // ---------------------------------------------------------------------------
  test('TC-INT-03: MPOrderPayCheckoutErrorHandler.handlePageLoadErrors() dispatches via dispatchWhenReady', async () => {
    window.melidata = { track: jest.fn() }; // ready immediately → microtask path

    const { MPOrderPayCheckoutErrorHandler } = loadAllInOneContext();
    const handler = new MPOrderPayCheckoutErrorHandler();

    jest.spyOn(document, 'querySelectorAll').mockImplementation((sel) => {
      if (sel === '.woocommerce-notices-wrapper .woocommerce-error') {
        return [{ textContent: 'payment_declined', querySelector: () => null }];
      }
      return [];
    });

    handler.handlePageLoadErrors();

    await Promise.resolve();
    await Promise.resolve();

    const event = dispatchSpy.mock.calls.find(c => c[0]?.type === 'mp_checkout_error');
    expect(event).toBeDefined();
    expect(event[0].detail.message).toBe('payment_declined');
    expect(event[0].detail.errorOrigin).toBe('post_submit_woocommerce');
  });

  test('TC-INT-03b: reset() between track() and async dispatch does not suppress already-queued event', async () => {
    // Melidata not yet loaded: track() runs sync but dispatch is pending melidata → exposes race window
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });

    const { MPOrderPayCheckoutErrorHandler, MPDispatchedErrorTracker } = loadAllInOneContext();
    const handler = new MPOrderPayCheckoutErrorHandler();

    jest.spyOn(document, 'querySelectorAll').mockImplementation((sel) => {
      if (sel === '.woocommerce-notices-wrapper .woocommerce-error') {
        return [{ textContent: 'payment_declined', querySelector: () => null }];
      }
      return [];
    });

    // track() marks the key; dispatchWhenReady is queued but awaiting melidata
    handler.handlePageLoadErrors();

    // Simulate form submit clearing the dedup tracker before melidata loads
    MPDispatchedErrorTracker.reset();

    // Melidata loads — unblocks the already-queued dispatchWhenReady
    window.dispatchEvent(new Event('load'));

    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();

    // The already-queued dispatch fires: it passed track() before reset()
    const event = dispatchSpy.mock.calls.find(c => c[0]?.type === 'mp_checkout_error');
    expect(event).toBeDefined();
    expect(event[0].detail.message).toBe('payment_declined');
    expect(event[0].detail.errorOrigin).toBe('post_submit_woocommerce');
  });
});
