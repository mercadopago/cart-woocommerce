const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const cardFormPath = resolveAlias('assets/js/checkouts/custom/entities/card-form.js');

describe('MPCardForm', () => {
  let cardForm;
  let MPCardForm;

  beforeAll(() => {
    global.wc_mercadopago_custom_checkout_params = {
      public_key: 'TEST-1234567890',
      locale: 'pt-BR',
      placeholders: {
        cardholderName: 'Nome do titular',
        cardExpirationDate: 'MM/AA',
        issuer: 'Banco emissor',
        installments: 'Parcelas',
      },
      error_messages: {
        installments: {
          'invalid_installment': 'Parcela inválida',
        },
        default: 'Erro ao processar pagamento',
      },
      input_helper_message: {
        cardNumber: {
          invalid_length: 'Insira o número completo.',
          invalid_value: 'Insira um número válido.',
          invalid_type: 'Número do cartão deve conter apenas dígitos',
        },
        cardholderName: {
          invalid_length: 'Nome do titular inválido',
        },
        expirationDate: {
          invalid_length: 'Data de validade inválida',
        },
        securityCode: {
          invalid_length: 'Código de segurança inválido',
          invalid_type: 'Código de segurança inválido',
        },
        identificationNumber: {
          invalid_length: 'Número do documento inválido',
        },
      },
    };

    global.wc_mercadopago_custom_card_form_params = {
      security_code_placeholder_text_3_digits: '123',
    };

    global.jQuery = jest.fn((selector) => {
      return {
        prepend: jest.fn(),
        length: 0,
        css: jest.fn(),
        block: jest.fn(),
        unblock: jest.fn(),
      };
    });

    global.CheckoutPage = {
      clearInputs: jest.fn(),
      clearCardState: jest.fn(),
      setChangeEventOnInstallments: jest.fn(),
      setZeroDollarInitialCitInstallmentsState: jest.fn(),
      setValueOn: jest.fn(),
      setCvvConfig: jest.fn(),
      setImageCard: jest.fn(),
      setSecureFieldInstructions: jest.fn(),
      loadAdditionalInfo: jest.fn(() => ({})),
      additionalInfoHandler: jest.fn(),
      setDisplayOfError: jest.fn(),
      setDisplayOfInputHelper: jest.fn(),
      shouldEnableInstallmentsComponent: jest.fn(),
      getHelperMessage: jest.fn(() => ({ innerHTML: '' })),
      setBackground: jest.fn(),
      removeAdditionFields: jest.fn(),
      findContainerField: jest.fn(() => 'container'),
      inputHelperName: jest.fn(() => 'helper-name'),
      verifyCardholderName: jest.fn(),
      verifyInstallmentsContainer: jest.fn(),
      setDisplayOfInputHelperMessage: jest.fn(),
      verifyCardholderNameOnFocus: jest.fn(),
      setDisplayOfInputHelperInfo: jest.fn(),
      cardholderNameHasError: jest.fn(() => false),
      clearDocumentLabelErrorOnInput: jest.fn(),
    };

    global.MPCheckoutFieldsDispatcher = {
      addEventListenerDispatcher: jest.fn(),
    };

    global.sendMetric = jest.fn();

    global.MPCardFormErrorCodes = {
      NO_PAYMENT_METHODS_FOUND: 'No payment methods found',
    };

    global.CARD_VALIDATION_REASON_BY_CODE = {
      invalid_length: 'invalid_length',
      invalid_type: 'empty_field',
      invalid_value: 'rejected_luhn',
    };

    global.window.mpCheckoutForm = '#checkout-form';

    global.window.mPmetrics = [];

    global.MercadoPago = jest.fn().mockImplementation(() => ({
      cardForm: jest.fn().mockReturnValue(Promise.resolve()),
    }));

    MPCardForm = loadFile(cardFormPath, 'MPCardForm', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();

    cardForm = new MPCardForm();

    document.body.innerHTML = `
      <input id="mp-amount" value="100.50" data-mp-cit-initial-context="false" />
      <input id="mp_checkout_type" value="custom" />
    `;

    window.mPmetrics = [];
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  describe('Initialization', () => {
    test('should create an instance of MPCardForm class', () => {
      expect(cardForm).toBeInstanceOf(MPCardForm);
    });

    test('should initialize form property as null', () => {
      expect(cardForm.form).toBeNull();
    });

    test('should initialize formMounted property as false', () => {
      expect(cardForm.formMounted).toBe(false);
    });

    test('should initialize mpFormId property as "checkout"', () => {
      expect(cardForm.mpFormId).toBe('checkout');
    });

    test('should define TIMEOUT_TO_WAIT_INIT_CARD_FORM as 10000', () => {
      expect(cardForm.TIMEOUT_TO_WAIT_INIT_CARD_FORM).toBe(10000);
    });
  });

  describe('getAmount()', () => {
    test.each([
      { inputValue: '100.50', expectedValue: '100.5', description: 'return mp-amount field value as string' },
      { inputValue: '250,75', expectedValue: '250.75', description: 'convert comma to dot in value' },
      { inputValue: '100', expectedValue: '100', description: 'return string number even with integer value' },
      { inputValue: '99.999', expectedValue: '99.999', description: 'handle values with multiple decimal places' },
      { inputValue: '', expectedValue: 'NaN', description: 'return "NaN" when field is empty' },
      { inputValue: '1234,56', expectedValue: '1234.56', description: 'convert value with comma and multiple digits' },
      { inputValue: '0', expectedValue: '0', description: 'return "0" when value is zero' },
      { inputValue: '5000.99', expectedValue: '5000.99', description: 'handle large values correctly' },
      { inputValue: '0,01', expectedValue: '0.01', description: 'handle very small values with comma' },
      { inputValue: '0.01', expectedValue: '0.01', description: 'handle very small values with dot' },
    ])(
      'Given mp-amount field has value "$inputValue", When getAmount() is called, Then should $description and return "$expectedValue"',
      ({ inputValue, expectedValue }) => {
        document.getElementById('mp-amount').value = inputValue;

        const amount = cardForm.getAmount();

        expect(amount).toBe(expectedValue);
        expect(typeof amount).toBe('string');
      }
    );
  });

  describe('zero-dollar initial CIT context', () => {
    function setContext({ amount, isInitialCit = 'true', checkoutType = 'custom' }) {
      cardForm.amount = amount;
      document.getElementById('mp-amount').dataset.mpCitInitialContext = isInitialCit;
      document.getElementById('mp_checkout_type').value = checkoutType;
    }

    test.each([
      { amount: '0', expected: true, description: 'zero string' },
      { amount: '0.00', expected: true, description: 'zero decimal string' },
      { amount: 0, expected: true, description: 'zero number' },
      { amount: '0.01', expected: false, description: 'positive amount' },
      { amount: '-0.01', expected: false, description: 'negative amount' },
      { amount: 'NaN', expected: false, description: 'NaN string' },
      { amount: '', expected: false, description: 'empty string' },
      { amount: null, expected: false, description: 'null' },
      { amount: undefined, expected: false, description: 'undefined' },
      { amount: Infinity, expected: false, description: 'infinite number' },
    ])(
      'Given initial CIT Custom checkout with $description, When the context is evaluated, Then returns $expected',
      ({ amount, expected }) => {
        setContext({ amount });

        expect(cardForm.isZeroDollarInitialCitContext()).toBe(expected);
      }
    );

    test.each([
      { isInitialCit: 'false', checkoutType: 'custom', description: 'server flag is false' },
      { isInitialCit: 'TRUE', checkoutType: 'custom', description: 'server flag is not the exact true contract' },
      { isInitialCit: 'true', checkoutType: 'super_token', description: 'checkout is Super Token' },
      { isInitialCit: 'true', checkoutType: 'wallet_button', description: 'checkout is Wallet Button' },
    ])(
      'Given amount is zero but $description, When the context is evaluated, Then it is not eligible',
      ({ isInitialCit, checkoutType }) => {
        setContext({ amount: '0', isInitialCit, checkoutType });

        expect(cardForm.isZeroDollarInitialCitContext()).toBe(false);
      }
    );

    test('Given the server context attribute is absent, When amount is zero, Then it is not eligible', () => {
      setContext({ amount: '0' });
      document.getElementById('mp-amount').removeAttribute('data-mp-cit-initial-context');

      expect(cardForm.isZeroDollarInitialCitContext()).toBe(false);
    });

    test('Given an eligible context, When stabilized, Then delegates the installments-only state and returns true', () => {
      setContext({ amount: '0' });

      expect(cardForm.stabilizeZeroDollarInitialCitInstallments()).toBe(true);
      expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).toHaveBeenCalledTimes(1);
    });

    test('Given a non-eligible context, When stabilization is attempted, Then it leaves installments untouched', () => {
      setContext({ amount: '10' });

      expect(cardForm.stabilizeZeroDollarInitialCitInstallments()).toBe(false);
      expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).not.toHaveBeenCalled();
    });
  });

  describe('handleCardFormErrors()', () => {
    test.each([
      {
        errorInput: [{ description: 'Invalid card number' }],
        expectedOutput: 'Invalid card number',
        description: 'return single error description from array',
      },
      {
        errorInput: [{ message: 'Card expired' }],
        expectedOutput: 'Card expired',
        description: 'return single error message from array',
      },
      {
        errorInput: [
          { description: 'Invalid card number' },
          { description: 'Invalid CVV' },
        ],
        expectedOutput: 'Invalid card number,Invalid CVV',
        description: 'return multiple error descriptions joined by comma',
      },
      {
        errorInput: [
          { message: 'Card expired' },
          { message: 'Insufficient funds' },
        ],
        expectedOutput: 'Card expired,Insufficient funds',
        description: 'return multiple error messages joined by comma',
      },
      {
        errorInput: [
          { description: 'Invalid card number' },
          { message: 'Card expired' },
        ],
        expectedOutput: 'Invalid card number,Card expired',
        description: 'return mixed error descriptions and messages joined by comma',
      },
      {
        errorInput: [
          { description: 'Primary error', message: 'Fallback error' },
        ],
        expectedOutput: 'Primary error',
        description: 'prioritize description over message in array items',
      },
      {
        errorInput: { description: 'Single error description' },
        expectedOutput: 'Single error description',
        description: 'return description from single error object',
      },
      {
        errorInput: { message: 'Single error message' },
        expectedOutput: 'Single error message',
        description: 'return message from single error object',
      },
      {
        errorInput: { description: 'Primary error', message: 'Fallback error' },
        expectedOutput: 'Primary error',
        description: 'prioritize description over message in single error object',
      },
    ])(
      'Given cardFormErrors is $description, When handleCardFormErrors() is called, Then should return "$expectedOutput"',
      ({ errorInput, expectedOutput }) => {
        const result = cardForm.handleCardFormErrors(errorInput);

        expect(result).toBe(expectedOutput);
      }
    );
  });

  describe('sendMetric()', () => {
    afterEach(() => {
      global.sendMetric = jest.fn();
    });

    test.each([
      {
        action: 'CARD_FORM_LOADED',
        label: 'Card form loaded successfully',
        target: 'checkout_page',
      },
      {
        action: 'PAYMENT_ERROR',
        label: 'Payment processing failed',
        target: 'payment_gateway',
      },
      {
        action: 'VALIDATION_SUCCESS',
        label: 'Card validation passed',
        target: 'card_validator',
      },
    ])(
      'Given window.sendMetric is available, When sendMetric("$action", "$label", "$target") is called, Then it forwards the arguments to the global Datadog beacon',
      ({ action, label, target }) => {
        const beacon = jest.fn();
        window.sendMetric = beacon;

        cardForm.sendMetric(action, label, target);

        expect(beacon).toHaveBeenCalledTimes(1);
        expect(beacon).toHaveBeenCalledWith(action, label, target);
      }
    );

    test('Given window.sendMetric is undefined, When sendMetric() is called, Then it does not throw', () => {
      window.sendMetric = undefined;

      expect(() => {
        cardForm.sendMetric('TEST_ACTION', 'Test label', 'test_target');
      }).not.toThrow();
    });
  });

  describe('isClassicCheckout()', () => {
    test.each([
      {
        elementExists: true,
        expectedResult: true,
        description: 'return true when classic checkout element exists in DOM',
      },
      {
        elementExists: false,
        expectedResult: false,
        description: 'return false when classic checkout element does not exist in DOM',
      },
    ])(
      'Given classic checkout element exists=$elementExists, When isClassicCheckout() is called, Then should $description',
      ({ elementExists, expectedResult }) => {
        document.body.innerHTML = '';

        if (elementExists) {
          const element = document.createElement('div');
          element.className = 'payment_method_woo-mercado-pago-custom';
          document.body.appendChild(element);
        }

        const result = cardForm.isClassicCheckout();

        expect(result).toBe(expectedResult);

        document.body.innerHTML = '';
      }
    );
  });

  describe('setupSecureFieldsStylesAndAddListeners()', () => {
    const mockFields = {
      cardNumber: { on: jest.fn() },
      expirationDate: { on: jest.fn() },
      securityCode: { on: jest.fn() },
    };

    describe('when MPCheckoutFieldsDispatcher is not loaded', () => {
      let localCardForm;

      beforeAll(() => {
        const savedDispatcher = global.MPCheckoutFieldsDispatcher;
        global.MPCheckoutFieldsDispatcher = undefined;
        const LocalMPCardForm = loadFile(cardFormPath, 'MPCardForm', global);
        localCardForm = new LocalMPCardForm();
        global.MPCheckoutFieldsDispatcher = savedDispatcher;
      });

      beforeEach(() => {
        jest.clearAllMocks();
        localCardForm.fields = { ...mockFields };
      });

      afterEach(() => {
        localCardForm.fields = null;
      });

      test('Given MPCheckoutFieldsDispatcher is undefined, When setupSecureFieldsStylesAndAddListeners() is called, Then should send MP_CHECKOUT_FIELDS_DISPATCHER_MISSING metric', () => {
        localCardForm.setupSecureFieldsStylesAndAddListeners();

        expect(global.sendMetric).toHaveBeenCalledWith(
          'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING',
          'setupSecureFieldsStylesAndAddListeners',
          'mp_checkout_init_error'
        );
      });

      test('Given fields is null, When setupSecureFieldsStylesAndAddListeners() is called, Then should return early and not send metric', () => {
        localCardForm.fields = null;

        localCardForm.setupSecureFieldsStylesAndAddListeners();

        expect(global.sendMetric).not.toHaveBeenCalled();
      });
    });

    describe('when MPCheckoutFieldsDispatcher is loaded', () => {
      beforeEach(() => {
        cardForm.fields = { ...mockFields };
      });

      afterEach(() => {
        cardForm.fields = null;
      });

      test('Given MPCheckoutFieldsDispatcher is defined, When setupSecureFieldsStylesAndAddListeners() is called, Then should not send MP_CHECKOUT_FIELDS_DISPATCHER_MISSING metric', () => {
        cardForm.setupSecureFieldsStylesAndAddListeners();

        expect(global.sendMetric).not.toHaveBeenCalledWith(
          'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING',
          'setupSecureFieldsStylesAndAddListeners',
          'mp_checkout_init_error'
        );
      });
    });
  });

  describe('initCardForm()', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <input id="mp-amount" value="100.00" />
        <div class="mp-checkout-custom-container"></div>
        <div class="mp-checkout-custom-load"></div>
      `;

      window.mpSdkInstance = undefined;
      window.mPmetrics = [];
    });

    afterEach(() => {
      document.body.innerHTML = '';
      window.mpSdkInstance = undefined;
      window.mPmetrics = [];
    });

    test('Given mpSdkInstance does not exist, When initCardForm() is called, Then should create new MercadoPago instance', () => {
      cardForm.initCardForm('100.00');

      expect(global.MercadoPago).toHaveBeenCalledWith(
        wc_mercadopago_custom_checkout_params.public_key,
        { locale: wc_mercadopago_custom_checkout_params.locale }
      );
      expect(window.mpSdkInstance).toBeDefined();
      expect(window.mpSdkInstance.cardForm).toBeDefined();
    });

    test('Given mpSdkInstance already exists, When initCardForm() is called, Then should reuse existing instance', () => {
      const mockCardForm = jest.fn().mockReturnValue(Promise.resolve());
      const existingInstance = { cardForm: mockCardForm };
      window.mpSdkInstance = existingInstance;

      cardForm.initCardForm('100.00');

      expect(global.MercadoPago).not.toHaveBeenCalled();
      expect(window.mpSdkInstance).toBe(existingInstance);
    });

    test('Given mpSdkInstance does not exist, When initCardForm() is called, Then it dispatches mp_sdk_instance_ready so the Super Token bundle can build its classes', () => {
      const sdkInstanceReadyListener = jest.fn();
      document.addEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);

      cardForm.initCardForm('100.00');

      expect(sdkInstanceReadyListener).toHaveBeenCalledTimes(1);

      document.removeEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);
    });

    test('Given mpSdkInstance already exists, When initCardForm() is called, Then it does not dispatch mp_sdk_instance_ready again', () => {
      window.mpSdkInstance = { cardForm: jest.fn().mockReturnValue(Promise.resolve()) };
      const sdkInstanceReadyListener = jest.fn();
      document.addEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);

      cardForm.initCardForm('100.00');

      expect(sdkInstanceReadyListener).not.toHaveBeenCalled();

      document.removeEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);
    });

    test('Given mpSdkInstance is set, When initCardForm() is called, Then should call SDK cardForm with full config including all fields', () => {
      const mockCardFormFn = jest.fn().mockReturnValue(Promise.resolve());
      window.mpSdkInstance = { cardForm: mockCardFormFn };

      cardForm.initCardForm('100.00');

      expect(mockCardFormFn).toHaveBeenCalledWith(
        expect.objectContaining({
          form: expect.objectContaining({
            identificationType: expect.anything(),
            identificationNumber: expect.anything(),
            issuer: expect.anything(),
            installments: expect.anything(),
          }),
        })
      );
    });
  });

  describe('formatTrackingAmount()', () => {
    test.each([
      { input: '100.00', expected: '100.00' },
      { input: '100.5', expected: '100.50' },
      { input: '1234.56', expected: '1234.56' },
      { input: 'R$ 1.234,56', expected: '1234.56' }, // separador europeu (milhar com ponto, decimal com vírgula)
      { input: '1,234.56', expected: '1234.56' },     // US-style (milhar com vírgula, decimal com ponto)
      { input: '99,90', expected: '99.90' },          // decimal com vírgula
      { input: 1234.56, expected: '1234.56' },        // número (coerção para string — não deve lançar)
      { input: null, expected: null },
      { input: undefined, expected: null },
      { input: 'abc', expected: null },
      { input: '', expected: null },
    ])(
      'Given input "$input", When formatTrackingAmount() is called, Then returns "$expected" (mesmo formato do contrato checkout_amount)',
      ({ input, expected }) => {
        expect(cardForm.formatTrackingAmount(input)).toBe(expected);
      }
    );
  });

  describe('dispatchCheckoutAmountEvent() — tracking de amount', () => {
    let dispatchEventSpy;
    const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
    const opened = () =>
      dispatchEventSpy.mock.calls.map((c) => c[0]).filter((e) => e.type === 'mp_checkout_opened');
    const changed = () =>
      dispatchEventSpy.mock.calls.map((c) => c[0]).filter((e) => e.type === 'mp_amount_changed');

    beforeEach(() => {
      dispatchEventSpy = jest.spyOn(document, 'dispatchEvent');
      window.sendMetric = jest.fn();
    });

    afterEach(() => {
      dispatchEventSpy.mockRestore();
      delete window.melidataReady;
    });

    describe('quando melidataReady resolve', () => {
      beforeEach(() => {
        window.melidataReady = Promise.resolve();
      });

      test('Given primeira chamada, When dispatchCheckoutAmountEvent(), Then dispara mp_checkout_opened 1x com amount formatado', async () => {
        cardForm.dispatchCheckoutAmountEvent('100.5');
        await flush();

        expect(opened()).toHaveLength(1);
        expect(opened()[0].detail).toEqual({ amount: '100.50' });
        expect(cardForm.hasFiredCheckoutOpenedEvent).toBe(true);
      });

      test('Given open já disparado, When chamado novamente com o mesmo amount, Then NÃO dispara open de novo nem mp_amount_changed', async () => {
        cardForm.dispatchCheckoutAmountEvent('100.00');
        cardForm.dispatchCheckoutAmountEvent('100.00');
        cardForm.dispatchCheckoutAmountEvent('100.00');
        await flush();

        expect(opened()).toHaveLength(1);
        expect(changed()).toHaveLength(0);
      });

      test('Given open já disparado, When o amount muda, Then dispara mp_amount_changed com amount e oldAmount', async () => {
        cardForm.dispatchCheckoutAmountEvent('100.00');
        await flush();
        cardForm.dispatchCheckoutAmountEvent('200.00');
        await flush();

        expect(changed()).toHaveLength(1);
        expect(changed()[0].detail).toEqual({ amount: '200.00', oldAmount: '100.00' });
      });

      test('Given amount que formata para null (ex.: DOM sem #mp-amount), When dispatchCheckoutAmountEvent() na primeira chamada, Then dispara mp_checkout_opened com amount null e marca hasFired', async () => {
        cardForm.dispatchCheckoutAmountEvent('abc'); // formatTrackingAmount → null

        await flush();

        expect(opened()).toHaveLength(1);
        expect(opened()[0].detail).toEqual({ amount: null });
        expect(cardForm.hasFiredCheckoutOpenedEvent).toBe(true);
      });

      test('Given open com amount null já disparado, When segue com amount válido (null !== "100.00"), Then NÃO dispara mp_amount_changed (previousAmount null é falsy)', async () => {
        cardForm.dispatchCheckoutAmountEvent('abc'); // open com amount null → lastTrackedAmount = null
        await flush();
        cardForm.dispatchCheckoutAmountEvent('100.00'); // previousAmount = null → guard previousAmount && ... barra
        await flush();

        expect(changed()).toHaveLength(0);
      });
    });

    describe('quando melidataReady rejeita (drop)', () => {
      beforeEach(() => {
        window.melidataReady = Promise.reject(new Error('Unable to load melidata script on page'));
      });

      test('Given melidataReady rejeita, When o open é processado, Then NÃO dispara o evento DOM e reporta mp_checkout_amount_tracking_dropped com value="true" (flag de sessão)', async () => {
        cardForm.dispatchCheckoutAmountEvent('100.00');
        await flush();

        expect(opened()).toHaveLength(0);
        expect(window.sendMetric).toHaveBeenCalledWith(
          'true',
          expect.stringContaining('melidataReady rejected on mp_checkout_opened'),
          'mp_checkout_amount_tracking_dropped'
        );
      });

      test('Given melidataReady rejeita em múltiplas transições (open + change), When processadas, Then reporta o drop apenas 1x por sessão', async () => {
        cardForm.dispatchCheckoutAmountEvent('100.00');
        await flush();
        cardForm.dispatchCheckoutAmountEvent('200.00');
        await flush();

        const dropCalls = window.sendMetric.mock.calls.filter(
          (c) => c[2] === 'mp_checkout_amount_tracking_dropped'
        );
        expect(dropCalls).toHaveLength(1);
      });
    });

    describe('quando melidataReady não é uma Promise (sobrescrito por terceiro)', () => {
      test.each([
        { label: 'truthy não-thenable (true)', value: true },
        { label: 'número (1)', value: 1 },
        { label: 'objeto sem then', value: {} },
      ])(
        'Given window.melidataReady = $label, When dispatchCheckoutAmountEvent(), Then NÃO lança, NÃO dispara o evento DOM e reporta o drop',
        ({ value }) => {
          window.melidataReady = value;

          expect(() => cardForm.dispatchCheckoutAmountEvent('100.00')).not.toThrow();

          expect(opened()).toHaveLength(0);
          expect(window.sendMetric).toHaveBeenCalledWith(
            'true',
            expect.stringContaining('melidataReady rejected on mp_checkout_opened'),
            'mp_checkout_amount_tracking_dropped'
          );
        }
      );
    });
  });

  describe('onInstallmentsReceived callback — zero-dollar initial CIT', () => {
    let callbacks;
    let warnSpy;

    function setContext({ amount = '0', isInitialCit = 'true', checkoutType = 'custom' } = {}) {
      cardForm.amount = amount;
      document.getElementById('mp-amount').dataset.mpCitInitialContext = isInitialCit;
      document.getElementById('mp_checkout_type').value = checkoutType;
    }

    beforeEach(() => {
      callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
      warnSpy.mockRestore();
    });

    test('Given eligible zero-dollar CIT and SDK installment error, When callback runs, Then fixes one installment without buyer alert or raw error log', () => {
      setContext();
      const addErrorAlertSpy = jest.spyOn(cardForm, 'addErrorAlert').mockImplementation(() => {});

      callbacks.onInstallmentsReceived(new TypeError("Cannot destructure property 'payer_costs'"), undefined);

      expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).toHaveBeenCalledTimes(1);
      expect(CheckoutPage.setChangeEventOnInstallments).not.toHaveBeenCalled();
      expect(addErrorAlertSpy).not.toHaveBeenCalled();
      expect(warnSpy).not.toHaveBeenCalled();
    });

    test('Given eligible zero-dollar CIT and an unexpected installments error, When callback runs, Then preserves the existing alert and warning behavior', () => {
      setContext();
      const error = new Error('invalid_installment');
      const addErrorAlertSpy = jest.spyOn(cardForm, 'addErrorAlert').mockImplementation(() => {});

      callbacks.onInstallmentsReceived(error, undefined);

      expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).not.toHaveBeenCalled();
      expect(addErrorAlertSpy).toHaveBeenCalledWith('Parcela inválida');
      expect(warnSpy).toHaveBeenCalledWith('Installments handling error: ', error);
    });

    test.each([
      { installments: { payer_costs: [] }, description: 'normalized empty payer_costs' },
      { installments: [], description: 'empty raw SDK response' },
    ])(
      'Given eligible zero-dollar CIT and $description, When callback succeeds, Then keeps the fixed installment state hidden',
      ({ installments }) => {
        setContext();

        callbacks.onInstallmentsReceived(null, installments);

        expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).toHaveBeenCalledTimes(1);
        expect(CheckoutPage.setChangeEventOnInstallments).not.toHaveBeenCalled();
        expect(warnSpy).not.toHaveBeenCalled();
      }
    );

    test('Given eligible zero-dollar CIT returns payer_costs, When callback runs, Then still keeps installments fixed at one', () => {
      setContext();
      const installments = { payer_costs: [{ installments: 1 }] };

      callbacks.onInstallmentsReceived(null, installments);

      expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).toHaveBeenCalledTimes(1);
      expect(CheckoutPage.setChangeEventOnInstallments).not.toHaveBeenCalled();
    });

    test('Given positive initial CIT and SDK installment error, When callback runs, Then preserves the existing alert and warning behavior', () => {
      setContext({ amount: '10' });
      const error = new Error('invalid_installment');
      const addErrorAlertSpy = jest.spyOn(cardForm, 'addErrorAlert').mockImplementation(() => {});

      callbacks.onInstallmentsReceived(error, undefined);

      expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).not.toHaveBeenCalled();
      expect(addErrorAlertSpy).toHaveBeenCalledWith('Parcela inválida');
      expect(warnSpy).toHaveBeenCalledWith('Installments handling error: ', error);
    });

    test.each([
      { isInitialCit: 'false', checkoutType: 'custom', description: 'server flag is false' },
      { isInitialCit: 'true', checkoutType: 'super_token', description: 'checkout is Super Token' },
    ])(
      'Given amount is zero but $description, When SDK returns an error, Then preserves the existing error behavior',
      ({ isInitialCit, checkoutType }) => {
        setContext({ isInitialCit, checkoutType });
        const error = new Error('invalid_installment');
        const addErrorAlertSpy = jest.spyOn(cardForm, 'addErrorAlert').mockImplementation(() => {});

        callbacks.onInstallmentsReceived(error, undefined);

        expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).not.toHaveBeenCalled();
        expect(addErrorAlertSpy).toHaveBeenCalledWith('Parcela inválida');
        expect(warnSpy).toHaveBeenCalledWith('Installments handling error: ', error);
      }
    );

    test('Given a zero-dollar CIT becomes positive, When another installments callback arrives, Then normal payer_costs rendering resumes', () => {
      setContext();
      callbacks.onInstallmentsReceived(null, { payer_costs: [] });
      jest.clearAllMocks();

      cardForm.amount = '49.90';
      const installments = { payer_costs: [{ installments: 1 }] };
      callbacks.onInstallmentsReceived(null, installments);

      expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).not.toHaveBeenCalled();
      expect(CheckoutPage.setChangeEventOnInstallments).toHaveBeenCalledWith(installments);
    });

    test('Given a positive CIT becomes zero, When another installments callback arrives, Then it switches to the fixed state', () => {
      setContext({ amount: '49.90' });
      callbacks.onInstallmentsReceived(null, { payer_costs: [{ installments: 1 }] });
      jest.clearAllMocks();

      cardForm.amount = '0';
      callbacks.onInstallmentsReceived(null, { payer_costs: [] });

      expect(CheckoutPage.setChangeEventOnInstallments).not.toHaveBeenCalled();
      expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).toHaveBeenCalledTimes(1);
    });
  });

  describe('onError callback — zero-dollar initial CIT cardholder-name isolation', () => {
    let callbacks;

    function setContext({ amount = '0', isInitialCit = 'true', checkoutType = 'custom' } = {}) {
      cardForm.amount = amount;
      document.getElementById('mp-amount').dataset.mpCitInitialContext = isInitialCit;
      document.getElementById('mp_checkout_type').value = checkoutType;
    }

    beforeEach(() => {
      callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
    });

    test('Given untouched holder name and the expected zero-dollar payer_costs error, When the SDK reports it globally, Then holder name stays pristine', () => {
      setContext();

      callbacks.onError([new TypeError("Cannot destructure property 'payer_costs'")]);

      expect(CheckoutPage.verifyCardholderName).not.toHaveBeenCalled();
    });

    test('Given a positive amount and the payer_costs SDK error, When onError runs, Then it preserves the existing holder-name validation behavior', () => {
      setContext({ amount: '10' });

      callbacks.onError([new TypeError("Cannot destructure property 'payer_costs'")]);

      expect(CheckoutPage.verifyCardholderName).toHaveBeenCalledTimes(1);
    });

    test('Given zero-dollar CIT and an unrelated global SDK error, When onError runs, Then it preserves the existing holder-name validation behavior', () => {
      setContext();

      callbacks.onError([new Error('unexpected SDK error')]);

      expect(CheckoutPage.verifyCardholderName).toHaveBeenCalledTimes(1);
    });

    test('Given zero-dollar CIT and an actual cardholderName error, When onError runs, Then holder name is still validated and displayed as invalid', () => {
      setContext();

      callbacks.onError([new Error('cardholderName is invalid')]);

      expect(CheckoutPage.verifyCardholderName).toHaveBeenCalledTimes(1);
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('fcCardholderName', 'add', 'mp-error');
      expect(CheckoutPage.setDisplayOfInputHelper).toHaveBeenCalledWith('mp-card-holder-name', 'flex');
    });
  });

  describe('onPaymentMethodsReceived callback', () => {
    let onPaymentMethodsReceived;

    beforeEach(() => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      onPaymentMethodsReceived = callbacks.onPaymentMethodsReceived;
    });

    test('Given SDK returns NO_PAYMENT_METHODS_FOUND error (invalid BIN), When onPaymentMethodsReceived is called, Then calls clearCardState (resetting name and state), sets invalid card message and does not call clearInputs', () => {
      const mockHelperEl = { innerHTML: '' };
      CheckoutPage.getHelperMessage = jest.fn(() => mockHelperEl);

      onPaymentMethodsReceived('No payment methods found', null);

      expect(CheckoutPage.clearCardState).toHaveBeenCalledTimes(1);
      expect(CheckoutPage.getHelperMessage).toHaveBeenCalledWith('cardNumber');
      expect(mockHelperEl.innerHTML).toBe('Insira um número válido.');
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('fcCardNumberContainer', 'add', 'mp-error');
      expect(CheckoutPage.setDisplayOfInputHelper).toHaveBeenCalledWith('mp-card-number', 'flex');
      expect(CheckoutPage.clearInputs).not.toHaveBeenCalled();
    });

    test('Given SDK returns an unknown error, When onPaymentMethodsReceived is called, Then calls clearCardState, sets generic fallback message and shows card number error', () => {
      const mockHelperEl = { innerHTML: '' };
      CheckoutPage.getHelperMessage = jest.fn(() => mockHelperEl);

      onPaymentMethodsReceived('Some unexpected SDK error', null);

      expect(CheckoutPage.clearCardState).toHaveBeenCalledTimes(1);
      expect(mockHelperEl.innerHTML).toBe('Insira o número completo.');
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('fcCardNumberContainer', 'add', 'mp-error');
      expect(CheckoutPage.setDisplayOfInputHelper).toHaveBeenCalledWith('mp-card-number', 'flex');
      expect(CheckoutPage.clearInputs).not.toHaveBeenCalled();
    });

    test('Given SDK returns an Error object with NO_PAYMENT_METHODS_FOUND message (invalid BIN), When onPaymentMethodsReceived is called, Then calls clearCardState and sets invalid card message via the error.message branch', () => {
      const mockHelperEl = { innerHTML: '' };
      CheckoutPage.getHelperMessage = jest.fn(() => mockHelperEl);

      onPaymentMethodsReceived(new Error('No payment methods found'), null);

      expect(CheckoutPage.clearCardState).toHaveBeenCalledTimes(1);
      expect(mockHelperEl.innerHTML).toBe('Insira um número válido.');
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('fcCardNumberContainer', 'add', 'mp-error');
      expect(CheckoutPage.setDisplayOfInputHelper).toHaveBeenCalledWith('mp-card-number', 'flex');
      expect(CheckoutPage.clearInputs).not.toHaveBeenCalled();
    });

    test('Given SDK returns null paymentMethods without error, When onPaymentMethodsReceived is called, Then calls clearCardState and shows card number error', () => {
      onPaymentMethodsReceived(null, null);

      expect(CheckoutPage.clearCardState).toHaveBeenCalledTimes(1);
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('fcCardNumberContainer', 'add', 'mp-error');
      expect(CheckoutPage.setDisplayOfInputHelper).toHaveBeenCalledWith('mp-card-number', 'flex');
      expect(CheckoutPage.clearInputs).not.toHaveBeenCalled();
    });

    test('Given SDK returns valid paymentMethods, When onPaymentMethodsReceived is called, Then calls clearInputs and sets paymentMethodId', () => {
      const mockPaymentMethod = {
        id: 'master',
        settings: [{ security_code: { length: 3 } }],
        secure_thumbnail: 'https://example.com/master.png',
        thumbnail: 'https://example.com/master.png',
        additional_info_needed: [],
        payment_type_id: 'credit_card',
      };

      onPaymentMethodsReceived(null, [mockPaymentMethod]);

      expect(CheckoutPage.clearInputs).toHaveBeenCalledTimes(1);
      expect(CheckoutPage.setValueOn).toHaveBeenCalledWith('paymentMethodId', 'master');
    });

    test('Given a valid BIN in zero-dollar initial CIT, When the BIN reset clears shared fields, Then the fixed installment state is restored afterwards', () => {
      document.getElementById('mp-amount').dataset.mpCitInitialContext = 'true';
      cardForm.amount = '0';
      const mockPaymentMethod = {
        id: 'master',
        settings: [{
          card_number: { length: 16 },
          security_code: { length: 3 },
        }],
        secure_thumbnail: 'https://example.com/master.png',
        thumbnail: 'https://example.com/master.png',
        additional_info_needed: [],
        payment_type_id: 'credit_card',
      };

      onPaymentMethodsReceived(null, [mockPaymentMethod]);

      expect(CheckoutPage.clearInputs).toHaveBeenCalledTimes(1);
      expect(CheckoutPage.setZeroDollarInitialCitInstallmentsState).toHaveBeenCalledTimes(1);
      expect(CheckoutPage.clearInputs.mock.invocationCallOrder[0])
        .toBeLessThan(CheckoutPage.setZeroDollarInitialCitInstallmentsState.mock.invocationCallOrder[0]);
    });
  });

  describe('onBinChange callback (early BIN-change reset)', () => {
    let callbacks;

    beforeEach(() => {
      callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
    });

    test('Given a BIN different from the last verdict, When onBinChange runs, Then it marks the BIN optimistically valid, clears the card-number error early and drops the residual paymentMethodId', () => {
      cardForm.lastVerdictBin = '11111111';
      cardForm.cardBinIsValid = false;

      callbacks.onBinChange('42356477');

      expect(cardForm.currentBin).toBe('42356477');
      expect(cardForm.cardBinIsValid).toBe(true);
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('fcCardNumberContainer', 'remove', 'mp-error');
      expect(CheckoutPage.setDisplayOfInputHelper).toHaveBeenCalledWith('mp-card-number', 'none');
      expect(CheckoutPage.setValueOn).toHaveBeenCalledWith('paymentMethodId', '');
    });

    test('Given the same BIN as the last verdict (editing within the same BIN), When onBinChange runs, Then it keeps the current error state and does not touch paymentMethodId', () => {
      cardForm.lastVerdictBin = '42356477';
      cardForm.cardBinIsValid = false;

      callbacks.onBinChange('42356477');

      expect(cardForm.currentBin).toBe('42356477');
      expect(cardForm.cardBinIsValid).toBe(false);
      expect(CheckoutPage.setDisplayOfError).not.toHaveBeenCalled();
      expect(CheckoutPage.setValueOn).not.toHaveBeenCalled();
    });

    test('Given an empty BIN (field cleared), When onBinChange runs, Then it records the empty bin and takes no action', () => {
      cardForm.lastVerdictBin = '42356477';

      callbacks.onBinChange('');

      expect(cardForm.currentBin).toBe('');
      expect(CheckoutPage.setDisplayOfError).not.toHaveBeenCalled();
      expect(CheckoutPage.setValueOn).not.toHaveBeenCalled();
    });

    test('Given the Super Token flow, When onBinChange runs on a new BIN, Then it clears the error but never clears paymentMethodId (Super Token owns that shared field)', () => {
      document.getElementById('mp_checkout_type').value = 'super_token';
      cardForm.lastVerdictBin = null;

      callbacks.onBinChange('42356477');

      expect(cardForm.cardBinIsValid).toBe(true);
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('fcCardNumberContainer', 'remove', 'mp-error');
      expect(CheckoutPage.setValueOn).not.toHaveBeenCalledWith('paymentMethodId', '');
    });

    test('Given the bin arrives as an object { bin }, When onBinChange runs, Then it reads the raw bin string from the object', () => {
      cardForm.lastVerdictBin = null;

      callbacks.onBinChange({ bin: '42356477' });

      expect(cardForm.currentBin).toBe('42356477');
      expect(CheckoutPage.setValueOn).toHaveBeenCalledWith('paymentMethodId', '');
    });

    test('Given a bin was tracked by onBinChange, When onPaymentMethodsReceived resolves for a recognized card, Then it records that bin as the last verdict and repopulates paymentMethodId', () => {
      callbacks.onBinChange('42356477');

      callbacks.onPaymentMethodsReceived(null, [{
        id: 'visa',
        settings: [{ security_code: { length: 3 } }],
        secure_thumbnail: '',
        thumbnail: '',
        additional_info_needed: [],
        payment_type_id: 'credit_card',
      }]);

      expect(cardForm.lastVerdictBin).toBe('42356477');
      expect(CheckoutPage.setValueOn).toHaveBeenCalledWith('paymentMethodId', 'visa');
    });
  });

  describe('clearInputs() other call sites — no regression from clearCardState()', () => {
    test('Given cardForm is unmounted without error, When onFormUnmounted is called, Then calls clearInputs and resolves without sending a metric', () => {
      const resolve = jest.fn();
      const callbacks = cardForm.getCardFormCallbacks(resolve, jest.fn());

      callbacks.onFormUnmounted(null);

      expect(CheckoutPage.clearInputs).toHaveBeenCalledTimes(1);
      expect(resolve).toHaveBeenCalledTimes(1);
      expect(global.sendMetric).not.toHaveBeenCalled();
    });

    test('Given cardForm is unmounted with error, When onFormUnmounted is called, Then still calls clearInputs and sends an unmount error metric', () => {
      const resolve = jest.fn();
      const callbacks = cardForm.getCardFormCallbacks(resolve, jest.fn());

      callbacks.onFormUnmounted(new Error('iframe detached'));

      expect(CheckoutPage.clearInputs).toHaveBeenCalledTimes(1);
      expect(global.sendMetric).toHaveBeenCalledWith('MP_CARDFORM_UNMOUNT_ERROR', 'iframe detached', 'mp_custom_checkout_security_fields_client');
    });

    test('Given cardNumber becomes empty (invalid_type), When onValidityChange is called, Then calls clearInputs to reset residual card state', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onValidityChange([{ code: 'invalid_type' }], 'cardNumber');

      expect(CheckoutPage.clearInputs).toHaveBeenCalledTimes(1);
      expect(CheckoutPage.setBackground).toHaveBeenCalledWith('fcCardNumberContainer', 'no-repeat #fff');
    });

    test('Given cardNumber fails the Luhn checksum (invalid_value), When onValidityChange is called, Then preserves the derived fields (BIN still valid) and still shows the error', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onValidityChange([{ code: 'invalid_value', message: 'card number rejected on Luhn Validation' }], 'cardNumber');

      expect(CheckoutPage.clearInputs).not.toHaveBeenCalled();
      expect(CheckoutPage.removeAdditionFields).not.toHaveBeenCalled();
      expect(cardForm.cardNumberValidity).toBe('invalid_value');
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('container', 'add', 'mp-error');
    });

    test('Given the cardholder name is already in error, When onValidityChange fires a cardNumber error, Then does not re-show the cardholder-name helper info', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      CheckoutPage.cardholderNameHasError.mockReturnValueOnce(true);

      callbacks.onValidityChange([{ code: 'invalid_length' }], 'cardNumber');

      expect(CheckoutPage.setDisplayOfInputHelperInfo).not.toHaveBeenCalledWith('mp-card-holder-name', 'flex');
    });

    test('Given cardNumber is simply too short (invalid_length), When onValidityChange is called, Then does not call clearInputs (user is still typing)', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onValidityChange([{ code: 'invalid_length' }], 'cardNumber');

      expect(CheckoutPage.clearInputs).not.toHaveBeenCalled();
    });

    test('Given getHelperMessage returns null (helper element missing from DOM), When onValidityChange is called with an error, Then does not throw and still applies the error state', () => {
      CheckoutPage.getHelperMessage = jest.fn(() => null);
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      expect(() => callbacks.onValidityChange([{ code: 'invalid_type' }], 'cardNumber')).not.toThrow();
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('container', 'add', 'mp-error');
    });
  });

  describe('cardBinIsValid tracking', () => {
    const mockPaymentMethod = {
      id: 'master',
      settings: [{ security_code: { length: 3 } }],
      secure_thumbnail: 'https://example.com/master.png',
      thumbnail: 'https://example.com/master.png',
      additional_info_needed: [],
      payment_type_id: 'credit_card',
    };

    test('Given onPaymentMethodsReceived is called with an error, When it runs, Then sets cardBinIsValid to false', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onPaymentMethodsReceived('No payment methods found', null);

      expect(cardForm.cardBinIsValid).toBe(false);
    });

    test('Given the BIN is not recognized, When onPaymentMethodsReceived runs, Then cardBinInvalidMessage is the message without the SDK prefix', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onPaymentMethodsReceived(new Error('MercadoPago.js - No payment methods found'), null);

      expect(cardForm.cardBinInvalidMessage).toBe('No payment methods found');
    });

    test('Given the BIN lookup fails (network/API), When onPaymentMethodsReceived runs, Then cardBinInvalidMessage carries the real error message', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onPaymentMethodsReceived(new Error('Request timed out'), null);

      expect(cardForm.cardBinInvalidMessage).toBe('Request timed out');
    });

    test('Given the BIN lookup error has no message, When onPaymentMethodsReceived runs, Then cardBinInvalidMessage falls back to unknown', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onPaymentMethodsReceived(new Error(''), null);

      expect(cardForm.cardBinInvalidMessage).toBe('unknown error message');
    });

    test('Given onPaymentMethodsReceived is called with null paymentMethods and no error, When it runs, Then sets cardBinIsValid to false', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onPaymentMethodsReceived(null, null);

      expect(cardForm.cardBinIsValid).toBe(false);
    });

    test('Given onPaymentMethodsReceived is called with a recognized payment method, When it runs, Then sets cardBinIsValid to true', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onPaymentMethodsReceived(null, [mockPaymentMethod]);

      expect(cardForm.cardBinIsValid).toBe(true);
    });

    test('Given cardBinIsValid is false, When onValidityChange is called with a valid cardNumber format, Then does not remove the error state', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      cardForm.cardBinIsValid = false;

      callbacks.onValidityChange(null, 'cardNumber');

      expect(CheckoutPage.setDisplayOfError).not.toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');
    });

    test('Given cardBinIsValid is false (invalid BIN detected), When onValidityChange fires an invalid_length error for cardNumber, Then does NOT flip cardBinIsValid (keeps the invalid-BIN lock) and tracks invalid_length', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      cardForm.cardBinIsValid = false;

      callbacks.onValidityChange([{ code: 'invalid_length' }], 'cardNumber');

      expect(cardForm.cardBinIsValid).toBe(false);
      expect(cardForm.cardNumberValidity).toBe('invalid_length');
    });

    test('Given cardBinIsValid is false, When onValidityChange fires an invalid_length error for a different field (securityCode), Then does not reset the cardNumber BIN flag', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      cardForm.cardBinIsValid = false;

      callbacks.onValidityChange([{ code: 'invalid_length' }], 'securityCode');

      expect(cardForm.cardBinIsValid).toBe(false);
    });

    test('Given cardBinIsValid is false, When onValidityChange fires an invalid_type error (empty field) for cardNumber, Then does NOT flip cardBinIsValid and tracks the empty state', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      cardForm.cardBinIsValid = false;

      callbacks.onValidityChange([{ code: 'invalid_type' }], 'cardNumber');

      expect(cardForm.cardBinIsValid).toBe(false);
      expect(cardForm.cardNumberValidity).toBe('invalid_type');
    });

    test('Given onValidityChange fires an error with a code and message for cardNumber, Then stores both the code and the message', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onValidityChange([{ code: 'invalid_value', message: 'card number rejected on Luhn Validation' }], 'cardNumber');

      expect(cardForm.cardNumberValidity).toBe('invalid_value');
      expect(cardForm.cardNumberValidityMessage).toBe('card number rejected on Luhn Validation');
    });

    test('Given a cardNumber that becomes valid (no error), When onValidityChange runs, Then clears both the code and the message', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      cardForm.cardNumberValidity = 'invalid_length';
      cardForm.cardNumberValidityMessage = "cardNumber should be of length '16'.";

      callbacks.onValidityChange(null, 'cardNumber');

      expect(cardForm.cardNumberValidity).toBeNull();
      expect(cardForm.cardNumberValidityMessage).toBeNull();
    });

    test('Given the SDK passes an empty error array for cardNumber, When onValidityChange runs, Then does not throw, cardNumberValidity is null and emits unexpected_error_format metric', () => {
      // Reload so the vm sandbox captures the current global.sendMetric reference
      // (the bare `sendMetric` global is snapshotted at load time).
      const LocalMPCardForm = loadFile(cardFormPath, 'MPCardForm', global);
      const localCardForm = new LocalMPCardForm();
      const callbacks = localCardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      expect(() => callbacks.onValidityChange([], 'cardNumber')).not.toThrow();
      expect(localCardForm.cardNumberValidity).toBeNull();
      expect(global.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_CARD_VALIDATION_BLOCKED',
        'unexpected_error_format',
        'mp_custom_card_validation',
        { reason: 'unexpected_error_format' }
      );
    });

    describe('getCardValidationReason()', () => {
      test('Given cardBinIsValid is false (complete BIN rejected, valid format), Then returns invalid_bin', () => {
        cardForm.cardBinIsValid = false;
        cardForm.cardNumberValidity = null;

        expect(cardForm.getCardValidationReason()).toBe('invalid_bin');
      });

      test('Given cardBinIsValid is false but the number is currently incomplete (invalid_length), Then the current state wins and returns invalid_length', () => {
        cardForm.cardBinIsValid = false;
        cardForm.cardNumberValidity = 'invalid_length';

        expect(cardForm.getCardValidationReason()).toBe('invalid_length');
      });

      test('Given the field is empty (cardNumberValidity invalid_type) and BIN not rejected, Then returns empty_field', () => {
        cardForm.cardBinIsValid = true;
        cardForm.cardNumberValidity = 'invalid_type';

        expect(cardForm.getCardValidationReason()).toBe('empty_field');
      });

      test('Given the number is incomplete (invalid_length) and BIN not rejected, Then returns invalid_length', () => {
        cardForm.cardBinIsValid = true;
        cardForm.cardNumberValidity = 'invalid_length';

        expect(cardForm.getCardValidationReason()).toBe('invalid_length');
      });

      test('Given a fresh, never-touched field (cardNumberValidity null) and BIN not rejected, Then returns empty_field (consistent with a touched-then-emptied field)', () => {
        cardForm.cardBinIsValid = true;
        cardForm.cardNumberValidity = null;

        expect(cardForm.getCardValidationReason()).toBe('empty_field');
      });

      test('Given the card number fails the Luhn checksum (invalid_value) and BIN not rejected, Then returns rejected_luhn', () => {
        cardForm.cardBinIsValid = true;
        cardForm.cardNumberValidity = 'invalid_value';

        expect(cardForm.getCardValidationReason()).toBe('rejected_luhn');
      });

      test('Given an unforeseen SDK validity code (not length/type/value) and BIN not rejected, Then reports it as-is instead of masking it as empty_field', () => {
        cardForm.cardBinIsValid = true;
        cardForm.cardNumberValidity = 'some_future_code';

        expect(cardForm.getCardValidationReason()).toBe('some_future_code');
      });
    });

    describe('getCardValidationDetail()', () => {
      test('Given the Luhn verdict (invalid_value) with message, Then returns code:message', () => {
        cardForm.cardNumberValidity = 'invalid_value';
        cardForm.cardNumberValidityMessage = 'card number rejected on Luhn Validation';

        expect(cardForm.getCardValidationDetail()).toBe('invalid_value:card number rejected on Luhn Validation');
      });

      test('Given the empty-field verdict (invalid_type) with message, Then returns code:message', () => {
        cardForm.cardNumberValidity = 'invalid_type';
        cardForm.cardNumberValidityMessage = 'cardNumber should be a number.';

        expect(cardForm.getCardValidationDetail()).toBe('invalid_type:cardNumber should be a number.');
      });

      test('Given the incomplete verdict (invalid_length) with message, Then returns code:message', () => {
        cardForm.cardNumberValidity = 'invalid_length';
        cardForm.cardNumberValidityMessage = "cardNumber should be of length '16'.";

        expect(cardForm.getCardValidationDetail()).toBe("invalid_length:cardNumber should be of length '16'.");
      });

      test('Given a format verdict with a code but no message, Then returns just the code', () => {
        cardForm.cardNumberValidity = 'invalid_length';
        cardForm.cardNumberValidityMessage = null;

        expect(cardForm.getCardValidationDetail()).toBe('invalid_length');
      });

      test('Given no format verdict but the BIN was rejected, Then returns just the (bounded) BIN message', () => {
        cardForm.cardNumberValidity = null;
        cardForm.cardBinIsValid = false;
        cardForm.cardBinInvalidMessage = 'No payment methods found';

        expect(cardForm.getCardValidationDetail()).toBe('No payment methods found');
      });

      test('Given no verdict at all, Then returns an empty string', () => {
        cardForm.cardNumberValidity = null;
        cardForm.cardBinIsValid = true;

        expect(cardForm.getCardValidationDetail()).toBe('');
      });
    });

    test('Given cardBinIsValid is true (default), When onValidityChange is called with a valid cardNumber format, Then removes the error state as before (regression)', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

      callbacks.onValidityChange(null, 'cardNumber');

      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');
    });

    test('Given cardBinIsValid is false, When onValidityChange is called for a different field (cardholderName), Then is not affected by the cardNumber guard', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      cardForm.cardBinIsValid = false;
      CheckoutPage.verifyCardholderName = jest.fn(() => true);

      callbacks.onValidityChange(null, 'cardholderName');

      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');
    });

    test('Given cardBinIsValid is false from a previous mount, When onReady runs again (remount), Then resets cardBinIsValid to true', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      cardForm.cardBinIsValid = false;

      callbacks.onReady({ cardNumber: { on: jest.fn() }, expirationDate: { on: jest.fn() }, securityCode: { on: jest.fn() } });

      expect(cardForm.cardBinIsValid).toBe(true);
    });

    test('Given a falsy value is thrown while processing a recognized payment method, When the catch displays the error, Then cardBinIsValid is set to false to stay consistent with the visible error', () => {
      const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());
      CheckoutPage.clearInputs.mockImplementationOnce(() => { throw undefined; });

      callbacks.onPaymentMethodsReceived(null, [mockPaymentMethod]);

      expect(cardForm.cardBinIsValid).toBe(false);
      expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith('fcCardNumberContainer', 'add', 'mp-error');
    });

    describe('behavior scenarios — real callback sequences', () => {
      test('Given an invalid BIN was detected, When the buyer deletes and retypes a digit (two consecutive valid-format checks), Then the error is never removed', () => {
        const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

        callbacks.onPaymentMethodsReceived('No payment methods found', null);
        callbacks.onValidityChange(null, 'cardNumber'); // deleting a digit — still within the accepted length range
        expect(CheckoutPage.setDisplayOfError).not.toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');

        callbacks.onValidityChange(null, 'cardNumber'); // retyping the digit
        expect(CheckoutPage.setDisplayOfError).not.toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');
      });

      test('Given a valid card was recognized, When the buyer switches to an invalid BIN and format stays valid, Then the error is kept visible', () => {
        const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

        callbacks.onPaymentMethodsReceived(null, [mockPaymentMethod]);
        callbacks.onPaymentMethodsReceived('No payment methods found', null);
        callbacks.onValidityChange(null, 'cardNumber');

        expect(CheckoutPage.setDisplayOfError).not.toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');
      });

      test('Given an invalid BIN whose brand length the SDK knows, When the buyer shortens (invalid_length) and retypes the same BIN (valid format), Then the error is NOT removed — the invalid-BIN lock survives the length event', () => {
        const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

        // Complete invalid BIN detected — the SDK reports "no payment methods"
        callbacks.onPaymentMethodsReceived('No payment methods found', null);
        expect(cardForm.cardBinIsValid).toBe(false);

        // Shorten one digit → the SDK knows the brand length and fires invalid_length
        callbacks.onValidityChange([{ code: 'invalid_length' }], 'cardNumber');
        // The lock must survive: the flag stays false (no reset)
        expect(cardForm.cardBinIsValid).toBe(false);

        // Retype the same BIN → format valid again; onPaymentMethodsReceived does NOT re-fire (same BIN)
        callbacks.onValidityChange(null, 'cardNumber');

        // The error must never be removed, so the pre-submit gate keeps blocking the invalid card
        expect(CheckoutPage.setDisplayOfError).not.toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');
      });

      test('Given an invalid BIN was detected, When the buyer corrects it to a recognized card, Then the error is removed normally', () => {
        const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

        callbacks.onPaymentMethodsReceived('No payment methods found', null);
        callbacks.onPaymentMethodsReceived(null, [mockPaymentMethod]);
        callbacks.onValidityChange(null, 'cardNumber');

        expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');
      });

      test('Given an invalid BIN error happened before a remount, When the form remounts and the buyer types a new card, Then the stale error state does not leak into the new attempt', () => {
        const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

        callbacks.onPaymentMethodsReceived('No payment methods found', null);
        callbacks.onReady({ cardNumber: { on: jest.fn() }, expirationDate: { on: jest.fn() }, securityCode: { on: jest.fn() } });
        callbacks.onValidityChange(null, 'cardNumber');

        expect(CheckoutPage.setDisplayOfError).toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');
      });

      test('Given the form remounts after an invalid BIN (e.g. updated_checkout), When the buyer re-enters an invalid BIN into the freshly recreated fields, Then onPaymentMethodsReceived re-applies the error — the onReady reset to true is safe because a remount forces re-evaluation from an empty field', () => {
        const callbacks = cardForm.getCardFormCallbacks(jest.fn(), jest.fn());

        callbacks.onPaymentMethodsReceived('No payment methods found', null);
        expect(cardForm.cardBinIsValid).toBe(false);

        // remount (updated_checkout): onReady resets the flag; the SDK recreates the card number iframe empty
        callbacks.onReady({ cardNumber: { on: jest.fn() }, expirationDate: { on: jest.fn() }, securityCode: { on: jest.fn() } });
        expect(cardForm.cardBinIsValid).toBe(true);

        // re-entry into the empty field triggers a fresh BIN evaluation that re-blocks
        callbacks.onPaymentMethodsReceived('No payment methods found', null);
        expect(cardForm.cardBinIsValid).toBe(false);

        CheckoutPage.setDisplayOfError.mockClear();
        callbacks.onValidityChange(null, 'cardNumber');
        expect(CheckoutPage.setDisplayOfError).not.toHaveBeenCalledWith(expect.anything(), 'removed', 'mp-error');
      });
    });
  });

  describe('getCardFormConfig()', () => {
    test('When getCardFormConfig() is called, Then should return config with all fields including identificationType, identificationNumber, issuer and installments', () => {
      const config = cardForm.getCardFormConfig();

      expect(config).toHaveProperty('cardNumber');
      expect(config).toHaveProperty('cardholderName');
      expect(config).toHaveProperty('cardExpirationDate');
      expect(config).toHaveProperty('securityCode');
      expect(config).toHaveProperty('identificationType');
      expect(config).toHaveProperty('identificationNumber');
      expect(config).toHaveProperty('issuer');
      expect(config).toHaveProperty('installments');
    });

    test('When getCardFormConfig() is called, Then cardNumber config should have correct style and customFonts', () => {
      const config = cardForm.getCardFormConfig();

      expect(config.cardNumber).toEqual(
        expect.objectContaining({
          id: 'form-checkout__cardNumber-container',
          placeholder: '1234 1234 1234 1234',
          style: expect.objectContaining({ fontSize: '16px' }),
          customFonts: expect.any(Array),
        })
      );
    });

    test('When getCardFormConfig() is called, Then cardNumber enables Luhn validation', () => {
      const config = cardForm.getCardFormConfig();

      expect(config.cardNumber.enableLuhnValidation).toBe(true);
    });
  });
});
