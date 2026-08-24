/**
 * Regression guard for the SDK late-recovery path (PSW-4277 cutover).
 *
 * The cutover makes bootstrap.ts compose the runtime instances itself in self-construct mode.
 * The SDK-readiness watcher (PSW-4274/TASK-010) exists to recover a late SDK that arrives after
 * the 15s poll window closed, via its card-form recovery callback. That callback must re-run the
 * composition — passing an empty `() => {}` (as the pre-cutover hybrid did) silently drops the
 * Super Token flow for that scenario.
 */
const mockPublish = jest.fn();
const noop = jest.fn();

jest.mock('@super-token/adapters/platform', () => ({
  CoreMonitorMetricsAdapter: jest.fn().mockImplementation(() => ({ kind: 'metrics', sendMetric: jest.fn() })),
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

const mockTriggerHandler = { kind: 'trigger-handler' };
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
  SuperTokenTriggerHandler: jest.fn().mockImplementation(() => mockTriggerHandler),
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

const platform = require('@super-token/adapters/platform');
const runtimeTriggerHandler = require('@super-token/adapters/runtime/SuperTokenTriggerHandler');

describe('super-token/bootstrap — SDK late-recovery wires composition into the watcher', () => {
  let watcherComposeCallback;

  beforeAll(async () => {
    jest.useFakeTimers();
    delete window.mpSdkInstance; // SDK absent at load → whenSdkReady only arms its poll, no build
    window.mpEventHandler = { setSuperTokenDependencies: jest.fn() };
    window.wc_mercadopago_supertoken_bundle_params = {
      site_id: 'MLB',
      self_construct: true,
      super_token_version: 'v2.1',
      platform_id: 'bp_woo',
      current_user_email: 'buyer-test-user',
    };

    require('@super-token/bootstrap');
    // Flush the variant-resolution microtask so composeWithVariant binds the recompose trigger.
    await Promise.resolve();
    await Promise.resolve();

    watcherComposeCallback = platform.SdkReadinessWatcher.mock.results[0].value.start.mock.calls[0][0];
  });

  afterAll(() => {
    jest.useRealTimers();
  });

  it('Given the SDK was absent at load, When the watcher recovery callback fires with the SDK now present, Then the composition runs (instances built)', () => {
    // No SDK yet → nothing composed, and the watcher received a real (non-empty) compose callback.
    expect(runtimeTriggerHandler.SuperTokenTriggerHandler).not.toHaveBeenCalled();
    expect(typeof watcherComposeCallback).toBe('function');

    window.mpSdkInstance = { getSDKInstanceId: () => 'sdk-id' };
    watcherComposeCallback();

    expect(runtimeTriggerHandler.SuperTokenTriggerHandler).toHaveBeenCalledTimes(1);
    expect(mockPublish).toHaveBeenCalledTimes(1);
  });
});
