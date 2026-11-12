/**
 * Unit tests for the Pix Blocks source (assets/js/blocks/pix.block.js).
 *
 * pix-block-lifecycle.test.js runs the built artifact (build/pix.block.js). This suite loads the
 * source through the Blocks babel-jest transform so the Smart Pix lifecycle and the checkout
 * observers are measured against assets/js/blocks/. WordPress/WooCommerce packages are not
 * installed, so they are replaced by virtual modules backed by the real React renderer.
 */
const React = require('react');
const { createRoot } = require('react-dom/client');
const { act } = require('react');

const PAYMENT_METHOD_NAME = 'woo-mercado-pago-pix';
const CART_UPDATE_NAMESPACE = 'mercadopago_blocks_update_cart';

const mockSettings = {
  title: 'Pix',
  supports: ['products'],
  params: {
    amount: 10,
    currencyRatio: 2,
    icon: 'pix.png',
    fee_title: '(fee)',
    message_error_amount: 'Invalid amount',
    test_mode: false,
    test_mode_title: 'Test mode',
    test_mode_description: 'Use test credentials',
    pix_template_title: 'Pay with Pix',
    smart_pix_mp_icon_src: 'mp.svg',
    smart_pix_selected_account_text: 'Selected account',
  },
};
const mockRegisterPaymentMethod = jest.fn();
const mockExtensionCartUpdate = jest.fn();

jest.mock('@wordpress/element', () => require('react'), { virtual: true });
jest.mock('@wordpress/html-entities', () => ({ decodeEntities: (value) => value }), { virtual: true });
jest.mock('@woocommerce/blocks-registry', () => ({
  registerPaymentMethod: (method) => mockRegisterPaymentMethod(method),
}), { virtual: true });
jest.mock('@woocommerce/blocks-checkout', () => ({
  extensionCartUpdate: (...args) => mockExtensionCartUpdate(...args),
}), { virtual: true });
jest.mock('@woocommerce/settings', () => ({
  getSetting: (name, fallback) => (name === 'woo-mercado-pago-pix_data' ? mockSettings : fallback),
}), { virtual: true });

let paymentMethod;

beforeAll(() => {
  // The Blocks transform uses the classic JSX runtime (React.createElement).
  global.React = React;
  global.IS_REACT_ACT_ENVIRONMENT = true;
  require('../../../assets/js/blocks/pix.block.js');
  paymentMethod = mockRegisterPaymentMethod.mock.calls[0][0];
});

afterAll(() => {
  delete global.React;
  delete global.IS_REACT_ACT_ENVIRONMENT;
});

beforeEach(() => {
  mockExtensionCartUpdate.mockClear();
  window.sendMetric = jest.fn();
  window.mpHiddenInputDataFromBlocksCheckout = { conventional_pix: '1' };
});

afterEach(() => {
  delete window.sendMetric;
  delete window.mpSmartPixBlocksLifecycle;
  delete window.mpHiddenInputDataFromBlocksCheckout;
  delete window.MPCheckoutErrorDispatcher;
  document.body.innerHTML = '';
});

function buildProps(overrides = {}) {
  const unsubscribers = { payment: [], success: [], fail: [] };
  const track = (bucket) => jest.fn(() => {
    const unsubscribe = jest.fn();
    unsubscribers[bucket].push(unsubscribe);
    return unsubscribe;
  });

  return {
    unsubscribers,
    props: {
      billing: { cartTotal: { value: 1234 }, currency: { minorUnit: 2 } },
      eventRegistration: {
        onPaymentSetup: track('payment'),
        onCheckoutSuccess: track('success'),
        onCheckoutFail: track('fail'),
      },
      emitResponse: {
        responseTypes: { SUCCESS: 'success', ERROR: 'error', FAIL: 'fail' },
        noticeContexts: { CHECKOUT: 'checkout', PAYMENTS: 'payments' },
      },
      ...overrides,
    },
  };
}

function renderPix(overrides = {}) {
  const container = document.createElement('div');
  document.body.appendChild(container);
  const root = createRoot(container);
  const { props, unsubscribers } = buildProps(overrides);
  act(() => root.render(React.cloneElement(paymentMethod.content, props)));

  return {
    container,
    props,
    unsubscribers,
    onPaymentSetup: props.eventRegistration.onPaymentSetup,
    onSuccess: () => props.eventRegistration.onCheckoutSuccess.mock.calls[0][0],
    onFail: () => props.eventRegistration.onCheckoutFail.mock.calls[0][0],
    setCartTotal: (value) => act(() => root.render(React.cloneElement(paymentMethod.content, {
      ...props,
      billing: { ...props.billing, cartTotal: { value } },
    }))),
    unmount: () => act(() => root.unmount()),
  };
}

