const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const superTokenTriggerHandlerPath = resolveAlias('assets/js/checkouts/super-token/entities/super-token-trigger-handler.js');

describe('MPSuperTokenTriggerHandler', () => {
  let triggerHandler;
  let MPSuperTokenTriggerHandler;
  let mockAuthenticator;
  let mockEmailListener;
  let mockPaymentMethods;

  beforeAll(() => {
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
      emailAlreadyVerified: jest.fn().mockReturnValue(false),
      isAbleToUseSuperToken: jest.fn().mockReturnValue(true),
      canUseSuperTokenFlow: jest.fn().mockResolvedValue(true),
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
      renderAccountPaymentMethods: jest.fn(),
      unmountCardForm: jest.fn(),
      mountCardForm: jest.fn(),
    };

    triggerHandler = new MPSuperTokenTriggerHandler(
      mockAuthenticator,
      mockEmailListener,
      mockPaymentMethods
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

    test('should initialize isAuthenticating as false', () => {
      expect(triggerHandler.isAuthenticating).toBe(false);
    });

    test('should initialize isAlreadyListeningForm as false', () => {
      expect(triggerHandler.isAlreadyListeningForm).toBe(false);
    });

    test('should initialize dependencies correctly', () => {
      expect(triggerHandler.mpSuperTokenAuthenticator).toBe(mockAuthenticator);
      expect(triggerHandler.wcEmailListener).toBe(mockEmailListener);
      expect(triggerHandler.mpSuperTokenPaymentMethods).toBe(mockPaymentMethods);
    });

    test('should define correct constants', () => {
      expect(triggerHandler.WALLET_BUTTON_CONTAINER_SELECTOR).toBe('.mp-wallet-button-container');
      expect(triggerHandler.CHECKOUT_TYPE_SELECTOR).toBe('#mp_checkout_type');
      expect(triggerHandler.CURRENT_USER_EMAIL).toBe('test@example.com');
    });
  });

  describe('onSDKFieldFocus()', () => {
    beforeEach(() => {
      triggerHandler.currentAmount = '100.50';
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
    });

    test('Given wallet button is active, When onSDKFieldFocus() is called, Then should return early without triggering authentication', () => {
      document.body.innerHTML += '<div class="mp-wallet-button-container"></div>';

      triggerHandler.onSDKFieldFocus('cardNumber');

      expect(mockAuthenticator.authenticate).not.toHaveBeenCalled();
    });

    test('Given isAuthenticating is true, When onSDKFieldFocus() is called, Then should return early without triggering authentication', () => {
      triggerHandler.isAuthenticating = true;

      triggerHandler.onSDKFieldFocus('cardNumber');

      expect(mockAuthenticator.authenticate).not.toHaveBeenCalled();
    });

    test('Given user closed modal, When onSDKFieldFocus() is called, Then should return early without triggering authentication', () => {
      mockAuthenticator.isUserClosedModal.mockReturnValue(true);

      triggerHandler.onSDKFieldFocus('cardNumber');

      expect(mockAuthenticator.authenticate).not.toHaveBeenCalled();
    });

    test('Given no buyer email, When onSDKFieldFocus() is called, Then should return early without triggering authentication', () => {
      // Create new instance with null email
      global.wc_mercadopago_supertoken_trigger_handler_params.current_user_email = null;
      const triggerHandlerWithNoEmail = new MPSuperTokenTriggerHandler(
        mockAuthenticator,
        mockEmailListener,
        mockPaymentMethods
      );
      
      triggerHandlerWithNoEmail.wcBuyerEmail = null;
      triggerHandlerWithNoEmail.currentAmount = '100.50';
      mockEmailListener.getEmail.mockReturnValue(null);

      triggerHandlerWithNoEmail.onSDKFieldFocus('cardNumber');

      expect(mockAuthenticator.authenticate).not.toHaveBeenCalled();

      // Restore
      global.wc_mercadopago_supertoken_trigger_handler_params.current_user_email = 'test@example.com';
    });

    test('Given all conditions are met, When onSDKFieldFocus() is called with cardNumber field, Then should trigger authentication', async () => {
      triggerHandler.onSDKFieldFocus('cardNumber');

      expect(mockAuthenticator.authenticate).toHaveBeenCalledWith('100.50', 'buyer@example.com');
      expect(triggerHandler.isAuthenticating).toBe(true);
      
      await Promise.resolve();
      
      expect(triggerHandler.isAuthenticating).toBe(false);
    });

    test('Given all conditions are met, When onSDKFieldFocus() is called with cardholderName field, Then should trigger authentication', () => {
      triggerHandler.onSDKFieldFocus('cardholderName');

      expect(mockAuthenticator.authenticate).toHaveBeenCalledWith('100.50', 'buyer@example.com');
    });

    test('Given all conditions are met, When onSDKFieldFocus() is called with expirationDate field, Then should trigger authentication', () => {
      triggerHandler.onSDKFieldFocus('expirationDate');

      expect(mockAuthenticator.authenticate).toHaveBeenCalledWith('100.50', 'buyer@example.com');
    });

    test('Given all conditions are met, When onSDKFieldFocus() is called with securityCode field, Then should trigger authentication', () => {
      triggerHandler.onSDKFieldFocus('securityCode');

      expect(mockAuthenticator.authenticate).toHaveBeenCalledWith('100.50', 'buyer@example.com');
    });

    test('Given authentication is triggered, When promise resolves, Then should set isAuthenticating to false', async () => {
      mockAuthenticator.authenticate.mockResolvedValue(true);

      triggerHandler.onSDKFieldFocus('cardNumber');

      expect(triggerHandler.isAuthenticating).toBe(true);

      await Promise.resolve();

      expect(triggerHandler.isAuthenticating).toBe(false);
    });

    test('Given authentication is triggered, When promise rejects, Then should set isAuthenticating to false', async () => {
      const rejectPromise = Promise.reject(new Error('Authentication failed')).catch(() => {});
      mockAuthenticator.authenticate.mockReturnValue(rejectPromise);

      triggerHandler.onSDKFieldFocus('cardNumber');

      expect(triggerHandler.isAuthenticating).toBe(true);

      // Wait for promise to be handled by the .finally() in onTriggerSDK
      await new Promise(resolve => setTimeout(resolve, 15));

      expect(triggerHandler.isAuthenticating).toBe(false);
    });
  });

  describe('shouldAutoTriggerOnSDKFocus()', () => {
    test('Given wallet button is active, When shouldAutoTriggerOnSDKFocus() is called, Then should return false', () => {
      document.body.innerHTML += '<div class="mp-wallet-button-container"></div>';

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(false);
    });

    test('Given wallet button is not active, When shouldAutoTriggerOnSDKFocus() is called, Then should return true', () => {
      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(true);
    });

    test('Given isAuthenticating is true, When shouldAutoTriggerOnSDKFocus() is called, Then should return false', () => {
      triggerHandler.isAuthenticating = true;

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(false);
    });

    test('Given isAuthenticating is false, When shouldAutoTriggerOnSDKFocus() is called, Then should return true', () => {
      triggerHandler.isAuthenticating = false;

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(true);
    });

    test('Given user closed modal, When shouldAutoTriggerOnSDKFocus() is called, Then should return false', () => {
      mockAuthenticator.isUserClosedModal.mockReturnValue(true);

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(false);
    });

    test('Given user did not close modal, When shouldAutoTriggerOnSDKFocus() is called, Then should return true', () => {
      mockAuthenticator.isUserClosedModal.mockReturnValue(false);

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(true);
    });

    test('Given all conditions are false (wallet inactive, not authenticating, modal not closed), When shouldAutoTriggerOnSDKFocus() is called, Then should return true', () => {
      triggerHandler.isAuthenticating = false;
      mockAuthenticator.isUserClosedModal.mockReturnValue(false);

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(true);
    });

    test('Given wallet button is active AND isAuthenticating is true, When shouldAutoTriggerOnSDKFocus() is called, Then should return false (wallet button check first)', () => {
      document.body.innerHTML += '<div class="mp-wallet-button-container"></div>';
      triggerHandler.isAuthenticating = true;

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(false);
    });

    test('Given wallet button is active AND user closed modal, When shouldAutoTriggerOnSDKFocus() is called, Then should return false', () => {
      document.body.innerHTML += '<div class="mp-wallet-button-container"></div>';
      mockAuthenticator.isUserClosedModal.mockReturnValue(true);

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(false);
    });

    test('Given isAuthenticating is true AND user closed modal, When shouldAutoTriggerOnSDKFocus() is called, Then should return false (isAuthenticating check first)', () => {
      triggerHandler.isAuthenticating = true;
      mockAuthenticator.isUserClosedModal.mockReturnValue(true);

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(false);
    });

    test('Given all three conditions are blocking (wallet active, authenticating, modal closed), When shouldAutoTriggerOnSDKFocus() is called, Then should return false', () => {
      document.body.innerHTML += '<div class="mp-wallet-button-container"></div>';
      triggerHandler.isAuthenticating = true;
      mockAuthenticator.isUserClosedModal.mockReturnValue(true);

      const result = triggerHandler.shouldAutoTriggerOnSDKFocus();

      expect(result).toBe(false);
    });
  });

  describe('onTriggerSDK()', () => {
    test('Given valid parameters, When onTriggerSDK() is called, Then should set isAuthenticating to true immediately', () => {
      expect(triggerHandler.isAuthenticating).toBe(false);

      triggerHandler.onTriggerSDK('buyer@example.com', 'cardNumber');

      expect(triggerHandler.isAuthenticating).toBe(true);
    });

    test('Given valid parameters, When onTriggerSDK() is called, Then should call authenticate with correct arguments', () => {
      triggerHandler.currentAmount = '250.75';

      triggerHandler.onTriggerSDK('buyer@example.com', 'cardNumber');

      expect(mockAuthenticator.authenticate).toHaveBeenCalledWith('250.75', 'buyer@example.com');
      expect(mockAuthenticator.authenticate).toHaveBeenCalledTimes(1);
    });

    test('Given authentication succeeds, When onTriggerSDK() promise resolves, Then should set isAuthenticating to false', async () => {
      mockAuthenticator.authenticate.mockResolvedValue(true);

      triggerHandler.onTriggerSDK('buyer@example.com', 'cardNumber');

      expect(triggerHandler.isAuthenticating).toBe(true);

      await Promise.resolve();

      expect(triggerHandler.isAuthenticating).toBe(false);
    });

    test('Given authentication fails, When onTriggerSDK() promise rejects, Then should set isAuthenticating to false', async () => {
      const rejectPromise = Promise.reject(new Error('Auth failed')).catch(() => {});
      mockAuthenticator.authenticate.mockReturnValue(rejectPromise);

      triggerHandler.onTriggerSDK('buyer@example.com', 'cardNumber');

      expect(triggerHandler.isAuthenticating).toBe(true);

      await new Promise(resolve => setTimeout(resolve, 15));

      expect(triggerHandler.isAuthenticating).toBe(false);
    });

    test('Given different field names, When onTriggerSDK() is called multiple times sequentially, Then should call authenticate for each', async () => {
      mockAuthenticator.authenticate.mockResolvedValue(true);

      triggerHandler.currentAmount = '100.00';

      triggerHandler.onTriggerSDK('buyer@example.com', 'cardNumber');
      await Promise.resolve();

      triggerHandler.onTriggerSDK('buyer@example.com', 'cardholderName');
      await Promise.resolve();

      expect(mockAuthenticator.authenticate).toHaveBeenCalledTimes(2);
    });

    test('Given onTriggerSDK is called, When authenticate throws error, Then should still reset isAuthenticating in finally block', async () => {
      const rejectPromise = Promise.reject(new Error('Immediate error')).catch(() => {});
      mockAuthenticator.authenticate.mockReturnValue(rejectPromise);

      triggerHandler.onTriggerSDK('buyer@example.com', 'cardNumber');

      expect(triggerHandler.isAuthenticating).toBe(true);

      await new Promise(resolve => setTimeout(resolve, 15));

      expect(triggerHandler.isAuthenticating).toBe(false);
    });
  });

  describe('walletButtonIsActive()', () => {
    test('Given wallet button container exists in DOM, When walletButtonIsActive() is called, Then should return true', () => {
      document.body.innerHTML += '<div class="mp-wallet-button-container"></div>';

      const result = triggerHandler.walletButtonIsActive();

      expect(result).toBe(true);
    });

    test('Given wallet button container does not exist in DOM, When walletButtonIsActive() is called, Then should return false', () => {
      const result = triggerHandler.walletButtonIsActive();

      expect(result).toBe(false);
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
      // The triggerHandler was created in beforeEach with CURRENT_USER_EMAIL = 'test@example.com'
      triggerHandler.wcBuyerEmail = null;
      mockEmailListener.getEmail.mockReturnValue(null);

      const result = triggerHandler.getBuyerEmail();

      // Should return the CURRENT_USER_EMAIL set during instantiation
      expect(result).toBe(triggerHandler.CURRENT_USER_EMAIL);
      expect(triggerHandler.CURRENT_USER_EMAIL).toBe('test@example.com');
    });

    test('Given all email sources are null/empty, When getBuyerEmail() is called, Then should return CURRENT_USER_EMAIL from params', () => {
      // The triggerHandler was created in beforeEach with CURRENT_USER_EMAIL = 'test@example.com'
      triggerHandler.wcBuyerEmail = null;
      mockEmailListener.getEmail.mockReturnValue('');

      const result = triggerHandler.getBuyerEmail();

      // Should return empty string from emailListener (truthy check) or CURRENT_USER_EMAIL
      expect(result).toBe(triggerHandler.CURRENT_USER_EMAIL);
      expect(triggerHandler.CURRENT_USER_EMAIL).toBe('test@example.com');
    });
  });

  describe('reset()', () => {
    test('Given isAuthenticating is true, When reset() is called, Then should set isAuthenticating to false', () => {
      triggerHandler.isAuthenticating = true;

      triggerHandler.reset();

      expect(triggerHandler.isAuthenticating).toBe(false);
    });

    test('Given isAuthenticating is false, When reset() is called, Then should keep isAuthenticating as false', () => {
      triggerHandler.isAuthenticating = false;

      triggerHandler.reset();

      expect(triggerHandler.isAuthenticating).toBe(false);
    });
  });

  describe('Integration: onSDKFieldFocus() flow', () => {
    test('Given all conditions are met, When user focuses on cardNumber field, Then should complete full authentication flow', async () => {
      triggerHandler.currentAmount = '100.00';
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      mockAuthenticator.authenticate.mockResolvedValue(true);

      triggerHandler.onSDKFieldFocus('cardNumber');

      expect(triggerHandler.isAuthenticating).toBe(true);
      expect(mockAuthenticator.authenticate).toHaveBeenCalledWith('100.00', 'buyer@example.com');

      await Promise.resolve();

      expect(triggerHandler.isAuthenticating).toBe(false);
    });

    test('Given wallet button is active, When user focuses on any field, Then should not trigger authentication at all', () => {
      document.body.innerHTML += '<div class="mp-wallet-button-container"></div>';
      triggerHandler.currentAmount = '100.00';
      triggerHandler.wcBuyerEmail = 'buyer@example.com';

      triggerHandler.onSDKFieldFocus('cardNumber');

      expect(mockAuthenticator.authenticate).not.toHaveBeenCalled();
      expect(triggerHandler.isAuthenticating).toBe(false);
    });

    test('Given authentication is in progress, When user focuses on another field, Then should not trigger another authentication', () => {
      triggerHandler.currentAmount = '100.00';
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      triggerHandler.isAuthenticating = true;

      triggerHandler.onSDKFieldFocus('cardNumber');

      expect(mockAuthenticator.authenticate).not.toHaveBeenCalled();
    });
  });
});

