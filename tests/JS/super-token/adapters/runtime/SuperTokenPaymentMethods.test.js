const { SuperTokenPaymentMethods } = require('@super-token/adapters/runtime/SuperTokenPaymentMethods');

const buildParams = (overrides = {}) => ({
  yellow_wallet_path: 'yellow_wallet.png',
  yellow_money_path: 'yellow_money.png',
  white_card_path: 'white_card.png',
  payment_methods_list_text: 'Saved methods',
  payment_methods_list_alt_text: 'Saved methods list',
  last_digits_text: 'ending in',
  new_card_text: 'New card',
  account_money_text: 'Account money',
  account_money_wallet_with_investment_text: 'Wallet + investment',
  account_money_wallet_text: 'Wallet',
  account_money_investment_text: 'Investment',
  account_money_available_text: 'Available',
  interest_free_part_one_text: 'up to',
  interest_free_part_two_text: 'interest free',
  input_helper_message: {
    installments: {
      bank_interest_hint_text: 'bank interest',
      required: 'Select installments',
      interest_free_option_text: 'interest free',
    },
    securityCode: { invalid_type: 'Invalid CVV' },
  },
  input_title: { installments: 'Installments' },
  placeholders: { installments: 'Choose' },
  security_code_input_title_text: 'Security code',
  security_code_placeholder_text_3_digits: '123',
  security_code_placeholder_text_4_digits: '1234',
  security_code_tooltip_text_3_digits: '3-digit tooltip',
  security_code_tooltip_text_4_digits: '4-digit tooltip',
  site_id: 'MLB',
  currency: 'BRL',
  intl: 'pt-BR',
  mercado_pago_card_name: 'Mercado Pago card',
  mercado_pago_credit_card_name: 'Mercado Pago credit',
  consumer_credits_due_date: 'First due',
  mlb_installment_debit_auto_text: 'debit auto',
  interest_rate_mlb_text: 'rate',
  effective_total_cost_mlb_text: 'CET',
  iof_mlb_text: 'IOF',
  borrowed_amount_mlb_text: 'borrowed',
  per_month: 'per month',
  per_year: 'per year',
  cat_mlm_text: 'CAT',
  no_iva_text: 'no IVA',
  tna_mlm_text: 'TNA',
  system_amortization_mlm_text: 'amortization',
  cftea_mla_text: 'CFTEA',
  tna_mla_text: 'TNA MLA',
  tea_mla_text: 'TEA MLA',
  fixed_rate_text: 'fixed rate',
  mercadopago_privacy_policy: 'Privacy policy',
  new_mp_logo_path: 'new_mp_logo.png',
  mp_logo_blue_path: 'mp_blue.png',
  mp_logo_dark_path: 'mp_dark.png',
  saved_cards_title: 'Your cards',
  saved_card_title: 'Your card',
  mp_methods_title: 'MP methods',
  account_money_balance_text: 'Balance available',
  saved_payment_method_title: 'Saved method',
  current_user_email: 'buyer-test-user',
  months_abbreviated: { jan: 'jan' },
  payment_methods_thumbnails: {},
  payment_methods_order: 'cards_first',
  update_security_code_with_retry_error_text: 'CVV retry text',
  update_security_code_no_retry_error_text: 'CVV no-retry text',
  authorize_payment_method_with_retry_error_text: 'Auth retry text',
  authorize_payment_method_no_retry_error_text: 'Auth no-retry text',
  select_payment_method_error_text: 'Select error text',
  ...overrides,
});

const buildMetrics = (overrides = {}) => ({
  sendMetric: jest.fn(),
  registerSelectPaymentMethod: jest.fn(),
  getPaymentMethodLoadingTime: jest.fn(),
  fetchPaymentMethodSuccess: jest.fn(),
  hasEscNotExists: jest.fn(),
  fetchPaymentMethodSkipped: jest.fn(),
  fetchPaymentMethodTimeout: jest.fn(),
  getPaymentMethodFail: jest.fn(),
  errorToMountCVVField: jest.fn(),
  updateSecurityCodeGetCardIdSuccess: jest.fn(),
  updateSecurityCodeCardTokenCreated: jest.fn(),
  updateSecurityCodePseudotokenUpdated: jest.fn(),
  updateSecurityCodeSuccess: jest.fn(),
  errorToUpdateSecurityCode: jest.fn(),
  errorToExcludeRecaptchaFromPreValidation: jest.fn(),
  captchaFieldToggledOnPreValidation: jest.fn(),
  errorToSubmitWithoutInstallmentSelected: jest.fn(),
  getSdkInstanceId: jest.fn().mockReturnValue('sdk-instance-1'),
  errorToRenderAccountPaymentMethods: jest.fn(),
  ...overrides,
});

const buildSdkField = () => {
  const handlers = {};
  const field = {
    handlers,
    mount: jest.fn(() => field),
    on: jest.fn((event, cb) => {
      handlers[event] = cb;
      return field;
    }),
    update: jest.fn(() => field),
    unmount: jest.fn(),
    focus: jest.fn(),
    blur: jest.fn(),
  };
  return field;
};

const buildSdk = (overrides = {}) => ({
  getAccountPaymentMethod: jest.fn(),
  getAccountPaymentMethods: jest.fn(),
  getCardId: jest.fn(),
  updatePseudotoken: jest.fn().mockResolvedValue(undefined),
  fields: {
    create: jest.fn(),
    createCardToken: jest.fn(),
  },
  ...overrides,
});

const buildEmailListener = (overrides = {}) => ({
  isValid: jest.fn().mockReturnValue(true),
  getEmail: jest.fn().mockReturnValue('buyer-test-user'),
  onEmailChange: jest.fn(),
  ...overrides,
});

const build = ({
  sdk = buildSdk(),
  metrics = buildMetrics(),
  params = buildParams(),
  renderSavedMethods = jest.fn(),
  emailListener = buildEmailListener(),
} = {}) => {
  const controller = new SuperTokenPaymentMethods(sdk, metrics, params, renderSavedMethods, emailListener);
  return { controller, sdk, metrics, params, renderSavedMethods, emailListener };
};

const creditCard = (overrides = {}) => ({
  id: 'visa',
  token: 'tok_visa',
  name: 'Visa',
  thumbnail: 'visa.png',
  type: 'credit_card',
  card: { card_number: { last_four_digits: '1234' } },
  issuer: { name: 'Visa Bank' },
  security_code_settings: { mode: 'mandatory', length: 3 },
  has_esc: true,
  installments: [],
  ...overrides,
});

const accountMoney = (overrides = {}) => ({
  id: 'account_money',
  token: 'tok_am',
  name: 'Account money',
  thumbnail: 'am.png',
  type: 'account_money',
  has_account_money: true,
  has_account_money_invested: false,
  ...overrides,
});

