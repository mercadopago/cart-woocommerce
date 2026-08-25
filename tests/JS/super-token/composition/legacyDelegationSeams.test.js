// Contract test: publishLegacyDelegationSeams must publish every orchestration seam to the legacy
// bridge, each as a callable, so the still-legacy JS classes can delegate. The individual use
// cases/sessions have their own tests; here we only assert the wiring is complete and well-shaped.
jest.mock('@super-token/adapters/legacy/globalBridge');

const globalBridge = require('@super-token/adapters/legacy/globalBridge');
const { publishLegacyDelegationSeams } = require('@super-token/composition/legacyDelegationSeams');
const { MPSuperTokenErrorCodes } = require('@super-token/core/checkoutSession/ErrorClassification');

describe('publishLegacyDelegationSeams', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    publishLegacyDelegationSeams();
  });

  // Each seam publisher and the property the published object must expose (a callable).
  const seams = [
    ['publishFinalizers', ['finalizeClassic', 'finalizeBlocks']],
    ['publishCheckoutValidationResolver', ['resolveCheckoutValidation']],
    ['publishWooCommerceValidationErrors', ['hasWooCommerceValidationErrors']],
    ['publishSelectors', ['selectSavedPaymentMethod']],
    ['publishReset', ['resetOnError']],
    ['publishAccountPaymentMethods', ['getAccountPaymentMethods']],
    ['publishAuthorizePayment', ['authorizePayment']],
    ['publishFetchAndRender', ['fetchAndRenderPaymentMethods']],
    ['publishLoad', ['loadSuperToken']],
    ['publishCancelLoad', ['cancelLoad']],
    ['publishResetCustomCheckout', ['resetCustomCheckout']],
    ['publishHandleError', ['handleError']],
    ['publishRestorePreloaded', ['restorePreloadedPaymentMethod']],
    ['publishEnsureEmailListener', ['ensureEmailListenerRegistered']],
  ];

  it.each(seams)('publishes %s exactly once with callable seam(s)', (publisher, fnKeys) => {
    expect(globalBridge[publisher]).toHaveBeenCalledTimes(1);
    const published = globalBridge[publisher].mock.calls[0][0];
    fnKeys.forEach((key) => expect(typeof published[key]).toBe('function'));
  });

  it('publishes the error codes constant (not a seam object)', () => {
    expect(globalBridge.publishErrorCodes).toHaveBeenCalledTimes(1);
    expect(globalBridge.publishErrorCodes).toHaveBeenCalledWith(MPSuperTokenErrorCodes);
  });

  it('publishes every seam group (no seam is forgotten)', () => {
    const publishersCalled = Object.keys(globalBridge)
      .filter((key) => key.startsWith('publish') && globalBridge[key].mock && globalBridge[key].mock.calls.length > 0);
    // 14 seam groups above + publishErrorCodes.
    expect(publishersCalled).toHaveLength(seams.length + 1);
  });
});