function validLifecycle(overrides = {}) {
  return {
    mount: jest.fn(),
    refresh: jest.fn(),
    destroy: jest.fn(),
    handleCheckoutFailure: jest.fn(() => null),
    ...overrides,
  };
}

const flushPromises = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('Pix Blocks payment method registration', () => {
  it('given the Pix settings, when the module loads, then it registers the Pix payment method', () => {
    expect(mockRegisterPaymentMethod).toHaveBeenCalledTimes(1);
    expect(paymentMethod.name).toBe(PAYMENT_METHOD_NAME);
    expect(paymentMethod.ariaLabel).toBe('Pix');
    expect(paymentMethod.canMakePayment()).toBe(true);
    expect(paymentMethod.supports).toEqual({ features: ['products'] });
  });

  it('given the Pix settings, when the label renders, then it shows the title, fee and icon', () => {
    const container = document.createElement('div');
    const root = createRoot(container);
    act(() => root.render(paymentMethod.label));

    expect(container.querySelector('span').textContent).toBe('Pix (fee)');
    expect(container.querySelector('img').getAttribute('src')).toBe('pix.png');
    act(() => root.unmount());
  });

  it('given a valid amount, when the content renders, then it exposes the hidden Smart Pix root', () => {
    const view = renderPix();
    const smartPixRoot = view.container.querySelector('#mp-smart-pix-blocks-root');

    expect(view.container.querySelector('pix-template').getAttribute('title')).toBe('Pay with Pix');
    expect(view.container.querySelector('.mp-checkout-pix-terms-and-conditions')).not.toBeNull();
    expect(view.container.querySelector('test-mode')).toBeNull();
    expect(smartPixRoot.hidden).toBe(true);
    expect(smartPixRoot.dataset.smartPixSurface).toBe('blocks');
    expect(smartPixRoot.dataset.smartPixMpIconSrc).toBe('mp.svg');
    expect(smartPixRoot.dataset.smartPixSelectedAccountCopy).toBe('Selected account');
    view.unmount();
  });

  it('given test mode, when the content renders, then it shows the test mode notice', () => {
    mockSettings.params.test_mode = true;
    try {
      const view = renderPix();
      expect(view.container.querySelector('test-mode').getAttribute('title')).toBe('Test mode');
      view.unmount();
    } finally {
      mockSettings.params.test_mode = false;
    }
  });

  it('given no amount, when the content renders, then it shows only the amount error', () => {
    mockSettings.params.amount = null;
    try {
      const view = renderPix();
      expect(view.container.querySelector('.alert-message').textContent).toBe('Invalid amount');
      expect(view.container.querySelector('#mp-smart-pix-blocks-root')).toBeNull();
      view.unmount();
    } finally {
      mockSettings.params.amount = 10;
    }
  });
});

describe('Pix Blocks conventional payment setup', () => {
  it('given no Smart Pix lifecycle, when the block mounts, then it registers one setup observer with the hidden input data', () => {
    const view = renderPix();

    expect(view.onPaymentSetup).toHaveBeenCalledTimes(1);
    const response = view.onPaymentSetup.mock.calls[0][0]();
    expect(response).toEqual({
      type: 'success',
      meta: { paymentMethodData: { conventional_pix: '1' } },
    });
    expect(response.meta.paymentMethodData).not.toBe(window.mpHiddenInputDataFromBlocksCheckout);
    expect(mockExtensionCartUpdate).toHaveBeenCalledWith({
      namespace: CART_UPDATE_NAMESPACE,
      data: { action: 'add', gateway: PAYMENT_METHOD_NAME },
    });
    view.unmount();
  });

  it('given the conventional observer, when the block unmounts, then it unsubscribes and removes the discount', () => {
    const view = renderPix();
    view.unmount();

    expect(view.unsubscribers.payment[0]).toHaveBeenCalledTimes(1);
    expect(view.unsubscribers.success[0]).toHaveBeenCalledTimes(1);
    expect(view.unsubscribers.fail[0]).toHaveBeenCalledTimes(1);
    expect(mockExtensionCartUpdate).toHaveBeenLastCalledWith({
      namespace: CART_UPDATE_NAMESPACE,
      data: { action: 'remove', gateway: PAYMENT_METHOD_NAME },
    });
  });
});

