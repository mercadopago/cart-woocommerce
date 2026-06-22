const vm = require('vm');
const fs = require('fs');
const { resolveAlias } = require('../../helpers/path-resolver');

const CART_UPDATE_HELPER_PATH = resolveAlias('assets/js/blocks/helpers/cart-update.helper.js');

/**
 * Carrega o helper num contexto vm controlado.
 *
 * O arquivo usa `export { ... }` (ESM) e o projeto não tem transform Babel no Jest
 * (jest.config.js: `transform: {}`), então o vm não entende `export`. Removemos a
 * linha de export e devolvemos as funções via expressão final.
 *
 * Injetamos `setTimeout`/`Date` *já fakeados* (chamamos useFakeTimers antes de carregar)
 * e o `Date` do realm principal, para que o loop de espera de `waitForHandler`
 * (`Date.now() - start >= 15000`) avance junto com `jest.advanceTimersByTimeAsync`.
 * `sendMetric` é um mock fresco por teste, capturado no closure das funções.
 */
function loadCartUpdateHelper(context) {
  const source = fs
    .readFileSync(CART_UPDATE_HELPER_PATH, 'utf8')
    .replace(/^export\s*\{[^}]*\};?\s*$/m, '');

  const wrapped =
    source +
    '\n({ addDiscountAndCommission, handleCartTotalChange, removeDiscountAndCommission });';

  const script = new vm.Script(wrapped, { filename: CART_UPDATE_HELPER_PATH });

  return script.runInNewContext({
    window: context.window,
    document: global.document,
    console: global.console,
    setTimeout: global.setTimeout,
    clearTimeout: global.clearTimeout,
    Promise: global.Promise,
    Date: global.Date,
    sendMetric: context.sendMetric,
  });
}

