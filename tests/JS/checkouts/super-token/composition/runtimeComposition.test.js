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

describe('composeRuntime', () => {
  let metrics;
  let recompose;

  beforeEach(() => {
    jest.clearAllMocks();
    metrics = { sendMetric: jest.fn(), storeLanguageDivergesFromSite: jest.fn() };
    document.documentElement.lang = '';
    recompose = { current: () => {} };
    delete window.mpSdkInstance;
    delete window.mpSuperTokenTriggerHandler;
  });

  it('Given variant resolution rejects, When composed, Then it reports super_token_compose_failed', async () => {
    mockResolveVariant.mockRejectedValue(new Error('variant boom'));

    await composeRuntime({}, recompose, metrics);

    expect(metrics.sendMetric).toHaveBeenCalledWith(
      'super_token_compose_failed',
      'mp_super_token_init',
      'variant boom',
    );
    expect(globalBridge.publishOrderAndDecorate).toHaveBeenCalledTimes(1);
  });

  it('Given a resolved variant, When composed, Then it publishes the order+decorate seam', async () => {
    mockResolveVariant.mockResolvedValue('v2');
    // SDK present + legacy trigger handler already built → the hybrid no-op guard keeps the runtime
    // classes out of this unit (bootstrap.* covers that path).
    window.mpSdkInstance = {};
    window.mpSuperTokenTriggerHandler = {};

    await composeRuntime({}, recompose, metrics);

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

    await composeRuntime({}, recompose, metrics);

    expect(recompose.current).not.toBe(initial);
    expect(typeof recompose.current).toBe('function');
  });

  it('Given an MLC store on a plugin that sends store_language_copy, When composed, Then the plugin params reach the domain untouched', async () => {
    const platform = require('@super-token/adapters/platform');
    mockResolveVariant.mockResolvedValue('v2');
    window.mpSdkInstance = {};
    window.mpSuperTokenTriggerHandler = {};
    window.mpSuperTokenPaymentMethods = { ACCOUNT_MONEY_TEXT: 'Account money in Mercado Pago' };
    const params = { site_id: 'MLC', store_language_copy: true, account_money_text: 'Account money in Mercado Pago' };

    await composeRuntime(params, recompose, metrics);

    const [domainParams] = platform.createDomainConfig.mock.calls[0];
    expect(domainParams).toBe(params);
    expect(domainParams.account_money_text).toBe('Account money in Mercado Pago');
    expect(window.mpSuperTokenPaymentMethods.ACCOUNT_MONEY_TEXT).toBe('Account money in Mercado Pago');

    delete window.mpSuperTokenPaymentMethods;
  });

  it('Given an MLC store on an older plugin whose legacy controller is already built, When composed, Then the domain and the legacy controller get the bundle Chilean copy', async () => {
    // The trigger-handler guard makes buildAndPublishInstances a no-op here, so this is the only
    // place the legacy controller's copy gets refreshed for this hybrid scenario.
    const platform = require('@super-token/adapters/platform');
    mockResolveVariant.mockResolvedValue('v2');
    window.mpSdkInstance = {};
    window.mpSuperTokenTriggerHandler = {};
    window.mpSuperTokenPaymentMethods = { ACCOUNT_MONEY_TEXT: 'Texto genérico es.mo' };

    await composeRuntime({ site_id: 'MLC', account_money_text: 'Texto genérico es.mo' }, recompose, metrics);

    const [domainParams] = platform.createDomainConfig.mock.calls[0];
    expect(domainParams.account_money_text).toBe('Dinero disponible en Mercado Pago');
    expect(window.mpSuperTokenPaymentMethods.ACCOUNT_MONEY_TEXT).toBe('Dinero disponible en Mercado Pago');

    delete window.mpSuperTokenPaymentMethods;
  });

  it('Given a store language that differs from the credential site, When composed, Then the divergence is reported', async () => {
    mockResolveVariant.mockResolvedValue('v2');
    document.documentElement.lang = 'es-ES';

    await composeRuntime({ site_id: 'MLC', store_language_copy: true }, recompose, metrics);

    expect(metrics.storeLanguageDivergesFromSite).toHaveBeenCalledWith('es-es', 'MLC');
  });

  it('Given the store language expected for the credential site, When composed, Then no divergence is reported', async () => {
    mockResolveVariant.mockResolvedValue('v2');
    document.documentElement.lang = 'es-CL';

    await composeRuntime({ site_id: 'MLC', store_language_copy: true }, recompose, metrics);

    expect(metrics.storeLanguageDivergesFromSite).not.toHaveBeenCalled();
  });
});
