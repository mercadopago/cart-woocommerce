const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');

describe.each(['mobile-checkout-classic-observer', 'mobile-checkout-classic-observer.min'])(
  'Custom Classic mobile fragment recovery (%s)', (observerFile) => {
  const containerIds = [
    'form-checkout__cardNumber-container',
    'form-checkout__expirationDate-container',
    'form-checkout__securityCode-container',
  ];
  const flushMicrotasks = async () => {
    for (let i = 0; i < 20; i++) await Promise.resolve();
  };
  let observer;
  let cardForm;
  let eventHandler;
  let handlers;
  let finishSuperTokenLoad;
  let update;
  let unmount;

  function createPaymentFragment() {
    const payment = document.createElement('div');
    payment.id = 'payment';
    const radio = document.createElement('input');
    radio.type = 'radio';
    radio.id = 'payment_method_woo-mercado-pago-custom';
    radio.checked = true;
    payment.append(radio);
    const custom = document.createElement('div');
    custom.className = 'mp-checkout-custom-container';
    containerIds.forEach((id) => {
      const container = document.createElement('div');
      container.id = id;
      custom.append(container);
    });
    payment.append(custom);
    const amount = document.createElement('input');
    amount.id = 'mp-amount';
    amount.value = '100.00';
    payment.append(amount);
    return payment;
  }

  beforeEach(async () => {
    jest.useFakeTimers();
    document.body.replaceChildren();
    const form = document.createElement('form');
    form.className = 'woocommerce-checkout';
    form.append(createPaymentFragment());
    document.body.append(form);
    handlers = {};
    const jQueryMock = jest.fn(() => ({
      on: (event, handler) => { handlers[event] = handler; },
      off: (event) => { delete handlers[event]; },
    }));
    const context = {
      jQuery: jQueryMock,
      sendMetric: jest.fn(),
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
      setInterval: global.setInterval,
      clearInterval: global.clearInterval,
      navigator: { userAgent: 'Mozilla/5.0 (Linux; Android 15)' },
      wc_mercadopago_custom_checkout_params: { placeholders: {} },
      wc_mercadopago_custom_card_form_params: { security_code_placeholder_text_3_digits: 'CVV' },
      CheckoutPage: {
        verifyCardholderNameOnFocus: jest.fn(),
        clearDocumentLabelErrorOnInput: jest.fn(),
        setSecureFieldInstructions: jest.fn(),
        clearInputs: jest.fn(),
      },
    };
    const loadEntity = (file, name) => loadFile(
      resolveAlias(`assets/js/checkouts/custom/entities/${file}.js`), name, context
    );
    const MPCardForm = loadEntity('card-form', 'MPCardForm');
    const Observer = loadEntity(observerFile, 'MobileCheckoutClassicObserver');
    context.MobileCheckoutClassicObserver = Observer;
    const MPEventHandler = loadEntity('event-handler', 'MPEventHandler');
    unmount = jest.fn();
    window.mpSdkInstance = {
      cardForm: jest.fn(({ callbacks }) => {
        // The SDK owns these frames and callbacks; replacing a WooCommerce fragment
        // disconnects the old nodes without calling the SDK's unmount method.
        const frames = containerIds.map((id) => {
          const iframe = document.createElement('iframe');
          document.getElementById(id).append(iframe);
          return iframe;
        });
        callbacks.onFormMounted();
        Promise.resolve().then(() => callbacks.onReady({}));
        return {
          unmount: () => {
            unmount();
            frames.forEach((iframe) => iframe.remove());
            callbacks.onFormUnmounted();
          },
        };
      }),
    };
    cardForm = new MPCardForm();
    jest.spyOn(cardForm, 'dispatchCheckoutAmountEvent').mockImplementation(() => {});
    jest.spyOn(cardForm, 'createLoadSpinner').mockImplementation(() => {});
    jest.spyOn(cardForm, 'removeLoadSpinner').mockImplementation(() => {});
    await cardForm.initCardForm();
    await flushMicrotasks();
    eventHandler = new MPEventHandler(cardForm, {});
    const superTokenLoad = new Promise((resolve) => { finishSuperTokenLoad = resolve; });
    jest.spyOn(eventHandler, 'getSuperTokenDeps').mockReturnValue({
      superTokenTriggerHandler: { loadSuperToken: jest.fn(() => superTokenLoad) },
    });
    update = jest.spyOn(eventHandler, 'handleUpdatedCheckout');
    observer = new Observer(
      cardForm,
      eventHandler.isCheckoutCustomPaymentMethodSelected.bind(eventHandler),
      eventHandler.handleUpdatedCheckout.bind(eventHandler)
    );
  });

  afterEach(() => {
    observer?.destroy();
    delete window.mpSdkInstance;
    jest.clearAllTimers();
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  it.each([true, false])(
    'recovers detached frames after an overlapping update (Custom remains selected: %s)',
    async (customSelected) => {
      handlers.updated_checkout();
      await flushMicrotasks();
      expect(update).toHaveBeenCalledTimes(1);
      expect(observer._updateInFlight).toBe(true);

      const oldFragment = document.getElementById('payment');
      const oldFrames = [...oldFragment.querySelectorAll('iframe')];
      oldFragment.replaceWith(createPaymentFragment());
      document.getElementById('payment_method_woo-mercado-pago-custom').checked = customSelected;
      handlers.updated_checkout();
      await flushMicrotasks();

      expect(oldFrames.every((frame) => !frame.isConnected)).toBe(true);
      expect(cardForm.formMounted).toBe(true);
      expect(cardForm.fields).toBeTruthy();
      expect(document.querySelectorAll('iframe')).toHaveLength(0);
      expect(unmount).not.toHaveBeenCalled();

      finishSuperTokenLoad();
      await flushMicrotasks();

      expect(update).toHaveBeenCalledTimes(2);
      expect(observer._updateInFlight).toBe(false);
      expect(window.mpSdkInstance.cardForm).toHaveBeenCalledTimes(customSelected ? 2 : 1);
      expect(unmount).toHaveBeenCalledTimes(customSelected ? 1 : 0);
      containerIds.forEach((id) => {
        expect(document.getElementById(id).querySelectorAll('iframe')).toHaveLength(customSelected ? 1 : 0);
      });
    }
  );
});