describe('cart-update.helper', () => {
  const BRL = { minorUnit: 2 };

  let sendMetric;
  let windowMock;
  let cardForm;
  let helper;

  /**
   * Monta um `window` realista com os handlers que o checkout Blocks expõe,
   * todos prontos de imediato (caminho feliz). Cada teste pode sobrescrever
   * partes específicas antes de carregar o helper.
   */
  function buildWindow(overrides = {}) {
    cardForm = {
      formMounted: false,
      createLoadSpinner: jest.fn(),
      removeLoadSpinner: jest.fn(),
      initCardForm: jest.fn().mockResolvedValue(undefined),
      form: { unmount: jest.fn() },
    };

    return {
      mpSuperTokenPaymentMethods: { hideSuperTokenError: jest.fn() },
      mpCustomCheckoutHandler: { cardForm },
      mpSuperTokenTriggerHandler: { loadSuperToken: jest.fn().mockResolvedValue(undefined) },
      ...overrides,
    };
  }

  beforeEach(() => {
    jest.useFakeTimers();
    document.body.innerHTML = '';
    sendMetric = jest.fn();
    windowMock = buildWindow();
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
  });

  function load() {
    helper = loadCartUpdateHelper({ window: windowMock, sendMetric });
  }

  // -------------------------------------------------------------------------
  // addDiscountAndCommission
  // -------------------------------------------------------------------------
  describe('addDiscountAndCommission', () => {
    it('GIVEN a callback and a gateway WHEN called THEN forwards an "add" action payload and returns the callback result', () => {
      // GIVEN
      load();
      const callback = jest.fn().mockReturnValue('dispatched');

      // WHEN
      const result = helper.addDiscountAndCommission(callback, 'woo-mercado-pago-custom');

      // THEN
      expect(callback).toHaveBeenCalledWith({
        namespace: 'mercadopago_blocks_update_cart',
        data: { action: 'add', gateway: 'woo-mercado-pago-custom' },
      });
      expect(result).toBe('dispatched');
    });
  });

  // -------------------------------------------------------------------------
  // removeDiscountAndCommission
  // -------------------------------------------------------------------------
  describe('removeDiscountAndCommission', () => {
    it('GIVEN a callback and a gateway WHEN called THEN forwards a "remove" action payload and returns the callback result', () => {
      // GIVEN
      load();
      const callback = jest.fn().mockReturnValue('dispatched');

      // WHEN
      const result = helper.removeDiscountAndCommission(callback, 'woo-mercado-pago-custom');

      // THEN
      expect(callback).toHaveBeenCalledWith({
        namespace: 'mercadopago_blocks_update_cart',
        data: { action: 'remove', gateway: 'woo-mercado-pago-custom' },
      });
      expect(result).toBe('dispatched');
    });
  });

  // -------------------------------------------------------------------------
  // handleCartTotalChange — happy path
  // -------------------------------------------------------------------------
  describe('handleCartTotalChange — happy path', () => {
    it('GIVEN ready handlers WHEN the cart total changes THEN clears the error, reinitializes the card form and reloads SuperToken with the formatted amount', async () => {
      // GIVEN
      load();

      // WHEN
      await helper.handleCartTotalChange(1000, BRL);

      // THEN
      expect(windowMock.mpSuperTokenPaymentMethods.hideSuperTokenError).toHaveBeenCalledTimes(1);
      expect(cardForm.createLoadSpinner).toHaveBeenCalledTimes(1);
      expect(cardForm.initCardForm).toHaveBeenCalledWith('10');
      expect(windowMock.mpSuperTokenTriggerHandler.loadSuperToken).toHaveBeenCalledWith('10');
    });

    it('GIVEN the flow finishes WHEN it settles THEN removes the load spinner only after the configured delay', async () => {
      // GIVEN
      load();

      // WHEN
      await helper.handleCartTotalChange(1000, BRL);

      // THEN — spinner removal is intentionally deferred (REMOVE_LOAD_SPINNER_DELAY = 500ms)
      expect(cardForm.removeLoadSpinner).not.toHaveBeenCalled();
      await jest.advanceTimersByTimeAsync(500);
      expect(cardForm.removeLoadSpinner).toHaveBeenCalledTimes(1);
    });
  });

  // -------------------------------------------------------------------------
  // handleCartTotalChange — currency formatting & ratio
  // -------------------------------------------------------------------------
  describe('handleCartTotalChange — amount formatting', () => {
    it('GIVEN a currencyRatio greater than zero WHEN the cart total changes THEN multiplies the formatted amount by the ratio', async () => {
      // GIVEN
      load();

      // WHEN — 1000 minor units => "10.00", ratio 2 => "20"
      await helper.handleCartTotalChange(1000, BRL, 2);

      // THEN
      expect(cardForm.initCardForm).toHaveBeenCalledWith('20');
      expect(windowMock.mpSuperTokenTriggerHandler.loadSuperToken).toHaveBeenCalledWith('20');
    });

    it('GIVEN a non-positive currencyRatio WHEN the cart total changes THEN keeps the unscaled formatted amount', async () => {
      // GIVEN
      load();

      // WHEN — ratio 0 is ignored, so the amount stays as the formatted "10.00"
      await helper.handleCartTotalChange(1000, BRL, 0);

      // THEN
      expect(cardForm.initCardForm).toHaveBeenCalledWith('10.00');
    });

    it('GIVEN a zero-decimal currency WHEN the cart total changes THEN formats the amount with no minor units', async () => {
      // GIVEN
      load();

      // WHEN — minorUnit 0 (e.g. CLP): 5000 stays 5000
      await helper.handleCartTotalChange(5000, { minorUnit: 0 });

      // THEN
      expect(cardForm.initCardForm).toHaveBeenCalledWith('5000');
    });
  });

  // -------------------------------------------------------------------------
  // handleCartTotalChange — Ghost State: unmount must respect the real DOM
  // -------------------------------------------------------------------------
  describe('handleCartTotalChange — card form unmount logic', () => {
    it('GIVEN a mounted form and no submission in progress WHEN the cart total changes THEN unmounts the stale card form before reinitializing', async () => {
      // GIVEN
      windowMock = buildWindow();
      cardForm.formMounted = true; // no spinner in the DOM => not submitting
      load();

      // WHEN
      await helper.handleCartTotalChange(1000, BRL);

      // THEN
      expect(cardForm.form.unmount).toHaveBeenCalledTimes(1);
      expect(cardForm.initCardForm).toHaveBeenCalledTimes(1);
    });

    it('GIVEN a mounted form WHILE a submission is in progress WHEN the cart total changes THEN does NOT unmount the card form mid-submit', async () => {
      // GIVEN — the Blocks submission spinner is present in the DOM
      windowMock = buildWindow();
      cardForm.formMounted = true;
      document.body.innerHTML = '<div class="wc-block-components-spinner"></div>';
      load();

      // WHEN
      await helper.handleCartTotalChange(1000, BRL);

      // THEN — unmounting during submit would break tokenization
      expect(cardForm.form.unmount).not.toHaveBeenCalled();
    });

    it('GIVEN a form that is not mounted WHEN the cart total changes THEN does not attempt to unmount', async () => {
      // GIVEN
      load(); // formMounted defaults to false

      // WHEN
      await helper.handleCartTotalChange(1000, BRL);

      // THEN
      expect(cardForm.form.unmount).not.toHaveBeenCalled();
    });
  });

  // -------------------------------------------------------------------------
  // handleCartTotalChange — handler readiness (waitForHandler timeouts)
  // -------------------------------------------------------------------------
  describe('handleCartTotalChange — handler readiness', () => {
    it('GIVEN the custom checkout handler never loads WHEN 15s elapse THEN reports the not-ready metric and skips card form initialization', async () => {
      // GIVEN — handler stays undefined for the whole wait window
      windowMock = buildWindow({ mpCustomCheckoutHandler: undefined });
      load();

      // WHEN
      const pending = helper.handleCartTotalChange(1000, BRL);
      await jest.advanceTimersByTimeAsync(15000);
      await pending;

      // THEN
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_HANDLER_NOT_READY_ON_CART_UPDATE',
        expect.any(String),
        'mp_cart_total_change_error',
      );
      expect(cardForm.initCardForm).not.toHaveBeenCalled();
    });

    it('GIVEN the SuperToken trigger handler never loads WHEN 15s elapse THEN still initializes the card form and reports the SuperToken not-ready metric without throwing', async () => {
      // GIVEN — custom handler is ready, SuperToken trigger handler never appears
      windowMock = buildWindow({ mpSuperTokenTriggerHandler: undefined });
      load();

      // WHEN
      const pending = helper.handleCartTotalChange(1000, BRL);
      await jest.advanceTimersByTimeAsync(15000);
      await expect(pending).resolves.toBeUndefined();

      // THEN — card form was already reinitialized before the SuperToken wait
      expect(cardForm.initCardForm).toHaveBeenCalledWith('10');
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_SUPER_TOKEN_HANDLER_NOT_READY_ON_CART_UPDATE',
        expect.any(String),
        'mp_super_token_init_error',
      );
    });
  });

  // -------------------------------------------------------------------------
  // handleCartTotalChange — error cascade prevention
  // -------------------------------------------------------------------------
  describe('handleCartTotalChange — error handling', () => {
    it('GIVEN an invalid amount WHEN formatting throws THEN reports the update error metric instead of bubbling up', async () => {
      // GIVEN — a non-integer value makes formatCurrency throw
      load();

      // WHEN
      await expect(helper.handleCartTotalChange(10.5, BRL)).resolves.toBeUndefined();

      // THEN
      expect(sendMetric).toHaveBeenCalledWith(
        'error_to_update_cart_total',
        'Invalid input',
        'mp_cart_total_change_error',
      );
      expect(windowMock.mpSuperTokenTriggerHandler.loadSuperToken).not.toHaveBeenCalled();
    });

    it('GIVEN initCardForm rejects WHEN the cart total changes THEN reports the error and still removes the spinner in the finally block', async () => {
      // GIVEN
      windowMock = buildWindow();
      cardForm.initCardForm.mockRejectedValue(new Error('init boom'));
      load();

      // WHEN
      await helper.handleCartTotalChange(1000, BRL);

      // THEN — error is captured...
      expect(sendMetric).toHaveBeenCalledWith(
        'error_to_update_cart_total',
        'init boom',
        'mp_cart_total_change_error',
      );
      // ...and the deferred cleanup still runs so the checkout is never left spinning
      await jest.advanceTimersByTimeAsync(500);
      expect(cardForm.removeLoadSpinner).toHaveBeenCalledTimes(1);
    });
  });

  // -------------------------------------------------------------------------
  // handleCartTotalChange — defensive optional chaining (uncontrolled stores)
  // -------------------------------------------------------------------------
  describe('handleCartTotalChange — resilience', () => {
    it('GIVEN a cardForm without createLoadSpinner WHEN the cart total changes THEN does not throw and still initializes the card form', async () => {
      // GIVEN — some store states expose a partial cardForm
      windowMock = buildWindow();
      delete cardForm.createLoadSpinner;
      load();

      // WHEN / THEN
      await expect(helper.handleCartTotalChange(1000, BRL)).resolves.toBeUndefined();
      expect(cardForm.initCardForm).toHaveBeenCalledWith('10');
    });

    it('GIVEN SuperToken payment methods are absent WHEN the cart total changes THEN safely skips hideSuperTokenError and proceeds', async () => {
      // GIVEN
      windowMock = buildWindow({ mpSuperTokenPaymentMethods: undefined });
      load();

      // WHEN / THEN — optional chaining must keep the flow alive
      await expect(helper.handleCartTotalChange(1000, BRL)).resolves.toBeUndefined();
      expect(cardForm.initCardForm).toHaveBeenCalledWith('10');
    });
  });
});
