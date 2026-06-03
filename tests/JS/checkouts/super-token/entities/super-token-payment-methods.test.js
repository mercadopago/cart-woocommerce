const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const superTokenPaymentMethodsPath = resolveAlias('assets/js/checkouts/super-token/entities/super-token-payment-methods.js');

describe('MPSuperTokenPaymentMethods - Installments Pre-selection', () => {
  let MPSuperTokenPaymentMethods;
  let instance;

  const mockBundleParams = {
    yellow_wallet_path: '',
    yellow_money_path: '',
    white_card_path: '',
    payment_methods_list_text: '',
    payment_methods_list_alt_text: '',
    last_digits_text: 'ending in',
    new_card_text: 'New card',
    account_money_text: 'Account money',
    account_money_wallet_with_investment_text: '',
    account_money_wallet_text: '',
    account_money_investment_text: '',
    account_money_available_text: '',
    interest_free_part_one_text: '',
    interest_free_part_two_text: '',
    input_helper_message: {
      installments: {
        bank_interest_hint_text: '',
        required: 'Required',
        interest_free_option_text: '(interest-free)',
      },
      securityCode: {},
    },
    input_title: { installments: 'Installments' },
    placeholders: { installments: 'Select installments' },
    security_code_input_title_text: '',
    security_code_placeholder_text_3_digits: '',
    security_code_placeholder_text_4_digits: '',
    security_code_tooltip_text_3_digits: '',
    security_code_tooltip_text_4_digits: '',
    site_id: 'MLB',
    currency: 'BRL',
    intl: 'pt-BR',
    mercado_pago_card_name: '',
    consumer_credits_due_date: '',
    mlb_installment_debit_auto_text: '',
    interest_rate_mlb_text: '',
    effective_total_cost_mlb_text: '',
    iof_mlb_text: '',
    borrowed_amount_mlb_text: '',
    per_month: '',
    per_year: '',
    cat_mlm_text: '',
    no_iva_text: '',
    tna_mlm_text: '',
    system_amortization_mlm_text: '',
    cftea_mla_text: 'CFT',
    tna_mla_text: 'TNA',
    tea_mla_text: 'TEA',
    fixed_rate_text: '',
    mercadopago_privacy_policy: '',
    new_mp_logo_path: '',
  };

  const mockInstallments = [
    {
      installments: 1,
      installment_amount: 100,
      installment_rate: 0,
      installment_rate_collector: [],
      total_amount: 100,
      labels: [],
    },
    {
      installments: 3,
      installment_amount: 35,
      installment_rate: 5,
      installment_rate_collector: [],
      total_amount: 105,
      labels: [],
    },
    {
      installments: 6,
      installment_amount: 18.5,
      installment_rate: 11,
      installment_rate_collector: [],
      total_amount: 111,
      labels: [],
    },
  ];

  const makeCreditCardPaymentMethod = (installments = mockInstallments) => ({
    id: 'visa',
    type: 'credit_card',
    token: 'token123',
    card: { card_number: { last_four_digits: '1234' } },
    installments,
    security_code_settings: { length: 3, card_location: 'back' },
  });

  const makeDebitCardPaymentMethod = () => ({
    id: 'maestro',
    type: 'debit_card',
    token: 'token456',
    card: { card_number: { last_four_digits: '5678' } },
    installments: [],
    security_code_settings: { length: 3, card_location: 'back' },
  });

  const makePrepaidCardPaymentMethod = () => ({
    id: 'prepaid',
    type: 'prepaid_card',
    token: 'token789',
    card: { card_number: { last_four_digits: '9012' } },
    installments: [],
    security_code_settings: { length: 3, card_location: 'back' },
  });

  beforeAll(() => {
    global.wc_mercadopago_supertoken_bundle_params = mockBundleParams;
    global.MPCheckoutFieldsDispatcher = undefined;
    global.MPSuperTokenErrorCodes = {
      SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND: 'SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND',
      SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND: 'SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND',
      SUPER_TOKEN_METRICS_NOT_FOUND: 'SUPER_TOKEN_METRICS_NOT_FOUND',
      SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR',
      SELECT_PAYMENT_METHOD_NOT_VALID: 'SELECT_PAYMENT_METHOD_NOT_VALID',
      UPDATE_SECURITY_CODE_ERROR: 'UPDATE_SECURITY_CODE_ERROR',
    };
    global.Intl = Intl;

    MPSuperTokenPaymentMethods = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    document.body.innerHTML = '';
    instance = new MPSuperTokenPaymentMethods();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  describe('buildCreditCardDetailsInnerHTML', () => {
    test('should pre-select the first installment option for credit cards', () => {
      const paymentMethod = makeCreditCardPaymentMethod();
      const html = instance.buildCreditCardDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const select = document.querySelector('select[data-checkout="installments"]');
      expect(select).not.toBeNull();

      const options = select.querySelectorAll('option');
      expect(options[0].selected).toBe(true);
      expect(options[0].value).toBe('1');
    });

    test('should not include a disabled placeholder option', () => {
      const paymentMethod = makeCreditCardPaymentMethod();
      const html = instance.buildCreditCardDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const select = document.querySelector('select[data-checkout="installments"]');
      const disabledOptions = select.querySelectorAll('option[disabled]');
      expect(disabledOptions.length).toBe(0);
    });

    test('should not include a placeholder option with empty value', () => {
      const paymentMethod = makeCreditCardPaymentMethod();
      const html = instance.buildCreditCardDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const select = document.querySelector('select[data-checkout="installments"]');
      const emptyOptions = Array.from(select.querySelectorAll('option')).filter(opt => opt.value === '');
      expect(emptyOptions.length).toBe(0);
    });

    test('should render all installment options from normalized list', () => {
      const paymentMethod = makeCreditCardPaymentMethod();
      const html = instance.buildCreditCardDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const select = document.querySelector('select[data-checkout="installments"]');
      const options = select.querySelectorAll('option');
      expect(options.length).toBe(3);
      expect(options[0].value).toBe('1');
      expect(options[1].value).toBe('3');
      expect(options[2].value).toBe('6');
    });

    test('should return section without select for credit card with empty installments', () => {
      const paymentMethod = makeCreditCardPaymentMethod([]);
      const html = instance.buildCreditCardDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const select = document.querySelector('select[data-checkout="installments"]');
      expect(select).toBeNull();
    });

    test('should return section without installments select for debit card', () => {
      const paymentMethod = makeDebitCardPaymentMethod();
      const html = instance.buildCreditCardDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const select = document.querySelector('select[data-checkout="installments"]');
      expect(select).toBeNull();
    });

    test('should return section without installments select for prepaid card', () => {
      const paymentMethod = makePrepaidCardPaymentMethod();
      const html = instance.buildCreditCardDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const select = document.querySelector('select[data-checkout="installments"]');
      expect(select).toBeNull();
    });

    test('should return empty string for non-card payment method', () => {
      const paymentMethod = { id: 'account_money', type: 'account_money' };
      const html = instance.buildCreditCardDetailsInnerHTML(paymentMethod);
      expect(html).toBe('');
    });

    test('should limit to 6 installments for Colombia (MCO)', () => {
      const mcoInstance = new MPSuperTokenPaymentMethods();
      global.wc_mercadopago_supertoken_bundle_params.site_id = 'MCO';
      const mcoClass = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', global);
      const mcoObj = new mcoClass();

      const manyInstallments = Array.from({ length: 12 }, (_, i) => ({
        installments: i + 1,
        installment_amount: 100 / (i + 1),
        installment_rate: i > 0 ? 5 : 0,
        installment_rate_collector: [],
        total_amount: 100 + (i > 0 ? 5 : 0),
        labels: [],
      }));

      const paymentMethod = makeCreditCardPaymentMethod(manyInstallments);
      const html = mcoObj.buildCreditCardDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const select = document.querySelector('select[data-checkout="installments"]');
      const options = select.querySelectorAll('option');
      expect(options.length).toBe(6);
      expect(options[0].selected).toBe(true);
      expect(options[0].value).toBe('1');

      // Restore site_id
      global.wc_mercadopago_supertoken_bundle_params.site_id = 'MLB';
    });
  });

  describe('installmentsWasSelected', () => {
    test('should return true when first installment is pre-selected', () => {
      const paymentMethod = makeCreditCardPaymentMethod();
      const html = instance.buildCreditCardDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const result = instance.installmentsWasSelected(paymentMethod);
      expect(result).toBe(true);
    });

    test('given consumer credits with no installment selected, when installmentsWasSelected is called, should return false', () => {
      const paymentMethod = {
        id: 'consumer_credits',
        type: 'digital_currency',
        token: 'token_credits',
        card: { card_number: {} },
        installments: mockInstallments,
      };
      instance.mpSuperTokenMetrics = { renderConsumerCreditsDetailsInnerHTML: jest.fn() };
      const html = instance.buildConsumerCreditsDetailsInnerHTML(paymentMethod);

      document.body.innerHTML = html;

      const result = instance.installmentsWasSelected(paymentMethod);
      expect(result).toBe(false);
    });
  });

  describe('normalizeInstallments', () => {
    test('should include taxInfo for MLA installments with labels', () => {
      global.wc_mercadopago_supertoken_bundle_params.site_id = 'MLA';
      const mlaClass = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', global);
      const mlaObj = new mlaClass();

      const installmentsWithLabels = [
        {
          installments: 3,
          installment_amount: 35,
          installment_rate: 5,
          installment_rate_collector: [],
          total_amount: 105,
          labels: ['CFT_10,5|TEA_8,2|TNA_7,1'],
        },
      ];

      const result = mlaObj.normalizeInstallments(installmentsWithLabels);

      expect(result[0].taxInfo).toBeDefined();
      expect(result[0].taxInfo.cft).toBe('10,5');
      expect(result[0].taxInfo.tea).toBe('8,2');
      expect(result[0].taxInfo.tna).toBe('7,1');

      // Restore site_id
      global.wc_mercadopago_supertoken_bundle_params.site_id = 'MLB';
    });
  });

  describe('createPaymentMethodElement - installments initialization', () => {
    let elementInstance;
    const mockCheckoutPage = {
      updateTaxInfoForSelect: jest.fn(),
    };

    beforeEach(() => {
      mockCheckoutPage.updateTaxInfoForSelect.mockClear();
      document.body.innerHTML = '<input type="hidden" id="cardInstallments" value="">';

      const ElementClass = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', {
        ...global,
        CheckoutPage: mockCheckoutPage,
      });
      elementInstance = new ElementClass();
    });

    const makeCreditCardForElement = () => ({
      ...makeCreditCardPaymentMethod(),
      name: 'Visa',
      thumbnail: '',
      installments: [
        {
          installments: 1,
          installment_amount: 100,
          installment_rate: 0,
          installment_rate_collector: ['MERCADOPAGO'],
          total_amount: 100,
          labels: [],
        },
        {
          installments: 3,
          installment_amount: 35,
          installment_rate: 5,
          installment_rate_collector: [],
          total_amount: 105,
          labels: [],
        },
      ],
    });

    test('should sync cardInstallments hidden input with pre-selected value on initialization', () => {
      const paymentMethod = makeCreditCardForElement();
      elementInstance.createPaymentMethodElement(paymentMethod);

      const cardInstallments = document.getElementById('cardInstallments');
      expect(cardInstallments.value).toBe('1');
    });

    test('should call CheckoutPage.updateTaxInfoForSelect with pre-selected value on initialization', () => {
      const paymentMethod = makeCreditCardForElement();
      elementInstance.createPaymentMethodElement(paymentMethod);

      expect(mockCheckoutPage.updateTaxInfoForSelect).toHaveBeenCalledWith(
        '1',
        expect.stringContaining('mp-super-token-installments-tax-info-'),
        paymentMethod.installments
      );
    });
  });

  describe('createPaymentMethodElement - consumer credits installments dispatcher', () => {
    let dispatcherInstance;
    let mockDispatcher;
    let mockContractController;
    let mockMetrics;

    const makeConsumerCreditsPaymentMethod = () => ({
      id: 'consumer_credits',
      type: 'digital_currency',
      name: 'Mercado Crédito',
      thumbnail: '',
      token: 'token_credits',
      credits_pricing_id: 'pricing_123',
      card: { card_number: {} },
      installments: [
        {
          installments: 3,
          installment_amount: 35,
          installment_rate: 5,
          installment_rate_collector: [],
          total_amount: 105,
          labels: [],
          consumer_credits: { conditions: {} },
        },
        {
          installments: 6,
          installment_amount: 18.5,
          installment_rate: 11,
          installment_rate_collector: [],
          total_amount: 111,
          labels: [],
          consumer_credits: { conditions: {} },
        },
      ],
    });

    beforeEach(() => {
      mockDispatcher = { addEventListenerDispatcher: jest.fn() };
      mockContractController = { update: jest.fn() };
      mockMetrics = {
        renderCreditsContract: jest.fn(),
        installmentsFilled: jest.fn(),
        renderConsumerCreditsHint: jest.fn(),
        renderConsumerCreditsDueDate: jest.fn(),
        renderConsumerCreditsDetailsInnerHTML: jest.fn(),
        errorToUpdateCreditsContract: jest.fn(),
        registerOpenCreditsInfoModal: jest.fn(),
        sendMetric: jest.fn(),
      };

      document.body.innerHTML = `
        <input type="hidden" id="cardInstallments" value="">
        <div id="mp-consumer-credits-hint"></div>
        <div id="mp-consumer-credits-due-date"></div>
        <div id="mp-consumer-credits-legal-text"></div>
        <div id="mp-consumer-credits-debit-auto-text"></div>
      `;

      const DispatcherClass = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', {
        ...global,
        MPCheckoutFieldsDispatcher: mockDispatcher,
      });
      dispatcherInstance = new DispatcherClass();
      dispatcherInstance.mpSuperTokenMetrics = mockMetrics;
      dispatcherInstance.mpSdkInstance = {
        renderCreditsContract: jest.fn(() => Promise.resolve(mockContractController)),
      };
    });

    test('given consumer credits installment selected, when change event fires, then should dispatch super_token_installments_filled via MPCheckoutFieldsDispatcher', async () => {
      const paymentMethod = makeConsumerCreditsPaymentMethod();
      const element = dispatcherInstance.createPaymentMethodElement(paymentMethod);
      document.body.appendChild(element);

      // Wait for renderCreditsContract promise to resolve and register the change listener
      await new Promise(resolve => setTimeout(resolve, 0));

      const select = document.querySelector(`#mp-super-token-installments-select-${dispatcherInstance.paymentMethodIdentifier(paymentMethod)}`);
      expect(select).not.toBeNull();

      select.value = '3';
      select.dispatchEvent(new Event('change'));

      expect(mockDispatcher.addEventListenerDispatcher).toHaveBeenCalledWith(
        null,
        'focusout',
        'super_token_installments_filled',
        { onlyDispatch: true }
      );
    });

    test('given consumer credits installment selected, when change event fires, then should call installmentsFilled metric with consumer_credits', async () => {
      const paymentMethod = makeConsumerCreditsPaymentMethod();
      const element = dispatcherInstance.createPaymentMethodElement(paymentMethod);
      document.body.appendChild(element);

      await new Promise(resolve => setTimeout(resolve, 0));

      const select = document.querySelector(`#mp-super-token-installments-select-${dispatcherInstance.paymentMethodIdentifier(paymentMethod)}`);
      select.value = '3';
      select.dispatchEvent(new Event('change'));

      expect(mockMetrics.installmentsFilled).toHaveBeenCalledWith('consumer_credits');
    });

    test('given consumer credits installment selected, when #cardInstallments is absent from DOM, then should not throw TypeError', async () => {
      document.body.innerHTML = `
        <div id="mp-consumer-credits-hint"></div>
        <div id="mp-consumer-credits-due-date"></div>
        <div id="mp-consumer-credits-legal-text"></div>
        <div id="mp-consumer-credits-debit-auto-text"></div>
      `;

      const paymentMethod = makeConsumerCreditsPaymentMethod();
      const element = dispatcherInstance.createPaymentMethodElement(paymentMethod);
      document.body.appendChild(element);

      await new Promise(resolve => setTimeout(resolve, 0));

      const select = document.querySelector(`#mp-super-token-installments-select-${dispatcherInstance.paymentMethodIdentifier(paymentMethod)}`);
      select.value = '3';

      expect(() => select.dispatchEvent(new Event('change'))).not.toThrow();
    });
  });

  describe('createPaymentMethodElement - installments dispatcher missing metric', () => {
    let metricInstance;

    const makeCreditCardForMetric = () => ({
      id: 'credit_card_id',
      type: 'credit_card',
      name: 'Visa',
      thumbnail: '',
      token: 'token123',
      card: { card_number: { last_four_digits: '1234' } },
      security_code_settings: { length: 3, card_location: 'back' },
      installments: [
        { installments: 1, installment_amount: 100, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'], total_amount: 100, labels: [] },
        { installments: 3, installment_amount: 35, installment_rate: 5, installment_rate_collector: [], total_amount: 105, labels: [] },
      ],
    });

    beforeEach(() => {
      document.body.innerHTML = '<input type="hidden" id="cardInstallments" value="">';
      global.sendMetric = jest.fn();

      const MetricClass = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', {
        ...global,
        MPCheckoutFieldsDispatcher: undefined,
        CheckoutPage: { updateTaxInfoForSelect: jest.fn() },
      });
      metricInstance = new MetricClass();
    });

    afterEach(() => {
      delete global.sendMetric;
    });

    test('Given MPCheckoutFieldsDispatcher is absent, When createPaymentMethodElement() is called with credit card, Then should send MP_CHECKOUT_FIELDS_DISPATCHER_MISSING metric', () => {
      metricInstance.createPaymentMethodElement(makeCreditCardForMetric());

      expect(global.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING',
        'super_token_installments_setup',
        'mp_super_token_init_error'
      );
    });

    test('Given MPCheckoutFieldsDispatcher is absent, When createPaymentMethodElement() is called twice, Then should send metric only once', () => {
      const paymentMethod = makeCreditCardForMetric();
      metricInstance.createPaymentMethodElement(paymentMethod);
      metricInstance.createPaymentMethodElement(paymentMethod);

      expect(global.sendMetric).toHaveBeenCalledTimes(1);
    });

    test('Given MPCheckoutFieldsDispatcher is present, When createPaymentMethodElement() is called with credit card, Then should not send dispatcher missing metric', () => {
      const PresentClass = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', {
        ...global,
        MPCheckoutFieldsDispatcher: { addEventListenerDispatcher: jest.fn() },
        CheckoutPage: { updateTaxInfoForSelect: jest.fn() },
      });
      PresentClass.prototype.installmentsDispatcherMissingReported = false;
      const presentInstance = new PresentClass();
      presentInstance.createPaymentMethodElement(makeCreditCardForMetric());

      expect(global.sendMetric).not.toHaveBeenCalledWith(
        'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING',
        'super_token_installments_setup',
        expect.any(String)
      );
    });
  });

  describe('createPaymentMethodElement - consumer credits dispatcher missing metric', () => {
    let missingInstance;
    let mockMetrics;

    const makeConsumerCreditsForMetric = () => ({
      id: 'consumer_credits',
      type: 'digital_currency',
      name: 'Mercado Crédito',
      thumbnail: '',
      token: 'token_credits',
      credits_pricing_id: 'pricing_123',
      card: { card_number: {} },
      installments: [
        { installments: 3, installment_amount: 35, installment_rate: 5, installment_rate_collector: [], total_amount: 105, labels: [], consumer_credits: { conditions: {} } },
      ],
    });

    beforeEach(() => {
      mockMetrics = {
        renderCreditsContract: jest.fn(),
        installmentsFilled: jest.fn(),
        renderConsumerCreditsHint: jest.fn(),
        renderConsumerCreditsDueDate: jest.fn(),
        renderConsumerCreditsDetailsInnerHTML: jest.fn(),
        errorToUpdateCreditsContract: jest.fn(),
        registerOpenCreditsInfoModal: jest.fn(),
        sendMetric: jest.fn(),
      };

      document.body.innerHTML = `
        <input type="hidden" id="cardInstallments" value="">
        <div id="mp-consumer-credits-hint"></div>
        <div id="mp-consumer-credits-due-date"></div>
        <div id="mp-consumer-credits-legal-text"></div>
        <div id="mp-consumer-credits-debit-auto-text"></div>
      `;

      global.sendMetric = jest.fn();

      const MissingClass = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', {
        ...global,
        MPCheckoutFieldsDispatcher: undefined,
      });
      missingInstance = new MissingClass();
      missingInstance.mpSuperTokenMetrics = mockMetrics;
      missingInstance.mpSdkInstance = {
        renderCreditsContract: jest.fn(() => Promise.resolve({ update: jest.fn() })),
      };
    });

    afterEach(() => {
      delete global.sendMetric;
    });

    test('Given MPCheckoutFieldsDispatcher is absent, When createPaymentMethodElement() is called with consumer credits, Then should send MP_CHECKOUT_FIELDS_DISPATCHER_MISSING metric at setup time', () => {
      missingInstance.createPaymentMethodElement(makeConsumerCreditsForMetric());

      expect(global.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING',
        'super_token_consumer_credits_installments_setup',
        'mp_super_token_init_error'
      );
    });

    test('Given MPCheckoutFieldsDispatcher is absent, When createPaymentMethodElement() is called twice with consumer credits, Then should send metric only once', () => {
      const paymentMethod = makeConsumerCreditsForMetric();
      missingInstance.createPaymentMethodElement(paymentMethod);
      missingInstance.createPaymentMethodElement(paymentMethod);

      expect(global.sendMetric).toHaveBeenCalledTimes(1);
    });
  });

  describe('isSelectedPaymentMethodValid', () => {
    const makePaymentMethodElement = (identifier) => {
      const el = document.createElement('div');
      el.id = identifier;
      document.body.appendChild(el);
      return el;
    };

    const makeSecurityCodeContainer = (token, { hasError = false, helperVisible = false } = {}) => {
      const container = document.createElement('div');
      container.id = `mp-super-token-security-code-container-${token}`;
      if (hasError) container.classList.add('error');
      if (helperVisible) {
        const helper = document.createElement('div');
        helper.id = 'mp-input-with-tooltip-helper-error';
        helper.style.display = 'flex';
        container.appendChild(helper);
      }
      document.body.appendChild(container);
      return container;
    };

    test('given credit card with empty installments array, when isSelectedPaymentMethodValid is called, should return true if CVV is valid', () => {
      const paymentMethod = { id: 'visa', type: 'credit_card', token: 'token_visa', card: { card_number: { last_four_digits: '1234' } }, installments: [], security_code_settings: { mode: 'mandatory', length: 3 } };
      instance.activePaymentMethod = paymentMethod;

      const identifier = instance.paymentMethodIdentifier(paymentMethod);
      makePaymentMethodElement(identifier);
      makeSecurityCodeContainer(paymentMethod.token);

      instance.securityFieldsActiveInstance = { unmount: jest.fn() };
      instance.verifyIsSecurityCodeReferenceTrue = jest.fn().mockReturnValue(true);

      const result = instance.isSelectedPaymentMethodValid();

      expect(result).toBe(true);
    });
  });

  describe('validateInstallmentSelection', () => {
    const makePaymentMethodContainer = (identifier) => {
      const el = document.createElement('div');
      el.id = identifier;
      document.body.appendChild(el);
      return el;
    };

    const addInstallmentsDropdown = (container, identifier, { withValue = false } = {}) => {
      const select = document.createElement('select');
      select.id = `mp-super-token-installments-select-${identifier}`;
      if (withValue) select.value = '3';
      container.appendChild(select);
      return select;
    };

    const mockMetrics = () => {
      instance.mpSuperTokenMetrics = { errorToSubmitWithoutInstallmentSelected: jest.fn() };
    };

    beforeEach(() => {
      instance.forceShowValidationErrors = jest.fn();
    });

    test('given consumer credits with installment dropdown and no installment selected, when validateInstallmentSelection is called, should return false, fire metric tagged as consumer_credits and show validation errors', () => {
      const paymentMethod = { id: 'consumer_credits', type: 'digital_currency', token: 'token_credits', card: { card_number: {} } };
      instance.activePaymentMethod = paymentMethod;
      mockMetrics();

      const identifier = instance.paymentMethodIdentifier(paymentMethod);
      const container = makePaymentMethodContainer(identifier);
      addInstallmentsDropdown(container, identifier, { withValue: false });

      const result = instance.validateInstallmentSelection();

      expect(result).toBe(false);
      expect(instance.mpSuperTokenMetrics.errorToSubmitWithoutInstallmentSelected).toHaveBeenCalledTimes(1);
      expect(instance.mpSuperTokenMetrics.errorToSubmitWithoutInstallmentSelected).toHaveBeenCalledWith('consumer_credits');
      expect(instance.forceShowValidationErrors).toHaveBeenCalledTimes(1);
    });

    test('given credit card with installment dropdown and no installment selected, when validateInstallmentSelection is called, should return false and fire metric tagged as credit_card', () => {
      const paymentMethod = { id: 'visa', type: 'credit_card', token: 'token_visa', card: { card_number: { last_four_digits: '1234' } }, installments: [{ installments: 3 }] };
      instance.activePaymentMethod = paymentMethod;
      mockMetrics();

      const identifier = instance.paymentMethodIdentifier(paymentMethod);
      const container = makePaymentMethodContainer(identifier);
      addInstallmentsDropdown(container, identifier, { withValue: false });

      const result = instance.validateInstallmentSelection();

      expect(result).toBe(false);
      expect(instance.mpSuperTokenMetrics.errorToSubmitWithoutInstallmentSelected).toHaveBeenCalledTimes(1);
      expect(instance.mpSuperTokenMetrics.errorToSubmitWithoutInstallmentSelected).toHaveBeenCalledWith('credit_card');
      expect(instance.forceShowValidationErrors).toHaveBeenCalledTimes(1);
    });

    test('given credit card with installment dropdown and installment selected, when validateInstallmentSelection is called, should return true', () => {
      const paymentMethod = { id: 'visa', type: 'credit_card', token: 'token_visa', card: { card_number: { last_four_digits: '1234' } } };
      instance.activePaymentMethod = paymentMethod;
      mockMetrics();

      const identifier = instance.paymentMethodIdentifier(paymentMethod);
      const container = makePaymentMethodContainer(identifier);
      const select = addInstallmentsDropdown(container, identifier);

      const option = document.createElement('option');
      option.value = '3';
      select.appendChild(option);
      select.value = '3';

      const result = instance.validateInstallmentSelection();

      expect(result).toBe(true);
      expect(instance.mpSuperTokenMetrics.errorToSubmitWithoutInstallmentSelected).not.toHaveBeenCalled();
      expect(instance.forceShowValidationErrors).not.toHaveBeenCalled();
    });

    test('given credit card with no installment dropdown rendered (empty installments), when validateInstallmentSelection is called, should return true', () => {
      const paymentMethod = { id: 'visa', type: 'credit_card', token: 'token_visa', card: { card_number: { last_four_digits: '1234' } }, installments: [] };
      instance.activePaymentMethod = paymentMethod;
      mockMetrics();

      const identifier = instance.paymentMethodIdentifier(paymentMethod);
      makePaymentMethodContainer(identifier);
      // no installments dropdown added to container

      const result = instance.validateInstallmentSelection();

      expect(result).toBe(true);
      expect(instance.mpSuperTokenMetrics.errorToSubmitWithoutInstallmentSelected).not.toHaveBeenCalled();
      expect(instance.forceShowValidationErrors).not.toHaveBeenCalled();
    });

    test('given account money payment method, when validateInstallmentSelection is called, should return true', () => {
      const paymentMethod = { id: 'account_money', type: 'account_money' };
      instance.activePaymentMethod = paymentMethod;
      mockMetrics();

      const identifier = instance.paymentMethodIdentifier(paymentMethod);
      makePaymentMethodContainer(identifier);

      const result = instance.validateInstallmentSelection();

      expect(result).toBe(true);
      expect(instance.mpSuperTokenMetrics.errorToSubmitWithoutInstallmentSelected).not.toHaveBeenCalled();
      expect(instance.forceShowValidationErrors).not.toHaveBeenCalled();
    });

    test('given an unexpected DOM error during validation, when validateInstallmentSelection is called, should send observability metric, log only the error message to console, attempt to surface validation errors and re-throw the error', () => {
      const domError = new Error('unexpected DOM error');
      const getElementByIdSpy = jest.spyOn(document, 'getElementById').mockImplementation(() => { throw domError; });

      instance.activePaymentMethod = { id: 'visa', type: 'credit_card', token: 'token-visa', card: { card_number: { last_four_digits: '1234' } } };
      const sendMetric = jest.fn();
      instance.mpSuperTokenMetrics = { sendMetric };
      instance.forceShowValidationErrors = jest.fn();

      expect(() => instance.validateInstallmentSelection()).toThrow(domError);
      expect(sendMetric).toHaveBeenCalledWith('error_to_validate_installment_selection', 'true', 'unexpected DOM error');
      expect(console.error).toHaveBeenCalledWith('Error validating installment selection: ', 'unexpected DOM error');
      expect(instance.forceShowValidationErrors).toHaveBeenCalledTimes(1);

      getElementByIdSpy.mockRestore();
    });

    test('given an unexpected error without a message, when validateInstallmentSelection is called, should fall back to "unknown" in the console log and metric', () => {
      const domError = {};
      const getElementByIdSpy = jest.spyOn(document, 'getElementById').mockImplementation(() => { throw domError; });

      instance.activePaymentMethod = { id: 'visa', type: 'credit_card', token: 'token-visa', card: { card_number: { last_four_digits: '1234' } } };
      const sendMetric = jest.fn();
      instance.mpSuperTokenMetrics = { sendMetric };
      instance.forceShowValidationErrors = jest.fn();

      let thrown = null;
      try {
        instance.validateInstallmentSelection();
      } catch (e) {
        thrown = e;
      }

      expect(thrown).toBe(domError);
      expect(sendMetric).toHaveBeenCalledWith('error_to_validate_installment_selection', 'true', 'unknown');
      expect(console.error).toHaveBeenCalledWith('Error validating installment selection: ', 'unknown');

      getElementByIdSpy.mockRestore();
    });

    test('given forceShowValidationErrors itself throws while handling a previous exception, when validateInstallmentSelection is called, should swallow the UI error logging only its message and re-throw the original error', () => {
      const domError = new Error('original DOM error');
      const uiError = new Error('UI error while showing validation errors');
      const getElementByIdSpy = jest.spyOn(document, 'getElementById').mockImplementation(() => { throw domError; });

      instance.activePaymentMethod = { id: 'visa', type: 'credit_card', token: 'token-visa', card: { card_number: { last_four_digits: '1234' } } };
      const sendMetric = jest.fn();
      instance.mpSuperTokenMetrics = { sendMetric };
      instance.forceShowValidationErrors = jest.fn().mockImplementation(() => { throw uiError; });

      expect(() => instance.validateInstallmentSelection()).toThrow(domError);
      expect(instance.forceShowValidationErrors).toHaveBeenCalledTimes(1);
      expect(console.error).toHaveBeenCalledWith('Failed to render validation errors after exception: ', 'UI error while showing validation errors');

      getElementByIdSpy.mockRestore();
    });
  });

  describe('updateSecurityCode()', () => {
    let mockMpSdkInstance;
    let mockMpSuperTokenMetrics;

    beforeEach(() => {
      mockMpSdkInstance = {
        getCardId: jest.fn().mockResolvedValue({ card_id: 'card-123' }),
        fields: {
          createCardToken: jest.fn().mockResolvedValue({ id: 'token-456' }),
        },
        updatePseudotoken: jest.fn().mockResolvedValue(undefined),
      };

      mockMpSuperTokenMetrics = {
        updateSecurityCodeGetCardIdSuccess: jest.fn(),
        updateSecurityCodeCardTokenCreated: jest.fn(),
        updateSecurityCodePseudotokenUpdated: jest.fn(),
        updateSecurityCodeSuccess: jest.fn(),
        errorToUpdateSecurityCode: jest.fn(),
      };

      instance.mpSdkInstance = mockMpSdkInstance;
      instance.mpSuperTokenMetrics = mockMpSuperTokenMetrics;
      instance.activePaymentMethod = {
        token: 'pm-token-abc',
        security_code_settings: { mode: 'mandatory' },
      };
      instance.superToken = 'super-token-xyz';
    });

    test('Given all SDK calls succeed, When updateSecurityCode() is called, Then all 4 metrics fire and error metric does not', async () => {
      await instance.updateSecurityCode();

      expect(mockMpSuperTokenMetrics.updateSecurityCodeGetCardIdSuccess).toHaveBeenCalledTimes(1);
      expect(mockMpSuperTokenMetrics.updateSecurityCodeCardTokenCreated).toHaveBeenCalledTimes(1);
      expect(mockMpSuperTokenMetrics.updateSecurityCodePseudotokenUpdated).toHaveBeenCalledTimes(1);
      expect(mockMpSuperTokenMetrics.updateSecurityCodeSuccess).toHaveBeenCalledTimes(1);
      expect(mockMpSuperTokenMetrics.errorToUpdateSecurityCode).not.toHaveBeenCalled();
    });

    test('Given getCardId fails, When updateSecurityCode() is called, Then no step metrics fire and error metric fires', async () => {
      mockMpSdkInstance.getCardId.mockRejectedValue(new Error('getCardId failed'));

      await expect(instance.updateSecurityCode()).rejects.toThrow('UPDATE_SECURITY_CODE_ERROR');

      expect(mockMpSuperTokenMetrics.updateSecurityCodeGetCardIdSuccess).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.updateSecurityCodeCardTokenCreated).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.updateSecurityCodePseudotokenUpdated).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.updateSecurityCodeSuccess).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.errorToUpdateSecurityCode).toHaveBeenCalledTimes(1);
    });

    test('Given createCardToken fails, When updateSecurityCode() is called, Then only metric 1 fires', async () => {
      mockMpSdkInstance.fields.createCardToken.mockRejectedValue(new Error('createCardToken failed'));

      await expect(instance.updateSecurityCode()).rejects.toThrow('UPDATE_SECURITY_CODE_ERROR');

      expect(mockMpSuperTokenMetrics.updateSecurityCodeGetCardIdSuccess).toHaveBeenCalledTimes(1);
      expect(mockMpSuperTokenMetrics.updateSecurityCodeCardTokenCreated).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.updateSecurityCodePseudotokenUpdated).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.updateSecurityCodeSuccess).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.errorToUpdateSecurityCode).toHaveBeenCalledTimes(1);
    });

    test('Given updatePseudotoken fails, When updateSecurityCode() is called, Then only metrics 1 and 2 fire', async () => {
      mockMpSdkInstance.updatePseudotoken.mockRejectedValue(new Error('updatePseudotoken failed'));

      await expect(instance.updateSecurityCode()).rejects.toThrow('UPDATE_SECURITY_CODE_ERROR');

      expect(mockMpSuperTokenMetrics.updateSecurityCodeGetCardIdSuccess).toHaveBeenCalledTimes(1);
      expect(mockMpSuperTokenMetrics.updateSecurityCodeCardTokenCreated).toHaveBeenCalledTimes(1);
      expect(mockMpSuperTokenMetrics.updateSecurityCodePseudotokenUpdated).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.updateSecurityCodeSuccess).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.errorToUpdateSecurityCode).toHaveBeenCalledTimes(1);
    });

    test('Given activePaymentMethod has no security_code_settings, When updateSecurityCode() is called, Then no SDK calls and no metrics fire', async () => {
      instance.activePaymentMethod = { token: 'pm-token-abc', type: 'account_money' };

      await instance.updateSecurityCode();

      expect(mockMpSdkInstance.getCardId).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.updateSecurityCodeGetCardIdSuccess).not.toHaveBeenCalled();
      expect(mockMpSuperTokenMetrics.updateSecurityCodeSuccess).not.toHaveBeenCalled();
    });

    test('Given mpSuperTokenMetrics is null, When updateSecurityCode() is called, Then it does not throw a secondary TypeError', async () => {
      instance.mpSuperTokenMetrics = null;

      await expect(instance.updateSecurityCode()).resolves.toBeUndefined();
    });
  });
});
