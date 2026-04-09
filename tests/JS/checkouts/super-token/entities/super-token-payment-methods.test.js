const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const superTokenPaymentMethodsPath = resolveAlias('assets/js/checkouts/super-token/entities/super-token-payment-methods.js');

describe('MPSuperTokenPaymentMethods', () => {
  let paymentMethods;
  let MPSuperTokenPaymentMethods;
  let mockMpSdkInstance;
  let mockMpSuperTokenMetrics;

  beforeAll(() => {
    global.wc_mercadopago_supertoken_bundle_params = {
      yellow_wallet_path: '',
      yellow_money_path: '',
      white_card_path: '',
      payment_methods_list_text: '',
      payment_methods_list_alt_text: '',
      last_digits_text: '',
      new_card_text: '',
      account_money_text: '',
      account_money_wallet_with_investment_text: '',
      account_money_wallet_text: '',
      account_money_investment_text: '',
      account_money_available_text: '',
      interest_free_part_one_text: '',
      interest_free_part_two_text: '',
      input_helper_message: {
        installments: {
          bank_interest_hint_text: '',
          required: '',
          interest_free_option_text: '',
        },
        securityCode: {},
      },
      input_title: { installments: '' },
      placeholders: { installments: '' },
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
      cftea_mla_text: '',
      tna_mla_text: '',
      tea_mla_text: '',
      fixed_rate_text: '',
      mercadopago_privacy_policy: '',
      new_mp_logo_path: '',
      payment_methods_thumbnails: {},
      payment_methods_order: 'cards_first',
      update_security_code_with_retry_error_text: '',
      update_security_code_no_retry_error_text: '',
      authorize_payment_method_with_retry_error_text: '',
      authorize_payment_method_no_retry_error_text: '',
      select_payment_method_error_text: '',
    };

    global.Intl = Intl;
    global.MPCheckoutFieldsDispatcher = class {};
    global.MPSuperTokenErrorCodes = {
      UPDATE_SECURITY_CODE_ERROR: 'UPDATE_SECURITY_CODE_ERROR',
      AUTHORIZE_PAYMENT_METHOD_ERROR: 'AUTHORIZE_PAYMENT_METHOD_ERROR',
      SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR',
    };

    MPSuperTokenPaymentMethods = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();

    mockMpSdkInstance = {
      getSDKInstanceId: jest.fn(() => 'test-sdk-instance-id'),
    };

    mockMpSuperTokenMetrics = {
      sendMetric: jest.fn(),
    };

    document.body.innerHTML = `
      <input id="mp_checkout_type" value="" />
      <div class="mp-checkout-custom-card-flags"></div>
      <div class="mp-wallet-button-container-wrapper"></div>
    `;

    paymentMethods = new MPSuperTokenPaymentMethods(mockMpSdkInstance, mockMpSuperTokenMetrics);
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  describe('reset()', () => {
    test('Given checkout type is super_token, When reset() is called, Then should set checkout type to custom', () => {
      document.querySelector('#mp_checkout_type').value = 'super_token';

      paymentMethods.reset();

      expect(document.querySelector('#mp_checkout_type').value).toBe('custom');
    });

    test('Given checkout type is already custom, When reset() is called, Then should keep checkout type as custom (idempotent)', () => {
      document.querySelector('#mp_checkout_type').value = 'custom';

      paymentMethods.reset();

      expect(document.querySelector('#mp_checkout_type').value).toBe('custom');
    });

    test('Given checkout type is empty, When reset() is called, Then should set checkout type to custom', () => {
      document.querySelector('#mp_checkout_type').value = '';

      paymentMethods.reset();

      expect(document.querySelector('#mp_checkout_type').value).toBe('custom');
    });

    test('Given reset() is called, Then should clear paymentMethods array', () => {
      paymentMethods.paymentMethods = [{ id: 1 }];

      paymentMethods.reset();

      expect(paymentMethods.paymentMethods).toEqual([]);
    });

    test('Given reset() is called, Then should set activePaymentMethod to null', () => {
      paymentMethods.activePaymentMethod = { id: 1 };

      paymentMethods.reset();

      expect(paymentMethods.activePaymentMethod).toBeNull();
    });

    test('Given reset() is called, Then should clear attemptsByErrorCode', () => {
      paymentMethods.attemptsByErrorCode = { ERROR_CODE: 2 };

      paymentMethods.reset();

      expect(paymentMethods.attemptsByErrorCode).toEqual({});
    });

    test('Given reset() is called, Then should clear securityCodeReferences', () => {
      paymentMethods.securityCodeReferences = { ref: 'value' };

      paymentMethods.reset();

      expect(paymentMethods.securityCodeReferences).toEqual({});
    });

    test('Given reset() is called, Then should set isRendering to false', () => {
      paymentMethods.isRendering = true;

      paymentMethods.reset();

      expect(paymentMethods.isRendering).toBe(false);
    });
  });

  describe('setCheckoutType()', () => {
    test('Given a type value, When setCheckoutType() is called, Then should update the hidden input value', () => {
      paymentMethods.setCheckoutType('super_token');

      expect(document.querySelector('#mp_checkout_type').value).toBe('super_token');
    });

    test('Given custom type, When setCheckoutType() is called, Then should set value to custom', () => {
      paymentMethods.setCheckoutType('custom');

      expect(document.querySelector('#mp_checkout_type').value).toBe('custom');
    });

    test('Given #mp_checkout_type element does not exist in DOM, When setCheckoutType() is called, Then should not throw', () => {
      document.body.innerHTML = '';

      expect(() => {
        paymentMethods.setCheckoutType('custom');
      }).not.toThrow();
    });
  });
});
