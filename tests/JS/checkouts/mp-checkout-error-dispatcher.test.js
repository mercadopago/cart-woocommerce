const { resolveAlias } = require('../helpers/path-resolver');
const { loadFile } = require('../helpers/load-file');

const DISPATCHER_PATH = resolveAlias('assets/js/checkouts/mp-checkout-error-dispatcher.js');

function loadOrderPayHandler() {
  return loadFile(DISPATCHER_PATH, 'MPOrderPayCheckoutErrorHandler', {
    jQuery: jest.fn(),
    CustomEvent: global.CustomEvent,
    MutationObserver: global.MutationObserver,
    Event: global.Event,
  });
}

describe('MPOrderPayCheckoutErrorHandler — waitForMelidata()', () => {
  let handler;

  beforeEach(() => {
    handler = new (loadOrderPayHandler())();
    delete window.melidata;
    delete window.melidataReady;
    jest.spyOn(document, 'querySelector').mockReturnValue(null);
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.melidata;
    delete window.melidataReady;
  });

  // ---------------------------------------------------------------------------
  // Branch 1: window.melidata já existe
  // ---------------------------------------------------------------------------
  test('TC-WFM-01: resolve imediatamente quando window.melidata está definido', async () => {
    window.melidata = { track: jest.fn() };

    await expect(handler.waitForMelidata()).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // Branch 2: melidataReady é uma Promise (thenable)
  // ---------------------------------------------------------------------------
  test('TC-WFM-02: encadeia .then(resolve) quando melidataReady é uma Promise', async () => {
    window.melidataReady = Promise.resolve();

    await expect(handler.waitForMelidata()).resolves.toBeUndefined();
  });

  test('TC-WFM-02b: resolve mesmo se melidataReady rejeitar (.catch absorve)', async () => {
    // .catch(resolve) chama resolve(error) — a Promise resolve com o Error como valor,
    // não rejeita. O comportamento importante é que o checkout não trava.
    window.melidataReady = Promise.reject(new Error('melidata failed'));

    await expect(handler.waitForMelidata()).resolves.toBeInstanceOf(Error);
  });

  // ---------------------------------------------------------------------------
  // Branch 3: melidataReady é truthy mas não é uma Promise
  // Cenário real: script de terceiro redefine window.melidataReady = true
  // para sinalizar "pronto" sem usar Promise
  // ---------------------------------------------------------------------------
  test('TC-WFM-03: resolve imediatamente quando melidataReady=true (truthy, não-thenable)', async () => {
    window.melidataReady = true;

    await expect(handler.waitForMelidata()).resolves.toBeUndefined();
  });

  test('TC-WFM-03b: resolve imediatamente quando melidataReady=1 (número truthy)', async () => {
    window.melidataReady = 1;

    await expect(handler.waitForMelidata()).resolves.toBeUndefined();
  });

  test('TC-WFM-03c: não tenta chamar .then() em melidataReady truthy não-thenable', async () => {
    window.melidataReady = true;

    // Se o código erroneamente tentasse window.melidataReady.then(resolve),
    // lançaria TypeError — a Promise retornada nunca resolveria.
    // O fato de a Promise resolver prova que o branch truthy-não-thenable
    // chama resolve() diretamente sem tocar em .then().
    const promise = handler.waitForMelidata();
    await expect(promise).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // Branch 4: document.readyState === 'complete' (melidata ausente)
  // ---------------------------------------------------------------------------
  test('TC-WFM-04: resolve imediatamente quando readyState=complete e melidata ausente', async () => {
    Object.defineProperty(document, 'readyState', { value: 'complete', configurable: true });

    await expect(handler.waitForMelidata()).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // Branch 5: aguarda evento load (nenhuma condição anterior satisfeita)
  // ---------------------------------------------------------------------------
  test('TC-WFM-05: resolve ao disparar window load quando melidata ainda não existe', async () => {
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });

    const promise = handler.waitForMelidata();

    window.dispatchEvent(new Event('load'));

    await expect(promise).resolves.toBeUndefined();
  });

  test('TC-WFM-05b: ao disparar load com melidataReady Promise pendente, encadeia .then(resolve)', async () => {
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });

    const promise = handler.waitForMelidata();

    window.melidataReady = Promise.resolve();
    window.dispatchEvent(new Event('load'));

    await expect(promise).resolves.toBeUndefined();
  });
});
