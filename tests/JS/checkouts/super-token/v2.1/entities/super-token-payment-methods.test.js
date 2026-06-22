const { resolveAlias } = require('../../../../helpers/path-resolver');
const { loadFile } = require('../../../../helpers/load-file');
const superTokenPaymentMethodsPath = resolveAlias(`assets/js/checkouts/super-token/${global.SUPER_TOKEN_VERSION}/entities/super-token-payment-methods.js`);

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
    current_user_email: '',
    mp_logo_blue_path: '',
    mp_logo_dark_path: '',
    saved_cards_title: 'Saved cards',
    saved_card_title: 'Saved card',
    mp_methods_title: 'You can also use',
    account_money_balance_text: 'Enough to pay for this purchase.',
    saved_payment_method_title: 'Saved payment method',
    update_security_code_with_retry_error_text: '',
    update_security_code_no_retry_error_text: '',
    authorize_payment_method_with_retry_error_text: '',
    authorize_payment_method_no_retry_error_text: '',
    select_payment_method_error_text: '',
    payment_methods_thumbnails: {},
    payment_methods_order: 'cards_first',
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
});

describe('MPSuperTokenPaymentMethods — Pure business rule functions', () => {
  let instance;

  const mockBundleParamsPure = {
    yellow_wallet_path: '', yellow_money_path: '', white_card_path: '',
    new_mp_logo_path: '', mp_logo_blue_path: 'blue.png', mp_logo_dark_path: 'dark.png',
    payment_methods_list_text: '', payment_methods_list_alt_text: '',
    last_digits_text: '', new_card_text: '', account_money_text: '',
    account_money_wallet_with_investment_text: '', account_money_wallet_text: '',
    account_money_investment_text: '', account_money_available_text: '',
    interest_free_part_one_text: '', interest_free_part_two_text: '',
    interest_free_option_text: '', security_code_input_title_text: '',
    security_code_placeholder_text_3_digits: '', security_code_placeholder_text_4_digits: '',
    security_code_tooltip_text_3_digits: '', security_code_tooltip_text_4_digits: '',
    security_code_error_message_text: '', mercado_pago_card_name: '',
    consumer_credits_due_date: '', mlb_installment_debit_auto_text: '',
    interest_rate_mlb_text: '', effective_total_cost_mlb_text: '', iof_mlb_text: '',
    borrowed_amount_mlb_text: '', per_month: '', per_year: '',
    cat_mlm_text: '', no_iva_text: '', tna_mlm_text: '',
    system_amortization_mlm_text: '', cftea_mla_text: '', tna_mla_text: '', tea_mla_text: '',
    fixed_rate_text: '', mercadopago_privacy_policy: '',
    input_helper_message: { installments: {}, securityCode: {} },
    input_title: { installments: '' }, placeholders: { installments: '' },
    site_id: 'MLB', currency: 'BRL', intl: 'pt-BR',
    update_security_code_with_retry_error_text: '',
    update_security_code_no_retry_error_text: '',
    authorize_payment_method_with_retry_error_text: '',
    authorize_payment_method_no_retry_error_text: '',
    select_payment_method_error_text: '',
    saved_cards_title: 'Cartões salvos',
    mp_methods_title: 'Você também pode usar',
    account_money_balance_text: '',
    saved_payment_method_title: 'Meio de pagamento salvo',
    payment_methods_order: 'cards_first',
    payment_methods_thumbnails: {},
    plugin_version: '', platform_version: '', location: '', theme: '',
    cust_id: '', wallet_button_enabled: false,
    public_key: '', platform_id: '', super_token_error_messages: {},
  };

  beforeAll(() => {
    global.wc_mercadopago_supertoken_bundle_params = mockBundleParamsPure;
    global.MPCheckoutFieldsDispatcher = undefined;
    global.MPSuperTokenErrorCodes = {
      SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR',
      SELECT_PAYMENT_METHOD_NOT_VALID: 'SELECT_PAYMENT_METHOD_NOT_VALID',
      SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND: 'SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND',
      SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND: 'SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND',
      SUPER_TOKEN_METRICS_NOT_FOUND: 'SUPER_TOKEN_METRICS_NOT_FOUND',
    };
    global.Intl = Intl;
    const MPSuperTokenPaymentMethods = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', global);
    instance = new MPSuperTokenPaymentMethods();
  });

  const card = (type, issuerName) => ({ type, issuer: { name: issuerName } });
  const am = () => ({ type: 'account_money' });
  const credits = () => ({ type: 'digital_currency' });

  describe('groupPaymentMethods', () => {
    test('UT-01: separates cards into cardPaymentMethods and AM/Credits into otherPaymentMethods', () => {
      const paymentMethods = [
        card('credit_card', 'Visa'), card('credit_card', 'Mercado Pago'),
        card('credit_card', 'BBVA'), card('credit_card', 'Galicia'),
        am(), credits(),
      ];
      const { cardPaymentMethods, otherPaymentMethods } = instance.groupPaymentMethods(paymentMethods);
      expect(cardPaymentMethods.map(paymentMethod => paymentMethod.issuer.name)).toEqual(['Visa', 'Mercado Pago', 'BBVA']);
      expect(otherPaymentMethods.map(paymentMethod => paymentMethod.type)).toEqual(['account_money', 'digital_currency']);
    });

    test('UT-02: limits cardPaymentMethods to 3 cards', () => {
      const paymentMethods = [1, 2, 3, 4, 5].map(() => card('credit_card', 'X'));
      expect(instance.groupPaymentMethods(paymentMethods).cardPaymentMethods).toHaveLength(3);
    });

    test('UT-03: otherPaymentMethods is empty when only cards', () => {
      const paymentMethods = [card('credit_card', 'A'), card('debit_card', 'B')];
      expect(instance.groupPaymentMethods(paymentMethods).otherPaymentMethods).toHaveLength(0);
    });

    test('UT-04: preserves SICAP order in slice', () => {
      const paymentMethods = [
        card('credit_card', 'First'), card('debit_card', 'Second'), card('prepaid_card', 'Third'),
      ];
      const { cardPaymentMethods } = instance.groupPaymentMethods(paymentMethods);
      expect(cardPaymentMethods.map(paymentMethod => paymentMethod.issuer.name)).toEqual(['First', 'Second', 'Third']);
    });
  });

  describe('getMpCardThumbnailPath', () => {
    const iconPaths = { blue: 'blue.png', dark: 'dark.png' };

    test('UT-05: returns blue for MLA', () => {
      expect(instance.getMpCardThumbnailPath('MLA', iconPaths)).toBe('blue.png');
    });

    test('UT-05b: returns blue for MLM', () => {
      expect(instance.getMpCardThumbnailPath('MLM', iconPaths)).toBe('blue.png');
    });

    test('UT-06: returns dark for MLB', () => {
      expect(instance.getMpCardThumbnailPath('MLB', iconPaths)).toBe('dark.png');
    });

    test('UT-07: returns dark as safe default for unknown site_id', () => {
      expect(instance.getMpCardThumbnailPath('XXX', iconPaths)).toBe('dark.png');
    });
  });

  describe('isMercadoPagoCard', () => {
    test('UT-12: true for prepaid_card with issuer Mercado Pago', () => {
      expect(instance.isMercadoPagoCard({ type: 'prepaid_card', issuer: { name: 'Mercado Pago' } })).toBe(true);
    });

    test('UT-13: true — case-insensitive and substring match', () => {
      expect(instance.isMercadoPagoCard({ type: 'prepaid_card', issuer: { name: 'mercado pago argentina' } })).toBe(true);
    });

    test('UT-14: false for different issuer', () => {
      expect(instance.isMercadoPagoCard({ type: 'prepaid_card', issuer: { name: 'BBVA' } })).toBe(false);
    });

    test('UT-14b: false for credit_card type', () => {
      expect(instance.isMercadoPagoCard({ type: 'credit_card', issuer: { name: 'Mercado Pago' } })).toBe(false);
    });
  });

  describe('isMercadoPagoCreditCard', () => {
    test('UT-16: true for credit_card with MP issuer', () => {
      expect(instance.isMercadoPagoCreditCard({ type: 'credit_card', issuer: { name: 'Cartão Mercado Pago' } })).toBe(true);
    });

    test('UT-16b: true — case-insensitive and substring match', () => {
      expect(instance.isMercadoPagoCreditCard({ type: 'credit_card', issuer: { name: 'mercado pago crédito' } })).toBe(true);
    });

    test('UT-16c: false for prepaid_card type', () => {
      expect(instance.isMercadoPagoCreditCard({ type: 'prepaid_card', issuer: { name: 'Mercado Pago' } })).toBe(false);
    });

    test('UT-16d: false for credit_card with non-MP issuer', () => {
      expect(instance.isMercadoPagoCreditCard({ type: 'credit_card', issuer: { name: 'Visa' } })).toBe(false);
    });

    test('UT-16e: false for null payment method', () => {
      expect(instance.isMercadoPagoCreditCard(null)).toBe(false);
    });

    test('UT-16f: false for debit_card type', () => {
      expect(instance.isMercadoPagoCreditCard({ type: 'debit_card', issuer: { name: 'Mercado Pago' } })).toBe(false);
    });
  });

  describe('buildPaymentMethodAriaLabel — pill text inclusion', () => {
    const creditCard = (name) => ({ type: 'credit_card', name, issuer: { name: 'Visa' } });

    test('UT-17: includes pill text when installmentsWithoutFee > 1 for credit card', () => {
      instance.INTEREST_FREE_PART_ONE_TEXT = 'hasta';
      instance.INTEREST_FREE_PART_TWO_TEXT = 'cuotas sin interés';
      const label = instance.buildPaymentMethodAriaLabel(creditCard('Visa Crédito'), '1234', 3);
      // MLB appends 'x' to installments count; pill must be present when > 1
      expect(label).toContain('hasta 3x cuotas sin interés');
    });

    test('UT-17b: omits pill text when installmentsWithoutFee === 1', () => {
      instance.INTEREST_FREE_PART_ONE_TEXT = 'hasta';
      instance.INTEREST_FREE_PART_TWO_TEXT = 'cuotas sin interés';
      const label = instance.buildPaymentMethodAriaLabel(creditCard('Visa Crédito'), '1234', 1);
      expect(label).not.toContain('cuotas sin interés');
    });

    test('UT-17c: omits pill text for non-card types even with installments > 1', () => {
      instance.INTEREST_FREE_PART_ONE_TEXT = 'hasta';
      instance.INTEREST_FREE_PART_TWO_TEXT = 'cuotas sin interés';
      const am = { type: 'account_money', name: 'Saldo en Mercado Pago' };
      const label = instance.buildPaymentMethodAriaLabel(am, null, 3);
      expect(label).not.toContain('cuotas sin interés');
      expect(label).toBe('Saldo en Mercado Pago');
    });
  });

});


