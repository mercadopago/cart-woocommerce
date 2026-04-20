const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const superTokenTriggerHandlerPath = resolveAlias('assets/js/checkouts/super-token/entities/super-token-trigger-handler.js');

describe('MPSuperTokenTriggerHandler', () => {
  let triggerHandler;
  let MPSuperTokenTriggerHandler;
  let mockAuthenticator;
  let mockEmailListener;
  let mockPaymentMethods;
  let mockErrorHandler;
  let mockMetrics;

  beforeAll(() => {
    global.wc_mercadopago_supertoken_bundle_params = {
      current_user_email: 'test@example.com',
    };

    global.jQuery = jest.fn((selector) => {
      const element = document.querySelector(selector);
      return {
        val: jest.fn((value) => {
          if (value !== undefined && element) {
            element.value = value;
          }
          return element ? element.value : '';
        }),
        submit: jest.fn(),
      };
    });

    MPSuperTokenTriggerHandler = loadFile(superTokenTriggerHandlerPath, 'MPSuperTokenTriggerHandler', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();

    mockAuthenticator = {
      getAccountPaymentMethods: jest.fn().mockResolvedValue([]),
      reset: jest.fn(),
      getAmountUsed: jest.fn().mockReturnValue(null),
      getEmailUsed: jest.fn().mockReturnValue(null),
      formatAmount: jest.fn((amount) => amount),
      setSuperTokenValidation: jest.fn(),
    };

    mockEmailListener = {
      getEmail: jest.fn().mockReturnValue(null),
      onEmailChange: jest.fn(),
      setupEmailChangeHandlers: jest.fn(),
    };

    mockPaymentMethods = {
      reset: jest.fn(),
      hasStoredPaymentMethods: jest.fn().mockReturnValue(false),
      getStoredPaymentMethods: jest.fn().mockReturnValue([]),
      renderAccountPaymentMethods: jest.fn().mockResolvedValue(),
      unmountCardForm: jest.fn(),
      mountCardForm: jest.fn(),
      hasCheckoutError: jest.fn().mockReturnValue(false),
      selectLastPaymentMethodChoosen: jest.fn(),
      hideSuperTokenError: jest.fn(),
      deselectAllPaymentMethods: jest.fn(),
      hideAllPaymentMethodDetails: jest.fn(),
      unmountActiveSecurityCodeInstance: jest.fn(),
      SUPER_TOKEN_STYLES: { PAYMENT_METHOD_LIST: 'mp-super-token-payment-methods-list' },
    };

    mockErrorHandler = {
      handleError: jest.fn(),
    };

    mockMetrics = {
      sendMetric: jest.fn(),
    };

    triggerHandler = new MPSuperTokenTriggerHandler(
      mockAuthenticator,
      mockEmailListener,
      mockPaymentMethods,
      mockErrorHandler,
      mockMetrics
    );

    document.body.innerHTML = `
      <input id="mp-amount" value="100.50" />
      <input id="mp_checkout_type" value="" />
    `;
  });

  describe('Initialization', () => {
    test('should create an instance of MPSuperTokenTriggerHandler class', () => {
      expect(triggerHandler).toBeInstanceOf(MPSuperTokenTriggerHandler);
    });

    test('should initialize isAlreadyListeningForm as false', () => {
      expect(triggerHandler.isAlreadyListeningForm).toBe(false);
    });

    test('should initialize isFetchingPaymentMethods as false', () => {
      expect(triggerHandler.isFetchingPaymentMethods).toBe(false);
    });

    test('should initialize dependencies correctly', () => {
      expect(triggerHandler.mpSuperTokenAuthenticator).toBe(mockAuthenticator);
      expect(triggerHandler.wcEmailListener).toBe(mockEmailListener);
      expect(triggerHandler.mpSuperTokenPaymentMethods).toBe(mockPaymentMethods);
      expect(triggerHandler.mpSuperTokenErrorHandler).toBe(mockErrorHandler);
    });

    test('should define correct constants', () => {
      expect(triggerHandler.CHECKOUT_TYPE_SELECTOR).toBe('#mp_checkout_type');
      expect(triggerHandler.CURRENT_USER_EMAIL).toBe('test@example.com');
    });
  });

  describe('getBuyerEmail()', () => {
    test('Given wcBuyerEmail is set, When getBuyerEmail() is called, Then should return wcBuyerEmail', () => {
      triggerHandler.wcBuyerEmail = 'buyer@example.com';

      const result = triggerHandler.getBuyerEmail();

      expect(result).toBe('buyer@example.com');
    });

    test('Given wcBuyerEmail is null but emailListener has email, When getBuyerEmail() is called, Then should return email from listener', () => {
      triggerHandler.wcBuyerEmail = null;
      mockEmailListener.getEmail.mockReturnValue('listener@example.com');

      const result = triggerHandler.getBuyerEmail();

      expect(result).toBe('listener@example.com');
    });

    test('Given wcBuyerEmail and emailListener are null, When getBuyerEmail() is called, Then should return CURRENT_USER_EMAIL', () => {
      triggerHandler.wcBuyerEmail = null;
      mockEmailListener.getEmail.mockReturnValue(null);

      const result = triggerHandler.getBuyerEmail();

      expect(result).toBe(triggerHandler.CURRENT_USER_EMAIL);
      expect(triggerHandler.CURRENT_USER_EMAIL).toBe('test@example.com');
    });

    test('Given all email sources are null/empty, When getBuyerEmail() is called, Then should return CURRENT_USER_EMAIL from params', () => {
      triggerHandler.wcBuyerEmail = null;
      mockEmailListener.getEmail.mockReturnValue('');

      const result = triggerHandler.getBuyerEmail();

      expect(result).toBe(triggerHandler.CURRENT_USER_EMAIL);
      expect(triggerHandler.CURRENT_USER_EMAIL).toBe('test@example.com');
    });
  });

  describe('amountHasChanged()', () => {
    test('Given currentAmount differs from amountUsed, When amountHasChanged() is called, Then should return true', () => {
      triggerHandler.currentAmount = '200.00';
      mockAuthenticator.getAmountUsed.mockReturnValue('100.00');

      const result = triggerHandler.amountHasChanged();

      expect(result).toBe(true);
    });

    test('Given currentAmount equals amountUsed, When amountHasChanged() is called, Then should return false', () => {
      triggerHandler.currentAmount = '100.00';
      mockAuthenticator.getAmountUsed.mockReturnValue('100.00');

      const result = triggerHandler.amountHasChanged();

      expect(result).toBe(false);
    });

    test('Given currentAmount is null, When amountHasChanged() is called, Then should return false', () => {
      triggerHandler.currentAmount = null;
      mockAuthenticator.getAmountUsed.mockReturnValue('100.00');

      const result = triggerHandler.amountHasChanged();

      expect(result).toBe(false);
    });

    test('Given amountUsed is null, When amountHasChanged() is called, Then should return false', () => {
      triggerHandler.currentAmount = '100.00';
      mockAuthenticator.getAmountUsed.mockReturnValue(null);

      const result = triggerHandler.amountHasChanged();

      expect(result).toBe(false);
    });
  });

  describe('emailHasChanged()', () => {
    test('Given email differs from emailUsed, When emailHasChanged() is called, Then should return true', () => {
      triggerHandler.wcBuyerEmail = 'new@example.com';
      mockAuthenticator.getEmailUsed.mockReturnValue('old@example.com');

      const result = triggerHandler.emailHasChanged();

      expect(result).toBe(true);
    });

    test('Given email equals emailUsed, When emailHasChanged() is called, Then should return false', () => {
      triggerHandler.wcBuyerEmail = 'same@example.com';
      mockAuthenticator.getEmailUsed.mockReturnValue('same@example.com');

      const result = triggerHandler.emailHasChanged();

      expect(result).toBe(false);
    });
  });

  describe('isDifferentEmail()', () => {
    test('Given different email, When isDifferentEmail() is called, Then should return true', () => {
      triggerHandler.wcBuyerEmail = 'old@example.com';

      const result = triggerHandler.isDifferentEmail('new@example.com');

      expect(result).toBe(true);
    });

    test('Given same email, When isDifferentEmail() is called, Then should return false', () => {
      triggerHandler.wcBuyerEmail = 'same@example.com';

      const result = triggerHandler.isDifferentEmail('same@example.com');

      expect(result).toBe(false);
    });
  });

  describe('isSuperTokenPaymentMethodsLoaded()', () => {
    test('Given payment methods are stored, When isSuperTokenPaymentMethodsLoaded() is called, Then should return true', () => {
      mockPaymentMethods.hasStoredPaymentMethods.mockReturnValue(true);

      const result = triggerHandler.isSuperTokenPaymentMethodsLoaded();

      expect(result).toBe(true);
    });

    test('Given no payment methods stored, When isSuperTokenPaymentMethodsLoaded() is called, Then should return false', () => {
      mockPaymentMethods.hasStoredPaymentMethods.mockReturnValue(false);

      const result = triggerHandler.isSuperTokenPaymentMethodsLoaded();

      expect(result).toBe(false);
    });
  });

  describe('resetFlow()', () => {
    test('Given triggerHandler, When resetFlow() is called, Then should reset both authenticator and payment methods', () => {
      triggerHandler.resetFlow();

      expect(mockAuthenticator.reset).toHaveBeenCalled();
      expect(mockPaymentMethods.reset).toHaveBeenCalled();
    });
  });

  describe('hasLastException()', () => {
    test('Given lastException is set, When hasLastException() is called, Then should return true', () => {
      triggerHandler.setLastException(new Error('test'));

      expect(triggerHandler.hasLastException()).toBe(true);
    });

    test('Given lastException is null, When hasLastException() is called, Then should return false', () => {
      triggerHandler.setLastException(null);

      expect(triggerHandler.hasLastException()).toBe(false);
    });
  });

  describe('fetchAndRenderSuperTokenPaymentMethods()', () => {
    test('Given no buyer email, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should return early', async () => {
      triggerHandler.wcBuyerEmail = null;
      mockEmailListener.getEmail.mockReturnValue(null);
      global.wc_mercadopago_supertoken_bundle_params.current_user_email = null;

      const handler = new MPSuperTokenTriggerHandler(
        mockAuthenticator,
        mockEmailListener,
        mockPaymentMethods,
        mockErrorHandler,
        mockMetrics
      );

      await handler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockAuthenticator.getAccountPaymentMethods).not.toHaveBeenCalled();

      // Restore
      global.wc_mercadopago_supertoken_bundle_params.current_user_email = 'test@example.com';
    });

    test('Given valid email and payment methods returned, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should render payment methods', async () => {
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      triggerHandler.currentAmount = '100.00';
      const mockPMs = [{ id: 'visa', type: 'credit_card' }];
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(mockPMs);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockAuthenticator.getAccountPaymentMethods).toHaveBeenCalledWith('100.00', 'buyer@example.com');
      expect(mockPaymentMethods.renderAccountPaymentMethods).toHaveBeenCalledWith(mockPMs, '100.00');
    });

    test('Given valid email but no payment methods returned, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should not render', async () => {
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      triggerHandler.currentAmount = '100.00';
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(null);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockPaymentMethods.renderAccountPaymentMethods).not.toHaveBeenCalled();
    });
  });

  describe('loadSuperToken()', () => {
    test('Given payment methods already loaded, When loadSuperToken() is called, Then should render from stored payment methods', async () => {
      mockPaymentMethods.hasStoredPaymentMethods.mockReturnValue(true);
      const storedPMs = [{ id: 'visa' }];
      mockPaymentMethods.getStoredPaymentMethods.mockReturnValue(storedPMs);
      mockAuthenticator.formatAmount.mockReturnValue('100.00');

      await triggerHandler.loadSuperToken('100.00');

      expect(mockPaymentMethods.renderAccountPaymentMethods).toHaveBeenCalledWith(storedPMs, '100.00');
      expect(mockAuthenticator.getAccountPaymentMethods).not.toHaveBeenCalled();
    });

    test('Given amount changed, When loadSuperToken() is called, Then should reset flow before fetching', async () => {
      triggerHandler.currentAmount = '100.00';
      mockAuthenticator.getAmountUsed.mockReturnValue('100.00');
      mockAuthenticator.formatAmount.mockReturnValue('200.00');
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(null);

      await triggerHandler.loadSuperToken('200.00');

      expect(mockAuthenticator.reset).toHaveBeenCalled();
      expect(mockPaymentMethods.reset).toHaveBeenCalled();
    });
  });
});
