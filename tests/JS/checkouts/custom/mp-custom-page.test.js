const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');

const MP_CUSTOM_PAGE_PATH = resolveAlias('assets/js/checkouts/custom/mp-custom-page.js');

function loadCheckoutPage() {
  return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
    wc_mercadopago_custom_checkout_params: {
      site_id: 'MLC',
      input_helper_message: {
        installments: {
          interest_free_option_text: 'sem juros',
          bank_interest_hint_text: 'Sujeito a juros do banco emissor',
        },
      },
    },
    wc_mercadopago_custom_page_params: {
      installments_select_placeholder_text: 'Selecione as parcelas',
    },
    CheckoutElements: {},
  });
}

describe('CheckoutPage', () => {
  let CheckoutPage;

  beforeEach(() => {
    CheckoutPage = loadCheckoutPage();
  });

  describe('hasThirdPartyInterestFreeInstallment()', () => {
    test('Given installmentsData is undefined, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(undefined)).toBe(false);
    });

    test('Given installmentsData is null, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(null)).toBe(false);
    });

    test('Given installmentsData has no payer_costs, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment({})).toBe(false);
    });

    test('Given payer_costs is not an array, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment({ payer_costs: 'invalid' })).toBe(false);
    });

    test('Given payer_costs is an empty array, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment({ payer_costs: [] })).toBe(false);
    });

    test('Given installment_rate_collector is null, When called, Then returns false', () => {
      const data = {
        payer_costs: [
          { installments: 3, installment_rate: 0, installment_rate_collector: null },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(false);
    });

    test('Given installment_rate_collector is missing, When called, Then returns false', () => {
      const data = {
        payer_costs: [
          { installments: 3, installment_rate: 0 },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(false);
    });

    test('Given collector has THIRD_PARTY but installment_rate is not 0, When called, Then returns false', () => {
      const data = {
        payer_costs: [
          { installments: 6, installment_rate: 12.5, installment_rate_collector: ['THIRD_PARTY'] },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(false);
    });

    test('Given installment_rate is 0 but collector is MERCADOPAGO, When called, Then returns false', () => {
      const data = {
        payer_costs: [
          { installments: 6, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'] },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(false);
    });

    test('Given at least one payer_cost has THIRD_PARTY and installment_rate is 0, When called, Then returns true', () => {
      const data = {
        payer_costs: [
          { installments: 1, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'] },
          { installments: 3, installment_rate: 0, installment_rate_collector: ['THIRD_PARTY'] },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(true);
    });
  });

  describe('getHelperMessage() — null-safety hardening', () => {
    function loadCheckoutPageWithCardNumberElement() {
      return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLC', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: { mpCardNumber: 'mp-card-number' },
      });
    }

    afterEach(() => {
      document.body.innerHTML = '';
    });

    test('Given the input-helper element does not exist in the DOM, When getHelperMessage is called, Then returns null instead of throwing', () => {
      const page = loadCheckoutPageWithCardNumberElement();

      expect(() => page.getHelperMessage('cardNumber')).not.toThrow();
      expect(page.getHelperMessage('cardNumber')).toBeNull();
    });

    test('Given the input-helper element exists with a .mp-helper child, When getHelperMessage is called, Then returns the helper text node', () => {
      const page = loadCheckoutPageWithCardNumberElement();
      document.body.innerHTML = `
        <input-helper input-id="mp-card-number-helper">
          <div class="mp-helper">
            <span class="mp-helper-text"></span>
          </div>
        </input-helper>
      `;

      const result = page.getHelperMessage('cardNumber');

      expect(result).not.toBeNull();
      expect(result.className).toBe('mp-helper-text');
    });
  });

  describe('cardNumberHasError()', () => {
    function loadCheckoutPageWithCardContainer() {
      return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLC', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: { fcCardNumberContainer: '#form-checkout__cardNumber-container' },
      });
    }

    afterEach(() => {
      document.body.innerHTML = '';
    });

    test('Given the container has mp-error, When called, Then returns true', () => {
      const page = loadCheckoutPageWithCardContainer();
      document.body.innerHTML = '<div id="form-checkout__cardNumber-container" class="mp-error"></div>';

      expect(page.cardNumberHasError()).toBe(true);
    });

    test('Given the container has mp-error-2px, When called, Then returns true', () => {
      const page = loadCheckoutPageWithCardContainer();
      document.body.innerHTML = '<div id="form-checkout__cardNumber-container" class="mp-error-2px"></div>';

      expect(page.cardNumberHasError()).toBe(true);
    });

    test('Given the container has no error class, When called, Then returns false', () => {
      const page = loadCheckoutPageWithCardContainer();
      document.body.innerHTML = '<div id="form-checkout__cardNumber-container"></div>';

      expect(page.cardNumberHasError()).toBe(false);
    });

    test('Given the container is absent from the DOM, When called, Then returns false without throwing', () => {
      const page = loadCheckoutPageWithCardContainer();
      document.body.innerHTML = '';

      expect(() => page.cardNumberHasError()).not.toThrow();
      expect(page.cardNumberHasError()).toBe(false);
    });
  });

  describe('cardholderNameHasError()', () => {
    function loadCheckoutPageWithCardholder() {
      return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLC', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: { fcCardholderName: '#form-checkout__cardholderName' },
      });
    }

    afterEach(() => {
      document.body.innerHTML = '';
    });

    test.each(['mp-error', 'mp-error-2px'])('Given the cardholder input has %s, When called, Then returns true', (cls) => {
      const page = loadCheckoutPageWithCardholder();
      document.body.innerHTML = `<input id="form-checkout__cardholderName" class="${cls}" />`;

      expect(page.cardholderNameHasError()).toBe(true);
    });

    test('Given the cardholder input has no error class, When called, Then returns false', () => {
      const page = loadCheckoutPageWithCardholder();
      document.body.innerHTML = '<input id="form-checkout__cardholderName" />';

      expect(page.cardholderNameHasError()).toBe(false);
    });

    test('Given the cardholder input is absent, When called, Then returns false without throwing', () => {
      const page = loadCheckoutPageWithCardholder();
      document.body.innerHTML = '';

      expect(() => page.cardholderNameHasError()).not.toThrow();
      expect(page.cardholderNameHasError()).toBe(false);
    });
  });

  describe('clearCardState() — Super Token field ownership (PSW-4342)', () => {
    function loadCheckoutPageForCardState() {
      return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLC', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: {
          paymentMethodId: '#paymentMethodId',
          cardInstallments: '#cardInstallments',
          fcCardholderName: '#fcCardholderName',
          fcCardNumberContainer: '#fcCardNumberContainer',
        },
      });
    }

    function setupDom(checkoutType) {
      document.body.innerHTML = `
        <input id="mp_checkout_type" value="${checkoutType}" />
        <input id="paymentMethodId" value="visa" />
        <input id="cardInstallments" value="3" />
        <input id="fcCardholderName" value="Someone" />
        <div id="fcCardNumberContainer"></div>
      `;
    }

    afterEach(() => {
      document.body.innerHTML = '';
    });

    test('Given checkout is super_token, When clearCardState is called, Then #paymentMethodId and #cardInstallments are preserved', () => {
      const page = loadCheckoutPageForCardState();
      setupDom('super_token');

      page.clearCardState();

      expect(document.getElementById('paymentMethodId').value).toBe('visa');
      expect(document.getElementById('cardInstallments').value).toBe('3');
    });

    test('Given checkout is Custom (not super_token), When clearCardState is called, Then #paymentMethodId and #cardInstallments are cleared', () => {
      const page = loadCheckoutPageForCardState();
      setupDom('custom');

      page.clearCardState();

      expect(document.getElementById('paymentMethodId').value).toBe('');
      expect(document.getElementById('cardInstallments').value).toBe('');
    });

    test('Given checkout is Custom, When clearCardState is called, Then shared card state (cardholder name) is still cleared', () => {
      const page = loadCheckoutPageForCardState();
      setupDom('custom');

      page.clearCardState();

      expect(document.getElementById('fcCardholderName').value).toBe('');
    });

    test('Given removeAdditionFields is called with clearInstallmentsValue=false, Then #cardInstallments is preserved', () => {
      const page = loadCheckoutPageForCardState();
      setupDom('custom');

      page.removeAdditionFields(false);

      expect(document.getElementById('cardInstallments').value).toBe('3');
    });

    test('Given removeAdditionFields is called with the default argument, Then #cardInstallments is cleared', () => {
      const page = loadCheckoutPageForCardState();
      setupDom('custom');

      page.removeAdditionFields();

      expect(document.getElementById('cardInstallments').value).toBe('');
    });

    test('Given checkout is super_token, When clearInputs is called (the real unmount chain), Then #paymentMethodId and #cardInstallments are preserved', () => {
      const page = loadCheckoutPageForCardState();
      setupDom('super_token');

      page.clearInputs();

      expect(document.getElementById('paymentMethodId').value).toBe('visa');
      expect(document.getElementById('cardInstallments').value).toBe('3');
    });

    test('Given checkout is Custom, When clearInputs is called, Then #paymentMethodId and #cardInstallments are cleared', () => {
      const page = loadCheckoutPageForCardState();
      setupDom('custom');

      page.clearInputs();

      expect(document.getElementById('paymentMethodId').value).toBe('');
      expect(document.getElementById('cardInstallments').value).toBe('');
    });
  });

  describe('emitGateBlockedMetric()', () => {
    let sendMetric;

    function loadPageWithMetric() {
      return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLC', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: {},
        sendMetric,
      });
    }

    beforeEach(() => {
      sendMetric = jest.fn();
    });

    test('Given a gate/target/reason, When called, Then emits sendMetric with the unified gate contract', () => {
      const page = loadPageWithMetric();

      page.emitGateBlockedMetric('INSTALLMENTS', 'mp_custom_installments_validation', 'not_selected');

      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INSTALLMENTS_VALIDATION_BLOCKED',
        'not_selected',
        'mp_custom_installments_validation',
        { reason: 'not_selected' }
      );
    });

    test('Given sendMetric is not a function, When called, Then does not throw', () => {
      sendMetric = undefined;
      const page = loadPageWithMetric();

      expect(() => page.emitGateBlockedMetric('CARD', 'mp_custom_card_validation', 'invalid_bin')).not.toThrow();
    });
  });

  describe('runPreSubmitGates() — unified pre-submit gate (card -> installments -> document)', () => {
    let sendMetric;
    let cardForm;

    function loadPageForGates() {
      return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLC', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: {
          customContent: '#mp-custom-content',
          fcCardNumberContainer: '#form-checkout__cardNumber-container',
          fcIdentificationNumber: '#form-checkout__identificationNumber',
          mpDocumentContainer: '#mp-doc-div',
          mpDocumentInputLabel: '#mp-doc-label',
        },
        sendMetric,
      });
    }

    // docDisplay 'none'/'' => verifyDocument() returns true (document not required/hidden)
    function setupDom({
      cardError = false,
      installments = '3',
      docDisplay = 'none',
      docValue = '',
      docContainerError = false,
    } = {}) {
      document.body.innerHTML = `
        <div id="mp-custom-content"><div id="mp-doc-label" class="mp-input-label"></div></div>
        <div id="form-checkout__cardNumber-container" class="${cardError ? 'mp-error' : ''}"></div>
        <select id="form-checkout__installments">
          <option value="">placeholder</option>
          <option value="3">3</option>
        </select>
        <div id="mp-doc-div"></div>
        <input id="form-checkout__identificationNumber" />
        <div id="form-checkout__identificationNumber-container" class="${docContainerError ? 'mp-error' : ''}"></div>
      `;
      document.getElementById('form-checkout__installments').value = installments;
      document.getElementById('mp-doc-div').style.display = docDisplay;
      document.getElementById('form-checkout__identificationNumber').value = docValue;
    }

    beforeEach(() => {
      sendMetric = jest.fn();
      cardForm = {
        getCardValidationReason: jest.fn(() => 'invalid_bin'),
        scrollToCardForm: jest.fn(),
        removeLoadSpinner: jest.fn(),
        removeBlockOverlay: jest.fn(),
      };
      // jsdom does not implement scrollIntoView; stub it so gate scroll calls do not throw
      Element.prototype.scrollIntoView = jest.fn();
    });

    afterEach(() => {
      document.body.innerHTML = '';
    });

    test('Given the card number has an error, When called, Then blocks on the card gate with the reason from getCardValidationReason and emits the card metric', async () => {
      const page = loadPageForGates();
      setupDom({ cardError: true });

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'card', reason: 'invalid_bin' });
      expect(cardForm.getCardValidationReason).toHaveBeenCalled();
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_CARD_VALIDATION_BLOCKED',
        'invalid_bin',
        'mp_custom_card_validation',
        { reason: 'invalid_bin' }
      );
      // Order Pay regression: a short-circuited gate must release WooCommerce's block overlay,
      // otherwise #order_review stays stuck. It is deferred to a microtask, so flush before asserting.
      await Promise.resolve();
      expect(cardForm.removeBlockOverlay).toHaveBeenCalled();
    });

    test('Given the card has an error and no cardForm is available, When called, Then falls back to invalid_length without throwing', () => {
      const page = loadPageForGates();
      setupDom({ cardError: true });

      const result = page.runPreSubmitGates(null);

      expect(result).toEqual({ passed: false, gate: 'card', reason: 'invalid_length' });
    });

    test('Given the card is valid but no installment is selected, When called, Then blocks on the installments gate with reason not_selected', async () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '' });

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'installments', reason: 'not_selected' });
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INSTALLMENTS_VALIDATION_BLOCKED',
        'not_selected',
        'mp_custom_installments_validation',
        { reason: 'not_selected' }
      );
      await Promise.resolve();
      expect(cardForm.removeBlockOverlay).toHaveBeenCalled();
    });

    test('Given card and installments are valid but the document is empty, When called, Then blocks on the document gate with reason empty_field', async () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '3', docDisplay: 'block', docValue: '' });

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'document', reason: 'empty_field' });
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_DOCUMENT_VALIDATION_BLOCKED',
        'empty_field',
        'mp_custom_document_validation',
        { reason: 'empty_field' }
      );
      await Promise.resolve();
      expect(cardForm.removeBlockOverlay).toHaveBeenCalled();
    });

    test('Given the document container shows an error and the field has a value, When called, Then blocks on the document gate with reason invalid_format', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '3', docDisplay: 'none', docValue: '123', docContainerError: true });

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'document', reason: 'invalid_format' });
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_DOCUMENT_VALIDATION_BLOCKED',
        'invalid_format',
        'mp_custom_document_validation',
        { reason: 'invalid_format' }
      );
    });

    test('Given all gates pass, When called, Then returns { passed: true } and emits no metric', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '3', docDisplay: 'none' });

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: true });
      expect(sendMetric).not.toHaveBeenCalled();
      expect(cardForm.getCardValidationReason).not.toHaveBeenCalled();
    });

    test('Given the card gate fails, When called, Then installments and document are not evaluated (short-circuit order)', () => {
      const page = loadPageForGates();
      setupDom({ cardError: true, installments: '' });

      const result = page.runPreSubmitGates(cardForm);

      expect(result.gate).toBe('card');
      expect(sendMetric).toHaveBeenCalledTimes(1);
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_CARD_VALIDATION_BLOCKED',
        expect.any(String),
        'mp_custom_card_validation',
        expect.any(Object)
      );
    });

    test('Given the document gate blocks, When called, Then the document label is painted with mp-label-error', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '3', docDisplay: 'block', docValue: '' });

      page.runPreSubmitGates(cardForm);

      expect(document.getElementById('mp-doc-label').classList.contains('mp-label-error')).toBe(true);
    });
  });

  describe('clearDocumentLabelErrorOnInput() — clears the document label error as the buyer types', () => {
    function loadPageForDoc() {
      return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLC', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: {
          customContent: '#mp-custom-content',
          mpDocumentContainer: '#mp-doc-div',
          mpDocumentInputLabel: '#mp-doc-label',
        },
        sendMetric: jest.fn(),
      });
    }

    // #form-checkout__identificationNumber is the Narciso component's HIDDEN field (written by copy,
    // never fires input); the buyer types into the visible [data-cy=input-document]. The listener is
    // delegated on #mp-doc-div to catch the visible input's bubbling event.
    beforeEach(() => {
      document.body.innerHTML = `
        <div id="mp-custom-content"><div id="mp-doc-label" class="mp-input-label mp-label-error"></div></div>
        <div id="mp-doc-div">
          <input data-cy="input-document" class="mp-document" />
          <input id="form-checkout__identificationNumber" type="hidden" />
        </div>
      `;
    });

    afterEach(() => {
      document.body.innerHTML = '';
    });

    test('Given a non-empty value is typed in the visible document input, When input fires, Then the label error is removed', () => {
      const page = loadPageForDoc();
      page.clearDocumentLabelErrorOnInput();

      const visible = document.querySelector('[data-cy="input-document"]');
      visible.value = '390.533.447-05';
      visible.dispatchEvent(new Event('input', { bubbles: true }));

      expect(document.getElementById('mp-doc-label').classList.contains('mp-label-error')).toBe(false);
    });

    test('Given the visible document input is empty, When input fires, Then the label error is kept', () => {
      const page = loadPageForDoc();
      page.clearDocumentLabelErrorOnInput();

      const visible = document.querySelector('[data-cy="input-document"]');
      visible.value = '';
      visible.dispatchEvent(new Event('input', { bubbles: true }));

      expect(document.getElementById('mp-doc-label').classList.contains('mp-label-error')).toBe(true);
    });

    test('Given input bubbles from the hidden field (not the visible input), When it fires, Then the label error is kept', () => {
      const page = loadPageForDoc();
      page.clearDocumentLabelErrorOnInput();

      const hidden = document.getElementById('form-checkout__identificationNumber');
      hidden.value = '39053344705';
      hidden.dispatchEvent(new Event('input', { bubbles: true }));

      expect(document.getElementById('mp-doc-label').classList.contains('mp-label-error')).toBe(true);
    });

    test('Given it is called on every cardForm remount, When called multiple times, Then the listener is bound only once', () => {
      const page = loadPageForDoc();
      const container = document.getElementById('mp-doc-div');
      const addSpy = jest.spyOn(container, 'addEventListener');

      page.clearDocumentLabelErrorOnInput();
      page.clearDocumentLabelErrorOnInput();
      page.clearDocumentLabelErrorOnInput();

      expect(addSpy.mock.calls.filter(([type]) => type === 'input')).toHaveLength(1);
    });
  });
});
