const vm = require('vm');
const fs = require('fs');
const { resolveAlias } = require('../../../helpers/path-resolver');
const superTokenErrorConstantsPath = resolveAlias('assets/js/checkouts/super-token/errors/super-token-error-constants.js');
const superTokenPaymentMethodsPath = resolveAlias('assets/js/checkouts/super-token/entities/super-token-payment-methods.js');

describe('MPSuperTokenPaymentMethods - ESC', () => {
  let MPSuperTokenPaymentMethods;
  let instance;
  let mockMpSdkInstance;
  let mockMetrics;
  let MPSuperTokenErrorCodes;

  const CREDIT_CARD_PM = {
    id: 'visa',
    type: 'credit_card',
    token: 'pseudo-token-123',
    has_esc: true,
    card: { card_number: { last_four_digits: '4242' } },
    security_code_settings: { mode: 'mandatory', length: 3 },
    installments: [{ installments: 1, installment_amount: 100, installment_rate: 0, total_amount: 100, installment_rate_collector: ['MERCADOPAGO'], labels: [] }],
  };

  const DEBIT_CARD_PM = {
    ...CREDIT_CARD_PM,
    id: 'maestro',
    type: 'debit_card',
    card: { card_number: { last_four_digits: '1234' } },
  };

  const ACCOUNT_MONEY_PM = {
    id: 'account_money',
    type: 'account_money',
    token: 'am-token',
  };

  beforeAll(() => {
    const errorConstantsCode = fs.readFileSync(superTokenErrorConstantsPath, 'utf8');
    const paymentMethodsCode = fs.readFileSync(superTokenPaymentMethodsPath, 'utf8');
    const combined = `${errorConstantsCode}\n${paymentMethodsCode}\n({ MPSuperTokenPaymentMethods, MPSuperTokenErrorCodes });`;

    const mockDispatchEvent = jest.fn();

    const context = {
      window: {
        location: { href: 'https://example.com/checkout' },
        mpCustomCheckoutHandler: { cardForm: { formMounted: false, initCardForm: jest.fn() } },
      },
      document: {
        dispatchEvent: mockDispatchEvent,
        createElement: (tag) => document.createElement(tag),
        getElementById: (id) => document.getElementById(id),
        querySelector: (sel) => document.querySelector(sel),
        querySelectorAll: (sel) => document.querySelectorAll(sel),
      },
      console,
      CustomEvent: class CustomEvent {
        constructor(name, options) { this.name = name; this.detail = options?.detail; }
      },
      Intl,
      setTimeout,
      clearTimeout,
      setInterval,
      clearInterval,
      Date,
      Promise,
      Error,
      JSON,
      Math,
      wc_mercadopago_supertoken_bundle_params: {
        site_id: 'MLB',
        currency: 'BRL',
        intl: 'pt-BR',
        plugin_version: '1.0.0',
        platform_version: '6.0.0',
        cust_id: 'test',
        location: 'https://example.com',
        yellow_wallet_path: '/wallet.png',
        yellow_money_path: '/money.png',
        white_card_path: '/card.png',
        new_mp_logo_path: '/logo.png',
        payment_methods_list_text: 'Meios de pagamento',
        payment_methods_list_alt_text: 'Alt text',
        last_digits_text: 'terminados em',
        new_card_text: 'Novo cartão',
        account_money_text: 'Dinheiro em conta',
        account_money_wallet_with_investment_text: '',
        account_money_wallet_text: '',
        account_money_investment_text: '',
        account_money_available_text: '',
        interest_free_part_one_text: 'Até',
        interest_free_part_two_text: 'sem juros',
        mercado_pago_card_name: 'Cartão Mercado Pago',
        consumer_credits_due_date: '',
        mlb_installment_debit_auto_text: '',
        interest_rate_mlb_text: '',
        effective_total_cost_mlb_text: '',
        iof_mlb_text: '',
        borrowed_amount_mlb_text: '',
        per_month: 'ao mês',
        per_year: 'ao ano',
        cat_mlm_text: '',
        no_iva_text: '',
        tna_mlm_text: '',
        system_amortization_mlm_text: '',
        cftea_mla_text: '',
        tna_mla_text: '',
        tea_mla_text: '',
        fixed_rate_text: '',
        mercadopago_privacy_policy: '',
        payment_methods_thumbnails: {},
        payment_methods_order: 'cards_first',
        update_security_code_with_retry_error_text: 'Retry error',
        update_security_code_no_retry_error_text: 'No retry error',
        authorize_payment_method_with_retry_error_text: 'Auth retry',
        authorize_payment_method_no_retry_error_text: 'Auth no retry',
        select_payment_method_error_text: 'Select error',
        security_code_input_title_text: 'Código de segurança',
        security_code_placeholder_text_3_digits: '123',
        security_code_placeholder_text_4_digits: '1234',
        security_code_tooltip_text_3_digits: '3 dígitos',
        security_code_tooltip_text_4_digits: '4 dígitos',
        input_title: { installments: 'Parcelas' },
        placeholders: { installments: 'Selecione' },
        input_helper_message: {
          installments: { required: 'Obrigatório', bank_interest_hint_text: '', interest_free_option_text: 'sem juros' },
          securityCode: { invalid_type: 'Código inválido' },
        },
      },
    };

    const script = new vm.Script(combined);
    const result = script.runInNewContext(context);
    MPSuperTokenPaymentMethods = result.MPSuperTokenPaymentMethods;
    MPSuperTokenErrorCodes = result.MPSuperTokenErrorCodes;
  });

  beforeEach(() => {
    jest.clearAllMocks();

    mockMetrics = {
      hasEscNotExists: jest.fn(),
      getPaymentMethodFail: jest.fn(),
      fetchPaymentMethodTimeout: jest.fn(),
      getPaymentMethodLoadingTime: jest.fn(),
      fetchPaymentMethodSuccess: jest.fn(),
      fetchPaymentMethodSkipped: jest.fn(),
      errorToMountCVVField: jest.fn(),
      getSdkInstanceId: jest.fn(() => 'sdk-123'),
    };

    mockMpSdkInstance = {
      getAccountPaymentMethods: jest.fn(),
      getAccountPaymentMethod: jest.fn(),
      fields: {
        create: jest.fn(() => ({
          mount: jest.fn().mockReturnThis(),
          on: jest.fn().mockReturnThis(),
          unmount: jest.fn(),
          update: jest.fn(),
          focus: jest.fn(),
          blur: jest.fn(),
        })),
      },
    };

    instance = new MPSuperTokenPaymentMethods(mockMpSdkInstance, mockMetrics);
    instance.setSuperToken('fast-payment-token-123');

    document.body.innerHTML = '';
  });

  // ─── shouldFetchPaymentMethodAgain ──────────────────────────

  describe('shouldFetchPaymentMethodAgain()', () => {
    test('Given credit card with has_esc=true, mode=mandatory and no data-cvv-is-required-double-check, When shouldFetchPaymentMethodAgain() is called, Then should return true', () => {
      const el = document.createElement('article');

      expect(instance.shouldFetchPaymentMethodAgain(CREDIT_CARD_PM, el)).toBe(true);
    });

    test('Given debit card with has_esc=true, mode=mandatory and no data-cvv-is-required-double-check, When shouldFetchPaymentMethodAgain() is called, Then should return true', () => {
      const el = document.createElement('article');

      expect(instance.shouldFetchPaymentMethodAgain(DEBIT_CARD_PM, el)).toBe(true);
    });

    test('Given credit card with has_esc=false, When shouldFetchPaymentMethodAgain() is called, Then should return false', () => {
      const el = document.createElement('article');
      const pm = { ...CREDIT_CARD_PM, has_esc: false };

      expect(instance.shouldFetchPaymentMethodAgain(pm, el)).toBe(false);
    });

    test('Given credit card with has_esc=true but data-cvv-is-required-double-check already set, When shouldFetchPaymentMethodAgain() is called, Then should return false', () => {
      const el = document.createElement('article');
      el.setAttribute('data-cvv-is-required-double-check', 'true');

      expect(instance.shouldFetchPaymentMethodAgain(CREDIT_CARD_PM, el)).toBe(false);
    });

    test('Given credit card with mode=optional, When shouldFetchPaymentMethodAgain() is called, Then should return false', () => {
      const el = document.createElement('article');
      const pm = { ...CREDIT_CARD_PM, security_code_settings: { mode: 'optional', length: 3 } };

      expect(instance.shouldFetchPaymentMethodAgain(pm, el)).toBe(false);
    });

    test('Given account_money payment method, When shouldFetchPaymentMethodAgain() is called, Then should return false', () => {
      const el = document.createElement('article');

      expect(instance.shouldFetchPaymentMethodAgain(ACCOUNT_MONEY_PM, el)).toBe(false);
    });
  });

  // ─── hasMissingEsc ────────────────────────────────────────

  describe('hasMissingEsc()', () => {
    test('Given credit card with has_esc undefined and mode=mandatory, When hasMissingEsc() is called, Then should return true', () => {
      const pm = { ...CREDIT_CARD_PM };
      delete pm.has_esc;

      expect(instance.hasMissingEsc(pm)).toBe(true);
    });

    test('Given credit card with has_esc=true, When hasMissingEsc() is called, Then should return false', () => {
      expect(instance.hasMissingEsc(CREDIT_CARD_PM)).toBe(false);
    });

    test('Given debit card with has_esc undefined and mode=mandatory, When hasMissingEsc() is called, Then should return true', () => {
      const pm = { ...DEBIT_CARD_PM };
      delete pm.has_esc;

      expect(instance.hasMissingEsc(pm)).toBe(true);
    });

    test('Given account_money, When hasMissingEsc() is called, Then should return false', () => {
      expect(instance.hasMissingEsc(ACCOUNT_MONEY_PM)).toBe(false);
    });
  });

  // ─── fetchPaymentMethod ────────────────────────────────────

  describe('fetchPaymentMethod()', () => {
    test('Given API returns updated payment method, When fetchPaymentMethod() is called, Then should return it and set data-cvv-is-required-double-check', async () => {
      const el = document.createElement('article');
      const updatedPM = { ...CREDIT_CARD_PM, has_esc: false };
      mockMpSdkInstance.getAccountPaymentMethod.mockResolvedValue({ data: updatedPM });

      const result = await instance.fetchPaymentMethod(CREDIT_CARD_PM, el);

      expect(result).toEqual(updatedPM);
      expect(el.getAttribute('data-cvv-is-required-double-check')).toBe('true');
      expect(mockMetrics.getPaymentMethodLoadingTime).toHaveBeenCalled();
    });

    test('Given API returns null data, When fetchPaymentMethod() is called, Then should throw FETCH_PAYMENT_METHOD_NOT_FOUND', async () => {
      const el = document.createElement('article');
      mockMpSdkInstance.getAccountPaymentMethod.mockResolvedValue({ data: null });

      await expect(instance.fetchPaymentMethod(CREDIT_CARD_PM, el))
        .rejects
        .toThrow(MPSuperTokenErrorCodes.FETCH_PAYMENT_METHOD_NOT_FOUND);
    });

    test('Given API returns undefined data, When fetchPaymentMethod() is called, Then should throw FETCH_PAYMENT_METHOD_NOT_FOUND', async () => {
      const el = document.createElement('article');
      mockMpSdkInstance.getAccountPaymentMethod.mockResolvedValue({});

      await expect(instance.fetchPaymentMethod(CREDIT_CARD_PM, el))
        .rejects
        .toThrow(MPSuperTokenErrorCodes.FETCH_PAYMENT_METHOD_NOT_FOUND);
    });

    test('Given API rejects, When fetchPaymentMethod() is called, Then should propagate the error', async () => {
      const el = document.createElement('article');
      mockMpSdkInstance.getAccountPaymentMethod.mockRejectedValue(new Error('network_error'));

      await expect(instance.fetchPaymentMethod(CREDIT_CARD_PM, el))
        .rejects
        .toThrow('network_error');
    });
  });

  // ─── updatePaymentMethodInList ────────────────────────────

  describe('updatePaymentMethodInList()', () => {
    test('Given payment method exists in list, When updatePaymentMethodInList() is called, Then should replace it', () => {
      const original = { ...CREDIT_CARD_PM, has_esc: true };
      const updated = { ...CREDIT_CARD_PM, has_esc: false };
      instance.paymentMethods = [original, ACCOUNT_MONEY_PM];

      instance.updatePaymentMethodInList(updated);

      expect(instance.paymentMethods[0].has_esc).toBe(false);
      expect(instance.paymentMethods[1]).toBe(ACCOUNT_MONEY_PM);
    });

    test('Given paymentMethods is null, When updatePaymentMethodInList() is called, Then should throw', () => {
      instance.paymentMethods = null;

      expect(() => instance.updatePaymentMethodInList(CREDIT_CARD_PM))
        .toThrow(MPSuperTokenErrorCodes.UPDATE_PAYMENT_METHOD_WITH_ESC_FAILED_EMPTY_METHODS);
    });
  });

  // ─── showDetailsSkeleton / hideDetailsSkeleton ────────────

  describe('showDetailsSkeleton() / hideDetailsSkeleton()', () => {
    let paymentMethodElement;

    beforeEach(() => {
      paymentMethodElement = document.createElement('article');
      const wrapper = document.createElement('div');
      wrapper.classList.add('mp-super-token-method-details-wrapper');
      paymentMethodElement.appendChild(wrapper);
    });

    test('Given payment method element with wrapper, When showDetailsSkeleton() is called, Then should add loading class and skeleton element', () => {
      instance.showDetailsSkeleton(paymentMethodElement);

      const wrapper = paymentMethodElement.querySelector('.mp-super-token-method-details-wrapper');
      expect(wrapper.classList.contains('mp-super-token-method-details-wrapper--loading')).toBe(true);
      expect(wrapper.querySelector('.mp-super-token-method-details-skeleton')).not.toBeNull();
    });

    test('Given skeleton is visible, When hideDetailsSkeleton() is called, Then should remove loading class and skeleton element', () => {
      instance.showDetailsSkeleton(paymentMethodElement);
      instance.hideDetailsSkeleton(paymentMethodElement);

      const wrapper = paymentMethodElement.querySelector('.mp-super-token-method-details-wrapper');
      expect(wrapper.classList.contains('mp-super-token-method-details-wrapper--loading')).toBe(false);
      expect(wrapper.querySelector('.mp-super-token-method-details-skeleton')).toBeNull();
    });

    test('Given element without wrapper, When showDetailsSkeleton() is called, Then should not throw', () => {
      const emptyElement = document.createElement('article');

      expect(() => instance.showDetailsSkeleton(emptyElement)).not.toThrow();
    });
  });

  // ─── removeSecurityCodeField ──────────────────────────────

  describe('removeSecurityCodeField()', () => {
    test('Given security code container exists in DOM, When removeSecurityCodeField() is called, Then should remove it', () => {
      const container = document.createElement('div');
      container.id = `mp-super-token-security-code-container-${CREDIT_CARD_PM.token}`;
      document.body.appendChild(container);

      instance.removeSecurityCodeField(CREDIT_CARD_PM);

      expect(document.getElementById(`mp-super-token-security-code-container-${CREDIT_CARD_PM.token}`)).toBeNull();
    });

    test('Given security code container does not exist in DOM, When removeSecurityCodeField() is called, Then should not throw', () => {
      expect(() => instance.removeSecurityCodeField(CREDIT_CARD_PM)).not.toThrow();
    });
  });

  // ─── handleWithEscPaymentMethod ───────────────────────────

  describe('handleWithEscPaymentMethod()', () => {
    let paymentMethodElement;

    beforeEach(() => {
      paymentMethodElement = document.createElement('article');
      paymentMethodElement.id = 'visa4242';
      const wrapper = document.createElement('div');
      wrapper.classList.add('mp-super-token-method-details-wrapper');
      paymentMethodElement.appendChild(wrapper);
      document.body.appendChild(paymentMethodElement);

      instance.paymentMethods = [CREDIT_CARD_PM, ACCOUNT_MONEY_PM];
    });

    test('Given credit card with has_esc=true and API returns has_esc=false, When handleWithEscPaymentMethod() is called, Then should return updated PM, send success metric and update list', async () => {
      const updatedPM = { ...CREDIT_CARD_PM, has_esc: false };
      mockMpSdkInstance.getAccountPaymentMethod.mockResolvedValue({ data: updatedPM });

      const result = await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElement);

      expect(result).toEqual(updatedPM);
      expect(instance.paymentMethods[0].has_esc).toBe(false);
      expect(instance.activePaymentMethod).toEqual(updatedPM);
      expect(mockMetrics.fetchPaymentMethodSuccess).toHaveBeenCalledWith('visa4242', true);
      expect(mockMetrics.getPaymentMethodFail).not.toHaveBeenCalled();
    });

    test('Given credit card with has_esc=true and API returns has_esc=true, When handleWithEscPaymentMethod() is called, Then should return updated PM with has_esc=true', async () => {
      const updatedPM = { ...CREDIT_CARD_PM, has_esc: true };
      mockMpSdkInstance.getAccountPaymentMethod.mockResolvedValue({ data: updatedPM });

      const result = await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElement);

      expect(result.has_esc).toBe(true);
      expect(mockMetrics.fetchPaymentMethodSuccess).toHaveBeenCalled();
    });

    test('Given API returns PM with mode=optional, When handleWithEscPaymentMethod() is called, Then should remove security code field', async () => {
      const securityCodeContainer = document.createElement('div');
      securityCodeContainer.id = `mp-super-token-security-code-container-${CREDIT_CARD_PM.token}`;
      document.body.appendChild(securityCodeContainer);

      const updatedPM = { ...CREDIT_CARD_PM, security_code_settings: { mode: 'optional', length: 3 } };
      mockMpSdkInstance.getAccountPaymentMethod.mockResolvedValue({ data: updatedPM });

      await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElement);

      expect(document.getElementById(`mp-super-token-security-code-container-${CREDIT_CARD_PM.token}`)).toBeNull();
    });

    test('Given API returns PM with mode=mandatory, When handleWithEscPaymentMethod() is called, Then should NOT remove security code field', async () => {
      const securityCodeContainer = document.createElement('div');
      securityCodeContainer.id = `mp-super-token-security-code-container-${CREDIT_CARD_PM.token}`;
      document.body.appendChild(securityCodeContainer);

      const updatedPM = { ...CREDIT_CARD_PM, security_code_settings: { mode: 'mandatory', length: 3 } };
      mockMpSdkInstance.getAccountPaymentMethod.mockResolvedValue({ data: updatedPM });

      await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElement);

      expect(document.getElementById(`mp-super-token-security-code-container-${CREDIT_CARD_PM.token}`)).not.toBeNull();
    });

    test('Given credit card with has_esc=true and API fails, When handleWithEscPaymentMethod() is called, Then should return fallback with has_esc=true and send fail metric', async () => {
      mockMpSdkInstance.getAccountPaymentMethod.mockRejectedValue(new Error('api_error'));

      const result = await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElement);

      expect(result.has_esc).toBe(true);
      expect(mockMetrics.getPaymentMethodFail).toHaveBeenCalled();
      expect(mockMetrics.fetchPaymentMethodSuccess).not.toHaveBeenCalled();
    });

    test('Given credit card with has_esc=true and API times out, When handleWithEscPaymentMethod() is called, Then should return fallback and send getPaymentMethodLoadingTime and getPaymentMethodFail', async () => {
      mockMpSdkInstance.getAccountPaymentMethod.mockImplementation(
        () => new Promise((_, reject) => setTimeout(() => reject(new Error(MPSuperTokenErrorCodes.GET_PAYMENT_METHOD_TIMEOUT_ERROR)), 10))
      );

      const result = await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElement);

      expect(result.has_esc).toBe(true);
      expect(mockMetrics.getPaymentMethodLoadingTime).toHaveBeenCalled();
      expect(mockMetrics.getPaymentMethodFail).toHaveBeenCalled();
    });

    test('Given credit card with has_esc undefined (missing), When handleWithEscPaymentMethod() is called, Then should return original PM and send hasEscNotExists metric', async () => {
      const pmWithoutEsc = { ...CREDIT_CARD_PM };
      delete pmWithoutEsc.has_esc;

      const result = await instance.handleWithEscPaymentMethod(pmWithoutEsc, paymentMethodElement);

      expect(result.has_esc).toBeUndefined();
      expect(mockMetrics.hasEscNotExists).toHaveBeenCalledWith('visa4242');
      expect(mockMetrics.fetchPaymentMethodSuccess).not.toHaveBeenCalled();
    });

    test('Given account_money payment method, When handleWithEscPaymentMethod() is called, Then should return original PM and send skip metric with reason not_card', async () => {
      const result = await instance.handleWithEscPaymentMethod(ACCOUNT_MONEY_PM, paymentMethodElement);

      expect(result).toEqual(ACCOUNT_MONEY_PM);
      expect(result.has_esc).toBeUndefined();
      expect(mockMetrics.hasEscNotExists).not.toHaveBeenCalled();
      expect(mockMetrics.getPaymentMethodFail).not.toHaveBeenCalled();
      expect(mockMetrics.fetchPaymentMethodSuccess).not.toHaveBeenCalled();
      expect(mockMetrics.fetchPaymentMethodSkipped).toHaveBeenCalledWith(
        expect.any(String),
        'not_card'
      );
    });

    test('Given credit card already checked (data-cvv-is-required-double-check set), When handleWithEscPaymentMethod() is called, Then should return original PM, not call API, and send skip metric with reason already_checked', async () => {
      paymentMethodElement.setAttribute('data-cvv-is-required-double-check', 'true');

      const result = await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElement);

      expect(mockMpSdkInstance.getAccountPaymentMethod).not.toHaveBeenCalled();
      expect(result.has_esc).toBe(true);
      expect(mockMetrics.fetchPaymentMethodSkipped).toHaveBeenCalledWith(
        expect.any(String),
        'already_checked'
      );
    });

    test('Given any scenario, When handleWithEscPaymentMethod() completes, Then skeleton should always be removed (finally)', async () => {
      mockMpSdkInstance.getAccountPaymentMethod.mockRejectedValue(new Error('fail'));

      await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElement);

      const wrapper = paymentMethodElement.querySelector('.mp-super-token-method-details-wrapper');
      expect(wrapper.classList.contains('mp-super-token-method-details-wrapper--loading')).toBe(false);
      expect(wrapper.querySelector('.mp-super-token-method-details-skeleton')).toBeNull();
    });
  });

  // ─── handleWithEscPaymentMethod: race condition / stale generation ───

  describe('handleWithEscPaymentMethod() - stale generation (race condition)', () => {
    const CREDIT_CARD_PM_B = {
      id: 'master',
      type: 'credit_card',
      token: 'pseudo-token-456',
      has_esc: true,
      card: { card_number: { last_four_digits: '5678' } },
      security_code_settings: { mode: 'mandatory', length: 3 },
      installments: [],
    };

    let paymentMethodElementA;
    let paymentMethodElementB;

    beforeEach(() => {
      paymentMethodElementA = document.createElement('article');
      paymentMethodElementA.id = 'visa4242';
      const wrapperA = document.createElement('div');
      wrapperA.classList.add('mp-super-token-method-details-wrapper');
      paymentMethodElementA.appendChild(wrapperA);
      document.body.appendChild(paymentMethodElementA);

      paymentMethodElementB = document.createElement('article');
      paymentMethodElementB.id = 'master5678';
      const wrapperB = document.createElement('div');
      wrapperB.classList.add('mp-super-token-method-details-wrapper');
      paymentMethodElementB.appendChild(wrapperB);
      document.body.appendChild(paymentMethodElementB);

      instance.paymentMethods = [CREDIT_CARD_PM, CREDIT_CARD_PM_B];
    });

    test(
      'Given card A selected then card B selected quickly before A fetch resolves, ' +
      'When card A ESC fetch resolves after card B already incremented the generation, ' +
      'Then handleWithEscPaymentMethod should return null for the stale A call (not the original stale paymentMethod)',
      async () => {
        const updatedCardA = { ...CREDIT_CARD_PM, has_esc: false };
        const updatedCardB = { ...CREDIT_CARD_PM_B, has_esc: false };

        let resolveCardAFetch;
        const cardAFetchPromise = new Promise(resolve => { resolveCardAFetch = resolve; });

        // Card A: slow fetch (held) — Card B: fast fetch (resolves immediately)
        instance.fetchPaymentMethod = jest.fn()
          .mockReturnValueOnce(cardAFetchPromise)
          .mockResolvedValueOnce(updatedCardB);

        // Card A selected: generation becomes 1, fetch is held in-flight
        const promiseA = instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElementA);

        // Card B selected before A resolves: generation becomes 2, fetch resolves fast
        const resultB = await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM_B, paymentMethodElementB);

        // Card A's stale fetch now resolves (generation is already 2, not 1)
        resolveCardAFetch(updatedCardA);
        const resultA = await promiseA;

        // Card B: non-stale — should return updated data
        expect(resultB).toEqual(updatedCardB);

        // Card A: stale — should return null so caller skips mountSecurityCodeField
        // BUG: currently returns CREDIT_CARD_PM (stale original) instead of null
        expect(resultA).toBeNull();
      }
    );

    test(
      'Given a single card selection with no concurrent selection, ' +
      'When the ESC fetch completes normally, ' +
      'Then handleWithEscPaymentMethod should return the updated payment method (generation guard must not interfere)',
      async () => {
        const updatedCardA = { ...CREDIT_CARD_PM, has_esc: false };

        instance.fetchPaymentMethod = jest.fn().mockResolvedValue(updatedCardA);

        const result = await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElementA);

        expect(result).toEqual(updatedCardA);
      }
    );
  });

  // ─── Integration: mountSecurityCodeField guard ────────────

  describe('mountSecurityCodeField() guard', () => {
    test('Given payment method with mode=mandatory, When mountSecurityCodeField() is called, Then should proceed to mount CVV', () => {
      const spy = jest.spyOn(instance, 'unmountCardForm').mockImplementation(() => {});
      jest.spyOn(instance, 'unmountActiveSecurityCodeInstance').mockImplementation(() => {});

      instance.mountSecurityCodeField(CREDIT_CARD_PM);

      expect(spy).toHaveBeenCalled();
      spy.mockRestore();
    });

    test('Given payment method with mode=optional, When mountSecurityCodeField() is called, Then should return early', () => {
      const pm = { ...CREDIT_CARD_PM, security_code_settings: { mode: 'optional', length: 3 } };
      const spy = jest.spyOn(instance, 'unmountCardForm');

      instance.mountSecurityCodeField(pm);

      expect(spy).not.toHaveBeenCalled();
      spy.mockRestore();
    });
  });

  // ─── Integration: forceShowValidationErrors ────────────────

  describe('forceShowValidationErrors()', () => {
    test('Given active credit card with mode=mandatory and security code ref not true, When forceShowValidationErrors() is called, Then should force security code validation', () => {
      const el = document.createElement('article');
      el.id = 'visa4242';
      el.scrollIntoView = jest.fn();
      document.body.appendChild(el);

      instance.activePaymentMethod = CREDIT_CARD_PM;
      const spy = jest.spyOn(instance, 'forceSecurityCodeValidation').mockImplementation(() => {});
      jest.spyOn(instance, 'verifyIsSecurityCodeReferenceTrue').mockReturnValue(false);

      instance.forceShowValidationErrors();

      expect(spy).toHaveBeenCalled();
      spy.mockRestore();
    });
  });

  // ─── Integration: isSelectedPaymentMethodValid ─────────────

  describe('isSelectedPaymentMethodValid()', () => {
    test('Given active payment method with mode=mandatory but no security field instance, When isSelectedPaymentMethodValid() is called, Then should return false', () => {
      const el = document.createElement('article');
      el.id = 'visa4242';
      document.body.appendChild(el);

      instance.activePaymentMethod = CREDIT_CARD_PM;
      instance.securityFieldsActiveInstance = null;

      expect(instance.isSelectedPaymentMethodValid()).toBe(false);
    });
  });

  // ─── getSkipReason ────────────────────────────────────────

  describe('getSkipReason()', () => {
    test('Given element with data-cvv-is-required-double-check, When getSkipReason() is called, Then should return already_checked', () => {
      const el = document.createElement('article');
      el.setAttribute('data-cvv-is-required-double-check', 'true');

      expect(instance.getSkipReason(CREDIT_CARD_PM, el)).toBe('already_checked');
    });

    test('Given account_money payment method, When getSkipReason() is called, Then should return not_card', () => {
      const el = document.createElement('article');

      expect(instance.getSkipReason(ACCOUNT_MONEY_PM, el)).toBe('not_card');
    });

    test('Given credit card with mode=optional, When getSkipReason() is called, Then should return security_code_not_required', () => {
      const el = document.createElement('article');
      const pm = { ...CREDIT_CARD_PM, security_code_settings: { mode: 'optional', length: 3 } };

      expect(instance.getSkipReason(pm, el)).toBe('security_code_not_required');
    });

    test('Given credit card with has_esc=false, When getSkipReason() is called, Then should return esc_disabled', () => {
      const el = document.createElement('article');
      const pm = { ...CREDIT_CARD_PM, has_esc: false };

      expect(instance.getSkipReason(pm, el)).toBe('esc_disabled');
    });

    test('Given non-card payment method with double-check attribute, When getSkipReason() is called, Then should return already_checked (first condition wins)', () => {
      const el = document.createElement('article');
      el.setAttribute('data-cvv-is-required-double-check', 'true');

      expect(instance.getSkipReason(ACCOUNT_MONEY_PM, el)).toBe('already_checked');
    });

    test('Given credit card with has_esc=true and mode=mandatory and no double-check, When getSkipReason() is called, Then should return unknown', () => {
      const el = document.createElement('article');

      expect(instance.getSkipReason(CREDIT_CARD_PM, el)).toBe('unknown');
    });
  });

  // ─── handleWithEscPaymentMethod skip metric scenarios ────

  describe('handleWithEscPaymentMethod() - skip metric', () => {
    let paymentMethodElement;

    beforeEach(() => {
      paymentMethodElement = document.createElement('article');
      paymentMethodElement.id = 'visa4242';
      const wrapper = document.createElement('div');
      wrapper.classList.add('mp-super-token-method-details-wrapper');
      paymentMethodElement.appendChild(wrapper);
      document.body.appendChild(paymentMethodElement);

      instance.paymentMethods = [CREDIT_CARD_PM, ACCOUNT_MONEY_PM];
    });

    afterEach(() => {
      paymentMethodElement.remove();
    });

    test('Given credit card with esc_disabled, When handleWithEscPaymentMethod() is called, Then should send skip metric with reason esc_disabled', async () => {
      const pm = { ...CREDIT_CARD_PM, has_esc: false };

      const result = await instance.handleWithEscPaymentMethod(pm, paymentMethodElement);

      expect(result).toEqual(pm);
      expect(mockMetrics.fetchPaymentMethodSkipped).toHaveBeenCalledWith(
        expect.any(String),
        'esc_disabled'
      );
      expect(mockMetrics.fetchPaymentMethodSuccess).not.toHaveBeenCalled();
    });

    test('Given credit card with mode=optional, When handleWithEscPaymentMethod() is called, Then should send skip metric with reason security_code_not_required', async () => {
      const pm = { ...CREDIT_CARD_PM, security_code_settings: { mode: 'optional', length: 3 } };

      const result = await instance.handleWithEscPaymentMethod(pm, paymentMethodElement);

      expect(result).toEqual(pm);
      expect(mockMetrics.fetchPaymentMethodSkipped).toHaveBeenCalledWith(
        expect.any(String),
        'security_code_not_required'
      );
    });

    test('Given credit card with has_esc=true and fetch succeeds, When handleWithEscPaymentMethod() is called, Then should NOT send skip metric', async () => {
      const updatedPM = { ...CREDIT_CARD_PM, has_esc: false };
      mockMpSdkInstance.getAccountPaymentMethod.mockResolvedValue({ data: updatedPM });

      await instance.handleWithEscPaymentMethod(CREDIT_CARD_PM, paymentMethodElement);

      expect(mockMetrics.fetchPaymentMethodSkipped).not.toHaveBeenCalled();
      expect(mockMetrics.fetchPaymentMethodSuccess).toHaveBeenCalled();
    });

    test('Given credit card with has_esc undefined (hasMissingEsc branch), When handleWithEscPaymentMethod() is called, Then should NOT send skip metric', async () => {
      const pmWithoutEsc = { ...CREDIT_CARD_PM };
      delete pmWithoutEsc.has_esc;

      await instance.handleWithEscPaymentMethod(pmWithoutEsc, paymentMethodElement);

      expect(mockMetrics.fetchPaymentMethodSkipped).not.toHaveBeenCalled();
      expect(mockMetrics.hasEscNotExists).toHaveBeenCalled();
    });
  });

  // ─── parseMsToSeconds ─────────────────────────────────────

  describe('parseMsToSeconds()', () => {
    test.each([
      { ms: 5000, expected: '5.00' },
      { ms: 1234, expected: '1.23' },
      { ms: 500, expected: '0.50' },
      { ms: 0, expected: '0.00' },
    ])('Given $ms ms, When parseMsToSeconds() is called, Then should return "$expected"', ({ ms, expected }) => {
      expect(instance.parseMsToSeconds(ms)).toBe(expected);
    });
  });
});
