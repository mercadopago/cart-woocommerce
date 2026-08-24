const mockStart = jest.fn();
const mockRecover = jest.fn();
const mockCheck = jest.fn();
const mockPublishFinalizers = jest.fn();
const mockPublishSelectors = jest.fn();
const mockPublishReset = jest.fn();
const mockPublishOrderAndDecorate = jest.fn();
const mockPublishAccountPaymentMethods = jest.fn();
const mockPublishAuthorizePayment = jest.fn();
const mockPublishFetchAndRender = jest.fn();
const mockPublishLoad = jest.fn();
const mockPublishCancelLoad = jest.fn();
const mockPublishResetCustomCheckout = jest.fn();
const mockPublishHandleError = jest.fn();
const mockPublishRestorePreloaded = jest.fn();
const mockPublishEnsureEmailListener = jest.fn();
const mockPublishCheckoutValidationResolver = jest.fn();
const mockPublishWooCommerceValidationErrors = jest.fn();

jest.mock('@super-token/adapters/platform', () => ({
  CoreMonitorMetricsAdapter: jest.fn(),
  SdkReadinessWatcher: jest.fn().mockImplementation(() => ({
    start: mockStart,
    recoverIfSdkIsNowAvailable: mockRecover,
  })),
  InitializationHealthChecker: jest.fn().mockImplementation(() => ({ check: mockCheck })),
  CARD_FORM_MOUNTED_EVENT: 'mp_card_form_mounted',
  createDomainConfig: jest.fn(() => ({ paymentMethodsOrder: 'cards_first', copy: {}, thumbnails: {} })),
  // Bundle/prod resolves the A/B variant here before composing; the wiring is unblocked once it resolves.
  VariantConfigAdapter: jest.fn().mockImplementation(() => ({ resolve: jest.fn().mockResolvedValue('v2') })),
}));

jest.mock('@super-token/adapters/legacy/globalBridge', () => ({
  publishErrorCodes: jest.fn(),
  publishFinalizers: mockPublishFinalizers,
  publishSelectors: mockPublishSelectors,
  publishReset: mockPublishReset,
  publishOrderAndDecorate: mockPublishOrderAndDecorate,
  publishAccountPaymentMethods: mockPublishAccountPaymentMethods,
  publishAuthorizePayment: mockPublishAuthorizePayment,
  publishFetchAndRender: mockPublishFetchAndRender,
  publishLoad: mockPublishLoad,
  publishCancelLoad: mockPublishCancelLoad,
  publishResetCustomCheckout: mockPublishResetCustomCheckout,
  publishHandleError: mockPublishHandleError,
  publishRestorePreloaded: mockPublishRestorePreloaded,
  publishEnsureEmailListener: mockPublishEnsureEmailListener,
  publishCheckoutValidationResolver: mockPublishCheckoutValidationResolver,
  publishWooCommerceValidationErrors: mockPublishWooCommerceValidationErrors,
}));

const platform = require('@super-token/adapters/platform');

describe('super-token/bootstrap wiring', () => {
  beforeAll(async () => {
    // The order+decorate seam is only published when the localized params are present.
    window.wc_mercadopago_supertoken_bundle_params = { site_id: 'MLB' };
    // Loading the module runs the synchronous wiring (finalizers + resilience layer) and kicks off
    // the async A/B variant resolution; the order+decorate seam publishes after it resolves.
    require('@super-token/bootstrap');
    // Flush the VariantConfigAdapter.resolve() microtask so composeWithVariant runs before asserting.
    await Promise.resolve();
    await Promise.resolve();
  });

  it('Given the bundle loads, When bootstrap runs, Then it publishes the finalizers, the selector, the reset, the order+decorate and the account-methods load, constructs the resilience layer, and starts the watcher', () => {
    expect(mockPublishFinalizers).toHaveBeenCalledTimes(1);
    expect(mockPublishSelectors).toHaveBeenCalledTimes(1);
    expect(mockPublishReset).toHaveBeenCalledTimes(1);
    expect(mockPublishOrderAndDecorate).toHaveBeenCalledTimes(1);
    expect(mockPublishAccountPaymentMethods).toHaveBeenCalledTimes(1);
    expect(mockPublishAuthorizePayment).toHaveBeenCalledTimes(1);
    expect(mockPublishFetchAndRender).toHaveBeenCalledTimes(1);
    expect(mockPublishLoad).toHaveBeenCalledTimes(1);
    expect(mockPublishCancelLoad).toHaveBeenCalledTimes(1);
    expect(mockPublishResetCustomCheckout).toHaveBeenCalledTimes(1);
    expect(mockPublishHandleError).toHaveBeenCalledTimes(1);
    expect(mockPublishRestorePreloaded).toHaveBeenCalledTimes(1);
    expect(mockPublishEnsureEmailListener).toHaveBeenCalledTimes(1);
    expect(mockPublishCheckoutValidationResolver).toHaveBeenCalledTimes(1);
    expect(mockPublishWooCommerceValidationErrors).toHaveBeenCalledTimes(1);
    expect(platform.CoreMonitorMetricsAdapter).toHaveBeenCalledTimes(1);
    expect(platform.SdkReadinessWatcher).toHaveBeenCalledTimes(1);
    expect(platform.InitializationHealthChecker).toHaveBeenCalledTimes(1);
    expect(mockStart).toHaveBeenCalledTimes(1);
  });

  it('Given the health checker, When constructed, Then getInstances returns null until the legacy instances exist', () => {
    const { getInstances } = platform.InitializationHealthChecker.mock.calls[0][0];

    expect(getInstances()).toBeNull();

    window.mpSuperTokenTriggerHandler = { kind: 'trigger-handler' };
    window.mpSuperTokenAuthenticator = { kind: 'authenticator' };
    window.mpSuperTokenPaymentMethods = { kind: 'payment-methods' };
    window.mpSuperTokenMetrics = { kind: 'metrics' };
    window.mpSuperTokenErrorHandler = { kind: 'error-handler' };

    expect(getInstances()).toMatchObject({ triggerHandler: { kind: 'trigger-handler' } });

    delete window.mpSuperTokenTriggerHandler;
  });

  it('Given mp_card_form_mounted fires, When the combined listener runs, Then it recovers before checking (order pre-condition)', () => {
    const calls = [];
    mockRecover.mockImplementation(() => calls.push('recover'));
    mockCheck.mockImplementation(() => calls.push('check'));

    document.dispatchEvent(new Event('mp_card_form_mounted'));

    expect(calls).toEqual(['recover', 'check']);
  });
});