describe('Pix Blocks Smart Pix lifecycle', () => {
  it('given a valid lifecycle, when the block mounts, then it mounts Smart Pix without a conventional observer', () => {
    const lifecycle = validLifecycle();
    const create = jest.fn(() => lifecycle);
    window.mpSmartPixBlocksLifecycle = { create };
    const view = renderPix();

    expect(create).toHaveBeenCalledTimes(1);
    expect(lifecycle.mount).toHaveBeenCalledTimes(1);
    expect(view.onPaymentSetup).not.toHaveBeenCalled();

    const options = create.mock.calls[0][0];
    expect(options.onPaymentSetup).toBe(view.onPaymentSetup);
    expect(options.emitResponse).toEqual({
      responseTypes: { SUCCESS: 'success', ERROR: 'error' },
      noticeContexts: { CHECKOUT: 'checkout' },
    });
    expect(options.getConventionalPaymentMethodData()).toEqual({ conventional_pix: '1' });
    expect(options.getCartAmount()).toBe('24.68');
    view.unmount();
  });

  it.each([
    [5000, '100.00'],
    [5025, '100.50'],
    [5013, '100.26'],
  ])('given a cart total of %i cents, when the real Super Token authenticator builds eligibility, then it calls the SDK with %s', async (cartTotal, amount) => {
    const { SuperTokenAuthenticator } = require('@super-token/adapters/runtime/SuperTokenAuthenticator');
    const create = jest.fn(() => validLifecycle());
    window.mpSmartPixBlocksLifecycle = { create };
    const view = renderPix();
    view.setCartTotal(cartTotal);
    const sdk = { authenticator: jest.fn().mockResolvedValue({ getSimplifiedAuth: jest.fn() }) };
    const metrics = { sendMetric: jest.fn(), errorToBuildAuthenticator: jest.fn() };
    const authenticator = new SuperTokenAuthenticator(sdk, {}, metrics, 'BP1', 'MLB');

    const built = await authenticator.buildAuthenticator(create.mock.calls[0][0].getCartAmount(), 'buyer@example.com');

    expect(built).not.toBeNull();
    expect(sdk.authenticator).toHaveBeenCalledWith(amount, 'buyer@example.com', expect.any(Object));
    expect(metrics.sendMetric).not.toHaveBeenCalledWith('super_token_skipped_invalid_amount', 'true', '');
    view.unmount();
  });

  it('given a mounted lifecycle, when the cart total changes, then it refreshes Smart Pix once per new total', () => {
    const lifecycle = validLifecycle();
    const create = jest.fn(() => lifecycle);
    window.mpSmartPixBlocksLifecycle = { create };
    const view = renderPix();

    view.setCartTotal(1234);
    expect(lifecycle.refresh).not.toHaveBeenCalled();

    view.setCartTotal(5000);
    expect(lifecycle.refresh).toHaveBeenCalledTimes(1);
    expect(create.mock.calls[0][0].getCartAmount()).toBe('100.00');
    expect(create).toHaveBeenCalledTimes(1);
    expect(lifecycle.mount).toHaveBeenCalledTimes(1);
    view.unmount();
  });

  it('given a refresh that rejects or throws, when the cart total changes, then the block keeps working', async () => {
    const lifecycle = validLifecycle({
      refresh: jest.fn()
        .mockImplementationOnce(() => Promise.reject(new Error('cdn async failure')))
        .mockImplementationOnce(() => { throw new Error('cdn sync failure'); }),
    });
    window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
    const view = renderPix();

    expect(() => view.setCartTotal(2000)).not.toThrow();
    await flushPromises();
    expect(() => view.setCartTotal(3000)).not.toThrow();

    expect(lifecycle.refresh).toHaveBeenCalledTimes(2);
    expect(lifecycle.destroy).not.toHaveBeenCalled();
    view.unmount();
  });

  it('given no lifecycle, when the cart total changes, then the block keeps rendering', () => {
    const view = renderPix();

    expect(() => view.setCartTotal(2000)).not.toThrow();
    view.unmount();
  });

  it('given a mounted lifecycle, when the block unmounts, then it destroys Smart Pix and removes the discount', () => {
    const lifecycle = validLifecycle();
    window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
    const view = renderPix();
    view.unmount();

    expect(lifecycle.destroy).toHaveBeenCalledTimes(1);
    expect(mockExtensionCartUpdate).toHaveBeenLastCalledWith({
      namespace: CART_UPDATE_NAMESPACE,
      data: { action: 'remove', gateway: PAYMENT_METHOD_NAME },
    });
  });

  it('given a destroy that throws, when the block unmounts, then the teardown does not break', () => {
    const lifecycle = validLifecycle({ destroy: jest.fn(() => { throw new Error('cdn destroy failure'); }) });
    window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
    const view = renderPix();

    expect(() => view.unmount()).not.toThrow();
    expect(lifecycle.destroy).toHaveBeenCalledTimes(1);
  });

  it('given an invalid lifecycle, when the block mounts, then it destroys it, reports the error and falls back to conventional Pix', () => {
    const lifecycle = { mount: jest.fn(), destroy: jest.fn() };
    window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
    const view = renderPix();

    expect(lifecycle.mount).not.toHaveBeenCalled();
    expect(lifecycle.destroy).toHaveBeenCalledTimes(1);
    expect(window.sendMetric).toHaveBeenCalledWith(
      'MP_PIX_BLOCKS_LIFECYCLE_CREATION_ERROR',
      'lifecycle_creation_failed',
      'mp_pix_blocks_init_error',
    );
    expect(view.onPaymentSetup).toHaveBeenCalledTimes(1);
    view.unmount();
    expect(lifecycle.destroy).toHaveBeenCalledTimes(1);
    expect(view.unsubscribers.payment[0]).toHaveBeenCalledTimes(1);
  });

  it('given create throws and metrics fail, when the block mounts, then conventional Pix is still registered', () => {
    window.mpSmartPixBlocksLifecycle = { create: jest.fn(() => { throw new Error('cdn create failure'); }) };
    window.sendMetric = jest.fn(() => { throw new Error('metrics down'); });
    const view = renderPix();

    expect(window.sendMetric).toHaveBeenCalledTimes(1);
    expect(view.onPaymentSetup).toHaveBeenCalledTimes(1);
    view.unmount();
  });

  it('given an invalid lifecycle whose destroy throws and no metrics script, when the block mounts, then conventional Pix is registered', () => {
    delete window.sendMetric;
    const lifecycle = { mount: jest.fn(), refresh: jest.fn(), destroy: jest.fn(() => { throw new Error('boom'); }) };
    window.mpSmartPixBlocksLifecycle = { create: () => ({ ...lifecycle, refresh: undefined }) };
    const view = renderPix();

    expect(view.onPaymentSetup).toHaveBeenCalledTimes(1);
    view.unmount();
  });

  it('given a late Smart Pix bundle, when it announces readiness, then it mounts once and removes the conventional observer', () => {
    const view = renderPix();
    expect(view.onPaymentSetup).toHaveBeenCalledTimes(1);

    const lifecycle = validLifecycle();
    const create = jest.fn(() => lifecycle);
    window.mpSmartPixBlocksLifecycle = { create };
    act(() => document.dispatchEvent(new CustomEvent('mp_smart_pix_bundle_ready')));
    act(() => document.dispatchEvent(new CustomEvent('mp_smart_pix_bundle_ready')));

    expect(create).toHaveBeenCalledTimes(1);
    expect(lifecycle.mount).toHaveBeenCalledTimes(1);
    expect(view.unsubscribers.payment[0]).toHaveBeenCalledTimes(1);
    expect(view.onPaymentSetup).toHaveBeenCalledTimes(1);

    view.unmount();
    expect(lifecycle.destroy).toHaveBeenCalledTimes(1);
    act(() => document.dispatchEvent(new CustomEvent('mp_smart_pix_bundle_ready')));
    expect(create).toHaveBeenCalledTimes(1);
  });

  it('given a late invalid bundle, when it announces readiness, then the existing conventional observer is kept without duplicates', () => {
    const view = renderPix();
    window.mpSmartPixBlocksLifecycle = { create: () => ({}) };
    act(() => document.dispatchEvent(new CustomEvent('mp_smart_pix_bundle_ready')));

    expect(window.sendMetric).toHaveBeenCalledWith(
      'MP_PIX_BLOCKS_LIFECYCLE_CREATION_ERROR',
      'lifecycle_creation_failed',
      'mp_pix_blocks_init_error',
    );
    expect(view.onPaymentSetup).toHaveBeenCalledTimes(1);
    expect(view.unsubscribers.payment[0]).not.toHaveBeenCalled();
    view.unmount();
  });
});

