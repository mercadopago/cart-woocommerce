// Focused test for composeRuntime's own orchestration: variant resolution → order+decorate seam →
// SDK-readiness gate. The full happy-path instance wiring (the 6 runtime classes) is covered
// end-to-end by the bootstrap.* suites; here the runtime classes are never reached because the
// pre-cutover trigger-handler guard makes buildAndPublishInstances a no-op.
const mockResolveVariant = jest.fn();

jest.mock('@super-token/composition/variantRuntime', () => ({
  resolveSuperTokenVariant: (...args) => mockResolveVariant(...args),
}));
jest.mock('@super-token/adapters/legacy/globalBridge');
jest.mock('@super-token/adapters/platform', () => ({
  CoreMonitorMetricsAdapter: jest.fn(),
  createDomainConfig: jest.fn(() => ({ paymentMethodsOrder: 'cards_first' })),
}));
jest.mock('@super-token/core/checkoutSession/PaymentMethodCatalog', () => ({
  PaymentMethodCatalog: jest.fn(() => ({ reorderAccountPaymentMethods: (m) => m })),
}));
jest.mock('@super-token/core/paymentMethods/registry', () => ({
  PaymentMethodRegistry: jest.fn(() => ({ decorateAccountPaymentMethods: (m) => m })),
}));
jest.mock('@super-token/core/paymentMethods/CreditCardMethod', () => ({ CreditCardMethod: jest.fn() }));
jest.mock('@super-token/core/paymentMethods/ConsumerCreditsMethod', () => ({ ConsumerCreditsMethod: jest.fn() }));

const globalBridge = require('@super-token/adapters/legacy/globalBridge');
const { composeRuntime } = require('@super-token/composition/runtimeComposition');

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('composeRuntime', () => {
  let metrics;
  let recompose;

  beforeEach(() => {
    jest.clearAllMocks();
    metrics = { sendMetric: jest.fn() };
    recompose = { current: () => {} };
    delete window.mpSdkInstance;
    delete window.mpSuperTokenTriggerHandler;
  });

  it('Given variant resolution rejects, When composed, Then it reports super_token_compose_failed', async () => {
    mockResolveVariant.mockRejectedValue(new Error('variant boom'));

    composeRuntime({}, recompose, metrics);
    await flush();

    expect(metrics.sendMetric).toHaveBeenCalledWith(
      'super_token_compose_failed',
      'mp_super_token_init',
      'variant boom',
    );
    expect(globalBridge.publishOrderAndDecorate).not.toHaveBeenCalled();
  });

  it('Given a resolved variant, When composed, Then it publishes the order+decorate seam', async () => {
    mockResolveVariant.mockResolvedValue('v2');
    // SDK present + legacy trigger handler already built → the hybrid no-op guard keeps the runtime
    // classes out of this unit (bootstrap.* covers that path).
    window.mpSdkInstance = {};
    window.mpSuperTokenTriggerHandler = {};

    composeRuntime({}, recompose, metrics);
    await flush();

    expect(globalBridge.publishOrderAndDecorate).toHaveBeenCalledTimes(1);
    expect(typeof globalBridge.publishOrderAndDecorate.mock.calls[0][0].orderAndDecorate).toBe('function');
    // The hybrid guard prevented instance publication.
    expect(globalBridge.publish).not.toHaveBeenCalled();
    expect(metrics.sendMetric).not.toHaveBeenCalled();
  });

  it('Given a resolved variant, When composed, Then recompose.current is set to the (guarded) rebuild', async () => {
    mockResolveVariant.mockResolvedValue('v2');
    window.mpSdkInstance = {};
    window.mpSuperTokenTriggerHandler = {};
    const initial = recompose.current;

    composeRuntime({}, recompose, metrics);
    await flush();

    expect(recompose.current).not.toBe(initial);
    expect(typeof recompose.current).toBe('function');
  });
});
