/**
 * Regression guard for the custom-checkout-handler-missing metric's timing.
 *
 * window.mpCustomCheckoutHandler is assigned on mp-custom-checkout.js's own DOMContentLoaded
 * listener — a signal with no causal relationship to the SDK-readiness gate composeRuntime waits
 * on. In self-construct mode composeRuntime runs as a plain synchronous script (no CDN fetch
 * delay), so it can run before that listener fires. A single point-in-time read of the global
 * false-positives in that race; it must poll instead (mirrors waitForHandler in
 * blocks/helpers/cart-update.helper.js).
 */
const mockPublish = jest.fn();
const mockSendMetric = jest.fn();
const noop = jest.fn();

jest.mock('@super-token/adapters/platform', () => ({
  CoreMonitorMetricsAdapter: jest.fn().mockImplementation(() => ({ kind: 'metrics', sendMetric: mockSendMetric })),
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

jest.mock('@super-token/adapters/runtime/SuperTokenPaymentMethods', () => ({
  SuperTokenPaymentMethods: jest.fn().mockImplementation(() => ({ kind: 'payment-methods' })),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenAuthenticator', () => ({
  SuperTokenAuthenticator: jest.fn().mockImplementation(() => ({ kind: 'authenticator' })),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenErrorHandler', () => ({
  SuperTokenErrorHandler: jest.fn().mockImplementation(() => ({ kind: 'error-handler' })),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenTriggerHandler', () => ({
  SuperTokenTriggerHandler: jest.fn().mockImplementation(() => ({ kind: 'trigger-handler' })),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenEmailListener', () => ({
  SuperTokenEmailListener: jest.fn().mockImplementation(() => ({ kind: 'email-listener' })),
}));
jest.mock('@super-token/adapters/runtime/SuperTokenDebounce', () => ({
  SuperTokenDebounce: jest.fn().mockImplementation(() => ({ kind: 'debounce' })),
}));
jest.mock('@super-token/adapters/view', () => ({
  createVariantView: jest.fn(() => ({ renderSavedPaymentMethods: jest.fn() })),
  createVariantViewDeps: jest.fn(() => ({})),
}));

const CUSTOM_HANDLER_MISSING_METRIC = 'MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS';

describe('super-token/bootstrap — custom-checkout-handler-missing metric timing', () => {
  beforeEach(async () => {
    jest.resetModules();
    jest.useFakeTimers();
    mockSendMetric.mockClear();

    delete window.mpCustomCheckoutHandler;
    delete window.mpSuperTokenTriggerHandler;
    // SDK already ready at load, mirroring the self-construct race: composeRuntime runs
    // synchronously, before mp-custom-checkout.js's DOMContentLoaded listener has a chance to fire.
    window.mpSdkInstance = { getSDKInstanceId: () => 'sdk-id' };
    window.mpEventHandler = { setSuperTokenDependencies: jest.fn() };
    window.wc_mercadopago_supertoken_bundle_params = {
      site_id: 'MLB',
      self_construct: true,
      super_token_version: 'v2.1',
      platform_id: 'bp_woo',
      current_user_email: 'buyer-test-user',
    };

    require('@super-token/bootstrap');
    // Flush the variant-resolution microtask so buildAndPublishInstances runs and arms the poll.
    await Promise.resolve();
    await Promise.resolve();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('Given the handler is assigned while the poll is still running, When it catches up, Then the missing-handler metric is never reported', async () => {
    // Simulates mp-custom-checkout.js's DOMContentLoaded listener firing after composition already ran.
    window.mpCustomCheckoutHandler = {};

    await jest.advanceTimersByTimeAsync(15000);

    expect(mockSendMetric).not.toHaveBeenCalledWith(
      CUSTOM_HANDLER_MISSING_METRIC,
      expect.anything(),
      expect.anything(),
    );
  });

  it('Given the handler never appears, When the poll window elapses, Then the missing-handler metric is reported exactly once', async () => {
    await jest.advanceTimersByTimeAsync(15000);

    expect(mockSendMetric).toHaveBeenCalledWith(
      CUSTOM_HANDLER_MISSING_METRIC,
      'mp_super_token_init',
      'mp_super_token_init_error',
    );
    expect(mockSendMetric).toHaveBeenCalledTimes(1);
  });
});
