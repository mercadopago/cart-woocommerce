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

  beforeAll(() => {
    global.wc_mercadopago_supertoken_bundle_params = {
      current_user_email: 'test@example.com',
    };

    global.wc_mercadopago_supertoken_trigger_handler_params = {
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
      authenticate: jest.fn().mockResolvedValue(true),
      reset: jest.fn(),
      isUserClosedModal: jest.fn().mockReturnValue(false),
      getAmountUsed: jest.fn().mockReturnValue(null),
      getEmailUsed: jest.fn().mockReturnValue(null),
      emailAlreadyVerified: jest.fn().mockReturnValue(false),
      isAbleToUseSuperToken: jest.fn().mockReturnValue(true),
      canUseSuperTokenFlow: jest.fn().mockResolvedValue(true),
      formatAmount: jest.fn((v) => v),
      getAccountPaymentMethods: jest.fn().mockResolvedValue([]),
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
      SUPER_TOKEN_STYLES: { PAYMENT_METHOD_LIST: 'mp-payment-methods-list' },
      deselectAllPaymentMethods: jest.fn(),
      hideAllPaymentMethodDetails: jest.fn(),
      unmountActiveSecurityCodeInstance: jest.fn(),
      activePaymentMethod: null,
    };

    mockErrorHandler = {
      handleError: jest.fn(),
    };

    triggerHandler = new MPSuperTokenTriggerHandler(
      mockAuthenticator,
      mockEmailListener,
      mockPaymentMethods,
      mockErrorHandler
    );

    document.body.innerHTML = `
      <input id="mp-amount" value="100.50" />
      <input id="mp_checkout_type" value="" />
    `;
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  describe('Initialization', () => {
    test('should create an instance of MPSuperTokenTriggerHandler class', () => {
      expect(triggerHandler).toBeInstanceOf(MPSuperTokenTriggerHandler);
    });

    test('should initialize isAlreadyListeningForm as false', () => {
      expect(triggerHandler.isAlreadyListeningForm).toBe(false);
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
      triggerHandler.wcBuyerEmail = 'wc-email@example.com';
      mockEmailListener.getEmail.mockReturnValue('listener-email@example.com');

      const result = triggerHandler.getBuyerEmail();

      expect(result).toBe('wc-email@example.com');
    });

    test('Given wcBuyerEmail is null but emailListener has email, When getBuyerEmail() is called, Then should return email from listener', () => {
      triggerHandler.wcBuyerEmail = null;
      mockEmailListener.getEmail.mockReturnValue('listener-email@example.com');

      const result = triggerHandler.getBuyerEmail();

      expect(result).toBe('listener-email@example.com');
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

  describe('resetFlow()', () => {
    test('Given resetFlow() is called, Then should reset authenticator and payment methods', () => {
      triggerHandler.resetFlow();

      expect(mockAuthenticator.reset).toHaveBeenCalledTimes(1);
      expect(mockPaymentMethods.reset).toHaveBeenCalledTimes(1);
    });
  });

  describe('isDifferentEmail()', () => {
    test('Given a different email, When isDifferentEmail() is called, Then should return true', () => {
      triggerHandler.wcBuyerEmail = 'old@example.com';

      const result = triggerHandler.isDifferentEmail('new@example.com');

      expect(result).toBe(true);
    });

    test('Given the same email, When isDifferentEmail() is called, Then should return false', () => {
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

    test('Given no payment methods are stored, When isSuperTokenPaymentMethodsLoaded() is called, Then should return false', () => {
      mockPaymentMethods.hasStoredPaymentMethods.mockReturnValue(false);

      const result = triggerHandler.isSuperTokenPaymentMethodsLoaded();

      expect(result).toBe(false);
    });
  });

  describe('amountHasChanged()', () => {
    test('Given currentAmount and amountUsed are different, When amountHasChanged() is called, Then should return true', () => {
      triggerHandler.currentAmount = '200.00';
      mockAuthenticator.getAmountUsed.mockReturnValue('100.00');

      const result = triggerHandler.amountHasChanged();

      expect(result).toBe(true);
    });

    test('Given currentAmount and amountUsed are the same, When amountHasChanged() is called, Then should return false', () => {
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
  });

  describe('emailHasChanged()', () => {
    test('Given current email and emailUsed are different, When emailHasChanged() is called, Then should return true', () => {
      triggerHandler.wcBuyerEmail = 'new@example.com';
      mockAuthenticator.getEmailUsed.mockReturnValue('old@example.com');

      const result = triggerHandler.emailHasChanged();

      expect(result).toBe(true);
    });

    test('Given current email and emailUsed are the same, When emailHasChanged() is called, Then should return false', () => {
      triggerHandler.wcBuyerEmail = 'same@example.com';
      mockAuthenticator.getEmailUsed.mockReturnValue('same@example.com');

      const result = triggerHandler.emailHasChanged();

      expect(result).toBe(false);
    });
  });

  describe('customCheckoutIsEnable()', () => {
    test('Given blocks radio element exists, When customCheckoutIsEnable() is called, Then should return true', () => {
      document.body.innerHTML += '<input type="radio" value="woo-mercado-pago-custom" />';

      const result = triggerHandler.customCheckoutIsEnable();

      expect(result).toBe(true);
    });

    test('Given classic radio element exists, When customCheckoutIsEnable() is called, Then should return true', () => {
      document.body.innerHTML += '<input type="radio" id="payment_method_woo-mercado-pago-custom" />';

      const result = triggerHandler.customCheckoutIsEnable();

      expect(result).toBe(true);
    });

    test('Given no checkout radio element exists, When customCheckoutIsEnable() is called, Then should return false', () => {
      const result = triggerHandler.customCheckoutIsEnable();

      expect(result).toBe(false);
    });
  });

  describe('isClassicCheckout()', () => {
    test('Given classic radio selector exists, When isClassicCheckout() is called, Then should return true', () => {
      document.body.innerHTML += '<input type="radio" id="payment_method_woo-mercado-pago-custom" />';

      const result = triggerHandler.isClassicCheckout();

      expect(result).toBe(true);
    });

    test('Given classic radio selector does not exist, When isClassicCheckout() is called, Then should return false', () => {
      const result = triggerHandler.isClassicCheckout();

      expect(result).toBe(false);
    });
  });
});