describe('SuperTokenPaymentMethods', () => {
  beforeAll(() => {
    // jsdom does not implement scrollIntoView; the notice-render primitive calls it.
    window.HTMLElement.prototype.scrollIntoView = jest.fn();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    delete window.jQuery;
    delete window.mpEventHandler;
    delete window.MPCheckoutFieldsDispatcher;
    delete window.sendMetric;
    delete window.mpCustomCheckoutHandler;
  });

  describe('state accessors', () => {
    it('Given a super token, When stored, Then it is read back', () => {
      const { controller } = build();

      controller.setSuperToken('ST_1');

      expect(controller.getSuperToken()).toBe('ST_1');
    });

    it('Given an amount, When stored, Then it is read back', () => {
      const { controller } = build();

      controller.storeAmount('10.00');

      expect(controller.getAmount()).toBe('10.00');
    });

    it('Given methods stored in memory, When queried, Then presence and contents are reported', () => {
      const { controller } = build();
      const methods = [creditCard()];

      controller.storePaymentMethodsInMemory(methods);

      expect(controller.getStoredPaymentMethods()).toBe(methods);
      expect(controller.hasStoredPaymentMethods()).toBe(true);
    });

    it('Given an active method, When stored and cleared, Then active and last-chosen track the legacy rules', () => {
      const { controller } = build();
      const card = creditCard();

      controller.storeActivePaymentMethod(card);
      expect(controller.getActivePaymentMethod()).toBe(card);
      expect(controller.getLastPaymentMethodChoosen()).toBe(card);

      controller.clearActivePaymentMethod();
      expect(controller.getActivePaymentMethod()).toBeNull();
      expect(controller.getLastPaymentMethodChoosen()).toBe(card);
    });
  });

  describe('paymentMethodIdentifier', () => {
    it('Given a card, When identified, Then it combines id and last four digits', () => {
      const { controller } = build();

      expect(controller.paymentMethodIdentifier(creditCard())).toBe('visa1234');
    });

    it('Given null, When identified, Then it returns an empty string', () => {
      const { controller } = build();

      expect(controller.paymentMethodIdentifier(null)).toBe('');
    });

    it('Given account money without a card, When identified, Then it returns just the id', () => {
      const { controller } = build();

      expect(controller.paymentMethodIdentifier(accountMoney())).toBe('account_money');
    });
  });

  describe('convertErrorCodeToErrorMessage', () => {
    it('Given a mapped code within retry budget, When converted, Then it returns the with-retry copy', () => {
      const { controller } = build();

      expect(controller.convertErrorCodeToErrorMessage('UPDATE_SECURITY_CODE_ERROR')).toBe('CVV retry text');
    });

    it('Given the retry budget is exhausted, When converted, Then it returns the no-retry copy and reports the limit metric', () => {
      const { controller, metrics } = build();

      controller.convertErrorCodeToErrorMessage('UPDATE_SECURITY_CODE_ERROR');
      controller.convertErrorCodeToErrorMessage('UPDATE_SECURITY_CODE_ERROR');
      const message = controller.convertErrorCodeToErrorMessage('UPDATE_SECURITY_CODE_ERROR');

      expect(message).toBe('CVV no-retry text');
      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_retry_limit_reached', 'UPDATE_SECURITY_CODE_ERROR', '');
    });

    it('Given an unmapped code, When converted, Then it returns the generic copy', () => {
      const { controller } = build();

      expect(controller.convertErrorCodeToErrorMessage('SOMETHING_ELSE')).toBe('CVV retry text');
    });
  });

  describe('security code reference flags', () => {
    it('Given a reference set true then false, When verified, Then it tracks the last write', () => {
      const { controller } = build();
      const card = creditCard();

      controller.setSecurityCodeReferenceTrue(card);
      expect(controller.verifyIsSecurityCodeReferenceTrue(card)).toBe(true);

      controller.setSecurityCodeReferenceFalse(card);
      expect(controller.verifyIsSecurityCodeReferenceTrue(card)).toBe(false);
    });
  });

  describe('checkout type + error surface primitives', () => {
    it('Given the checkout type field, When set, Then its value is updated', () => {
      document.body.innerHTML = '<input id="mp_checkout_type" value="custom" />';
      const { controller } = build();

      controller.setCheckoutType('super_token');

      expect(document.querySelector('#mp_checkout_type').value).toBe('super_token');
    });

    it('Given a payment methods list, When an error is shown then hidden, Then the notice is added and removed', () => {
      document.body.innerHTML = '<div class="mp-super-token-payment-methods-list"></div>';
      const { controller } = build();

      controller.showSuperTokenError('boom');
      expect(document.querySelector('#mp-fast-payments-error')).not.toBeNull();

      controller.hideSuperTokenError();
      expect(document.querySelector('#mp-fast-payments-error')).toBeNull();
    });

    it('Given a checkout error notice, When queried, Then hasCheckoutError is true', () => {
      document.body.innerHTML = '<andes-notice id="mp-fast-payments-error"></andes-notice>';
      const { controller } = build();

      expect(controller.hasCheckoutError()).toBe(true);
    });
  });

  describe('onSelectSuperTokenPaymentMethod (SelectSavedPaymentMethod delegation)', () => {
    const renderSelectableMethod = (paymentMethod, controller) => {
      document.body.innerHTML = `
        <input id="mp_checkout_type" value="custom" />
        <input id="paymentMethodId" />
        <input id="paymentTypeId" />
        <input id="cardTokenId" />
      `;
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(paymentMethod);
      element.classList.add('mp-super-token-payment-method');
      element.dataset.type = paymentMethod.type;
      document.body.appendChild(element);
      return element;
    };

    it('Given an unselected method, When selected, Then the use case runs the selection sequence over the instance', async () => {
      const { controller, metrics } = build();
      const method = accountMoney();
      const element = renderSelectableMethod(method, controller);

      await controller.onSelectSuperTokenPaymentMethod(element, method);

      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_withdraw', 'false', '');
      expect(metrics.registerSelectPaymentMethod).toHaveBeenCalledWith('account_money');
      expect(metrics.fetchPaymentMethodSkipped).toHaveBeenCalledWith('account_money', 'not_card');
      expect(element.classList.contains('mp-super-token-payment-method__selected')).toBe(true);
      expect(document.querySelector('#mp_checkout_type').value).toBe('super_token');
      expect(document.getElementById('paymentMethodId').value).toBe('account_money');
    });

    it('Given an already-selected method, When selected again, Then the use case short-circuits without emitting metrics', async () => {
      const { controller, metrics } = build();
      const method = accountMoney();
      const element = renderSelectableMethod(method, controller);
      element.classList.add('mp-super-token-payment-method__selected');

      await controller.onSelectSuperTokenPaymentMethod(element, method);

      expect(metrics.sendMetric).not.toHaveBeenCalled();
      expect(metrics.registerSelectPaymentMethod).not.toHaveBeenCalled();
    });
  });

  describe('handleWithEscPaymentMethod', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => jest.useRealTimers());

    const buildElement = () => {
      const element = document.createElement('article');
      document.body.appendChild(element);
      return element;
    };

    it('Given an ESC card requiring a fresh fetch, When handled, Then it fetches, updates the list and reports success', async () => {
      const updated = creditCard({ security_code_settings: { mode: 'not_required', length: 3 } });
      const sdk = buildSdk({ getAccountPaymentMethod: jest.fn().mockResolvedValue({ data: updated }) });
      const { controller, metrics } = build({ sdk });
      const card = creditCard();
      controller.storePaymentMethodsInMemory([card]);
      const element = buildElement();

      const result = await controller.handleWithEscPaymentMethod(card, element);

      expect(sdk.getAccountPaymentMethod).toHaveBeenCalledWith(null, 'tok_visa');
      expect(result).toBe(updated);
      expect(controller.getStoredPaymentMethods()[0]).toBe(updated);
      expect(metrics.fetchPaymentMethodSuccess).toHaveBeenCalledWith('visa1234', false);
    });

    it('Given the ESC selection generation changes mid-fetch, When handled, Then the stale result is dropped', async () => {
      const sdk = buildSdk({
        getAccountPaymentMethod: jest.fn().mockImplementation(() => {
          controller.escSelectionGeneration++;
          return Promise.resolve({ data: creditCard() });
        }),
      });
      const { controller } = build({ sdk });
      const card = creditCard();
      const element = buildElement();

      const result = await controller.handleWithEscPaymentMethod(card, element);

      expect(result).toBeNull();
    });

    it('Given a card with unknown ESC state, When handled, Then it reports the missing-ESC metric and keeps the method', async () => {
      const { controller, metrics } = build();
      const card = creditCard({ has_esc: undefined });
      const element = buildElement();

      const result = await controller.handleWithEscPaymentMethod(card, element);

      expect(metrics.hasEscNotExists).toHaveBeenCalledWith('visa1234');
      expect(result).toBe(card);
    });

    it('Given a non-card method, When handled, Then it reports the skip reason and keeps the method', async () => {
      const { controller, metrics } = build();
      const method = accountMoney();
      const element = buildElement();

      const result = await controller.handleWithEscPaymentMethod(method, element);

      expect(metrics.fetchPaymentMethodSkipped).toHaveBeenCalledWith('account_money', 'not_card');
      expect(result).toBe(method);
    });

    it('Given the fetch times out, When handled, Then it reports the timeout and failure metrics', async () => {
      const sdk = buildSdk({ getAccountPaymentMethod: jest.fn(() => new Promise(() => {})) });
      const { controller, metrics } = build({ sdk });
      const card = creditCard();
      const element = buildElement();

      const pending = controller.handleWithEscPaymentMethod(card, element);
      await jest.advanceTimersByTimeAsync(5000);
      const result = await pending;

      expect(metrics.fetchPaymentMethodTimeout).toHaveBeenCalledWith('visa1234');
      expect(metrics.getPaymentMethodFail).toHaveBeenCalled();
      expect(result).toBe(card);
    });
  });

  describe('updateSecurityCode', () => {
    it('Given an active CVV-required method, When updated, Then it runs the SDK pipeline and reports each step', async () => {
      const sdk = buildSdk({
        getCardId: jest.fn().mockResolvedValue({ card_id: 'CARD_1' }),
        fields: {
          create: jest.fn(),
          createCardToken: jest.fn().mockResolvedValue({ id: 'CT_1' }),
        },
      });
      const { controller, metrics } = build({ sdk });
      controller.setSuperToken('ST_1');
      controller.storeActivePaymentMethod(creditCard());

      await controller.updateSecurityCode();

      expect(sdk.getCardId).toHaveBeenCalledWith('ST_1', 'tok_visa');
      expect(sdk.updatePseudotoken).toHaveBeenCalledWith('ST_1', 'tok_visa', 'CT_1');
      expect(metrics.updateSecurityCodeSuccess).toHaveBeenCalledTimes(1);
    });

    it('Given the SDK pipeline fails, When updated, Then it reports the error and throws the mapped code', async () => {
      const sdk = buildSdk({ getCardId: jest.fn().mockRejectedValue(new Error('sdk down')) });
      const { controller, metrics } = build({ sdk });
      controller.setSuperToken('ST_1');
      controller.storeActivePaymentMethod(creditCard());

      await expect(controller.updateSecurityCode()).rejects.toThrow('UPDATE_SECURITY_CODE_ERROR');
      expect(metrics.errorToUpdateSecurityCode).toHaveBeenCalled();
    });

    it('Given no active method, When updated, Then it does nothing', async () => {
      const sdk = buildSdk({ getCardId: jest.fn() });
      const { controller } = build({ sdk });

      await controller.updateSecurityCode();

      expect(sdk.getCardId).not.toHaveBeenCalled();
    });
  });

  describe('mountSecurityCodeField', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => jest.useRealTimers());

    const renderCvvContainer = (token) => {
      document.body.innerHTML = `
        <div id="mp-super-token-security-code-container-${token}">
          <label class="mp-super-token-security-code-label"></label>
          <div id="mp-super-token-security-code-input-${token}" class="mp-super-token-security-code-input"></div>
          <span class="mp-super-token-security-code-tooltip"></span>
          <div id="mp-input-with-tooltip-helper-error"></div>
          <span id="mp-super-token-security-code-error-message"></span>
        </div>
      `;
    };

    it('Given a CVV-required card, When mounted, Then it wires the SDK field and stores the ready instance', () => {
      const field = buildSdkField();
      const sdk = buildSdk({ fields: { create: jest.fn(() => field), createCardToken: jest.fn() } });
      const { controller, metrics } = build({ sdk });
      const card = creditCard();
      renderCvvContainer(card.token);

      controller.mountSecurityCodeField(card);
      jest.advanceTimersByTime(200);

      expect(sdk.fields.create).toHaveBeenCalledWith('securityCode', expect.objectContaining({ placeholder: '123' }));
      expect(field.mount).toHaveBeenCalledWith(`mp-super-token-security-code-input-${card.token}`);

      field.handlers.ready();
      expect(field.update).toHaveBeenCalledWith({ settings: card.security_code_settings });
      expect(controller.securityFieldsActiveInstance).toBe(field);
      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_cvv_field_ready', 'true', '');

      field.handlers.validityChange({ errorMessages: [] });
      expect(controller.verifyIsSecurityCodeReferenceTrue(card)).toBe(true);
      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_cvv_filled', 'true', '');
    });

    it('Given a method that does not require a CVV, When mounted, Then it is a no-op', () => {
      const sdk = buildSdk({ fields: { create: jest.fn(), createCardToken: jest.fn() } });
      const { controller } = build({ sdk });

      controller.mountSecurityCodeField(accountMoney());
      jest.advanceTimersByTime(200);

      expect(sdk.fields.create).not.toHaveBeenCalled();
    });
  });

  describe('excludeRecaptchaFromPreValidation', () => {
    it('Given a checkout form with a captcha, When the spy runs on a non-submit serialize, Then it disables the field around serialization', () => {
      document.body.innerHTML = '<form class="checkout"><input name="g-recaptcha-response" value="tok" /></form>';
      const originalSerialize = jest.fn(function () { return 'serialized'; });
      window.jQuery = { fn: { serialize: originalSerialize } };
      const { controller, metrics } = build();

      controller.excludeRecaptchaFromPreValidation();
      expect(window.jQuery.fn.serialize.__mpRecaptchaSpy).toBe(true);

      const form = document.querySelector('form.checkout');
      window.jQuery.fn.serialize.apply([form], []);

      expect(originalSerialize).toHaveBeenCalled();
      expect(metrics.captchaFieldToggledOnPreValidation).toHaveBeenCalledWith('disabled', 'g-recaptcha-response');
      expect(metrics.captchaFieldToggledOnPreValidation).toHaveBeenCalledWith('enabled', 'g-recaptcha-response');
      expect(form.querySelector('[name="g-recaptcha-response"]').disabled).toBe(false);
    });

    it('Given jQuery.fn.serialize is unavailable, When invoked, Then it reports the unavailable metric', () => {
      document.body.innerHTML = '<form class="checkout"><input name="g-recaptcha-response" value="tok" /></form>';
      const { controller, metrics } = build();

      controller.excludeRecaptchaFromPreValidation();

      expect(metrics.errorToExcludeRecaptchaFromPreValidation).toHaveBeenCalledWith(
        'serialize_unavailable',
        'jQuery.fn.serialize is not available',
      );
    });
  });

  describe('account money selection decoration', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => jest.useRealTimers());

    it('Given an account-money row, When decorated, Then a balance line is appended', () => {
      document.body.innerHTML = `
        <div id="mp-checkout-super-token-root">
          <article class="mp-super-token-account-money-row">
            <div class="mp-super-token-payment-method__content"></div>
          </article>
        </div>
      `;
      const { controller } = build();
      const row = document.querySelector('.mp-super-token-account-money-row');

      controller.applyAccountMoneySelectionDecoration(row);

      const balanceLine = document.querySelector('.mp-super-token-am-balance-text');
      expect(balanceLine).not.toBeNull();
      expect(balanceLine.textContent).toBe('Balance available');
    });

    it('Given a balance line, When removed, Then it is torn down after the animation window', () => {
      document.body.innerHTML = `
        <div id="mp-checkout-super-token-root">
          <article class="mp-super-token-account-money-row mp-super-token-account-money-row--open">
            <div class="mp-super-token-payment-method__content">
              <p class="mp-super-token-am-balance-text mp-super-token-am-balance-text--open">Balance available</p>
            </div>
          </article>
        </div>
      `;
      const { controller } = build();

      controller.removeAccountMoneyBalanceLine();
      const balanceLine = document.querySelector('.mp-super-token-am-balance-text');
      expect(balanceLine.dataset.closing).toBe('1');
      expect(balanceLine.classList.contains('mp-super-token-am-balance-text--open')).toBe(false);

      jest.advanceTimersByTime(controller.ACCOUNT_MONEY_ANIMATION_MS + 50);
      expect(document.querySelector('.mp-super-token-am-balance-text')).toBeNull();
    });
  });

  describe('DOM visibility primitives', () => {
    it('Given a wallet button and card flags, When hidden and shown, Then their display toggles', () => {
      document.body.innerHTML = `
        <div class="mp-wallet-button-container-wrapper"></div>
        <div class="mp-checkout-custom-card-flags"></div>
      `;
      const { controller } = build();

      controller.hideWalletButton();
      controller.hideCardFlags();
      expect(document.querySelector('.mp-wallet-button-container-wrapper').style.display).toBe('none');
      expect(document.querySelector('.mp-checkout-custom-card-flags').style.display).toBe('none');

      controller.showWalletButton();
      controller.showCardFlags();
      expect(document.querySelector('.mp-wallet-button-container-wrapper').style.display).toBe('flex');
      expect(document.querySelector('.mp-checkout-custom-card-flags').style.display).toBe('flex');
    });
  });

  const debitCard = (overrides = {}) => creditCard({ id: 'master_debit', token: 'tok_debit', type: 'debit_card', ...overrides });
  const prepaidCard = (overrides = {}) => creditCard({ id: 'prepaid', token: 'tok_prepaid', type: 'prepaid_card', ...overrides });
  const consumerCredits = (overrides = {}) => ({
    id: 'consumer_credits',
    token: 'tok_credits',
    name: 'Mercado Crédito',
    thumbnail: 'credits.png',
    type: 'digital_currency',
    credits_pricing_id: 'cp_1',
    installments: [],
    ...overrides,
  });

  describe('getSelectedPreloadedPaymentMethodFromActivePaymentMethods', () => {
    it('Given a preloaded selection present among active methods, When resolved, Then the matching active method is returned', () => {
      const { controller } = build();
      const card = creditCard();
      controller.storePaymentMethodsInMemory([accountMoney(), card]);
      controller.storeSelectedPreloadedPaymentMethod(creditCard());

      expect(controller.getSelectedPreloadedPaymentMethodFromActivePaymentMethods()).toBe(card);
    });

    it('Given no preloaded selection, When resolved, Then it returns undefined', () => {
      const { controller } = build();
      controller.storePaymentMethodsInMemory([creditCard()]);

      expect(controller.getSelectedPreloadedPaymentMethodFromActivePaymentMethods()).toBeUndefined();
    });
  });

  describe('selectPreloadedPaymentMethod', () => {
    it('Given no preloaded method among active methods, When invoked, Then it reports the not-found metric and does not select', async () => {
      const { controller, metrics } = build();
      const onSelect = jest.spyOn(controller, 'onSelectSuperTokenPaymentMethod').mockResolvedValue();
      controller.storePaymentMethodsInMemory([]);

      await controller.selectPreloadedPaymentMethod();

      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_preloaded_method_not_found', 'true', '');
      expect(onSelect).not.toHaveBeenCalled();
    });

    it('Given a preloaded method without a DOM element, When invoked, Then it does not store or select', async () => {
      const { controller } = build();
      const onSelect = jest.spyOn(controller, 'onSelectSuperTokenPaymentMethod').mockResolvedValue();
      const card = creditCard();
      controller.storePaymentMethodsInMemory([card]);
      controller.storeSelectedPreloadedPaymentMethod(card);

      await controller.selectPreloadedPaymentMethod();

      expect(controller.getActivePaymentMethod()).toBeNull();
      expect(onSelect).not.toHaveBeenCalled();
    });

    it('Given a preloaded method with its DOM element, When invoked, Then it becomes active and is selected', async () => {
      const { controller } = build();
      const onSelect = jest.spyOn(controller, 'onSelectSuperTokenPaymentMethod').mockResolvedValue();
      const card = creditCard();
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(card);
      document.body.appendChild(element);
      controller.storePaymentMethodsInMemory([card]);
      controller.storeSelectedPreloadedPaymentMethod(card);

      await controller.selectPreloadedPaymentMethod();

      expect(controller.getActivePaymentMethod()).toBe(card);
      expect(onSelect).toHaveBeenCalledWith(element, card);
    });
  });

  describe('selectLastPaymentMethodChoosen', () => {
    it('Given no last chosen method, When invoked, Then it does nothing', () => {
      const { controller } = build();
      const onSelect = jest.spyOn(controller, 'onSelectSuperTokenPaymentMethod').mockResolvedValue();

      controller.selectLastPaymentMethodChoosen();

      expect(onSelect).not.toHaveBeenCalled();
    });

    it('Given a last chosen method without a DOM element, When invoked, Then it does not select', () => {
      const { controller } = build();
      const onSelect = jest.spyOn(controller, 'onSelectSuperTokenPaymentMethod').mockResolvedValue();
      controller.storeActivePaymentMethod(creditCard());

      controller.selectLastPaymentMethodChoosen();

      expect(onSelect).not.toHaveBeenCalled();
    });

    it('Given a last chosen method with its DOM element, When invoked, Then it is re-selected', () => {
      const { controller } = build();
      const onSelect = jest.spyOn(controller, 'onSelectSuperTokenPaymentMethod').mockResolvedValue();
      const card = creditCard();
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(card);
      document.body.appendChild(element);
      controller.storeActivePaymentMethod(card);

      controller.selectLastPaymentMethodChoosen();

      expect(onSelect).toHaveBeenCalledWith(element, card);
    });
  });

  describe('installmentsWasSelected', () => {
    const renderInstallmentsSelect = (controller, card, value) => {
      const select = document.createElement('select');
      select.id = `mp-super-token-installments-select-${controller.paymentMethodIdentifier(card)}`;
      const option = document.createElement('option');
      option.value = value;
      select.appendChild(option);
      select.value = value;
      document.body.appendChild(select);
      return select;
    };

    it('Given an installments select with a value, When queried, Then it returns true', () => {
      const { controller } = build();
      const card = creditCard();
      renderInstallmentsSelect(controller, card, '3');

      expect(controller.installmentsWasSelected(card)).toBe(true);
    });

    it('Given an installments select with an empty value, When queried, Then it returns false', () => {
      const { controller } = build();
      const card = creditCard();
      renderInstallmentsSelect(controller, card, '');

      expect(controller.installmentsWasSelected(card)).toBe(false);
    });

    it('Given no installments select, When queried, Then it returns false', () => {
      const { controller } = build();

      expect(controller.installmentsWasSelected(creditCard())).toBe(false);
    });
  });

  describe('setInstallmentsErrorState', () => {
    const renderInstallmentsGroup = (controller, card) => {
      const identifier = controller.paymentMethodIdentifier(card);
      document.body.innerHTML = `
        <select id="mp-super-token-installments-select-${identifier}"></select>
        <label for="mp-super-token-installments-select-${identifier}"></label>
        <div id="mp-super-token-installments-error-${identifier}" style="display: none;"></div>
      `;
      return {
        select: document.getElementById(`mp-super-token-installments-select-${identifier}`),
        label: document.querySelector(`label[for="mp-super-token-installments-select-${identifier}"]`),
        error: document.getElementById(`mp-super-token-installments-error-${identifier}`),
      };
    };

    it('Given the installments group, When an error is set, Then error styling is applied', () => {
      const { controller } = build();
      const card = creditCard();
      const { select, label, error } = renderInstallmentsGroup(controller, card);

      controller.setInstallmentsErrorState(card, true);

      expect(error.style.display).toBe('flex');
      expect(select.classList.contains('mp-super-token-error')).toBe(true);
      expect(label.classList.contains('mp-super-token-label-error')).toBe(true);
    });

    it('Given an errored installments group, When the error is cleared, Then error styling is removed', () => {
      const { controller } = build();
      const card = creditCard();
      const { select, label, error } = renderInstallmentsGroup(controller, card);
      controller.setInstallmentsErrorState(card, true);

      controller.setInstallmentsErrorState(card, false);

      expect(error.style.display).toBe('none');
      expect(select.classList.contains('mp-super-token-error')).toBe(false);
      expect(label.classList.contains('mp-super-token-label-error')).toBe(false);
    });

    it('Given a missing installments group, When invoked, Then it is a no-op', () => {
      const { controller } = build();

      expect(() => controller.setInstallmentsErrorState(creditCard(), true)).not.toThrow();
    });
  });

  describe('forceSecurityCodeValidation', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => jest.useRealTimers());

    const renderSecurityContainer = (token, withError = false) => {
      const container = document.createElement('div');
      container.id = `mp-super-token-security-code-container-${token}`;
      if (withError) container.classList.add('error');
      document.body.appendChild(container);
      return container;
    };

    it('Given no security code container, When invoked, Then it does nothing', () => {
      const { controller } = build();
      const toggle = jest.spyOn(controller, 'toggleSecurityCodeErrorMessage');

      controller.forceSecurityCodeValidation(creditCard());

      expect(toggle).not.toHaveBeenCalled();
    });

    it('Given no active security field instance, When invoked, Then the invalid-type message is shown immediately', () => {
      const { controller } = build();
      const card = creditCard();
      renderSecurityContainer(card.token);
      controller.securityFieldsActiveInstance = null;
      const toggle = jest.spyOn(controller, 'toggleSecurityCodeErrorMessage').mockImplementation(() => {});

      controller.forceSecurityCodeValidation(card);

      expect(toggle).toHaveBeenCalledWith('invalid_type', card);
    });

    it('Given an active field and no error after blur, When invoked, Then it focuses, blurs and shows the invalid-type message', () => {
      const { controller } = build();
      const card = creditCard();
      renderSecurityContainer(card.token, false);
      const field = buildSdkField();
      controller.securityFieldsActiveInstance = field;
      const toggle = jest.spyOn(controller, 'toggleSecurityCodeErrorMessage').mockImplementation(() => {});

      controller.forceSecurityCodeValidation(card);
      jest.advanceTimersByTime(150);

      expect(field.focus).toHaveBeenCalled();
      expect(field.blur).toHaveBeenCalled();
      expect(toggle).toHaveBeenCalledWith('invalid_type', card);
    });

    it('Given an active field and an error after blur, When invoked, Then it does not show the invalid-type message', () => {
      const { controller } = build();
      const card = creditCard();
      renderSecurityContainer(card.token, true);
      controller.securityFieldsActiveInstance = buildSdkField();
      const toggle = jest.spyOn(controller, 'toggleSecurityCodeErrorMessage').mockImplementation(() => {});

      controller.forceSecurityCodeValidation(card);
      jest.advanceTimersByTime(150);

      expect(toggle).not.toHaveBeenCalled();
    });
  });

  describe('forceShowValidationErrors', () => {
    it('Given no active payment method, When invoked, Then it does nothing', () => {
      const { controller } = build();
      const forceCvv = jest.spyOn(controller, 'forceSecurityCodeValidation');

      controller.forceShowValidationErrors();

      expect(forceCvv).not.toHaveBeenCalled();
    });

    it('Given a non-card active method, When invoked, Then it does nothing', () => {
      const { controller } = build();
      const setError = jest.spyOn(controller, 'setInstallmentsErrorState');
      controller.storeActivePaymentMethod(accountMoney());

      controller.forceShowValidationErrors();

      expect(setError).not.toHaveBeenCalled();
    });

    it('Given a credit card missing its DOM element, When invoked, Then it does nothing', () => {
      const { controller } = build();
      const setError = jest.spyOn(controller, 'setInstallmentsErrorState');
      controller.storeActivePaymentMethod(creditCard());

      controller.forceShowValidationErrors();

      expect(setError).not.toHaveBeenCalled();
    });

    it('Given a credit card requiring CVV without a true reference and without installments, When invoked, Then it forces both validations and scrolls', () => {
      const { controller } = build();
      const card = creditCard();
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(card);
      document.body.appendChild(element);
      controller.storeActivePaymentMethod(card);
      const forceCvv = jest.spyOn(controller, 'forceSecurityCodeValidation').mockImplementation(() => {});
      const setError = jest.spyOn(controller, 'setInstallmentsErrorState').mockImplementation(() => {});
      const scrollSpy = jest.spyOn(element, 'scrollIntoView');

      controller.forceShowValidationErrors();

      expect(forceCvv).toHaveBeenCalledWith(card);
      expect(setError).toHaveBeenCalledWith(card, true);
      expect(scrollSpy).toHaveBeenCalledWith({ behavior: 'smooth' });
    });

    it('Given a credit card whose CVV reference is already true, When invoked, Then it does not force the CVV validation', () => {
      const { controller } = build();
      const card = creditCard();
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(card);
      document.body.appendChild(element);
      controller.storeActivePaymentMethod(card);
      controller.setSecurityCodeReferenceTrue(card);
      const forceCvv = jest.spyOn(controller, 'forceSecurityCodeValidation').mockImplementation(() => {});
      jest.spyOn(controller, 'setInstallmentsErrorState').mockImplementation(() => {});

      controller.forceShowValidationErrors();

      expect(forceCvv).not.toHaveBeenCalled();
    });
  });

  describe('isSelectedPaymentMethodValid', () => {
    const renderContainer = (token, { withError = false, helperVisible = false } = {}) => {
      const container = document.createElement('div');
      container.id = `mp-super-token-security-code-container-${token}`;
      if (withError) container.classList.add('error');
      const helper = document.createElement('div');
      helper.id = 'mp-input-with-tooltip-helper-error';
      helper.style.display = helperVisible ? 'flex' : 'none';
      container.appendChild(helper);
      document.body.appendChild(container);
      return container;
    };

    it('Given no active method, When validated, Then it returns false', () => {
      const { controller } = build();

      expect(controller.isSelectedPaymentMethodValid()).toBe(false);
    });

    it('Given account money is active, When validated, Then it returns true', () => {
      const { controller } = build();
      controller.storeActivePaymentMethod(accountMoney());

      expect(controller.isSelectedPaymentMethodValid()).toBe(true);
    });

    it('Given a prepaid card is active, When validated, Then it returns true', () => {
      const { controller } = build();
      controller.storeActivePaymentMethod(prepaidCard());

      expect(controller.isSelectedPaymentMethodValid()).toBe(true);
    });

    it('Given the new-card option is active, When validated, Then it returns true', () => {
      const { controller } = build();
      controller.storeActivePaymentMethod(creditCard({ id: 'new_card' }));

      expect(controller.isSelectedPaymentMethodValid()).toBe(true);
    });

    it('Given a credit card without its DOM element, When validated, Then it returns false', () => {
      const { controller } = build();
      controller.storeActivePaymentMethod(creditCard());

      expect(controller.isSelectedPaymentMethodValid()).toBe(false);
    });

    it('Given a credit card that does not require CVV, When validated, Then it returns true', () => {
      const { controller } = build();
      const card = creditCard({ security_code_settings: { mode: 'optional', length: 3 } });
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(card);
      document.body.appendChild(element);
      controller.storeActivePaymentMethod(card);

      expect(controller.isSelectedPaymentMethodValid()).toBe(true);
    });

    it('Given a CVV-required card without an active security field, When validated, Then it returns false', () => {
      const { controller } = build();
      const card = creditCard();
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(card);
      document.body.appendChild(element);
      controller.storeActivePaymentMethod(card);
      controller.securityFieldsActiveInstance = null;

      expect(controller.isSelectedPaymentMethodValid()).toBe(false);
    });

    it('Given a CVV-required card whose reference is not true, When validated, Then it returns false', () => {
      const { controller } = build();
      const card = creditCard();
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(card);
      document.body.appendChild(element);
      renderContainer(card.token);
      controller.storeActivePaymentMethod(card);
      controller.securityFieldsActiveInstance = buildSdkField();

      expect(controller.isSelectedPaymentMethodValid()).toBe(false);
    });

    it('Given a valid CVV-required card with a true reference and no error, When validated, Then it returns true', () => {
      const { controller } = build();
      const card = creditCard();
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(card);
      document.body.appendChild(element);
      renderContainer(card.token);
      controller.storeActivePaymentMethod(card);
      controller.securityFieldsActiveInstance = buildSdkField();
      controller.setSecurityCodeReferenceTrue(card);

      expect(controller.isSelectedPaymentMethodValid()).toBe(true);
    });

    it('Given a CVV-required card whose container shows an error, When validated, Then it returns false', () => {
      const { controller } = build();
      const card = creditCard();
      const element = document.createElement('article');
      element.id = controller.paymentMethodIdentifier(card);
      document.body.appendChild(element);
      renderContainer(card.token, { withError: true });
      controller.storeActivePaymentMethod(card);
      controller.securityFieldsActiveInstance = buildSdkField();
      controller.setSecurityCodeReferenceTrue(card);

      expect(controller.isSelectedPaymentMethodValid()).toBe(false);
    });
  });

  describe('validateInstallmentSelection', () => {
    const renderCardWithInstallments = (controller, card, value) => {
      const identifier = controller.paymentMethodIdentifier(card);
      const element = document.createElement('article');
      element.id = identifier;
      const select = document.createElement('select');
      select.id = `mp-super-token-installments-select-${identifier}`;
      const option = document.createElement('option');
      option.value = value;
      select.appendChild(option);
      select.value = value;
      element.appendChild(select);
      document.body.appendChild(element);
    };

    it('Given a credit card with installments dropdown and no selection, When validated, Then it reports the metric, forces errors and returns false', () => {
      const { controller, metrics } = build();
      const card = creditCard();
      renderCardWithInstallments(controller, card, '');
      controller.storeActivePaymentMethod(card);
      const forceErrors = jest.spyOn(controller, 'forceShowValidationErrors').mockImplementation(() => {});

      expect(controller.validateInstallmentSelection()).toBe(false);
      expect(metrics.errorToSubmitWithoutInstallmentSelected).toHaveBeenCalledWith('credit_card');
      expect(forceErrors).toHaveBeenCalled();
    });

    it('Given consumer credits with no installments selected, When validated, Then the metric is tagged consumer_credits', () => {
      const { controller, metrics } = build();
      const credits = consumerCredits();
      renderCardWithInstallments(controller, credits, '');
      controller.storeActivePaymentMethod(credits);
      jest.spyOn(controller, 'forceShowValidationErrors').mockImplementation(() => {});

      expect(controller.validateInstallmentSelection()).toBe(false);
      expect(metrics.errorToSubmitWithoutInstallmentSelected).toHaveBeenCalledWith('consumer_credits');
    });

    it('Given a credit card with an installment already selected, When validated, Then it returns true', () => {
      const { controller, metrics } = build();
      const card = creditCard();
      renderCardWithInstallments(controller, card, '3');
      controller.storeActivePaymentMethod(card);

      expect(controller.validateInstallmentSelection()).toBe(true);
      expect(metrics.errorToSubmitWithoutInstallmentSelected).not.toHaveBeenCalled();
    });

    it('Given no installments dropdown, When validated, Then it returns true', () => {
      const { controller } = build();
      controller.storeActivePaymentMethod(creditCard());

      expect(controller.validateInstallmentSelection()).toBe(true);
    });

    it('Given an unexpected failure while validating, When invoked, Then it reports a metric, forces errors and rethrows', () => {
      const { controller, metrics } = build();
      controller.storeActivePaymentMethod(creditCard());
      jest.spyOn(controller, 'paymentMethodIdentifier').mockImplementation(() => {
        throw new Error('boom');
      });
      const forceErrors = jest.spyOn(controller, 'forceShowValidationErrors').mockImplementation(() => {});

      expect(() => controller.validateInstallmentSelection()).toThrow('boom');
      expect(metrics.sendMetric).toHaveBeenCalledWith('error_to_validate_installment_selection', 'true', 'boom');
      expect(forceErrors).toHaveBeenCalled();
    });
  });

  describe('render orchestration (slice 6c)', () => {
    const appendCustomCheckoutRoot = () => {
      const root = document.createElement('div');
      root.id = 'mp-checkout-super-token-root';
      const parent = document.createElement('div');
      parent.appendChild(root);
      document.body.appendChild(parent);
      return root;
    };

    describe('getAccountPaymentMethods', () => {
      it('Given a super token, When fetching, Then it stores the token and returns the SDK response', async () => {
        const response = { data: [creditCard()] };
        const sdk = buildSdk({ getAccountPaymentMethods: jest.fn().mockResolvedValue(response) });
        const { controller } = build({ sdk });

        const result = await controller.getAccountPaymentMethods('ST_9');

        expect(controller.getSuperToken()).toBe('ST_9');
        expect(sdk.getAccountPaymentMethods).toHaveBeenCalledWith('ST_9');
        expect(result).toBe(response);
      });
    });

    describe('addMercadoPagoPrivacyPolicyFooter', () => {
      it('Given the checkout root, When adding the footer, Then it is prepended as the first child', () => {
        const root = appendCustomCheckoutRoot();
        root.appendChild(document.createElement('div'));
        const { controller } = build();

        controller.addMercadoPagoPrivacyPolicyFooter();

        const footer = root.firstChild;
        expect(footer.tagName).toBe('FOOTER');
        expect(footer.id).toBe('mp-super-token-privacy-policy-footer');
        expect(footer.classList.contains('mp-privacy-policy-footer')).toBe(true);
        expect(footer.querySelector('span').textContent).toBe('Privacy policy');
      });

      it('Given no checkout root, When adding the footer, Then it is a no-op', () => {
        const { controller } = build();

        expect(() => controller.addMercadoPagoPrivacyPolicyFooter()).not.toThrow();
        expect(document.querySelector('#mp-super-token-privacy-policy-footer')).toBeNull();
      });
    });

    describe('addHorizontalRow', () => {
      it('Given the checkout root, When adding the row, Then an <hr> is prepended as the first child', () => {
        const root = appendCustomCheckoutRoot();
        root.appendChild(document.createElement('div'));
        const { controller } = build();

        controller.addHorizontalRow();

        const horizontalRow = root.firstChild;
        expect(horizontalRow.tagName).toBe('HR');
        expect(horizontalRow.classList.contains('mp-payment-methods-list-horizontal-row')).toBe(true);
      });
    });

    describe('convertCustomCheckoutAreaToPaymentMethodList', () => {
      it('Given the checkout area, When converted, Then it becomes an accessible listbox and its parent drops the box shadow', () => {
        const parent = document.createElement('div');
        const area = document.createElement('div');
        parent.appendChild(area);
        document.body.appendChild(parent);
        const { controller } = build();

        controller.convertCustomCheckoutAreaToPaymentMethodList(area);

        expect(area.id).toBe('mp-checkout-super-token-root');
        expect(area.classList.contains('mp-super-token-payment-methods-list')).toBe(true);
        expect(area.getAttribute('role')).toBe('listbox');
        expect(area.getAttribute('aria-label')).toBe('Saved methods list');
        expect(area.getAttribute('tabindex')).toBe('0');
        expect(area.classList.contains('mp-initial-state')).toBe(true);
        expect(parent.classList.contains('mp-box-shadow-none')).toBe(true);
      });
    });

    describe('convertCreditCardFormToPaymentMethodElement', () => {
      const buildFormContainer = () => {
        const container = document.createElement('div');
        const form = document.createElement('div');
        form.id = 'mp-checkout-custom-root';
        const inner = document.createElement('div');
        inner.classList.add('mp-checkout-custom-container');
        form.appendChild(inner);
        container.appendChild(form);
        return { container, form };
      };

      it('Given the new-card form, When converted, Then it gains the accordion classes and a header', () => {
        const { container, form } = buildFormContainer();
        const { controller } = build();

        controller.convertCreditCardFormToPaymentMethodElement(container);

        expect(form.classList.contains('mp-super-token-payment-method__accordion')).toBe(true);
        expect(
          form.querySelector('.mp-checkout-custom-container').classList.contains('mp-super-token-payment-method__accordion-content'),
        ).toBe(true);
        const header = form.querySelector('.mp-super-token-payment-method__accordion-header');
        expect(header).not.toBeNull();
        expect(header.getAttribute('aria-label')).toBe('New card');
        expect(header.querySelector('.mp-super-token-payment-method__accordion-title').textContent).toBe('New card');
      });

      it('Given the header, When clicked, Then it triggers the new-card selection', () => {
        const { container, form } = buildFormContainer();
        const { controller } = build();
        const onSelect = jest.spyOn(controller, 'onSelectNewCardPaymentMethod').mockImplementation(() => {});

        controller.convertCreditCardFormToPaymentMethodElement(container);
        form.querySelector('.mp-super-token-payment-method__accordion-header').click();

        expect(onSelect).toHaveBeenCalledTimes(1);
      });

      it('Given the header, When Enter or Space is pressed, Then it triggers the new-card selection', () => {
        const { container, form } = buildFormContainer();
        const { controller } = build();
        const onSelect = jest.spyOn(controller, 'onSelectNewCardPaymentMethod').mockImplementation(() => {});

        controller.convertCreditCardFormToPaymentMethodElement(container);
        const header = form.querySelector('.mp-super-token-payment-method__accordion-header');
        header.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
        header.dispatchEvent(new KeyboardEvent('keydown', { code: 'Space' }));

        expect(onSelect).toHaveBeenCalledTimes(2);
      });

      it('Given no new-card form element, When converted, Then it is a no-op', () => {
        const container = document.createElement('div');
        const { controller } = build();

        expect(() => controller.convertCreditCardFormToPaymentMethodElement(container)).not.toThrow();
      });
    });

    describe('focusFirstPaymentMethod', () => {
      it('Given a saved-method article, When focusing, Then the article receives focus', () => {
        const root = appendCustomCheckoutRoot();
        const article = document.createElement('article');
        root.appendChild(article);
        const focusSpy = jest.spyOn(article, 'focus');
        const { controller } = build();

        controller.focusFirstPaymentMethod();

        expect(focusSpy).toHaveBeenCalled();
      });

      it('Given only a new-card section, When focusing, Then the section receives focus', () => {
        const root = appendCustomCheckoutRoot();
        const section = document.createElement('section');
        root.appendChild(section);
        const focusSpy = jest.spyOn(section, 'focus');
        const { controller } = build();

        controller.focusFirstPaymentMethod();

        expect(focusSpy).toHaveBeenCalled();
      });
    });

    describe('removeAnimationInitialState', () => {
      beforeEach(() => jest.useFakeTimers());
      afterEach(() => jest.useRealTimers());

      it('Given the animated root, When the delay elapses, Then the animation class is removed', () => {
        const root = appendCustomCheckoutRoot();
        root.classList.add('mp-initial-state');
        const { controller } = build();

        controller.removeAnimationInitialState();
        expect(root.classList.contains('mp-initial-state')).toBe(true);
        jest.advanceTimersByTime(750);

        expect(root.classList.contains('mp-initial-state')).toBe(false);
      });
    });

    describe('onCustomCheckoutWasRendered', () => {
      it('Given the checkout element and methods, When rendered, Then the saved-methods view is delegated and the checkout type is set', () => {
        const root = appendCustomCheckoutRoot();
        const checkoutTypeInput = document.createElement('input');
        checkoutTypeInput.id = 'mp_checkout_type';
        document.body.appendChild(checkoutTypeInput);
        const methods = [creditCard()];
        const renderSavedMethods = jest.fn();
        const { controller } = build({ renderSavedMethods });

        controller.onCustomCheckoutWasRendered(root, methods);

        expect(renderSavedMethods).toHaveBeenCalledWith(root, methods);
        expect(checkoutTypeInput.value).toBe('super_token');
      });
    });

    describe('renderAccountPaymentMethods', () => {
      beforeEach(() => jest.useFakeTimers());
      afterEach(() => jest.useRealTimers());

      it('Given methods and an amount, When rendered, Then it stores state, delegates the render and dispatches readiness', () => {
        const root = appendCustomCheckoutRoot();
        const checkoutTypeInput = document.createElement('input');
        checkoutTypeInput.id = 'mp_checkout_type';
        document.body.appendChild(checkoutTypeInput);
        const methods = [creditCard()];
        const renderSavedMethods = jest.fn();
        const { controller, metrics } = build({ renderSavedMethods });
        const dispatched = jest.fn();
        document.addEventListener('supertoken_loaded', dispatched);

        controller.renderAccountPaymentMethods(methods, '10.00');

        expect(controller.getAmount()).toBe('10.00');
        expect(controller.getStoredPaymentMethods()).toBe(methods);
        expect(renderSavedMethods).toHaveBeenCalledWith(root, methods);
        expect(controller.isRendering).toBe(false);

        jest.advanceTimersByTime(500);
        expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_methods_ready', 'true', '');
        expect(dispatched).toHaveBeenCalled();

        document.removeEventListener('supertoken_loaded', dispatched);
      });

      it('Given the methods are already rendered, When invoked, Then it short-circuits before delegating the render', () => {
        const root = appendCustomCheckoutRoot();
        const alreadyRendered = document.createElement('div');
        alreadyRendered.classList.add('mp-super-token-payment-method');
        root.appendChild(alreadyRendered);
        const renderSavedMethods = jest.fn();
        const { controller } = build({ renderSavedMethods });

        controller.renderAccountPaymentMethods([creditCard()], '10.00');

        expect(renderSavedMethods).not.toHaveBeenCalled();
      });

      it('Given no checkout element, When rendered, Then it reports the render failure metric', () => {
        const methods = [creditCard()];
        const { controller, metrics } = build();

        controller.renderAccountPaymentMethods(methods, '10.00');

        expect(metrics.errorToRenderAccountPaymentMethods).toHaveBeenCalledTimes(1);
        expect(controller.isRendering).toBe(false);
      });
    });
  });
});
