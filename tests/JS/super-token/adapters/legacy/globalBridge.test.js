const {
  publish,
  publishErrorCodes,
  publishFinalizers,
  publishSelectors,
  publishReset,
  publishOrderAndDecorate,
  publishCheckoutValidationResolver,
  publishWooCommerceValidationErrors,
} = require('@super-token/adapters/legacy/globalBridge');

function buildInstances() {
  return {
    triggerHandler: { kind: 'trigger-handler' },
    authenticator: { kind: 'authenticator' },
    paymentMethods: { kind: 'payment-methods' },
    metrics: { kind: 'metrics' },
    errorHandler: { kind: 'error-handler' },
  };
}

const superTokenKeys = () =>
  Object.keys(window).filter((key) => key.startsWith('mpSuperToken')).sort();

describe('super-token/legacy/globalBridge publish', () => {
  beforeEach(() => {
    superTokenKeys().forEach((key) => { delete window[key]; });
  });

  it('Given the composed instances, When publish runs, Then each is exposed under its legacy window.mpSuperToken* name', () => {
    const instances = buildInstances();

    publish(instances);

    expect(window.mpSuperTokenTriggerHandler).toBe(instances.triggerHandler);
    expect(window.mpSuperTokenAuthenticator).toBe(instances.authenticator);
    expect(window.mpSuperTokenPaymentMethods).toBe(instances.paymentMethods);
    expect(window.mpSuperTokenMetrics).toBe(instances.metrics);
    expect(window.mpSuperTokenErrorHandler).toBe(instances.errorHandler);
  });

  it('Given the composed instances, When publish runs, Then it writes exactly the five known keys and no dynamic names', () => {
    publish(buildInstances());

    expect(superTokenKeys()).toEqual([
      'mpSuperTokenAuthenticator',
      'mpSuperTokenErrorHandler',
      'mpSuperTokenMetrics',
      'mpSuperTokenPaymentMethods',
      'mpSuperTokenTriggerHandler',
    ]);
  });

  it('Given the error-code constants, When publishErrorCodes runs, Then they are exposed under window.MPSuperTokenErrorCodes', () => {
    const codes = { UNKNOWN_ERROR: 'UNKNOWN_ERROR', SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR' };

    publishErrorCodes(codes);

    expect(window.MPSuperTokenErrorCodes).toBe(codes);
  });

  it('Given the refactored finalizers, When publishFinalizers runs, Then each is exposed under its window.mpSuperTokenFinalize* name', () => {
    const finalizeClassic = jest.fn();
    const finalizeBlocks = jest.fn();

    publishFinalizers({ finalizeClassic, finalizeBlocks });

    expect(window.mpSuperTokenFinalizeClassic).toBe(finalizeClassic);
    expect(window.mpSuperTokenFinalizeBlocks).toBe(finalizeBlocks);
  });

  it('Given the refactored selector, When publishSelectors runs, Then it is exposed under window.mpSuperTokenSelectPaymentMethod', () => {
    const selectSavedPaymentMethod = jest.fn();

    publishSelectors({ selectSavedPaymentMethod });

    expect(window.mpSuperTokenSelectPaymentMethod).toBe(selectSavedPaymentMethod);
  });

  it('Given the refactored reset, When publishReset runs, Then it is exposed under window.mpSuperTokenResetOnError', () => {
    const resetOnError = jest.fn();

    publishReset({ resetOnError });

    expect(window.mpSuperTokenResetOnError).toBe(resetOnError);
  });

  it('Given the refactored order+decorate, When publishOrderAndDecorate runs, Then it is exposed under window.mpSuperTokenOrderAndDecorate', () => {
    const orderAndDecorate = jest.fn();

    publishOrderAndDecorate({ orderAndDecorate });

    expect(window.mpSuperTokenOrderAndDecorate).toBe(orderAndDecorate);
  });

  it('Given the ported resolver, When publishCheckoutValidationResolver runs, Then it is exposed under window.mpResolveCheckoutValidation', () => {
    const resolveCheckoutValidation = jest.fn();

    publishCheckoutValidationResolver({ resolveCheckoutValidation });

    expect(window.mpResolveCheckoutValidation).toBe(resolveCheckoutValidation);

    delete window.mpResolveCheckoutValidation;
  });

  it('Given the ported validation gate, When publishWooCommerceValidationErrors runs, Then it is exposed under window.hasWooCommerceValidationErrors', () => {
    const hasWooCommerceValidationErrors = jest.fn();

    publishWooCommerceValidationErrors({ hasWooCommerceValidationErrors });

    expect(window.hasWooCommerceValidationErrors).toBe(hasWooCommerceValidationErrors);

    delete window.hasWooCommerceValidationErrors;
  });
});
