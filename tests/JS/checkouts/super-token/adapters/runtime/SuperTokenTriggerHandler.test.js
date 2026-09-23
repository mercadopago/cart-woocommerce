const { SuperTokenTriggerHandler } = require('@super-token/adapters/runtime/SuperTokenTriggerHandler');

const CURRENT_USER_EMAIL = 'default@store.com';

const buildAuthenticator = (overrides = {}) => ({
  getAmountUsed: jest.fn().mockReturnValue(null),
  getEmailUsed: jest.fn().mockReturnValue(null),
  formatAmount: jest.fn((amount) => amount),
  reset: jest.fn(),
  setSuperTokenValidation: jest.fn(),
  getAccountPaymentMethods: jest.fn().mockResolvedValue(null),
  ...overrides,
});

const buildEmailListener = (overrides = {}) => ({
  getEmail: jest.fn().mockReturnValue(null),
  isValid: jest.fn().mockReturnValue(true),
  onEmailChange: jest.fn(),
  setupEmailChangeHandlers: jest.fn(),
  ...overrides,
});

const buildPaymentMethods = (overrides = {}) => ({
  SUPER_TOKEN_STYLES: { PAYMENT_METHOD_LIST: 'mp-payment-method-list' },
  reset: jest.fn(),
  renderAccountPaymentMethods: jest.fn().mockResolvedValue(undefined),
  getStoredPaymentMethods: jest.fn().mockReturnValue([]),
  hasStoredPaymentMethods: jest.fn().mockReturnValue(false),
  hideSuperTokenError: jest.fn(),
  unmountCardForm: jest.fn(),
  mountCardForm: jest.fn(),
  getSelectedPreloadedPaymentMethod: jest.fn().mockReturnValue(null),
  hasCheckoutError: jest.fn().mockReturnValue(false),
  selectLastPaymentMethodChoosen: jest.fn(),
  selectPreloadedPaymentMethod: jest.fn().mockResolvedValue(undefined),
  storeSelectedPreloadedPaymentMethod: jest.fn(),
  getActivePaymentMethod: jest.fn().mockReturnValue(null),
  getPaymentMethodElementFromDOM: jest.fn().mockReturnValue(null),
  showPaymentMethodDetails: jest.fn(),
  paymentMethodIdentifier: jest.fn().mockReturnValue('visa'),
  getLastPaymentMethodChoosen: jest.fn().mockReturnValue(null),
  deselectAllPaymentMethods: jest.fn(),
  hideAllPaymentMethodDetails: jest.fn(),
  unmountActiveSecurityCodeInstance: jest.fn(),
  clearActivePaymentMethod: jest.fn(),
  ...overrides,
});

const buildErrorHandler = (overrides = {}) => ({ handleError: jest.fn().mockReturnValue('CODE'), ...overrides });

const buildMetrics = (overrides = {}) => ({
  sendMetric: jest.fn(),
  sendStaleCacheMetrics: jest.fn().mockResolvedValue(undefined),
  ...overrides,
});

const build = ({
  authenticator = buildAuthenticator(),
  emailListener = buildEmailListener(),
  paymentMethods = buildPaymentMethods(),
  errorHandler = buildErrorHandler(),
  metrics = buildMetrics(),
  currentUserEmail = CURRENT_USER_EMAIL,
} = {}) => {
  const handler = new SuperTokenTriggerHandler(
    authenticator,
    emailListener,
    paymentMethods,
    errorHandler,
    metrics,
    currentUserEmail,
  );
  return { handler, authenticator, emailListener, paymentMethods, errorHandler, metrics };
};

