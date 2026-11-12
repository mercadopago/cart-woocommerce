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
    ['publishFastPaymentsEligibility', ['isValidBuyerEmail', 'getSmartTransferPaymentMethods']],
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

  it('shares one SDK request while isolating Custom Gateway and Smart Pix methods', async () => {
    const savedCard = { type: 'credit_card', token: 'card-token' };
    const smartTransfer = {
      id: 'smart-transfer-bb',
      name: 'Banco do Brasil',
      thumbnail: '',
      token: 'smart-transfer-token',
      type: 'smart_transfer',
      open_finance_account_id: 'open-finance-account',
    };
    const getAccountPaymentMethods = jest.fn(async () => ({
      data: [savedCard, smartTransfer],
    }));
    window.mpSuperTokenAuthenticator = {
      buildAuthenticator: jest.fn(async () => ({
        getSimplifiedAuth: jest.fn(async () => true),
        getFastPaymentToken: jest.fn(async () => 'fast-token'),
      })),
      storeAuthenticator: jest.fn(),
      getSimplifiedAuth: jest.fn(async (authenticator) => authenticator.getSimplifiedAuth()),
      getFastPaymentToken: jest.fn(async (authenticator) => authenticator.getFastPaymentToken()),
      storeFastPaymentToken: jest.fn(),
    };
    window.mpSuperTokenPaymentMethods = { getAccountPaymentMethods };
    window.mpSuperTokenMetrics = {
      sendMetric: jest.fn(),
      isNotSimplifiedAuth: jest.fn(),
      canUseSuperToken: jest.fn(),
      cannotGetFastPaymentToken: jest.fn(),
      errorToGetAccountPaymentMethods: jest.fn(),
    };

    const superTokenSeam = globalBridge.publishAccountPaymentMethods.mock.calls[0][0];
    const smartPixSeam = globalBridge.publishFastPaymentsEligibility.mock.calls[0][0];
    const [superTokenMethods, smartTransfers] = await Promise.all([
      superTokenSeam.getAccountPaymentMethods('91.73', 'coexistence@example.com'),
      smartPixSeam.getSmartTransferPaymentMethods('91.73', 'coexistence@example.com'),
    ]);

    expect(getAccountPaymentMethods).toHaveBeenCalledTimes(1);
    expect(superTokenMethods).toEqual([savedCard]);
    expect(smartTransfers).toEqual([smartTransfer]);
  });

  it('invalidates the hybrid coordinator when a legacy checkout reset clears its authenticator', async () => {
    const getAccountPaymentMethods = jest.fn(async () => ({
      data: [{ type: 'credit_card', token: 'card-token' }],
    }));
    window.mpSuperTokenAuthenticator = {
      buildAuthenticator: jest.fn(async () => ({
        getSimplifiedAuth: jest.fn(async () => true),
        getFastPaymentToken: jest.fn(async () => 'fast-token'),
      })),
      storeAuthenticator: jest.fn(),
      getSimplifiedAuth: jest.fn(async (authenticator) => authenticator.getSimplifiedAuth()),
      getFastPaymentToken: jest.fn(async (authenticator) => authenticator.getFastPaymentToken()),
      storeFastPaymentToken: jest.fn(),
      setSuperTokenValidation: jest.fn(),
    };
    window.mpSuperTokenPaymentMethods = {
      getAccountPaymentMethods,
      hideSuperTokenError: jest.fn(),
      hasStoredPaymentMethods: jest.fn(() => false),
      unmountCardForm: jest.fn(),
      mountCardForm: jest.fn(),
    };
    window.mpSuperTokenMetrics = {
      sendMetric: jest.fn(),
      isNotSimplifiedAuth: jest.fn(),
      canUseSuperToken: jest.fn(),
      cannotGetFastPaymentToken: jest.fn(),
      errorToGetAccountPaymentMethods: jest.fn(),
    };
    window.mpSuperTokenTriggerHandler = {
      currentAmount: '47.00',
      customHandlerMissingReportedOnReset: false,
      mpSuperTokenAuthenticator: window.mpSuperTokenAuthenticator,
      mpSuperTokenPaymentMethods: window.mpSuperTokenPaymentMethods,
      resetFlow: jest.fn(),
      loadSuperToken: jest.fn(async () => {}),
      finalizeResetTail: jest.fn(),
    };
    window.mpCustomCheckoutHandler = {};

    const accountMethodsSeam = globalBridge.publishAccountPaymentMethods.mock.calls[0][0];
    const resetSeam = globalBridge.publishResetCustomCheckout.mock.calls[0][0];

    await accountMethodsSeam.getAccountPaymentMethods('47.00', 'reset@example.com');
    await accountMethodsSeam.getAccountPaymentMethods('47.00', 'reset@example.com');
    expect(getAccountPaymentMethods).toHaveBeenCalledTimes(1);

    resetSeam.resetCustomCheckout(true);
    await accountMethodsSeam.getAccountPaymentMethods('47.00', 'reset@example.com');

    expect(getAccountPaymentMethods).toHaveBeenCalledTimes(2);
    expect(window.mpSuperTokenAuthenticator.storeAuthenticator).toHaveBeenCalledTimes(2);
  });

  it('reuses the exact Super Token e-mail gate before either consumer can request the SDK', async () => {
    const smartPixSeam = globalBridge.publishFastPaymentsEligibility.mock.calls[0][0];

    expect(smartPixSeam.isValidBuyerEmail(' buyer@example.com ')).toBe(true);
    expect(smartPixSeam.isValidBuyerEmail('invalid')).toBe(false);
    await expect(
      smartPixSeam.getSmartTransferPaymentMethods('10.00', 'invalid'),
    ).resolves.toBeNull();
  });

  it('fails closed when Smart Pix asks before the shared runtime is ready', async () => {
    delete window.mpSuperTokenAuthenticator;
    delete window.mpSuperTokenPaymentMethods;
    delete window.mpSuperTokenMetrics;

    const smartPixSeam = globalBridge.publishFastPaymentsEligibility.mock.calls[0][0];

    await expect(
      smartPixSeam.getSmartTransferPaymentMethods('10.00', 'buyer@example.com'),
    ).resolves.toBeNull();
  });

  it('publishes every seam group (no seam is forgotten)', () => {
    const publishersCalled = Object.keys(globalBridge)
      .filter((key) => key.startsWith('publish') && globalBridge[key].mock && globalBridge[key].mock.calls.length > 0);
    // Seam groups above + publishErrorCodes.
    expect(publishersCalled).toHaveLength(seams.length + 1);
  });
});
