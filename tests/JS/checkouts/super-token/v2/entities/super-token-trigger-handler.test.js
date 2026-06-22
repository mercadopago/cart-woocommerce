const { resolveAlias } = require('../../../../helpers/path-resolver');
const { loadFile } = require('../../../../helpers/load-file');
const superTokenTriggerHandlerPath = resolveAlias(`assets/js/checkouts/super-token/${global.SUPER_TOKEN_VERSION}/entities/super-token-trigger-handler.js`);

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

    Object.defineProperty(global, 'CustomEvent', {
      value: global.CustomEvent || window.CustomEvent,
      enumerable: true,
      configurable: true,
      writable: true,
    });

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
      isValid: jest.fn().mockReturnValue(true),
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
      clearActivePaymentMethod: jest.fn(),
      storePaymentMethodsInMemory: jest.fn(),
      getActivePaymentMethod: jest.fn().mockReturnValue(null),
      getPaymentMethodElementFromDOM: jest.fn().mockReturnValue(null),
      showPaymentMethodDetails: jest.fn(),
      paymentMethodIdentifier: jest.fn().mockReturnValue(''),
      getLastPaymentMethodChoosen: jest.fn().mockReturnValue(null),
      storeSelectedPreloadedPaymentMethod: jest.fn(),
      getSelectedPreloadedPaymentMethod: jest.fn().mockReturnValue(null),
      selectPreloadedPaymentMethod: jest.fn(),
      SUPER_TOKEN_STYLES: { PAYMENT_METHOD_LIST: 'mp-super-token-payment-methods-list' },
    };

    mockErrorHandler = {
      handleError: jest.fn(),
    };

    mockMetrics = {
      sendMetric: jest.fn(),
      sendStaleCacheMetrics: jest.fn().mockResolvedValue(undefined),
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

  describe('cancelLoad()', () => {
    test('Given triggerHandler, When cancelLoad() is called, Then should increment loadGeneration, set isFetchingPaymentMethods=false and call reset()', () => {
      triggerHandler.isFetchingPaymentMethods = true;
      const generationBefore = triggerHandler.loadGeneration;

      triggerHandler.cancelLoad();

      expect(triggerHandler.loadGeneration).toBe(generationBefore + 1);
      expect(triggerHandler.isFetchingPaymentMethods).toBe(false);
      expect(mockPaymentMethods.reset).toHaveBeenCalledTimes(1);
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
      mockEmailListener.isValid.mockReturnValue(true);
      const mockPMs = [{ id: 'visa', type: 'credit_card' }];
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(mockPMs);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockAuthenticator.getAccountPaymentMethods).toHaveBeenCalledWith('100.00', 'buyer@example.com');
      expect(mockPaymentMethods.renderAccountPaymentMethods).toHaveBeenCalledWith(mockPMs, '100.00');
    });

    test('Given valid email but no payment methods returned, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should not render', async () => {
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      triggerHandler.currentAmount = '100.00';
      mockEmailListener.isValid.mockReturnValue(true);
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(null);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockPaymentMethods.renderAccountPaymentMethods).not.toHaveBeenCalled();
    });

    test('Given cancelLoad() is called during API fetch, When fetchAndRenderSuperTokenPaymentMethods() resolves, Then should not render', async () => {
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      triggerHandler.currentAmount = '100.00';
      const mockPMs = [{ id: 'visa', type: 'credit_card' }];
      mockAuthenticator.getAccountPaymentMethods.mockImplementation(async () => {
        triggerHandler.cancelLoad();
        return mockPMs;
      });

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockPaymentMethods.renderAccountPaymentMethods).not.toHaveBeenCalled();
    });

    test('Given no cancellation during fetch, When fetchAndRenderSuperTokenPaymentMethods() resolves, Then should render normally', async () => {
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      triggerHandler.currentAmount = '100.00';
      const mockPMs = [{ id: 'visa', type: 'credit_card' }];
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(mockPMs);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockPaymentMethods.renderAccountPaymentMethods).toHaveBeenCalledWith(mockPMs, '100.00');
    });

    test('Given invalid email from DOM, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should not call SDK and emit skip metric', async () => {
      triggerHandler.wcBuyerEmail = 'rua ferreropolis14';
      mockEmailListener.isValid.mockReturnValue(false);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockAuthenticator.getAccountPaymentMethods).not.toHaveBeenCalled();
      expect(mockMetrics.sendMetric).toHaveBeenCalledWith('super_token_skipped_invalid_email', 'true', '');
    });

    test('Given invalid email from DOM, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should not emit super_token_email_captured metric', async () => {
      triggerHandler.wcBuyerEmail = 'carlo';
      mockEmailListener.isValid.mockReturnValue(false);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockMetrics.sendMetric).not.toHaveBeenCalledWith('super_token_email_captured', 'true', '');
    });

    test('Given invalid CURRENT_USER_EMAIL, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should not call SDK', async () => {
      triggerHandler.wcBuyerEmail = null;
      triggerHandler.CURRENT_USER_EMAIL = 'nome sem arroba';
      mockEmailListener.getEmail.mockReturnValue(null);
      mockEmailListener.isValid.mockReturnValue(false);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockAuthenticator.getAccountPaymentMethods).not.toHaveBeenCalled();
      expect(mockMetrics.sendMetric).toHaveBeenCalledWith('super_token_skipped_invalid_email', 'true', '');
    });

    test('Given valid CURRENT_USER_EMAIL when DOM has no email, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should call SDK normally', async () => {
      triggerHandler.wcBuyerEmail = null;
      triggerHandler.CURRENT_USER_EMAIL = 'user@mercadopago.com';
      mockEmailListener.getEmail.mockReturnValue(null);
      mockEmailListener.isValid.mockReturnValue(true);
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(null);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockAuthenticator.getAccountPaymentMethods).toHaveBeenCalled();
    });

    test('Given email without @ from DOM, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should not call SDK', async () => {
      triggerHandler.wcBuyerEmail = 'iremol10 hotmail.com';
      mockEmailListener.isValid.mockReturnValue(false);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockAuthenticator.getAccountPaymentMethods).not.toHaveBeenCalled();
      expect(mockMetrics.sendMetric).toHaveBeenCalledWith('super_token_skipped_invalid_email', 'true', '');
    });

    test('Given duplicated pasted email, When fetchAndRenderSuperTokenPaymentMethods() is called, Then should not call SDK', async () => {
      triggerHandler.wcBuyerEmail = 'user@example.comuser@example.com';
      mockEmailListener.isValid.mockReturnValue(false);

      await triggerHandler.fetchAndRenderSuperTokenPaymentMethods();

      expect(mockAuthenticator.getAccountPaymentMethods).not.toHaveBeenCalled();
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

    test('Given loadSuperToken is called, When super token loads successfully, Then should call sendStaleCacheMetrics once', async () => {
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      mockAuthenticator.formatAmount.mockReturnValue('100.00');
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(null);

      await triggerHandler.loadSuperToken('100.00');

      expect(mockMetrics.sendStaleCacheMetrics).toHaveBeenCalledTimes(1);
    });

    test('Given loadSuperToken is called multiple times, When cacheMetricsDispatched is true, Then should NOT call sendStaleCacheMetrics again', async () => {
      triggerHandler.wcBuyerEmail = 'buyer@example.com';
      mockAuthenticator.formatAmount.mockReturnValue('100.00');
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(null);

      await triggerHandler.loadSuperToken('100.00');
      await triggerHandler.loadSuperToken('100.00');

      expect(mockMetrics.sendStaleCacheMetrics).toHaveBeenCalledTimes(1);
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

  describe('email-change callback (this.currentAmount guard — fix #1)', () => {
    // Captures the callback registered via wcEmailListener.onEmailChange inside loadSuperToken
    // and exercises the guard that decides whether an email change re-renders the super token.
    async function registerEmailCallback(firstAmount) {
      mockPaymentMethods.hasStoredPaymentMethods.mockReturnValue(false);
      mockAuthenticator.getAccountPaymentMethods.mockResolvedValue(null);
      await triggerHandler.loadSuperToken(firstAmount);
      return mockEmailListener.onEmailChange.mock.calls[0][0];
    }

    test('Given the listener was registered when the first amount was falsy, When currentAmount is later set and a different valid email is entered, Then it uses this.currentAmount and resets (closure regression guard)', async () => {
      // formatAmount(undefined) -> null: the OLD code closed over this falsy param and bailed forever
      mockAuthenticator.formatAmount.mockReturnValue(null);
      const emailCallback = await registerEmailCallback(undefined);
      expect(typeof emailCallback).toBe('function');

      // a later load sets a fresh amount on the instance
      triggerHandler.currentAmount = '150.00';
      triggerHandler.wcBuyerEmail = 'old@example.com';
      const resetSpy = jest.spyOn(triggerHandler, 'resetCustomCheckout').mockImplementation(() => {});

      await emailCallback('new@example.com', true);

      // with the closure bug this would bail (param was null); with this.currentAmount it resets
      expect(resetSpy).toHaveBeenCalled();
      expect(triggerHandler.wcBuyerEmail).toBe('new@example.com');
    });

    test('Given this.currentAmount is null, When a valid different email is entered, Then it does not reset (guard holds)', async () => {
      mockAuthenticator.formatAmount.mockReturnValue('100.00');
      const emailCallback = await registerEmailCallback('100.00');

      triggerHandler.currentAmount = null;
      triggerHandler.wcBuyerEmail = 'old@example.com';
      const resetSpy = jest.spyOn(triggerHandler, 'resetCustomCheckout').mockImplementation(() => {});

      await emailCallback('new@example.com', true);

      expect(resetSpy).not.toHaveBeenCalled();
    });

    test('Given an invalid email, When the callback runs, Then it does not reset regardless of currentAmount', async () => {
      mockAuthenticator.formatAmount.mockReturnValue('100.00');
      const emailCallback = await registerEmailCallback('100.00');

      triggerHandler.currentAmount = '100.00';
      triggerHandler.wcBuyerEmail = 'old@example.com';
      const resetSpy = jest.spyOn(triggerHandler, 'resetCustomCheckout').mockImplementation(() => {});

      await emailCallback('not-an-email', false);

      expect(resetSpy).not.toHaveBeenCalled();
    });
  });

  describe('resetSuperTokenOnError()', () => {
    test('Given mp_checkout_type is not super_token, When resetSuperTokenOnError() is called, Then should do nothing', () => {
      document.body.innerHTML = `<input id="mp_checkout_type" value="custom" />`;

      triggerHandler.resetSuperTokenOnError();

      expect(mockPaymentMethods.deselectAllPaymentMethods).not.toHaveBeenCalled();
    });

    test('Given mp_checkout_type is super_token, When resetSuperTokenOnError() is called, Then should always do full reset regardless of preserveSelection', () => {
      document.body.innerHTML = `<input id="mp_checkout_type" value="super_token" />`;

      triggerHandler.resetSuperTokenOnError(false);

      expect(mockPaymentMethods.deselectAllPaymentMethods).toHaveBeenCalledTimes(1);
      expect(mockPaymentMethods.hideAllPaymentMethodDetails).toHaveBeenCalledTimes(1);
      expect(mockPaymentMethods.unmountActiveSecurityCodeInstance).toHaveBeenCalledTimes(1);
      expect(mockPaymentMethods.reset).toHaveBeenCalledTimes(1);

      jest.clearAllMocks();

      triggerHandler.resetSuperTokenOnError(true);

      expect(mockPaymentMethods.deselectAllPaymentMethods).toHaveBeenCalledTimes(1);
      expect(mockPaymentMethods.hideAllPaymentMethodDetails).toHaveBeenCalledTimes(1);
      expect(mockPaymentMethods.unmountActiveSecurityCodeInstance).toHaveBeenCalledTimes(1);
      expect(mockPaymentMethods.reset).toHaveBeenCalledTimes(1);
    });

    test('Given preserveSelection is false (default), When resetSuperTokenOnError() is called, Then should NOT store selected payment method', () => {
      document.body.innerHTML = `<input id="mp_checkout_type" value="super_token" />`;
      const lastMethod = { id: 'visa', token: 'TOKEN123' };
      mockPaymentMethods.getLastPaymentMethodChoosen.mockReturnValue(lastMethod);

      triggerHandler.resetSuperTokenOnError();

      expect(mockPaymentMethods.storeSelectedPreloadedPaymentMethod).not.toHaveBeenCalled();
    });

    test('Given savedInstallments has a stale value from a previous cycle, When resetSuperTokenOnError(false) is called, Then should clear savedInstallments unconditionally', () => {
      document.body.innerHTML = `<input id="mp_checkout_type" value="super_token" />`;
      triggerHandler.savedInstallments = '6';

      triggerHandler.resetSuperTokenOnError(false);

      expect(triggerHandler.savedInstallments).toBeNull();
    });

    test('Given preserveSelection is true (recoverable error), When resetSuperTokenOnError() is called, Then should store last payment method and installments before reset', () => {
      document.body.innerHTML = `
        <input id="mp_checkout_type" value="super_token" />
        <input id="cardInstallments" value="3" />
      `;
      const lastMethod = { id: 'visa', token: 'TOKEN123', card: { card_number: { last_four_digits: '1234' } } };
      mockPaymentMethods.getLastPaymentMethodChoosen.mockReturnValue(lastMethod);

      triggerHandler.resetSuperTokenOnError(true);

      expect(mockPaymentMethods.storeSelectedPreloadedPaymentMethod).toHaveBeenCalledWith(lastMethod);
      expect(triggerHandler.savedInstallments).toBe('3');
    });

    test('Given mp_checkout_type is super_token and payment method list exists, When resetSuperTokenOnError() is called, Then should scroll to payment method list', () => {
      const scrollIntoViewMock = jest.fn();
      document.body.innerHTML = `
        <input id="mp_checkout_type" value="super_token" />
        <div class="mp-super-token-payment-methods-list"></div>
      `;
      document.querySelector('.mp-super-token-payment-methods-list').scrollIntoView = scrollIntoViewMock;

      triggerHandler.resetSuperTokenOnError();

      expect(scrollIntoViewMock).toHaveBeenCalledWith({ behavior: 'smooth' });
    });
  });

  describe('restorePreloadedPaymentMethod()', () => {
    let localHandler;
    let LocalTriggerHandler;

    beforeAll(() => {
      LocalTriggerHandler = loadFile(superTokenTriggerHandlerPath, 'MPSuperTokenTriggerHandler', {
        ...global,
        Event,
      });
    });

    beforeEach(() => {
      localHandler = new LocalTriggerHandler(
        mockAuthenticator,
        mockEmailListener,
        mockPaymentMethods,
        mockErrorHandler,
        mockMetrics
      );
    });

    test('Given no preloaded method and no checkout error, When restorePreloadedPaymentMethod() is called, Then should do nothing', async () => {
      await localHandler.restorePreloadedPaymentMethod();

      expect(mockPaymentMethods.selectLastPaymentMethodChoosen).not.toHaveBeenCalled();
      expect(mockPaymentMethods.selectPreloadedPaymentMethod).not.toHaveBeenCalled();
      expect(mockMetrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given no preloaded method but checkout error exists, When restorePreloadedPaymentMethod() is called, Then should call selectLastPaymentMethodChoosen', async () => {
      mockPaymentMethods.hasCheckoutError.mockReturnValue(true);

      await localHandler.restorePreloadedPaymentMethod();

      expect(mockPaymentMethods.selectLastPaymentMethodChoosen).toHaveBeenCalledTimes(1);
      expect(mockPaymentMethods.selectPreloadedPaymentMethod).not.toHaveBeenCalled();
    });

    test('Given preloaded method but selectPreloadedPaymentMethod does not set activeMethod, When restorePreloadedPaymentMethod() is called, Then should send active_method_not_set metric and clear savedInstallments', async () => {
      mockPaymentMethods.getSelectedPreloadedPaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.selectPreloadedPaymentMethod.mockResolvedValue();
      localHandler.savedInstallments = '3';

      await localHandler.restorePreloadedPaymentMethod();

      expect(mockPaymentMethods.storeSelectedPreloadedPaymentMethod).toHaveBeenCalledWith(null);
      expect(localHandler.savedInstallments).toBeNull();
      expect(mockMetrics.sendMetric).toHaveBeenCalledWith('super_token_restore_active_method_not_set', 'true', 'mp_super_token_restore_error');
      expect(mockPaymentMethods.showPaymentMethodDetails).not.toHaveBeenCalled();
    });

    test('Given preloaded method and activeMethod set but DOM element not found, When restorePreloadedPaymentMethod() is called, Then should send element_not_found metric', async () => {
      mockPaymentMethods.getSelectedPreloadedPaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.selectPreloadedPaymentMethod.mockResolvedValue();
      mockPaymentMethods.getActivePaymentMethod.mockReturnValue({ id: 'visa' });

      await localHandler.restorePreloadedPaymentMethod();

      expect(mockMetrics.sendMetric).toHaveBeenCalledWith('super_token_restore_element_not_found', 'true', 'mp_super_token_restore_error');
      expect(mockPaymentMethods.showPaymentMethodDetails).not.toHaveBeenCalled();
    });

    test('Given preloaded method with active element but no savedInstallments, When restorePreloadedPaymentMethod() is called, Then should show payment method details without restoring installments', async () => {
      const element = document.createElement('div');
      mockPaymentMethods.getSelectedPreloadedPaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.selectPreloadedPaymentMethod.mockResolvedValue();
      mockPaymentMethods.getActivePaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.getPaymentMethodElementFromDOM.mockReturnValue(element);

      await localHandler.restorePreloadedPaymentMethod();

      expect(mockPaymentMethods.showPaymentMethodDetails).toHaveBeenCalledWith(element);
      expect(mockMetrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given savedInstallments set but installments dropdown not in DOM, When restorePreloadedPaymentMethod() is called, Then should send dropdown_not_found metric', async () => {
      const element = document.createElement('div');
      mockPaymentMethods.getSelectedPreloadedPaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.selectPreloadedPaymentMethod.mockResolvedValue();
      mockPaymentMethods.getActivePaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.getPaymentMethodElementFromDOM.mockReturnValue(element);
      mockPaymentMethods.paymentMethodIdentifier.mockReturnValue('visa1234');
      localHandler.savedInstallments = '3';

      await localHandler.restorePreloadedPaymentMethod();

      expect(mockPaymentMethods.showPaymentMethodDetails).toHaveBeenCalledWith(element);
      expect(mockMetrics.sendMetric).toHaveBeenCalledWith('super_token_restore_installments_dropdown_not_found', 'true', 'mp_super_token_restore_error');
    });

    test('Given savedInstallments set but option value not in dropdown options, When restorePreloadedPaymentMethod() is called, Then should send option_not_found metric', async () => {
      const element = document.createElement('div');
      const dropdown = document.createElement('select');
      dropdown.id = 'mp-super-token-installments-select-visa1234';
      const option = document.createElement('option');
      option.value = '6';
      dropdown.appendChild(option);
      element.appendChild(dropdown);
      mockPaymentMethods.getSelectedPreloadedPaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.selectPreloadedPaymentMethod.mockResolvedValue();
      mockPaymentMethods.getActivePaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.getPaymentMethodElementFromDOM.mockReturnValue(element);
      mockPaymentMethods.paymentMethodIdentifier.mockReturnValue('visa1234');
      localHandler.savedInstallments = '3';

      await localHandler.restorePreloadedPaymentMethod();

      expect(mockMetrics.sendMetric).toHaveBeenCalledWith('super_token_restore_installment_option_not_found', 'true', 'mp_super_token_restore_error');
    });

    test('Given preloaded method, element found, savedInstallments with matching option, When restorePreloadedPaymentMethod() is called, Then should restore dropdown value, sync cardInstallments and dispatch change', async () => {
      const element = document.createElement('div');
      const dropdown = document.createElement('select');
      dropdown.id = 'mp-super-token-installments-select-visa1234';
      const option = document.createElement('option');
      option.value = '3';
      dropdown.appendChild(option);
      element.appendChild(dropdown);
      document.body.innerHTML += '<input id="cardInstallments" value="" />';
      mockPaymentMethods.getSelectedPreloadedPaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.selectPreloadedPaymentMethod.mockResolvedValue();
      mockPaymentMethods.getActivePaymentMethod.mockReturnValue({ id: 'visa' });
      mockPaymentMethods.getPaymentMethodElementFromDOM.mockReturnValue(element);
      mockPaymentMethods.paymentMethodIdentifier.mockReturnValue('visa1234');
      localHandler.savedInstallments = '3';
      const dispatchEventSpy = jest.spyOn(dropdown, 'dispatchEvent');

      await localHandler.restorePreloadedPaymentMethod();

      expect(mockPaymentMethods.showPaymentMethodDetails).toHaveBeenCalledWith(element);
      expect(dropdown.value).toBe('3');
      expect(document.getElementById('cardInstallments').value).toBe('3');
      expect(dispatchEventSpy).toHaveBeenCalledTimes(1);
      expect(mockMetrics.sendMetric).not.toHaveBeenCalled();
      expect(localHandler.savedInstallments).toBeNull();
    });
  });

  describe('resetCustomCheckout()', () => {
    let localHandler;
    let capturedSendMetric;
    let LocalTriggerHandler;

    beforeAll(() => {
      capturedSendMetric = jest.fn();
      LocalTriggerHandler = loadFile(superTokenTriggerHandlerPath, 'MPSuperTokenTriggerHandler', {
        ...global,
        sendMetric: capturedSendMetric,
      });
    });

    beforeEach(() => {
      capturedSendMetric.mockClear();
      delete global.window.mpCustomCheckoutHandler;
      localHandler = new LocalTriggerHandler(
        mockAuthenticator,
        mockEmailListener,
        mockPaymentMethods,
        mockErrorHandler,
        mockMetrics
      );
    });

    afterEach(() => {
      delete global.window.mpCustomCheckoutHandler;
    });

    test('Given mpCustomCheckoutHandler is absent and sendMetric is available, When resetCustomCheckout() is called, Then should send MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS metric', () => {
      localHandler.resetCustomCheckout();

      expect(capturedSendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS',
        'resetCustomCheckout',
        'mp_super_token_init_error'
      );
    });

    test('Given mpCustomCheckoutHandler is absent and sendMetric is available, When resetCustomCheckout() is called twice, Then should send metric only once', () => {
      localHandler.resetCustomCheckout();
      localHandler.resetCustomCheckout();

      expect(capturedSendMetric).toHaveBeenCalledTimes(1);
    });

    test('Given init block already reported the missing handler externally, When resetCustomCheckout() is called and handler is still absent, Then should send its own metric independently (split flag regression)', () => {
      capturedSendMetric('MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS', 'mp_super_token_init', 'mp_super_token_init_error');
      capturedSendMetric.mockClear();

      expect(localHandler.customHandlerMissingReportedOnReset).toBe(false);

      localHandler.resetCustomCheckout();

      expect(capturedSendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS',
        'resetCustomCheckout',
        'mp_super_token_init_error'
      );
      expect(localHandler.customHandlerMissingReportedOnReset).toBe(true);
    });

    test('Given mpCustomCheckoutHandler is absent and sendMetric is available, When resetCustomCheckout() is called, Then should set customHandlerMissingReportedOnReset to true', () => {
      localHandler.resetCustomCheckout();

      expect(localHandler.customHandlerMissingReportedOnReset).toBe(true);
    });

    test('Given mpCustomCheckoutHandler is absent but sendMetric is NOT available, When resetCustomCheckout() is called, Then should not set customHandlerMissingReportedOnReset', () => {
      const NoMetricClass = loadFile(superTokenTriggerHandlerPath, 'MPSuperTokenTriggerHandler', {
        ...global,
      });
      const noMetricHandler = new NoMetricClass(
        mockAuthenticator,
        mockEmailListener,
        mockPaymentMethods,
        mockErrorHandler,
        mockMetrics
      );

      noMetricHandler.resetCustomCheckout();

      expect(noMetricHandler.customHandlerMissingReportedOnReset).toBe(false);
    });

    test('Given mpCustomCheckoutHandler is present, When resetCustomCheckout() is called, Then should not send metric', () => {
      global.window.mpCustomCheckoutHandler = { cardForm: { createLoadSpinner: jest.fn() } };

      localHandler.resetCustomCheckout();

      expect(capturedSendMetric).not.toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS',
        expect.any(String),
        expect.any(String)
      );
    });
  });
});