describe('SuperTokenTriggerHandler', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    delete window.mpCustomCheckoutHandler;
  });

  describe('getBuyerEmail', () => {
    it('Given a stored buyer e-mail, When read, Then it returns it trimmed without touching the listener', () => {
      const { handler, emailListener } = build();
      handler.wcBuyerEmail = '  buyer@example.com  ';

      expect(handler.getBuyerEmail()).toBe('buyer@example.com');
      expect(emailListener.getEmail).not.toHaveBeenCalled();
    });

    it('Given no stored e-mail, When read, Then it falls back to the listener e-mail', () => {
      const emailListener = buildEmailListener({ getEmail: jest.fn().mockReturnValue('listener@example.com') });
      const { handler } = build({ emailListener });

      expect(handler.getBuyerEmail()).toBe('listener@example.com');
    });

    it('Given no stored and no listener e-mail, When read, Then it falls back to the current user e-mail', () => {
      const { handler } = build();

      expect(handler.getBuyerEmail()).toBe(CURRENT_USER_EMAIL);
    });
  });

  describe('amountHasChanged', () => {
    it('Given no amount was used yet, When queried, Then it returns false', () => {
      const { handler } = build();
      handler.currentAmount = '10.00';

      expect(handler.amountHasChanged()).toBe(false);
    });

    it('Given the current amount differs from the used one, When queried, Then it returns true', () => {
      const authenticator = buildAuthenticator({ getAmountUsed: jest.fn().mockReturnValue('5.00') });
      const { handler } = build({ authenticator });
      handler.currentAmount = '10.00';

      expect(handler.amountHasChanged()).toBe(true);
    });

    it('Given the current amount equals the used one, When queried, Then it returns false', () => {
      const authenticator = buildAuthenticator({ getAmountUsed: jest.fn().mockReturnValue('10.00') });
      const { handler } = build({ authenticator });
      handler.currentAmount = '10.00';

      expect(handler.amountHasChanged()).toBe(false);
    });
  });

  describe('emailHasChanged', () => {
    it('Given the buyer e-mail differs from the used one, When queried, Then it returns true', () => {
      const authenticator = buildAuthenticator({ getEmailUsed: jest.fn().mockReturnValue('old@example.com') });
      const { handler } = build({ authenticator });
      handler.wcBuyerEmail = 'new@example.com';

      expect(handler.emailHasChanged()).toBe(true);
    });

    it('Given no e-mail was used yet, When queried, Then it returns false', () => {
      const { handler } = build();
      handler.wcBuyerEmail = 'buyer@example.com';

      expect(handler.emailHasChanged()).toBe(false);
    });
  });

  describe('isDifferentEmail', () => {
    it('Given a different e-mail than the stored one, When compared, Then it returns true', () => {
      const { handler } = build();
      handler.wcBuyerEmail = 'buyer@example.com';

      expect(handler.isDifferentEmail('other@example.com')).toBe(true);
    });

    it('Given the same e-mail as the stored one, When compared, Then it returns false', () => {
      const { handler } = build();
      handler.wcBuyerEmail = 'buyer@example.com';

      expect(handler.isDifferentEmail('buyer@example.com')).toBe(false);
    });
  });

  describe('custom checkout radio detection', () => {
    it('Given the Blocks radio exists, When resolving the element, Then it returns it', () => {
      document.body.innerHTML = '<input value="woo-mercado-pago-custom" />';
      const { handler } = build();

      expect(handler.getCustomCheckoutRadioElement()).not.toBeNull();
      expect(handler.customCheckoutIsEnable()).toBe(true);
      expect(handler.isClassicCheckout()).toBe(false);
    });

    it('Given only the Classic radio exists, When queried, Then classic checkout is detected', () => {
      document.body.innerHTML = '<input id="payment_method_woo-mercado-pago-custom" />';
      const { handler } = build();

      expect(handler.isClassicCheckout()).toBe(true);
      expect(handler.customCheckoutIsEnable()).toBe(true);
    });

    it('Given a checked radio, When active is queried, Then it returns true', () => {
      document.body.innerHTML = '<input id="payment_method_woo-mercado-pago-custom" type="radio" checked />';
      const { handler } = build();

      expect(handler.customCheckoutIsActive()).toBe(true);
    });

    it('Given no radio at all, When queried, Then it is neither enabled nor active', () => {
      const { handler } = build();

      expect(handler.customCheckoutIsEnable()).toBe(false);
      expect(handler.customCheckoutIsActive()).toBeUndefined();
    });
  });

  describe('lastException', () => {
    it('Given an exception is stored, When queried, Then it is readable and reported as present', () => {
      const { handler } = build();
      const error = new Error('boom');

      handler.setLastException(error);

      expect(handler.getLastException()).toBe(error);
      expect(handler.hasLastException()).toBe(true);
    });

    it('Given no exception, When queried, Then none is reported', () => {
      const { handler } = build();

      expect(handler.hasLastException()).toBe(false);
    });
  });

  describe('resetFlow', () => {
    it('Given a flow reset, When run, Then it resets both the authenticator and the controller', () => {
      const { handler, authenticator, paymentMethods } = build();

      handler.resetFlow();

      expect(authenticator.reset).toHaveBeenCalledTimes(1);
      expect(paymentMethods.reset).toHaveBeenCalledTimes(1);
    });
  });

  describe('isSuperTokenPaymentMethodsLoaded', () => {
    it('Given the controller has stored methods, When queried, Then it returns true', () => {
      const paymentMethods = buildPaymentMethods({ hasStoredPaymentMethods: jest.fn().mockReturnValue(true) });
      const { handler } = build({ paymentMethods });

      expect(handler.isSuperTokenPaymentMethodsLoaded()).toBe(true);
    });
  });

  describe('dispatchStaleCacheMetricsOnce', () => {
    it('Given repeated calls, When dispatched, Then the stale cache metrics fire only once', () => {
      const { handler, metrics } = build();

      handler.dispatchStaleCacheMetricsOnce();
      handler.dispatchStaleCacheMetricsOnce();

      expect(metrics.sendStaleCacheMetrics).toHaveBeenCalledTimes(1);
    });
  });

  describe('cancelLoad (CancelLoad use case)', () => {
    it('Given an in-flight load, When cancelled, Then it bumps the generation, clears fetching and resets methods', () => {
      const { handler, paymentMethods } = build();
      handler.isFetchingPaymentMethods = true;
      handler.loadGeneration = 3;

      handler.cancelLoad();

      expect(handler.loadGeneration).toBe(4);
      expect(handler.isFetchingPaymentMethods).toBe(false);
      expect(paymentMethods.reset).toHaveBeenCalledTimes(1);
    });
  });

  describe('loadSuperToken (LoadSuperToken use case)', () => {
    it('Given a fetch already in flight for the same amount and e-mail, When loaded, Then it short-circuits without fetching', async () => {
      const { handler, authenticator, emailListener } = build();
      handler.isFetchingPaymentMethods = true;

      await handler.loadSuperToken('10.00');

      expect(handler.currentAmount).toBe('10.00');
      expect(authenticator.getAccountPaymentMethods).not.toHaveBeenCalled();
      expect(emailListener.onEmailChange).not.toHaveBeenCalled();
    });

    it('Given the amount changed, When loaded, Then it resets the flow and reports the amount-change metric', async () => {
      const authenticator = buildAuthenticator({ getAmountUsed: jest.fn().mockReturnValue('5.00') });
      const { handler, metrics } = build({ authenticator });

      await handler.loadSuperToken('10.00');

      expect(authenticator.reset).toHaveBeenCalled();
      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_reset_on_amount_change', 'true', '');
    });

    it('Given methods are already loaded, When loaded, Then it re-renders the stored methods without fetching', async () => {
      const stored = [{ token: 'PM_1' }];
      const paymentMethods = buildPaymentMethods({
        hasStoredPaymentMethods: jest.fn().mockReturnValue(true),
        getStoredPaymentMethods: jest.fn().mockReturnValue(stored),
      });
      const { handler, authenticator } = build({ paymentMethods });

      await handler.loadSuperToken('10.00');

      expect(paymentMethods.renderAccountPaymentMethods).toHaveBeenCalledWith(stored, '10.00');
      expect(authenticator.getAccountPaymentMethods).not.toHaveBeenCalled();
    });
  });

  describe('fetchAndRenderSuperTokenPaymentMethods (FetchAndRenderPaymentMethods use case)', () => {
    it('Given no buyer e-mail, When fetched, Then it reports the skipped-no-email metric and does not fetch', async () => {
      const { handler, authenticator, metrics } = build({ currentUserEmail: '' });

      await handler.fetchAndRenderSuperTokenPaymentMethods();

      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_skipped_no_email', 'true', '');
      expect(authenticator.getAccountPaymentMethods).not.toHaveBeenCalled();
    });

    it('Given an invalid buyer e-mail, When fetched, Then it reports the skipped-invalid-email metric and does not fetch', async () => {
      const emailListener = buildEmailListener({ isValid: jest.fn().mockReturnValue(false) });
      const { handler, authenticator, metrics } = build({ emailListener });
      handler.wcBuyerEmail = 'invalid';

      await handler.fetchAndRenderSuperTokenPaymentMethods();

      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_skipped_invalid_email', 'true', '');
      expect(authenticator.getAccountPaymentMethods).not.toHaveBeenCalled();
    });

    it('Given a valid e-mail and returned methods, When fetched, Then it captures the e-mail, fetches and renders', async () => {
      const methods = [{ token: 'PM_1' }];
      const authenticator = buildAuthenticator({ getAccountPaymentMethods: jest.fn().mockResolvedValue(methods) });
      const { handler, paymentMethods, metrics } = build({ authenticator });
      handler.wcBuyerEmail = 'buyer@example.com';
      handler.currentAmount = '10.00';

      await handler.fetchAndRenderSuperTokenPaymentMethods();

      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_email_captured', 'true', '');
      expect(authenticator.getAccountPaymentMethods).toHaveBeenCalledWith('10.00', 'buyer@example.com');
      expect(paymentMethods.renderAccountPaymentMethods).toHaveBeenCalledWith(methods, '10.00');
      expect(handler.isFetchingPaymentMethods).toBe(false);
    });

    it('Given the load generation is bumped mid-flight, When fetched, Then the stale result is dropped and nothing renders', async () => {
      const authenticator = buildAuthenticator({
        getAccountPaymentMethods: jest.fn().mockImplementation(() => {
          handler.loadGeneration++;
          return Promise.resolve([{ token: 'PM_1' }]);
        }),
      });
      const { handler, paymentMethods } = build({ authenticator });
      handler.wcBuyerEmail = 'buyer@example.com';
      handler.currentAmount = '10.00';

      await handler.fetchAndRenderSuperTokenPaymentMethods();

      expect(paymentMethods.renderAccountPaymentMethods).not.toHaveBeenCalled();
    });
  });

  describe('restorePreloadedPaymentMethod (RestorePreloadedPaymentMethod use case)', () => {
    it('Given no preloaded method but a checkout error, When restored, Then it re-selects the last chosen method', async () => {
      const paymentMethods = buildPaymentMethods({ hasCheckoutError: jest.fn().mockReturnValue(true) });
      const { handler } = build({ paymentMethods });

      await handler.restorePreloadedPaymentMethod();

      expect(paymentMethods.selectLastPaymentMethodChoosen).toHaveBeenCalledTimes(1);
    });

    it('Given a preloaded method that leaves no active method, When restored, Then it reports the active-method-not-set metric', async () => {
      const paymentMethods = buildPaymentMethods({
        getSelectedPreloadedPaymentMethod: jest.fn().mockReturnValue({ token: 'PM_1' }),
        getActivePaymentMethod: jest.fn().mockReturnValue(null),
      });
      const { handler, metrics } = build({ paymentMethods });

      await handler.restorePreloadedPaymentMethod();

      expect(paymentMethods.selectPreloadedPaymentMethod).toHaveBeenCalledTimes(1);
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'super_token_restore_active_method_not_set',
        'true',
        'mp_super_token_restore_error',
      );
    });
  });

  describe('resetSuperTokenOnError (ResetFlow use case)', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => jest.useRealTimers());

    it('Given the Super Token checkout is not active, When reset on error, Then it does nothing', () => {
      const { handler, paymentMethods } = build();

      handler.resetSuperTokenOnError();

      expect(paymentMethods.deselectAllPaymentMethods).not.toHaveBeenCalled();
    });

    it('Given the Super Token checkout is active, When reset on error, Then it clears the selection and resets the checkout', () => {
      document.body.innerHTML = '<input id="mp_checkout_type" value="super_token" />';
      const { handler, paymentMethods } = build();

      handler.resetSuperTokenOnError();

      expect(paymentMethods.deselectAllPaymentMethods).toHaveBeenCalledTimes(1);
      expect(paymentMethods.unmountActiveSecurityCodeInstance).toHaveBeenCalledTimes(1);
      expect(paymentMethods.hideSuperTokenError).toHaveBeenCalledTimes(1);
      expect(handler.savedInstallments).toBeNull();
    });
  });

  describe('resetCustomCheckout (ResetCustomCheckout use case)', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => jest.useRealTimers());

    it('Given a reset, When run, Then it hides the error, invalidates the token and clears the cache', () => {
      const { handler, authenticator, paymentMethods } = build();

      handler.resetCustomCheckout(true);

      expect(paymentMethods.hideSuperTokenError).toHaveBeenCalledTimes(1);
      expect(authenticator.setSuperTokenValidation).toHaveBeenCalledWith(false);
      expect(authenticator.reset).toHaveBeenCalledTimes(1);
    });

    it('Given stored methods, When reset, Then it remounts the card form', () => {
      const paymentMethods = buildPaymentMethods({ hasStoredPaymentMethods: jest.fn().mockReturnValue(true) });
      const { handler } = build({ paymentMethods });

      handler.resetCustomCheckout(true);

      expect(paymentMethods.unmountCardForm).toHaveBeenCalledTimes(1);
      expect(paymentMethods.mountCardForm).toHaveBeenCalledTimes(1);
    });
  });

  describe('ensureEmailListenerRegistered (EnsureEmailListenerRegistered use case)', () => {
    it('Given not yet listening, When registered, Then it wires the change handlers once and marks as listening', () => {
      const { handler, emailListener } = build();

      handler.ensureEmailListenerRegistered();

      expect(emailListener.onEmailChange).toHaveBeenCalledTimes(1);
      expect(emailListener.setupEmailChangeHandlers).toHaveBeenCalledTimes(1);
      expect(handler.isAlreadyListeningForm).toBe(true);
    });

    it('Given already listening, When registered again, Then it does nothing', () => {
      const { handler, emailListener } = build();
      handler.isAlreadyListeningForm = true;

      handler.ensureEmailListenerRegistered();

      expect(emailListener.onEmailChange).not.toHaveBeenCalled();
    });

    it('Given a valid distinct e-mail change on a known buyer, When the callback fires, Then it resets on e-mail change', async () => {
      jest.useFakeTimers();
      const { handler, emailListener, metrics } = build();
      handler.currentAmount = '10.00';
      handler.wcBuyerEmail = 'old@example.com';

      handler.ensureEmailListenerRegistered();
      const callback = emailListener.onEmailChange.mock.calls[0][0];
      await callback('new@example.com', true);

      expect(handler.wcBuyerEmail).toBe('new@example.com');
      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_reset_on_email_change', 'true', '');
      jest.useRealTimers();
    });
  });

  describe('finalizeResetTail', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => jest.useRealTimers());

    it('Given a stored exception, When the tail runs, Then it removes the spinner, restores and defers the error handling', async () => {
      window.mpCustomCheckoutHandler = {
        cardForm: { removeLoadSpinner: jest.fn() },
        eventHandler: { hideCheckoutClassicLoader: jest.fn() },
      };
      const { handler, errorHandler } = build();
      const error = new Error('boom');
      handler.setLastException(error);

      handler.finalizeResetTail();
      await jest.runAllTimersAsync();

      expect(window.mpCustomCheckoutHandler.cardForm.removeLoadSpinner).toHaveBeenCalledTimes(1);
      expect(window.mpCustomCheckoutHandler.eventHandler.hideCheckoutClassicLoader).toHaveBeenCalledTimes(1);
      expect(errorHandler.handleError).toHaveBeenCalledWith(error);
      expect(handler.getLastException()).toBeNull();
    });

    it('Given the restore throws, When the tail runs, Then it reports the restore-error metric and does not block', async () => {
      const paymentMethods = buildPaymentMethods({
        getSelectedPreloadedPaymentMethod: jest.fn(() => {
          throw new Error('restore down');
        }),
      });
      const { handler, metrics } = build({ paymentMethods });

      handler.finalizeResetTail();
      await jest.runAllTimersAsync();

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'super_token_restore_error',
        'restore down',
        'mp_super_token_restore_error',
      );
    });
  });
});
