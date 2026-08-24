const mockPublish = jest.fn();
const noop = jest.fn();

jest.mock('@super-token/adapters/platform', () => ({
  CoreMonitorMetricsAdapter: jest.fn().mockImplementation(() => ({ kind: 'metrics' })),
  SdkReadinessWatcher: jest.fn().mockImplementation(() => ({ start: jest.fn(), recoverIfSdkIsNowAvailable: jest.fn() })),
  InitializationHealthChecker: jest.fn().mockImplementation(() => ({ check: jest.fn() })),
  CARD_FORM_MOUNTED_EVENT: 'mp_card_form_mounted',
  createDomainConfig: jest.fn(() => ({ paymentMethodsOrder: 'cards_first', copy: {}, thumbnails: {} })),
}));

jest.mock('@super-token/adapters/legacy/globalBridge', () => ({
  publish: mockPublish,
  publishErrorCodes: noop,
  publishFinalizers: noop,
  publishSelectors: noop,
  publishReset: noop,
  publishOrderAndDecorate: noop,
  publishAccountPaymentMethods: noop,
  publishAuthorizePayment: noop,
  publishFetchAndRender: noop,
  publishLoad: noop,
  publishCancelLoad: noop,
  publishResetCustomCheckout: noop,
  publishHandleError: noop,
  publishRestorePreloaded: noop,
  publishEnsureEmailListener: noop,
  publishCheckoutValidationResolver: noop,
  publishWooCommerceValidationErrors: noop,
}));

const mockPaymentMethods = { kind: 'payment-methods' };
const mockAuthenticator = { kind: 'authenticator' };
const mockErrorHandler = { kind: 'error-handler' };
const mockTriggerHandler = { kind: 'trigger-handler' };

jest.mock('@super-token/adapters/runtime/SuperTokenPaymentMethods', () => ({
  SuperTokenPaymentMethods: jest.fn().mockImplementation(() => mockPaymentMethods),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenAuthenticator', () => ({
  SuperTokenAuthenticator: jest.fn().mockImplementation(() => mockAuthenticator),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenErrorHandler', () => ({
  SuperTokenErrorHandler: jest.fn().mockImplementation(() => mockErrorHandler),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenTriggerHandler', () => ({
  SuperTokenTriggerHandler: jest.fn().mockImplementation(() => mockTriggerHandler),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenEmailListener', () => ({
  SuperTokenEmailListener: jest.fn().mockImplementation(() => ({ kind: 'email-listener' })),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenDebounce', () => ({
  SuperTokenDebounce: jest.fn().mockImplementation(() => ({ kind: 'debounce' })),
}));

const mockCreateVariantView = jest.fn(() => ({ renderSavedPaymentMethods: jest.fn() }));
jest.mock('@super-token/adapters/view', () => ({
  createVariantView: mockCreateVariantView,
  createVariantViewDeps: jest.fn(() => ({})),
}));

const runtimePaymentMethods = require('@super-token/adapters/runtime/SuperTokenPaymentMethods');
const runtimeAuthenticator = require('@super-token/adapters/runtime/SuperTokenAuthenticator');
const runtimeTriggerHandler = require('@super-token/adapters/runtime/SuperTokenTriggerHandler');

describe('super-token/bootstrap self-construct (flip, dev mode)', () => {
  let setSuperTokenDependencies;

  beforeAll(() => {
    setSuperTokenDependencies = jest.fn();
    window.mpSdkInstance = { getSDKInstanceId: () => 'sdk-id' };
    window.mpCustomCheckoutHandler = {};
    window.mpEventHandler = { setSuperTokenDependencies };
    window.wc_mercadopago_supertoken_bundle_params = {
      site_id: 'MLB',
      self_construct: true,
      super_token_version: 'v2.1',
      platform_id: 'bp_woo',
      current_user_email: 'buyer-test-user',
    };
    // SDK already present, so whenSdkReady builds synchronously on require.
    require('@super-token/bootstrap');
  });

  it('Given self_construct with the SDK ready, When bootstrap runs, Then it constructs the ported entities', () => {
    expect(runtimePaymentMethods.SuperTokenPaymentMethods).toHaveBeenCalledTimes(1);
    expect(runtimeAuthenticator.SuperTokenAuthenticator).toHaveBeenCalledTimes(1);
    expect(runtimeTriggerHandler.SuperTokenTriggerHandler).toHaveBeenCalledTimes(1);
  });

  it('Given the entities are built, When bootstrap runs, Then it publishes them under window.mpSuperToken*', () => {
    expect(mockPublish).toHaveBeenCalledTimes(1);
    expect(mockPublish).toHaveBeenCalledWith(
      expect.objectContaining({
        triggerHandler: mockTriggerHandler,
        authenticator: mockAuthenticator,
        paymentMethods: mockPaymentMethods,
        errorHandler: mockErrorHandler,
      }),
    );
  });

  it('Given the Classic event handler is present, When bootstrap runs, Then it injects the same instances', () => {
    expect(setSuperTokenDependencies).toHaveBeenCalledTimes(1);
    expect(setSuperTokenDependencies).toHaveBeenCalledWith(mockPublish.mock.calls[0][0]);
  });

  it('Given a localized super_token_version, When the saved methods render, Then the view is built for that variant (dev follows the constant, not the cookie)', () => {
    // renderSavedMethods is the 4th constructor argument of SuperTokenPaymentMethods.
    const renderSavedMethods = runtimePaymentMethods.SuperTokenPaymentMethods.mock.calls[0][3];
    renderSavedMethods(document.createElement('div'), []);

    expect(mockCreateVariantView).toHaveBeenCalledWith('v2.1', expect.anything());
  });
});
