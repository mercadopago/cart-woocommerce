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
          invalid_length: 'Número do cartão inválido',
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
      setChangeEventOnInstallments: jest.fn(),
      setValueOn: jest.fn(),
      setCvvConfig: jest.fn(),
      setImageCard: jest.fn(),
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
    };

    global.MPCheckoutFieldsDispatcher = {
      addEventListenerDispatcher: jest.fn(),
    };

    global.sendMetric = jest.fn();

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
      <input id="mp-amount" value="100.50" />
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

  describe('dispatchCheckoutAmountEvent() — tracking de amount (PSW-4147)', () => {
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
});