describe('MPSuperTokenPaymentMethods — DOM adapter integration tests', () => {
  let MPSuperTokenPaymentMethodsIT;
  let instance;

  const mockBundleParamsIT = {
    yellow_wallet_path: '', yellow_money_path: '', white_card_path: '',
    new_mp_logo_path: '', mp_logo_blue_path: 'blue.png', mp_logo_dark_path: 'dark.png',
    current_user_email: '', payment_methods_list_text: '', payment_methods_list_alt_text: '',
    last_digits_text: 'ending in', new_card_text: 'New card', account_money_text: '',
    account_money_wallet_with_investment_text: '', account_money_wallet_text: '',
    account_money_investment_text: '', account_money_available_text: '',
    interest_free_part_one_text: '', interest_free_part_two_text: '',
    interest_free_option_text: '', security_code_input_title_text: '',
    security_code_placeholder_text_3_digits: '', security_code_placeholder_text_4_digits: '',
    security_code_tooltip_text_3_digits: '', security_code_tooltip_text_4_digits: '',
    security_code_error_message_text: '', mercado_pago_card_name: 'Cartão Mercado Pago',
    mercado_pago_credit_card_name: 'Cartão de Crédito Mercado Pago',
    consumer_credits_due_date: '', mlb_installment_debit_auto_text: '',
    interest_rate_mlb_text: '', effective_total_cost_mlb_text: '', iof_mlb_text: '',
    borrowed_amount_mlb_text: '', per_month: '', per_year: '',
    cat_mlm_text: '', no_iva_text: '', tna_mlm_text: '',
    system_amortization_mlm_text: '', cftea_mla_text: '', tna_mla_text: '', tea_mla_text: '',
    fixed_rate_text: '', mercadopago_privacy_policy: '',
    input_helper_message: { installments: {}, securityCode: {} },
    input_title: { installments: '' }, placeholders: { installments: '' },
    site_id: 'MLA', currency: 'ARS', intl: 'es-AR',
    saved_cards_title: 'Cartões salvos',
    saved_card_title: 'Cartão salvo',
    mp_methods_title: 'Você também pode usar',
    account_money_balance_text: 'Saldo suficiente para pagar esta compra.',
    saved_payment_method_title: 'Meio de pagamento salvo',
    payment_methods_list_text: 'Meios de pagamento salvos',
    payment_methods_thumbnails: {}, payment_methods_order: 'cards_first',
    update_security_code_with_retry_error_text: '',
    update_security_code_no_retry_error_text: '',
    authorize_payment_method_with_retry_error_text: '',
    authorize_payment_method_no_retry_error_text: '',
    select_payment_method_error_text: '',
  };

  const makeCardPM = (overrides = {}) => ({
    id: 'visa',
    type: 'credit_card',
    name: 'Visa Crédito',
    thumbnail: 'visa.png',
    token: 'token-visa',
    card: { card_number: { last_four_digits: '1234' } },
    installments: [],
    security_code_settings: { length: 3, card_location: 'back' },
    issuer: { name: 'Visa' },
    ...overrides,
  });

  const makeMpCardPM = (overrides = {}) => ({
    id: 'account_money_mp',
    type: 'prepaid_card',
    name: 'Cartão Mercado Pago',
    thumbnail: 'mp-thumb.png',
    token: 'token-mp',
    card: { card_number: { last_four_digits: '5678' } },
    installments: [],
    security_code_settings: { length: 3, card_location: 'back' },
    issuer: { name: 'Mercado Pago' },
    ...overrides,
  });

  const makeMpCreditCardPM = (overrides = {}) => ({
    id: 'visa',
    type: 'credit_card',
    name: 'Cartão Mercado Pago Crédito',
    thumbnail: 'https://http2.mlstatic.com/storage/cpp/static-files/e16e4f2c-8f19-495a-b24e-0cfaf37aea5a.png',
    token: 'token-mp-credit',
    card: { card_number: { last_four_digits: '1234', bin: '40784300', length: 16 } },
    installments: [],
    security_code_settings: { mode: 'optional', length: 3 },
    issuer: { name: 'Cartão Mercado Pago', id: 12510, bank: { country: 'BRA', name: 'Cartao Mercado Livre' } },
    ...overrides,
  });

  beforeAll(() => {
    global.wc_mercadopago_supertoken_bundle_params = mockBundleParamsIT;
    global.MPCheckoutFieldsDispatcher = undefined;
    global.MPSuperTokenErrorCodes = {
      SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND: 'SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND',
    };
    global.Intl = Intl;
    MPSuperTokenPaymentMethodsIT = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', global);
  });

  beforeEach(() => {
    document.body.innerHTML = '<div id="mp-checkout-super-token-root" class="mp-super-token-payment-methods-list"></div>';
    instance = new MPSuperTokenPaymentMethodsIT();
    instance.wcEmailListener = {
      isValid: (email) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email ?? ''),
      onEmailChange: () => {},
      getEmail: () => '',
    };
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  describe('renderSavedCardsBlock', () => {
    test('IT-01: creates section with header and escaped email', () => {
      instance.renderSavedCardsBlock([makeCardPM()], { email: 'user@test.com', icon: 'logo.png' });

      const section = document.querySelector('.mp-super-token-block--saved-cards');
      expect(section).not.toBeNull();
      const emailSpan = section.querySelector('.mp-super-token-block__email');
      expect(emailSpan).not.toBeNull();
      expect(emailSpan.textContent).toBe('user@test.com');
    });

    test('IT-01b: email in header is XSS-safe via innerHTML escapeHtml', () => {
      // Force isValid true so the XSS payload goes through escapeHtml in innerHTML
      const originalIsValid = instance.wcEmailListener.isValid;
      instance.wcEmailListener.isValid = () => true;

      instance.renderSavedCardsBlock([makeCardPM()], { email: '<script>alert(1)</script>', icon: '' });

      const section = document.querySelector('.mp-super-token-block--saved-cards');
      // escapeHtml converts < > into &lt; &gt; — the raw <script> tag should not appear
      expect(section.innerHTML).not.toContain('<script>');
      // The textContent should contain the raw string (not double-escaped)
      const emailSpan = section.querySelector('.mp-super-token-block__email');
      expect(emailSpan).not.toBeNull();
      expect(emailSpan.textContent).toBe('<script>alert(1)</script>');

      instance.wcEmailListener.isValid = originalIsValid;
    });

    test('IT-02: header omits email span when email is empty', () => {
      instance.renderSavedCardsBlock([makeCardPM()], { email: '', icon: 'logo.png' });

      const headerInfo = document.querySelector('.mp-super-token-block--saved-cards .mp-super-token-block__header-info');
      expect(headerInfo.querySelector('.mp-super-token-block__email')).toBeNull();
    });

    test('IT-02b: header omits email span when email is invalid', () => {
      instance.renderSavedCardsBlock([makeCardPM()], { email: 'not-an-email', icon: 'logo.png' });

      const headerInfo = document.querySelector('.mp-super-token-block--saved-cards .mp-super-token-block__header-info');
      expect(headerInfo.querySelector('.mp-super-token-block__email')).toBeNull();
    });
  });

  describe('setupEmailHeaderListener', () => {
    test('IT-03: onEmailChange updates email span in real time', () => {
      const capturedCallbacks = [];
      instance.wcEmailListener = {
        isValid: (email) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email ?? ''),
        onEmailChange: (cb) => capturedCallbacks.push(cb),
      };

      instance.renderSavedCardsBlock([makeCardPM()], { email: 'old@test.com', icon: '' });
      instance.setupEmailHeaderListener();

      capturedCallbacks[0]('new@test.com', true);

      const emailSpan = document.querySelector('.mp-super-token-block--saved-cards .mp-super-token-block__email');
      expect(emailSpan).not.toBeNull();
      expect(emailSpan.textContent).toBe('new@test.com');
    });

    test('IT-03b: onEmailChange removes email span when email becomes invalid', () => {
      const capturedCallbacks = [];
      instance.wcEmailListener = {
        isValid: (email) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email ?? ''),
        onEmailChange: (cb) => capturedCallbacks.push(cb),
      };

      instance.renderSavedCardsBlock([makeCardPM()], { email: 'valid@test.com', icon: '' });
      instance.setupEmailHeaderListener();

      capturedCallbacks[0]('invalid', false);

      expect(document.querySelector('.mp-super-token-block__email')).toBeNull();
    });

    test('IT-03c: onEmailChange updates email span in no-cards block (header in other-mp)', () => {
      const capturedCallbacks = [];
      instance.wcEmailListener = {
        isValid: (email) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email ?? ''),
        onEmailChange: (cb) => capturedCallbacks.push(cb),
      };

      const amPM = { id: 'account_money', type: 'account_money', name: 'Saldo', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };
      instance.renderOtherMpMethodsBlock([amPM], 'Meio de pagamento salvo', { email: 'old@test.com', icon: '' });
      instance.setupEmailHeaderListener();

      capturedCallbacks[0]('new@test.com', true);

      const emailSpan = document.querySelector('.mp-super-token-block--other-mp .mp-super-token-block__email');
      expect(emailSpan).not.toBeNull();
      expect(emailSpan.textContent).toBe('new@test.com');
    });
  });

  describe('renderOtherMpMethodsBlock', () => {
    const amPM = { id: 'account_money', type: 'account_money', name: 'Saldo', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };

    test('IT-04: block is omitted when otherPaymentMethods is empty', () => {
      instance.renderOtherMpMethodsBlock([]);
      expect(document.querySelector('.mp-super-token-block--other-mp')).toBeNull();
    });

    test('IT-04b: block is rendered when otherPaymentMethods has items', () => {
      instance.renderOtherMpMethodsBlock([amPM]);
      expect(document.querySelector('.mp-super-token-block--other-mp')).not.toBeNull();
    });

    test('IT-04c: uses custom title when provided', () => {
      instance.renderOtherMpMethodsBlock([amPM], 'Meio de pagamento salvo');
      const title = document.querySelector('.mp-super-token-block--other-mp .mp-super-token-block__title');
      expect(title.textContent).toBe('Meio de pagamento salvo');
    });

    test('IT-04d: renders email and logo in header when blockHeader is provided', () => {
      instance.renderOtherMpMethodsBlock([amPM], 'Meios de pagamento salvos', { email: 'u@test.com', icon: 'logo.png' });
      const block = document.querySelector('.mp-super-token-block--other-mp');
      expect(block.querySelector('.mp-super-token-block__email')).not.toBeNull();
      expect(block.querySelector('.mp-super-token-block__email').textContent).toBe('u@test.com');
      expect(block.querySelector('.mp-super-token-block__header-logo')).not.toBeNull();
    });

    test('IT-04e: omits email span when blockHeader email is invalid', () => {
      instance.renderOtherMpMethodsBlock([amPM], 'Meios de pagamento salvos', { email: '', icon: 'logo.png' });
      const block = document.querySelector('.mp-super-token-block--other-mp');
      expect(block.querySelector('.mp-super-token-block__email')).toBeNull();
    });
  });

  describe('renderSavedCardsBlock — singular/plural title', () => {
    test('IT-13: uses singular title when 1 card', () => {
      instance.renderSavedCardsBlock([makeCardPM()], { email: '', icon: '' }, 'Cartão salvo');
      const title = document.querySelector('.mp-super-token-block--saved-cards .mp-super-token-block__title');
      expect(title.textContent).toBe('Cartão salvo');
    });

    test('IT-13b: uses plural title (default) when 2+ cards', () => {
      instance.renderSavedCardsBlock([makeCardPM(), makeCardPM({ id: 'mastercard' })], { email: '', icon: '' });
      const title = document.querySelector('.mp-super-token-block--saved-cards .mp-super-token-block__title');
      expect(title.textContent).toBe('Cartões salvos');
    });
  });

  describe('normalizeAccountPaymentMethods — MP credit card', () => {
    const mpCreditPM = () => ({
      id: 'visa',
      type: 'credit_card',
      name: 'Cartão Mercado Pago Crédito',
      thumbnail: '',
      token: 'token-mp-credit',
      card: { card_number: { last_four_digits: '1234' } },
      installments: [],
      security_code_settings: { mode: 'optional', length: 3 },
      issuer: { name: 'Cartão Mercado Pago', id: 12510 },
    });

    test('IT-16: MP credit card gets country icon when no API thumbnail', () => {
      const [normalized] = instance.normalizeAccountPaymentMethods([mpCreditPM()]);
      expect(normalized.thumbnail).toBe('blue.png');
    });

    test('IT-16b: country icon always takes precedence over API thumbnail for MP credit card', () => {
      const pm = mpCreditPM();
      pm.thumbnail = 'https://api.mp.com/real-thumb.png';
      const [normalized] = instance.normalizeAccountPaymentMethods([pm]);
      expect(normalized.thumbnail).toBe('blue.png');
    });

    test('IT-16c: MP credit card name is overridden with the localized MP credit card name (PSW-4097)', () => {
      const [normalized] = instance.normalizeAccountPaymentMethods([mpCreditPM()]);
      expect(normalized.name).toBe('Cartão de Crédito Mercado Pago');
    });

    test('IT-16d: regular credit card still gets issuer + Crédito suffix', () => {
      const regularCard = {
        id: 'visa', type: 'credit_card', name: 'Visa', thumbnail: '',
        installments: [], security_code_settings: { length: 3, card_location: 'back' },
        issuer: { name: 'Banco Itaú' },
        card: { card_number: { last_four_digits: '1234' } },
      };
      const [normalized] = instance.normalizeAccountPaymentMethods([regularCard]);
      expect(normalized.name).toBe('Banco Itaú Crédito');
    });

    test('IT-16e: regular credit card still falls back to WHITE_CARD_PATH', () => {
      const regularCard = {
        id: 'visa', type: 'credit_card', name: 'Visa', thumbnail: '',
        installments: [], security_code_settings: { length: 3, card_location: 'back' },
        issuer: { name: 'Banco Itaú' },
        card: { card_number: { last_four_digits: '1234' } },
      };
      const [normalized] = instance.normalizeAccountPaymentMethods([regularCard]);
      expect(normalized.thumbnail).toBe(instance.WHITE_CARD_PATH);
    });

    test('IT-16f: MP credit card gets dark icon for MLB', () => {
      const originalSiteId = instance.SITE_ID;
      instance.SITE_ID = 'MLB';
      try {
        const [normalized] = instance.normalizeAccountPaymentMethods([mpCreditPM()]);
        expect(normalized.thumbnail).toBe('dark.png');
      } finally {
        instance.SITE_ID = originalSiteId;
      }
    });

    test('IT-16g: MP credit card rendered title uses the localized name after normalize (full path)', () => {
      const [normalized] = instance.normalizeAccountPaymentMethods([mpCreditPM()]);
      const el = instance.createPaymentMethodElement(normalized);
      const title = el.querySelector('.mp-super-token-payment-method__title');
      expect(title).not.toBeNull();
      expect(title.textContent.trim()).toBe('Cartão de Crédito Mercado Pago');
    });

    test('IT-16h: MP credit card name falls back to API name when localized constant is missing (partial deploy / stale cache)', () => {
      const originalName = instance.MERCADO_PAGO_CREDIT_CARD_NAME;
      instance.MERCADO_PAGO_CREDIT_CARD_NAME = undefined;
      try {
        const [normalized] = instance.normalizeAccountPaymentMethods([mpCreditPM()]);
        // Degrades gracefully to the API name instead of an empty string
        expect(normalized.name).toBe('Cartão Mercado Pago Crédito');
      } finally {
        instance.MERCADO_PAGO_CREDIT_CARD_NAME = originalName;
      }
    });
  });

  describe('organizePaymentMethodsElements', () => {
    const amPM = { id: 'account_money', type: 'account_money', name: 'Saldo', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };
    const creditsPM = { id: 'consumer_credits', type: 'digital_currency', name: 'Credits', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };

    test('IT-14: 0 cards + 1 other → only Block 2 with singular title and header', () => {
      instance.organizePaymentMethodsElements([amPM]);

      expect(document.querySelector('.mp-super-token-block--saved-cards')).toBeNull();
      const block2 = document.querySelector('.mp-super-token-block--other-mp');
      expect(block2).not.toBeNull();
      expect(block2.querySelector('.mp-super-token-block__title').textContent).toBe('Meio de pagamento salvo');
      expect(block2.querySelector('.mp-super-token-block__header-info')).not.toBeNull();
    });

    test('IT-14b: 0 cards + 2 others → renders Block 2 with plural title', () => {
      instance.mpSuperTokenMetrics = { renderConsumerCreditsDetailsInnerHTML: jest.fn() };
      instance.organizePaymentMethodsElements([amPM, creditsPM]);

      expect(document.querySelector('.mp-super-token-block--saved-cards')).toBeNull();
      const block2 = document.querySelector('.mp-super-token-block--other-mp');
      expect(block2).not.toBeNull();
      expect(block2.querySelector('.mp-super-token-block__title').textContent).toBe('Meios de pagamento salvos');
      expect(block2.querySelector('.mp-super-token-block__header-info')).not.toBeNull();
    });

    test('IT-14c: 0 cards + 0 others → nothing rendered', () => {
      instance.organizePaymentMethodsElements([]);

      expect(document.querySelector('.mp-super-token-block--saved-cards')).toBeNull();
      expect(document.querySelector('.mp-super-token-block--other-mp')).toBeNull();
    });

    test('IT-15: 1 card → Block 1 with singular title, Block 2 without header', () => {
      instance.organizePaymentMethodsElements([makeCardPM()]);

      const block1 = document.querySelector('.mp-super-token-block--saved-cards');
      expect(block1).not.toBeNull();
      expect(block1.querySelector('.mp-super-token-block__title').textContent).toBe('Cartão salvo');
      expect(block1.querySelector('.mp-super-token-block__header-info')).not.toBeNull();
      expect(document.querySelector('.mp-super-token-block--other-mp')).toBeNull();
    });

    test('IT-15b: 2 cards → Block 1 with plural title', () => {
      instance.organizePaymentMethodsElements([makeCardPM(), makeCardPM({ id: 'mastercard' })]);

      const block1 = document.querySelector('.mp-super-token-block--saved-cards');
      expect(block1.querySelector('.mp-super-token-block__title').textContent).toBe('Cartões salvos');
    });

    test('IT-15c: 2 cards + 1 other → both blocks rendered; Block 2 has no header', () => {
      instance.organizePaymentMethodsElements([makeCardPM(), makeCardPM({ id: 'mastercard' }), amPM]);

      expect(document.querySelector('.mp-super-token-block--saved-cards')).not.toBeNull();
      const block2 = document.querySelector('.mp-super-token-block--other-mp');
      expect(block2).not.toBeNull();
      expect(block2.querySelector('.mp-super-token-block__header-info')).toBeNull();
    });
  });

  describe('applyAccountMoneySelectionDecoration / removeAccountMoneyBalanceLine', () => {
    test('IT-05: inserts balance paragraph inside .mp-super-token-payment-method__content when present', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      paymentMethodRow.appendChild(contentDiv);
      container.appendChild(paymentMethodRow);

      instance.applyAccountMoneySelectionDecoration(paymentMethodRow);

      const balanceParagraph = contentDiv.querySelector('.mp-super-token-am-balance-text');
      expect(balanceParagraph).not.toBeNull();
      expect(balanceParagraph.textContent).toBe('Saldo suficiente para pagar esta compra.');
    });

    test('IT-05b: skips decoration when .mp-super-token-payment-method__content is absent', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      container.appendChild(paymentMethodRow);

      instance.applyAccountMoneySelectionDecoration(paymentMethodRow);

      // No content div means invalid DOM structure — balance text should not be inserted anywhere
      expect(document.querySelector('.mp-super-token-am-balance-text')).toBeNull();
    });

    test('IT-06: removeAccountMoneyBalanceLine triggers close animation', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      paymentMethodRow.appendChild(contentDiv);
      container.appendChild(paymentMethodRow);

      instance.applyAccountMoneySelectionDecoration(paymentMethodRow);
      expect(document.querySelector('.mp-super-token-am-balance-text')).not.toBeNull();

      instance.removeAccountMoneyBalanceLine();
      // Close animation is initiated: --open class removed (drives CSS transition); the node is
      // scheduled for DOM removal when the close transition ends (transitionend, with timeout fallback).
      expect(document.querySelector('.mp-super-token-am-balance-text--open')).toBeNull();
    });
  });

  describe('selectPaymentMethod — AM decoration', () => {
    test('IT-11: selectPaymentMethod applies decoration when data-type is account_money', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const amElement = document.createElement('article');
      amElement.dataset.type = 'account_money';
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      amElement.appendChild(contentDiv);
      container.appendChild(amElement);

      instance.selectPaymentMethod(amElement);

      expect(amElement.classList.contains('mp-super-token-payment-method__selected')).toBe(true);
      expect(contentDiv.querySelector('.mp-super-token-am-balance-text')).not.toBeNull();
    });

    test('IT-11b: selectPaymentMethod does not apply decoration for non-AM types', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const cardElement = document.createElement('article');
      cardElement.dataset.type = 'credit_card';
      container.appendChild(cardElement);

      instance.selectPaymentMethod(cardElement);

      expect(cardElement.classList.contains('mp-super-token-payment-method__selected')).toBe(true);
      expect(document.querySelector('.mp-super-token-am-balance-text')).toBeNull();
    });
  });

  describe('deselectAllPaymentMethods — balance line cleanup', () => {
    test('IT-12: deselectAllPaymentMethods triggers balance line close animation', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const amElement = document.createElement('article');
      amElement.dataset.type = 'account_money';
      amElement.classList.add('mp-super-token-payment-method__selected');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      amElement.appendChild(contentDiv);
      container.appendChild(amElement);

      instance.applyAccountMoneySelectionDecoration(amElement);
      expect(document.querySelector('.mp-super-token-am-balance-text')).not.toBeNull();

      instance.deselectAllPaymentMethods();

      expect(amElement.classList.contains('mp-super-token-payment-method__selected')).toBe(false);
      // Close animation initiated: --open class removed; DOM removal deferred to transitionend (timeout fallback)
      expect(document.querySelector('.mp-super-token-am-balance-text--open')).toBeNull();
    });
  });

  describe('reset', () => {
    test('IT-07: reset removes all blocks and initiates balance line close animation', () => {
      const amPM = { id: 'account_money', type: 'account_money', name: 'Saldo', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };

      instance.renderSavedCardsBlock([makeCardPM()], { email: 'u@test.com', icon: '' });
      instance.renderOtherMpMethodsBlock([amPM]);

      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      paymentMethodRow.appendChild(contentDiv);
      container.appendChild(paymentMethodRow);
      instance.applyAccountMoneySelectionDecoration(paymentMethodRow);

      expect(document.querySelectorAll('.mp-super-token-block').length).toBeGreaterThan(0);
      expect(document.querySelector('.mp-super-token-am-balance-text')).not.toBeNull();

      instance.reset();

      expect(document.querySelectorAll('.mp-super-token-block').length).toBe(0);
      // reset() initiates the balance line close animation; the node is scheduled for removal on transitionend (timeout fallback)
      expect(document.querySelector('.mp-super-token-am-balance-text--open')).toBeNull();
    });
  });

  describe('renderSavedCardsBlock — ARIA structure', () => {
    test('IT-18: section has role=group, aria-label and tabindex', () => {
      instance.renderSavedCardsBlock([makeCardPM()], { email: 'u@test.com', icon: '' });
      const section = document.querySelector('.mp-super-token-block--saved-cards');
      expect(section.getAttribute('role')).toBe('group');
      expect(section.getAttribute('aria-label')).toBe('Cartões salvos');
      expect(section.getAttribute('tabindex')).toBe('0');
    });

    test('IT-18b: block header logo has aria-hidden=true and empty alt', () => {
      instance.renderSavedCardsBlock([makeCardPM()], { email: 'u@test.com', icon: 'logo.png' });
      const logo = document.querySelector('.mp-super-token-block__header-logo');
      expect(logo.getAttribute('aria-hidden')).toBe('true');
      expect(logo.getAttribute('alt')).toBe('');
    });
  });

  describe('renderOtherMpMethodsBlock — ARIA structure', () => {
    test('IT-19: section has role=group, aria-label and tabindex', () => {
      const amPM = { id: 'account_money', type: 'account_money', name: 'Saldo', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };
      instance.renderOtherMpMethodsBlock([amPM]);
      const section = document.querySelector('.mp-super-token-block--other-mp');
      expect(section.getAttribute('role')).toBe('group');
      expect(section.getAttribute('aria-label')).toBe('Você também pode usar');
      expect(section.getAttribute('tabindex')).toBe('0');
    });
  });

  describe('applyAccountMoneySelectionDecoration — aria-label update', () => {
    test('IT-20: updates row aria-label to include balance text on selection', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const amElement = document.createElement('article');
      amElement.setAttribute('aria-label', 'Saldo no Mercado Pago');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      amElement.appendChild(contentDiv);
      container.appendChild(amElement);

      instance.applyAccountMoneySelectionDecoration(amElement);

      expect(amElement.getAttribute('aria-label')).toBe('Saldo no Mercado Pago. Saldo suficiente para pagar esta compra.');
    });

    test('IT-20b: balance paragraph has aria-live=polite and is appended to DOM before textContent is set', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const amElement = document.createElement('article');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      amElement.appendChild(contentDiv);
      container.appendChild(amElement);

      instance.applyAccountMoneySelectionDecoration(amElement);

      const balanceParagraph = contentDiv.querySelector('.mp-super-token-am-balance-text');
      expect(balanceParagraph.getAttribute('aria-live')).toBe('polite');
      expect(balanceParagraph.textContent).toBe('Saldo suficiente para pagar esta compra.');
    });
  });

  describe('deselectAllPaymentMethods — aria-label restore', () => {
    test('IT-21: restores AM row aria-label from dataset.baseAriaLabel on deselect', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const amElement = document.createElement('article');
      amElement.dataset.type = 'account_money';
      amElement.dataset.baseAriaLabel = 'Saldo no Mercado Pago';
      amElement.setAttribute('aria-label', 'Saldo no Mercado Pago');
      amElement.classList.add('mp-super-token-payment-method__selected');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      amElement.appendChild(contentDiv);
      container.appendChild(amElement);

      instance.applyAccountMoneySelectionDecoration(amElement);
      expect(amElement.getAttribute('aria-label')).toContain('Saldo suficiente para pagar esta compra.');

      instance.deselectAllPaymentMethods();

      expect(amElement.getAttribute('aria-label')).toBe('Saldo no Mercado Pago');
    });
  });

  describe('addMercadoPagoPrivacyPolicyFooter — privacy link accessibility', () => {
    test('IT-22: privacy link is a native <a> without redundant role or tabindex', () => {
      instance.MERCADO_PAGO_PRIVACY_POLICY = 'Saiba <a href="https://mp.com/privacy" target="_blank">como cuidamos da sua privacidade</a>.';
      instance.addMercadoPagoPrivacyPolicyFooter();
      const link = document.querySelector('#mp-super-token-privacy-policy-footer a');
      expect(link).not.toBeNull();
      expect(link.getAttribute('href')).toBe('https://mp.com/privacy');
      // role="link" and tabindex="0" are redundant on <a href> — not set
      expect(link.getAttribute('role')).toBeNull();
      expect(link.getAttribute('tabindex')).toBeNull();
    });
  });

  describe('createPaymentMethodElement — aria-hidden on thumbnail', () => {
    test('IT-23: thumbnail img has aria-hidden=true', () => {
      const el = instance.createPaymentMethodElement(makeCardPM());
      const img = el.querySelector('.mp-super-token-payment-method__thumbnail img');
      expect(img.getAttribute('aria-hidden')).toBe('true');
    });
  });

  describe('buildPaymentMethodAriaLabel — nbsp decode', () => {
    test('UT-18: decodes &nbsp; in payment method name', () => {
      const pm = { type: 'digital_currency', name: 'Linha de Crédito Mercado&nbsp;Pago' };
      const label = instance.buildPaymentMethodAriaLabel(pm, null, 0);
      expect(label).toBe('Linha de Crédito Mercado Pago');
      expect(label).not.toContain('&nbsp;');
    });
  });

  describe('createPaymentMethodElement — MP Card thumbnail', () => {
    test('IT-08: omits last-four-digits for Mercado Pago credit card', () => {
      const pm = makeMpCreditCardPM();
      const el = instance.createPaymentMethodElement(pm);
      expect(el.querySelector('.mp-super-token-payment-method__last-four-digits')).toBeNull();
    });

    test('IT-09: country icon takes precedence over API thumbnail for MP credit card', () => {
      const pm = makeMpCreditCardPM();
      const el = instance.createPaymentMethodElement(pm);
      const img = el.querySelector('img');
      expect(img).not.toBeNull();
      expect(img.getAttribute('src')).toBe('blue.png');
    });

    test('IT-10: API thumbnail used as fallback when country icon path is not configured', () => {
      const savedBlue = instance.MP_LOGO_BLUE_PATH;
      const savedDark = instance.MP_LOGO_DARK_PATH;
      try {
        instance.MP_LOGO_BLUE_PATH = '';
        instance.MP_LOGO_DARK_PATH = '';
        const pm = makeMpCreditCardPM();
        const el = instance.createPaymentMethodElement(pm);
        const img = el.querySelector('img');
        expect(img).not.toBeNull();
        expect(img.getAttribute('src')).toBe('https://http2.mlstatic.com/storage/cpp/static-files/e16e4f2c-8f19-495a-b24e-0cfaf37aea5a.png');
      } finally {
        instance.MP_LOGO_BLUE_PATH = savedBlue;
        instance.MP_LOGO_DARK_PATH = savedDark;
      }
    });
  });

  // Lock-in for the structural contract used by the cascade CSS animation
  // (rules in super-token-payment-methods.css rely on the base + modifier classes and on the header position)
  describe('section blocks — CSS animation contract', () => {
    test('IT-24: saved-cards section has both base and modifier classes', () => {
      instance.renderSavedCardsBlock([makeCardPM()], { email: 'u@test.com', icon: '' });
      const section = document.querySelector('.mp-super-token-block--saved-cards');
      expect(section.classList.contains('mp-super-token-block')).toBe(true);
      expect(section.classList.contains('mp-super-token-block--saved-cards')).toBe(true);
    });

    test('IT-24b: other-mp section has both base and modifier classes', () => {
      const amPM = { id: 'account_money', type: 'account_money', name: 'Saldo', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };
      instance.renderOtherMpMethodsBlock([amPM]);
      const section = document.querySelector('.mp-super-token-block--other-mp');
      expect(section.classList.contains('mp-super-token-block')).toBe(true);
      expect(section.classList.contains('mp-super-token-block--other-mp')).toBe(true);
    });

    test('IT-25: saved-cards section has header as firstElementChild followed by payment methods', () => {
      instance.renderSavedCardsBlock(
        [makeCardPM(), makeCardPM({ id: 'mastercard' })],
        { email: 'u@test.com', icon: '' }
      );
      const section = document.querySelector('.mp-super-token-block--saved-cards');
      expect(section.firstElementChild.tagName).toBe('HEADER');
      expect(section.firstElementChild.classList.contains('mp-super-token-block__header')).toBe(true);
      const paymentMethods = section.querySelectorAll(':scope > .mp-super-token-payment-method');
      expect(paymentMethods.length).toBe(2);
    });

    test('IT-25b: other-mp section has header as firstElementChild followed by payment methods', () => {
      const amPM = { id: 'account_money', type: 'account_money', name: 'Saldo', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };
      const creditsPM = { id: 'consumer_credits', type: 'consumer_credits', name: 'Linha de crédito', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };
      instance.renderOtherMpMethodsBlock([amPM, creditsPM]);
      const section = document.querySelector('.mp-super-token-block--other-mp');
      expect(section.firstElementChild.tagName).toBe('HEADER');
      expect(section.firstElementChild.classList.contains('mp-super-token-block__header')).toBe(true);
      const paymentMethods = section.querySelectorAll(':scope > .mp-super-token-payment-method');
      expect(paymentMethods.length).toBe(2);
    });
  });

  // Lock-in for the Account Money row open/close animation contract (PSW-4047)
  // The CSS transitions in super-token-payment-methods.css depend on:
  //   - createPaymentMethodElement adding `mp-super-token-account-money-row` (base class) only for AM
  //   - applyAccountMoneySelectionDecoration toggling `--open` on row + balance line via requestAnimationFrame (open)
  //   - removeAccountMoneyBalanceLine toggling `--open` off on row + balance line, then removing the node on transitionend (timeout fallback) (close)
  describe('Account Money row & new-card accordion — selection/animation contracts', () => {
    test('IT-26: createPaymentMethodElement adds mp-super-token-account-money-row class only for AM type', () => {
      const amPM = { id: 'account_money', type: 'account_money', name: 'Saldo', thumbnail: '', installments: [], security_code_settings: { length: 0, card_location: 'back' } };

      const amEl = instance.createPaymentMethodElement(amPM);
      const cardEl = instance.createPaymentMethodElement(makeCardPM());

      expect(amEl.classList.contains('mp-super-token-account-money-row')).toBe(true);
      expect(cardEl.classList.contains('mp-super-token-account-money-row')).toBe(false);
    });

    // The source class is loaded via vm.runInNewContext (see helpers/load-file.js).
    // The VM has its own copies of `requestAnimationFrame` / `setTimeout`, so
    // `jest.spyOn(global, ...)` and `jest.useFakeTimers()` cannot intercept calls
    // made inside the class — we must inject the mocks through the VM context
    // at load time (same pattern used at line 343 and 452).
    // If rafQueue is provided, rAF callbacks are queued (flush manually) instead of
    // running synchronously — used to simulate a stale frame in IT-31.
    const loadInstanceWithAnimMocks = (timeoutQueue, rafQueue) => {
      const Class = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', {
        ...global,
        requestAnimationFrame: (cb) => {
          if (rafQueue) { rafQueue.push(cb); return rafQueue.length; }
          cb(); return 1;
        },
        setTimeout: (cb, ms) => {
          if (timeoutQueue) timeoutQueue.push({ cb, ms });
          return (timeoutQueue?.length) ?? 1;
        },
        clearTimeout: () => {},
      });
      return new Class();
    };

    test('IT-27: applyAccountMoneySelectionDecoration adds --open to row and balance line after rAF flush', () => {
      const localInstance = loadInstanceWithAnimMocks();

      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      paymentMethodRow.classList.add('mp-super-token-account-money-row', 'mp-super-token-payment-method__selected');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      paymentMethodRow.appendChild(contentDiv);
      container.appendChild(paymentMethodRow);

      localInstance.applyAccountMoneySelectionDecoration(paymentMethodRow);

      expect(paymentMethodRow.classList.contains('mp-super-token-account-money-row--open')).toBe(true);
      const balanceParagraph = contentDiv.querySelector('.mp-super-token-am-balance-text');
      expect(balanceParagraph).not.toBeNull();
      expect(balanceParagraph.classList.contains('mp-super-token-am-balance-text--open')).toBe(true);
    });

    test('IT-28: removeAccountMoneyBalanceLine removes --open from row (drives title and gap close transition)', () => {
      const localInstance = loadInstanceWithAnimMocks();

      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      paymentMethodRow.classList.add('mp-super-token-account-money-row');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      paymentMethodRow.appendChild(contentDiv);
      container.appendChild(paymentMethodRow);

      paymentMethodRow.classList.add('mp-super-token-payment-method__selected');
      localInstance.applyAccountMoneySelectionDecoration(paymentMethodRow);
      expect(paymentMethodRow.classList.contains('mp-super-token-account-money-row--open')).toBe(true);

      localInstance.removeAccountMoneyBalanceLine();

      expect(paymentMethodRow.classList.contains('mp-super-token-account-money-row--open')).toBe(false);
      // Base class is preserved — the row itself remains in the DOM
      expect(paymentMethodRow.classList.contains('mp-super-token-account-money-row')).toBe(true);
    });

    test('IT-29: removeAccountMoneyBalanceLine removes the node when the close transition ends (event-driven)', () => {
      const timeoutQueue = [];
      const localInstance = loadInstanceWithAnimMocks(timeoutQueue);

      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      paymentMethodRow.classList.add('mp-super-token-account-money-row', 'mp-super-token-payment-method__selected');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      paymentMethodRow.appendChild(contentDiv);
      container.appendChild(paymentMethodRow);

      localInstance.applyAccountMoneySelectionDecoration(paymentMethodRow);
      const balanceLine = document.querySelector('.mp-super-token-am-balance-text');
      expect(balanceLine).not.toBeNull();

      localInstance.removeAccountMoneyBalanceLine();

      // --open removed (CSS close transition is in flight), but the node stays until the transition ends
      expect(document.querySelector('.mp-super-token-am-balance-text--open')).toBeNull();
      expect(document.querySelector('.mp-super-token-am-balance-text')).not.toBeNull();

      // transitionend of max-height removes the node
      const transitionEnd = new Event('transitionend');
      transitionEnd.propertyName = 'max-height';
      balanceLine.dispatchEvent(transitionEnd);
      expect(document.querySelector('.mp-super-token-am-balance-text')).toBeNull();

      // Firing the fallback timeout afterwards is a no-op (idempotent removal)
      expect(() => timeoutQueue[0]?.cb()).not.toThrow();
    });

    // Lock-in for the semibold-on-selected CSS rule: the new-card accordion must get `__selected`
    // with `__accordion-title` nested, so `.__accordion.__selected .__accordion-title` applies.
    test('IT-30: marks the accordion as selected, keeping the accordion-title nested inside it', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');

      const accordion = document.createElement('div');
      accordion.classList.add('mp-super-token-payment-method__accordion');

      const accordionContent = document.createElement('div');
      accordionContent.classList.add('mp-super-token-payment-method__accordion-content');

      const accordionHeader = document.createElement('section');
      accordionHeader.classList.add('mp-super-token-payment-method__accordion-header');
      accordionHeader.innerHTML = '<span class="mp-super-token-payment-method__accordion-title">Novo cartão</span>';

      accordion.appendChild(accordionContent);
      accordion.appendChild(accordionHeader);
      container.appendChild(accordion);

      instance.selectNewCardAccordion();

      // Contract the semibold rule depends on: `.__accordion.__selected .__accordion-title`
      expect(accordion.classList.contains('mp-super-token-payment-method__selected')).toBe(true);
      expect(accordion.querySelector('.mp-super-token-payment-method__accordion-title')).not.toBeNull();
      expect(accordionHeader.getAttribute('aria-selected')).toBe('true');
    });

    // Stale-frame guard (Codex P2): if the row gets deselected before the queued rAF runs,
    // the frame must NOT reopen it — avoids an orphan --open on an unselected row.
    test('IT-31: a stale animation frame does not reopen a row deselected before it ran', () => {
      const rafQueue = [];
      const localInstance = loadInstanceWithAnimMocks(undefined, rafQueue);

      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      paymentMethodRow.classList.add('mp-super-token-account-money-row', 'mp-super-token-payment-method__selected');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      paymentMethodRow.appendChild(contentDiv);
      container.appendChild(paymentMethodRow);

      // Open is queued but the frame has not run yet
      localInstance.applyAccountMoneySelectionDecoration(paymentMethodRow);
      expect(rafQueue.length).toBe(1);
      expect(paymentMethodRow.classList.contains('mp-super-token-account-money-row--open')).toBe(false);

      // Another method is selected in the meantime → this row is deselected
      paymentMethodRow.classList.remove('mp-super-token-payment-method__selected');

      // Stale frame finally runs — guard must skip the reopen
      rafQueue[0]();

      expect(paymentMethodRow.classList.contains('mp-super-token-account-money-row--open')).toBe(false);
      expect(contentDiv.querySelector('.mp-super-token-am-balance-text--open')).toBeNull();
    });

    test('IT-32: removeAccountMoneyBalanceLine falls back to a timeout if transitionend never fires', () => {
      const timeoutQueue = [];
      const localInstance = loadInstanceWithAnimMocks(timeoutQueue);

      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      paymentMethodRow.classList.add('mp-super-token-account-money-row', 'mp-super-token-payment-method__selected');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      paymentMethodRow.appendChild(contentDiv);
      container.appendChild(paymentMethodRow);

      localInstance.applyAccountMoneySelectionDecoration(paymentMethodRow);
      localInstance.removeAccountMoneyBalanceLine();
      expect(document.querySelector('.mp-super-token-am-balance-text')).not.toBeNull();

      // Fallback is queued with the transition duration + a small buffer (300 + 50)
      expect(timeoutQueue.length).toBe(1);
      expect(timeoutQueue[0].ms).toBe(350);

      // When it fires (transitionend never came), the node is removed
      timeoutQueue[0].cb();
      expect(document.querySelector('.mp-super-token-am-balance-text')).toBeNull();
    });

    // Fast-toggle resilience (review P3): the deferred node removal could leave two balance
    // lines coexisting; re-decorating must clear leftovers synchronously so only one exists.
    test('IT-33: re-decorating removes leftover balance lines synchronously (no coexisting nodes)', () => {
      const container = document.querySelector('#mp-checkout-super-token-root');
      const amElement = document.createElement('article');
      amElement.classList.add('mp-super-token-account-money-row', 'mp-super-token-payment-method__selected');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      amElement.appendChild(contentDiv);
      container.appendChild(amElement);

      // Simulate a leftover balance line still in the DOM (deferred removal from a prior close)
      const leftover = document.createElement('p');
      leftover.classList.add('mp-super-token-am-balance-text', 'mp-super-token-am-balance-text--open');
      contentDiv.appendChild(leftover);

      instance.applyAccountMoneySelectionDecoration(amElement);

      // The leftover was removed synchronously and exactly one balance line remains
      expect(container.querySelectorAll('.mp-super-token-am-balance-text').length).toBe(1);
      expect(container.contains(leftover)).toBe(false);
    });

    // Per-node idempotency: a second close on the same node must not register duplicate
    // transitionend listeners / fallback timers (review hardening — dataset.closing guard).
    test('IT-34: removeAccountMoneyBalanceLine does not register duplicate handlers on a re-close', () => {
      const timeoutQueue = [];
      const localInstance = loadInstanceWithAnimMocks(timeoutQueue);

      const container = document.querySelector('#mp-checkout-super-token-root');
      const paymentMethodRow = document.createElement('article');
      paymentMethodRow.classList.add('mp-super-token-account-money-row', 'mp-super-token-payment-method__selected');
      const contentDiv = document.createElement('div');
      contentDiv.classList.add('mp-super-token-payment-method__content');
      paymentMethodRow.appendChild(contentDiv);
      container.appendChild(paymentMethodRow);

      localInstance.applyAccountMoneySelectionDecoration(paymentMethodRow);

      // First close: flags the node and queues one fallback timer
      localInstance.removeAccountMoneyBalanceLine();
      expect(timeoutQueue.length).toBe(1);
      expect(document.querySelector('.mp-super-token-am-balance-text').dataset.closing).toBe('1');

      // Second close on the same node: early-returns, no extra timer/listener
      localInstance.removeAccountMoneyBalanceLine();
      expect(timeoutQueue.length).toBe(1);
    });
  });
});
