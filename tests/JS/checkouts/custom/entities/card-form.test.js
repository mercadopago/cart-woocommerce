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
    };

    global.MPCheckoutFieldsDispatcher = {
      addEventListenerDispatcher: jest.fn(),
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

    test('should define TIMEOUT_TO_WAIT_CHECKOUT_AMOUNT_LOAD as 2500', () => {
      expect(cardForm.TIMEOUT_TO_WAIT_CHECKOUT_AMOUNT_LOAD).toBe(2500);
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
    test.each([
      {
        action: 'CARD_FORM_LOADED',
        label: 'Card form loaded successfully',
        target: 'checkout_page',
        description: 'push metric to mPmetrics array with valid data',
      },
      {
        action: 'PAYMENT_ERROR',
        label: 'Payment processing failed',
        target: 'payment_gateway',
        description: 'push metric with error information',
      },
      {
        action: 'VALIDATION_SUCCESS',
        label: 'Card validation passed',
        target: 'card_validator',
        description: 'push metric with validation success',
      },
    ])(
      'Given mPmetrics is defined, When sendMetric("$action", "$label", "$target") is called, Then should $description',
      ({ action, label, target }) => {
        window.mPmetrics = [];

        cardForm.sendMetric(action, label, target);

        expect(window.mPmetrics).toHaveLength(1);
        expect(window.mPmetrics[0]).toEqual({
          action: action,
          label: label,
          target: target,
        });
      }
    );

    test('Given mPmetrics is undefined, When sendMetric() is called, Then should not throw error and do nothing', () => {
      window.mPmetrics = undefined;

      expect(() => {
        cardForm.sendMetric('TEST_ACTION', 'Test label', 'test_target');
      }).not.toThrow();

      expect(window.mPmetrics).toBeUndefined();
    });

    test('Given mPmetrics already has metrics, When sendMetric() is called multiple times, Then should append new metrics', () => {
      window.mPmetrics = [
        { action: 'INITIAL_ACTION', label: 'Initial', target: 'initial_target' },
      ];

      cardForm.sendMetric('SECOND_ACTION', 'Second metric', 'second_target');
      cardForm.sendMetric('THIRD_ACTION', 'Third metric', 'third_target');

      expect(window.mPmetrics).toHaveLength(3);
      expect(window.mPmetrics[0].action).toBe('INITIAL_ACTION');
      expect(window.mPmetrics[1].action).toBe('SECOND_ACTION');
      expect(window.mPmetrics[2].action).toBe('THIRD_ACTION');
    });
  });

  describe('shouldCreateLoadSpinner()', () => {
    test.each([
      {
        hasListRendered: false,
        hasClassicCheckout: false,
        hasTokenLoaded: false,
        expectedResult: true,
        description: 'return true when all conditions are false (should show spinner)',
      },
      {
        hasListRendered: true,
        hasClassicCheckout: false,
        hasTokenLoaded: false,
        expectedResult: false,
        description: 'return false when super token list is rendered',
      },
      {
        hasListRendered: false,
        hasClassicCheckout: true,
        hasTokenLoaded: false,
        expectedResult: false,
        description: 'return false when classic checkout is present',
      },
      {
        hasListRendered: false,
        hasClassicCheckout: false,
        hasTokenLoaded: true,
        expectedResult: false,
        description: 'return false when super token is loaded',
      },
      {
        hasListRendered: true,
        hasClassicCheckout: true,
        hasTokenLoaded: false,
        expectedResult: false,
        description: 'return false when both list rendered and classic checkout exist',
      },
      {
        hasListRendered: true,
        hasClassicCheckout: false,
        hasTokenLoaded: true,
        expectedResult: false,
        description: 'return false when both list rendered and token loaded',
      },
      {
        hasListRendered: false,
        hasClassicCheckout: true,
        hasTokenLoaded: true,
        expectedResult: false,
        description: 'return false when both classic checkout and token loaded',
      },
      {
        hasListRendered: true,
        hasClassicCheckout: true,
        hasTokenLoaded: true,
        expectedResult: false,
        description: 'return false when all conditions are true',
      },
    ])(
      'Given list rendered=$hasListRendered, classic checkout=$hasClassicCheckout, token loaded=$hasTokenLoaded, When shouldCreateLoadSpinner() is called, Then should $description',
      ({ hasListRendered, hasClassicCheckout, hasTokenLoaded, expectedResult }) => {
        document.body.innerHTML = '';

        if (hasListRendered) {
          const listElement = document.createElement('div');
          listElement.className = 'mp-super-token-payment-methods-list';
          document.body.appendChild(listElement);
        }

        if (hasClassicCheckout) {
          const checkoutElement = document.createElement('div');
          checkoutElement.className = 'payment_method_woo-mercado-pago-custom';
          document.body.appendChild(checkoutElement);
        }

        if (hasTokenLoaded) {
          window.mpSuperTokenTriggerHandler = {
            isSuperTokenPaymentMethodsLoaded: () => true,
          };
        } else {
          window.mpSuperTokenTriggerHandler = undefined;
        }

        const result = cardForm.shouldCreateLoadSpinner();

        expect(result).toBe(expectedResult);

        document.body.innerHTML = '';
        window.mpSuperTokenTriggerHandler = undefined;
      }
    );
  });

  describe('isSuperTokenPaymentMethodsLoaded()', () => {
    test.each([
      {
        handlerExists: true,
        methodReturns: true,
        expectedResult: true,
        description: 'return true when handler exists and method returns true',
      },
      {
        handlerExists: true,
        methodReturns: false,
        expectedResult: false,
        description: 'return false when handler exists and method returns false',
      },
      {
        handlerExists: false,
        methodReturns: null,
        expectedResult: false,
        description: 'return false when handler does not exist',
      },
    ])(
      'Given mpSuperTokenTriggerHandler exists=$handlerExists and returns=$methodReturns, When isSuperTokenPaymentMethodsLoaded() is called, Then should $description',
      ({ handlerExists, methodReturns, expectedResult }) => {
        if (handlerExists) {
          window.mpSuperTokenTriggerHandler = {
            isSuperTokenPaymentMethodsLoaded: () => methodReturns,
          };
        } else {
          window.mpSuperTokenTriggerHandler = undefined;
        }

        const result = cardForm.isSuperTokenPaymentMethodsLoaded();

        expect(result).toBe(expectedResult);

        window.mpSuperTokenTriggerHandler = undefined;
      }
    );
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

  describe('isSuperTokenPaymentMethodsListRendered()', () => {
    test.each([
      {
        elementExists: true,
        expectedResult: true,
        description: 'return true when super token payment methods list exists in DOM',
      },
      {
        elementExists: false,
        expectedResult: false,
        description: 'return false when super token payment methods list does not exist in DOM',
      },
    ])(
      'Given super token list element exists=$elementExists, When isSuperTokenPaymentMethodsListRendered() is called, Then should $description',
      ({ elementExists, expectedResult }) => {
        document.body.innerHTML = '';

        if (elementExists) {
          const element = document.createElement('div');
          element.className = 'mp-super-token-payment-methods-list';
          document.body.appendChild(element);
        }

        const result = cardForm.isSuperTokenPaymentMethodsListRendered();

        expect(result).toBe(expectedResult);

        document.body.innerHTML = '';
      }
    );
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
  });

  describe('setupSDKFieldFocusListeners()', () => {
    beforeEach(() => {
      window.mpSuperTokenTriggerHandler = undefined;
    });

    afterEach(() => {
      window.mpSuperTokenTriggerHandler = undefined;
    });

    test('Given mpSuperTokenTriggerHandler does not exist, When setupSDKFieldFocusListeners() is called, Then should return early and not attach listeners', () => {
      const mockField = {
        on: jest.fn(),
      };

      const fields = {
        cardNumber: mockField,
        cardholderName: mockField,
      };

      window.mpSuperTokenTriggerHandler = undefined;

      cardForm.setupSDKFieldFocusListeners(fields);

      expect(mockField.on).not.toHaveBeenCalled();
    });

    test('Given fields is null, When setupSDKFieldFocusListeners() is called, Then should return early and not throw error', () => {
      window.mpSuperTokenTriggerHandler = {
        onSDKFieldFocus: jest.fn(),
      };

      expect(() => {
        cardForm.setupSDKFieldFocusListeners(null);
      }).not.toThrow();

      expect(window.mpSuperTokenTriggerHandler.onSDKFieldFocus).not.toHaveBeenCalled();
    });

    test('Given fields is undefined, When setupSDKFieldFocusListeners() is called, Then should return early and not throw error', () => {
      window.mpSuperTokenTriggerHandler = {
        onSDKFieldFocus: jest.fn(),
      };

      expect(() => {
        cardForm.setupSDKFieldFocusListeners(undefined);
      }).not.toThrow();

      expect(window.mpSuperTokenTriggerHandler.onSDKFieldFocus).not.toHaveBeenCalled();
    });

    test('Given valid fields and mpSuperTokenTriggerHandler exists, When setupSDKFieldFocusListeners() is called, Then should attach focus listeners to all fields', () => {
      const mockOnSDKFieldFocus = jest.fn();
      window.mpSuperTokenTriggerHandler = {
        onSDKFieldFocus: mockOnSDKFieldFocus,
      };

      const mockCardNumberField = {
        on: jest.fn(),
      };

      const mockCardholderNameField = {
        on: jest.fn(),
      };

      const mockExpirationDateField = {
        on: jest.fn(),
      };

      const mockSecurityCodeField = {
        on: jest.fn(),
      };

      const fields = {
        cardNumber: mockCardNumberField,
        cardholderName: mockCardholderNameField,
        expirationDate: mockExpirationDateField,
        securityCode: mockSecurityCodeField,
      };

      cardForm.setupSDKFieldFocusListeners(fields);

      expect(mockCardNumberField.on).toHaveBeenCalledWith('focus', expect.any(Function));
      expect(mockCardholderNameField.on).toHaveBeenCalledWith('focus', expect.any(Function));
      expect(mockExpirationDateField.on).toHaveBeenCalledWith('focus', expect.any(Function));
      expect(mockSecurityCodeField.on).toHaveBeenCalledWith('focus', expect.any(Function));
    });

    test('Given valid fields with focus event, When focus event is triggered, Then should call onSDKFieldFocus with correct field name', () => {
      const mockOnSDKFieldFocus = jest.fn();
      window.mpSuperTokenTriggerHandler = {
        onSDKFieldFocus: mockOnSDKFieldFocus,
      };

      const mockCardNumberField = {
        on: jest.fn((event, callback) => {
          if (event === 'focus') {
            callback({});
          }
        }),
      };

      const fields = {
        cardNumber: mockCardNumberField,
      };

      cardForm.setupSDKFieldFocusListeners(fields);

      expect(mockOnSDKFieldFocus).toHaveBeenCalledWith('cardNumber');
    });

    test('Given field without .on() method, When setupSDKFieldFocusListeners() is called, Then should skip that field and not throw error', () => {
      const mockOnSDKFieldFocus = jest.fn();
      window.mpSuperTokenTriggerHandler = {
        onSDKFieldFocus: mockOnSDKFieldFocus,
      };

      const mockCardNumberField = {
        on: jest.fn(),
      };

      const fields = {
        cardNumber: mockCardNumberField,
        invalidField: { someOtherMethod: jest.fn() },
      };

      expect(() => {
        cardForm.setupSDKFieldFocusListeners(fields);
      }).not.toThrow();

      expect(mockCardNumberField.on).toHaveBeenCalledWith('focus', expect.any(Function));
    });

    test('Given field is null, When setupSDKFieldFocusListeners() is called, Then should skip that field and not throw error', () => {
      const mockOnSDKFieldFocus = jest.fn();
      window.mpSuperTokenTriggerHandler = {
        onSDKFieldFocus: mockOnSDKFieldFocus,
      };

      const mockCardNumberField = {
        on: jest.fn(),
      };

      const fields = {
        cardNumber: mockCardNumberField,
        invalidField: null,
      };

      expect(() => {
        cardForm.setupSDKFieldFocusListeners(fields);
      }).not.toThrow();

      expect(mockCardNumberField.on).toHaveBeenCalledWith('focus', expect.any(Function));
    });

    test('Given multiple fields, When setupSDKFieldFocusListeners() is called, Then should iterate dynamically over all fields using Object.keys()', () => {
      const mockOnSDKFieldFocus = jest.fn();
      window.mpSuperTokenTriggerHandler = {
        onSDKFieldFocus: mockOnSDKFieldFocus,
      };

      const createMockField = () => ({
        on: jest.fn((event, callback) => {
          if (event === 'focus') callback({});
        }),
      });

      const fields = {
        cardNumber: createMockField(),
        expirationDate: createMockField(),
        securityCode: createMockField(),
      };

      cardForm.setupSDKFieldFocusListeners(fields);

      expect(mockOnSDKFieldFocus).toHaveBeenCalledTimes(3);
      expect(mockOnSDKFieldFocus).toHaveBeenCalledWith('cardNumber');
      expect(mockOnSDKFieldFocus).toHaveBeenCalledWith('expirationDate');
      expect(mockOnSDKFieldFocus).toHaveBeenCalledWith('securityCode');
    });
  });
});