describe('Pix Blocks checkout success observer', () => {
  it('given a successful checkout, when the observer runs, then it reports the metric and answers success', async () => {
    const view = renderPix();
    const checkoutResponse = { processingResponse: { paymentStatus: 'pending' } };

    await expect(view.onSuccess()(checkoutResponse)).resolves.toEqual({ type: 'success' });
    expect(checkoutResponse.processingResponse.message).toBe(PAYMENT_METHOD_NAME);
    expect(window.sendMetric).toHaveBeenCalledWith('MP_PIX_BLOCKS_SUCCESS', 'pending', 'mp_checkout_blocks');
    view.unmount();
  });

  it('given a broken metrics script, when the success observer runs, then it still answers success', async () => {
    window.sendMetric = jest.fn(() => { throw new Error('metrics down'); });
    const view = renderPix();

    await expect(view.onSuccess()({ processingResponse: { paymentStatus: 'pending' } }))
      .resolves.toEqual({ type: 'success' });
    view.unmount();
  });
});

describe('Pix Blocks checkout fail observer', () => {
  const failure = (paymentDetails) => ({ processingResponse: { paymentStatus: 'failure', paymentDetails } });

  it('given no Smart Pix lifecycle, when the checkout fails, then it dispatches the error and keeps the payments notice', () => {
    const dispatch = jest.fn();
    window.MPCheckoutErrorDispatcher = { dispatchEventWhenBlocksCheckoutErrorOccurred: dispatch };
    const view = renderPix();
    const checkoutResponse = failure({ message: 'Pagamento recusado.' });

    expect(view.onFail()(checkoutResponse)).toEqual({
      type: 'fail',
      messageContext: 'payments',
      message: 'Pagamento recusado.',
    });
    expect(dispatch).toHaveBeenCalledWith(checkoutResponse);
    expect(window.sendMetric).toHaveBeenCalledWith('MP_PIX_BLOCKS_ERROR', 'failure', 'mp_checkout_blocks');
    view.unmount();
  });

  it('given broken dispatcher and metrics scripts, when the checkout fails, then the observer still answers', () => {
    window.MPCheckoutErrorDispatcher = {
      dispatchEventWhenBlocksCheckoutErrorOccurred: jest.fn(() => { throw new Error('dispatcher down'); }),
    };
    window.sendMetric = jest.fn(() => { throw new Error('metrics down'); });
    const view = renderPix();

    expect(view.onFail()(failure({ message: 'Falhou.' }))).toEqual({
      type: 'fail',
      messageContext: 'payments',
      message: 'Falhou.',
    });
    view.unmount();
  });

  it('given no processing response, when the checkout fails, then the observer answers without a message', () => {
    const view = renderPix();

    expect(view.onFail()(undefined)).toEqual({
      type: 'fail',
      messageContext: 'payments',
      message: undefined,
    });
    view.unmount();
  });

  it('given an inline Smart Pix failure, when the checkout fails, then the notice goes to the hidden inline context', () => {
    const lifecycle = validLifecycle({ handleCheckoutFailure: jest.fn(() => ({ placement: 'inline' })) });
    window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
    const view = renderPix();
    const paymentDetails = { smart_pix_error: 'smart_pix_validation_error' };

    expect(view.onFail()(failure(paymentDetails))).toEqual({
      type: 'fail',
      messageContext: 'mercadopago/smart-pix-inline',
      message: 'smart_pix_validation_error',
    });
    expect(lifecycle.handleCheckoutFailure).toHaveBeenCalledWith(paymentDetails);
    expect(view.onFail()(failure({ message: 'Conta indisponível.', smart_pix_error: 'x' })).message)
      .toBe('Conta indisponível.');
    view.unmount();
  });

  it('given a checkout placement, when the checkout fails, then the notice goes to the checkout context', () => {
    const lifecycle = validLifecycle({ handleCheckoutFailure: jest.fn(() => ({ placement: 'checkout' })) });
    window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
    const view = renderPix();

    expect(view.onFail()(failure({ message: 'Revise o pedido.' }))).toEqual({
      type: 'fail',
      messageContext: 'checkout',
      message: 'Revise o pedido.',
    });
    view.unmount();
  });

  it('given a lifecycle whose failure handler throws, when the checkout fails, then the plugin notice is kept', () => {
    const lifecycle = validLifecycle({
      handleCheckoutFailure: jest.fn(() => { throw new Error('cdn failure handler'); }),
    });
    window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
    const view = renderPix();

    expect(view.onFail()(failure({ message: 'Falhou.' }))).toEqual({
      type: 'fail',
      messageContext: 'payments',
      message: 'Falhou.',
    });
    view.unmount();
  });

  it('given a lifecycle without a failure handler, when the checkout fails, then the plugin notice is kept', () => {
    const lifecycle = validLifecycle({ handleCheckoutFailure: undefined });
    window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
    const view = renderPix();

    expect(view.onFail()(failure({ message: 'Falhou.' })).messageContext).toBe('payments');
    view.unmount();
  });
});
