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
  });
});
