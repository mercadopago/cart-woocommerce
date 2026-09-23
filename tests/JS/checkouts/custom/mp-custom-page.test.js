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

  describe('setDisplayOfError() — the document field is marked on the control the buyer navigates', () => {
    // Two inputs: the hidden one carries the submit value, the visible one is the
    // only one in the accessibility tree — see traps.md.
    function loadPageWithBothDocumentInputs() {
      const page = loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLB', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: {
          customContent: '.mp-checkout-custom-container',
          fcIdentificationNumber: '#form-checkout__identificationNumber',
          fcIdentificationNumberInput: '.mp-checkout-custom-container input.mp-document',
          fcIdentificationNumberContainer: '#form-checkout__identificationNumber-container',
        },
      });

      document.body.innerHTML = `
        <div class="mp-checkout-custom-container">
          <div id="form-checkout__identificationNumber-container">
            <input class="mp-document" data-cy="input-document" type="text" name="identificationNumber" />
            <input id="form-checkout__identificationNumber" type="hidden" />
          </div>
        </div>
      `;

      return {
        page,
        visivel: document.querySelector('input.mp-document'),
        escondido: document.getElementById('form-checkout__identificationNumber'),
      };
    }

    afterEach(() => {
      document.body.innerHTML = '';
    });

    describe('given the submit gate rejects the document', () => {
      test('when the error is displayed, then the visible input is the one announced as invalid', () => {
        const { page, visivel, escondido } = loadPageWithBothDocumentInputs();

        page.setDisplayOfError('fcIdentificationNumberContainer', 'add', 'mp-error');

        expect(visivel.getAttribute('aria-invalid')).toBe('true');
        expect(escondido.hasAttribute('aria-invalid')).toBe(false);
      });
    });

    describe('given the document is corrected', () => {
      test('when the error is removed, then the visible input goes back to valid', () => {
        const { page, visivel } = loadPageWithBothDocumentInputs();
        page.setDisplayOfError('fcIdentificationNumberContainer', 'add', 'mp-error');

        page.setDisplayOfError('fcIdentificationNumberContainer', 'removed', 'mp-error');

        expect(visivel.getAttribute('aria-invalid')).toBe('false');
      });
    });

    describe('given the component swapped the input name to its error flag', () => {
      // setInvalidState rewrites `name` to the flag-error value while the document is
      // invalid and not empty, so the field must not be matched by its name.
      test('when the error is displayed, then the field is still found and marked', () => {
        const { page, visivel } = loadPageWithBothDocumentInputs();
        visivel.setAttribute('name', 'docNumberError');

        page.setDisplayOfError('fcIdentificationNumberContainer', 'add', 'mp-error');

        expect(visivel.getAttribute('aria-invalid')).toBe('true');
      });
    });

    describe('given the component owns this field description', () => {
      test('when the error is displayed, then no aria-describedby is written over it', () => {
        const { page, visivel } = loadPageWithBothDocumentInputs();
        visivel.setAttribute('aria-describedby', 'form-checkout__identificationType-instruction');

        page.setDisplayOfError('fcIdentificationNumberContainer', 'add', 'mp-error');

        expect(visivel.getAttribute('aria-describedby')).toBe('form-checkout__identificationType-instruction');
      });
    });
  });

  describe('verifyCardholderName() — the accessible state follows the visible one', () => {
    // The visible state of this field is written by toggleErrorBorder, which does not
    // go through setDisplayOfError, so the sync has to happen in the shared funnel.
    function loadPageWithCardholderField() {
      const page = loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLB', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: {
          fcCardholderName: '#form-checkout__cardholderName',
          mpCardHolderNameHelper: '#mp-card-holder-div #mp-card-holder-name-helper',
          mpCardHolderNameHelperInfo: '#mp-card-holder-div #mp-card-holder-name-helper-info',
          mpCardholderNameInputLabel: '#mp-card-holder-div .mp-input-label',
        },
      });

      document.body.innerHTML = `
        <div id="mp-card-holder-div">
          <div class="mp-input-label"></div>
          <input id="form-checkout__cardholderName" aria-invalid="false"
                 aria-describedby="mp-card-holder-name-helper-info mp-card-holder-name-example" />
          <div id="mp-card-holder-name-helper-info"></div>
          <div id="mp-card-holder-name-helper"></div>
          <span id="mp-card-holder-name-example"></span>
        </div>
      `;

      return { page, input: document.getElementById('form-checkout__cardholderName') };
    }

    afterEach(() => {
      document.body.innerHTML = '';
    });

    describe('given a cardholder name the plugin rejects', () => {
      test('when it is verified, then the field is announced as invalid and described by the error', () => {
        const { page, input } = loadPageWithCardholderField();
        input.value = '1';

        expect(page.verifyCardholderName()).toBe(false);

        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(input.getAttribute('aria-describedby')).toBe('mp-card-holder-name-helper mp-card-holder-name-example');
      });
    });

    describe('given a valid cardholder name', () => {
      test('when it is verified, then the field goes back to the informative description', () => {
        const { page, input } = loadPageWithCardholderField();
        input.value = '1';
        page.verifyCardholderName();

        input.value = 'Maria Lopes';

        expect(page.verifyCardholderName()).toBe(true);

        expect(input.getAttribute('aria-invalid')).toBe('false');
        expect(input.getAttribute('aria-describedby')).toBe('mp-card-holder-name-helper-info mp-card-holder-name-example');
      });
    });

    describe('given the field is invalid and the visible state is red', () => {
      test('when it is verified, then the class and the announced state agree', () => {
        const { page, input } = loadPageWithCardholderField();
        input.value = '1';

        page.verifyCardholderName();

        const estaVermelho = input.classList.contains('mp-error') || input.classList.contains('mp-error-2px');
        expect(estaVermelho).toBe(true);
        expect(input.getAttribute('aria-invalid')).toBe('true');
      });
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

  describe('setZeroDollarInitialCitInstallmentsState()', () => {
    function loadPageForZeroDollarInstallments() {
      return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLC', input_helper_message: {} },
        wc_mercadopago_custom_page_params: {},
        CheckoutElements: {
          cardInstallments: '#cardInstallments',
          mpInstallmentsCard: '#mp-installments-card',
          mpInstallmentsContainer: '#mp-installments-container',
        },
      });
    }

    function setupZeroDollarInstallmentsDom() {
      document.body.innerHTML = `
        <div id="mp-installments-card" style="display: block">
          <div id="mp-installments-container"><div id="sdk-installments-ui"></div></div>
          <div class="mp-checkout-custom-installments-select-container">
            <label class="mp-input-label mp-label-error"></label>
            <select id="form-checkout__installments" class="mp-error" aria-invalid="true" aria-describedby="mp-installments-error">
              <option value="">placeholder</option>
              <option value="3" selected>3</option>
            </select>
            <div id="mp-installments-error" style="display: flex"></div>
            <div id="mp-installments-bank-interest-hint">bank hint</div>
            <div id="mp-installments-tax-info" style="display: block">tax hint</div>
          </div>
        </div>
        <input id="cardInstallments" value="6" />
        <input id="paymentMethodId" value="visa" />
        <div id="mp-doc-div" style="display: block">document state</div>
        <div id="mp-issuers" style="display: block">issuer state</div>
      `;
    }

    afterEach(() => {
      document.body.innerHTML = '';
    });

    test('Given stale installment UI, When zero-dollar CIT state is applied, Then it fixes one installment and clears only installment visuals and hints', () => {
      const page = loadPageForZeroDollarInstallments();
      setupZeroDollarInstallmentsDom();
      page.installmentsItemsData = { payer_costs: [{ installments: 6 }] };

      page.setZeroDollarInitialCitInstallmentsState();

      const select = document.getElementById('form-checkout__installments');
      expect(document.getElementById('cardInstallments').value).toBe('1');
      expect(document.getElementById('mp-installments-card').style.display).toBe('none');
      expect(document.getElementById('mp-installments-container').children).toHaveLength(0);
      expect(select.options).toHaveLength(0);
      expect(select.classList.contains('mp-error')).toBe(false);
      expect(select.getAttribute('aria-invalid')).toBe('false');
      expect(select.hasAttribute('aria-describedby')).toBe(false);
      expect(document.querySelector('.mp-input-label').classList.contains('mp-label-error')).toBe(false);
      expect(document.getElementById('mp-installments-error').style.display).toBe('none');
      expect(document.getElementById('mp-installments-bank-interest-hint')).toBeNull();
      expect(document.getElementById('mp-installments-tax-info').style.display).toBe('none');
      expect(document.getElementById('mp-installments-tax-info').textContent).toBe('');
      expect(page.installmentsItemsData).toEqual([]);
    });

    test('Given document, issuer and payment-method state coexist, When zero-dollar installments are stabilized, Then unrelated fields remain unchanged', () => {
      const page = loadPageForZeroDollarInstallments();
      setupZeroDollarInstallmentsDom();

      page.setZeroDollarInitialCitInstallmentsState();

      expect(document.getElementById('paymentMethodId').value).toBe('visa');
      expect(document.getElementById('mp-doc-div').style.display).toBe('block');
      expect(document.getElementById('mp-doc-div').textContent).toBe('document state');
      expect(document.getElementById('mp-issuers').style.display).toBe('block');
      expect(document.getElementById('mp-issuers').textContent).toBe('issuer state');
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

    test('Given no reason detail, When called, Then emits sendMetric with 3 args (message only)', () => {
      const page = loadPageWithMetric();

      page.emitGateBlockedMetric('INSTALLMENTS', 'mp_custom_installments_validation', 'not_selected');

      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INSTALLMENTS_VALIDATION_BLOCKED',
        'not_selected',
        'mp_custom_installments_validation'
      );
    });

    test('Given a reason detail, When called, Then includes it as { reason } in details', () => {
      const page = loadPageWithMetric();

      page.emitGateBlockedMetric('CARD', 'mp_custom_card_validation', 'rejected_luhn', 'invalid_value:card number rejected on Luhn Validation');

      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_CARD_VALIDATION_BLOCKED',
        'rejected_luhn',
        'mp_custom_card_validation',
        { reason: 'invalid_value:card number rejected on Luhn Validation' }
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
          cardInstallments: '#cardInstallments',
          mpInstallmentsCard: '#mp-installments-card',
          mpInstallmentsContainer: '#mp-installments-container',
          fcCardNumberContainer: '#form-checkout__cardNumber-container',
          fcIdentificationNumber: '#form-checkout__identificationNumber',
          fcIdentificationNumberContainer: '#form-checkout__identificationNumber-container',
          mpDocumentContainer: '#mp-doc-div',
          mpDocumentInputLabel: '#mp-doc-label',
          mpDocumentComponent: '#mp-custom-content input-document',
          fcIdentificationNumberInput: '#mp-custom-content input.mp-document',
        },
        sendMetric,
      });
    }

    // docDisplay 'none'/'' => verifyDocument() returns true (document not required/hidden)
    function setupDom({
      cardError = false,
      installments = '3',
      hiddenInstallments = '',
      docDisplay = 'none',
      docValue = '',
      docContainerError = false,
    } = {}) {
      // Same nesting as the rendered checkout: the document field lives inside the
      // Custom container, which is what setDisplayOfError scopes its lookup to.
      document.body.innerHTML = `
        <div id="mp-custom-content">
          <div id="mp-doc-label" class="mp-input-label"></div>
          <div id="mp-doc-div">
            <input-document>
              <div id="form-checkout__identificationNumber-container" class="${docContainerError ? 'mp-error' : ''}">
                <input class="mp-document" type="text" />
                <input type="hidden" id="form-checkout__identificationNumber" />
              </div>
            </input-document>
          </div>
        </div>
        <div id="form-checkout__cardNumber-container" class="${cardError ? 'mp-error' : ''}"></div>
        <div id="mp-installments-card" style="display: block">
          <div id="mp-installments-container"><div class="sdk-installments-ui"></div></div>
          <div class="mp-checkout-custom-installments-select-container">
            <label class="mp-input-label"></label>
            <select id="form-checkout__installments">
              <option value="">placeholder</option>
              <option value="3">3</option>
            </select>
            <div id="mp-installments-error" style="display: none"></div>
            <div id="mp-installments-tax-info" style="display: none"></div>
          </div>
        </div>
        <input type="hidden" id="cardInstallments" value="${hiddenInstallments}" />
      `;
      document.getElementById('form-checkout__installments').value = installments;
      document.getElementById('mp-doc-div').style.display = docDisplay;
      document.getElementById('form-checkout__identificationNumber').value = docValue;
    }

    beforeEach(() => {
      sendMetric = jest.fn();
      cardForm = {
        getCardValidationReason: jest.fn(() => 'invalid_bin'),
        getCardValidationDetail: jest.fn(() => 'No payment methods found'),
        isZeroDollarInitialCitContext: jest.fn(() => false),
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
        { reason: 'No payment methods found' }
      );
      // Order Pay regression: a short-circuited gate must release WooCommerce's block overlay,
      // otherwise #order_review stays stuck. It is deferred to a microtask, so flush before asserting.
      await Promise.resolve();
      expect(cardForm.removeBlockOverlay).toHaveBeenCalled();
    });

    test('Given the card number fails Luhn (rejected_luhn), When called, Then blocks on the card gate with reason rejected_luhn', async () => {
      const page = loadPageForGates();
      setupDom({ cardError: true });
      cardForm.getCardValidationReason.mockReturnValue('rejected_luhn');
      cardForm.getCardValidationDetail.mockReturnValue('invalid_value:card number rejected on Luhn Validation');

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'card', reason: 'rejected_luhn' });
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_CARD_VALIDATION_BLOCKED',
        'rejected_luhn',
        'mp_custom_card_validation',
        { reason: 'invalid_value:card number rejected on Luhn Validation' }
      );
      await Promise.resolve();
      expect(cardForm.removeBlockOverlay).toHaveBeenCalled();
    });

    test('Given the card has an error and no cardForm is available, When called, Then falls back to invalid_length without throwing', () => {
      const page = loadPageForGates();
      setupDom({ cardError: true });

      const result = page.runPreSubmitGates(null);

      expect(result).toEqual({ passed: false, gate: 'card', reason: 'invalid_length' });
    });

    test('Given zero-dollar initial CIT with no visible installment, When called, Then fixes one installment and continues after the installments gate', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '', hiddenInstallments: '6', docDisplay: 'none' });
      cardForm.isZeroDollarInitialCitContext.mockReturnValue(true);

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: true });
      expect(document.getElementById('cardInstallments').value).toBe('1');
      expect(document.getElementById('mp-installments-card').style.display).toBe('none');
      expect(document.getElementById('form-checkout__installments').options).toHaveLength(0);
      expect(sendMetric).not.toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INSTALLMENTS_VALIDATION_BLOCKED',
        expect.anything(),
        expect.anything()
      );
    });

    test('Given zero-dollar initial CIT with invalid document, When called, Then skips only installments and still blocks on document', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '', hiddenInstallments: '', docDisplay: 'block', docValue: '' });
      cardForm.isZeroDollarInitialCitContext.mockReturnValue(true);

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'document', reason: 'empty_field' });
      expect(document.getElementById('cardInstallments').value).toBe('1');
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_DOCUMENT_VALIDATION_BLOCKED',
        'empty_field',
        'mp_custom_document_validation'
      );
    });

    test('Given zero-dollar initial CIT with invalid card, When called, Then card remains the first gate and installments state is not changed', () => {
      const page = loadPageForGates();
      setupDom({ cardError: true, installments: '', hiddenInstallments: '6' });
      cardForm.isZeroDollarInitialCitContext.mockReturnValue(true);

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'card', reason: 'invalid_bin' });
      expect(cardForm.isZeroDollarInitialCitContext).not.toHaveBeenCalled();
      expect(document.getElementById('cardInstallments').value).toBe('6');
      expect(document.getElementById('mp-installments-card').style.display).toBe('block');
    });

    test('Given the card is valid but no installment is selected, When called, Then blocks on the installments gate with reason not_selected', async () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '' });

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'installments', reason: 'not_selected' });
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INSTALLMENTS_VALIDATION_BLOCKED',
        'not_selected',
        'mp_custom_installments_validation'
      );
      await Promise.resolve();
      expect(cardForm.removeBlockOverlay).toHaveBeenCalled();
    });

    test('Given a non-eligible checkout has stale hidden installment one and no visible selection, When called, Then it preserves the regular installments gate', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '', hiddenInstallments: '1' });

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'installments', reason: 'not_selected' });
      expect(document.getElementById('cardInstallments').value).toBe('1');
      expect(document.getElementById('mp-installments-card').style.display).toBe('block');
    });

    test('Given card and installments are valid but the document is empty, When called, Then blocks on the document gate with reason empty_field', async () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '3', docDisplay: 'block', docValue: '' });

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'document', reason: 'empty_field' });
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_DOCUMENT_VALIDATION_BLOCKED',
        'empty_field',
        'mp_custom_document_validation'
      );
      await Promise.resolve();
      expect(cardForm.removeBlockOverlay).toHaveBeenCalled();
    });

    describe('given the document gate blocks and the buyer never touched the field', () => {
      test('when it blocks, then the visible input is announced as invalid', () => {
        const page = loadPageForGates();
        setupDom({ cardError: false, installments: '3', docDisplay: 'block', docValue: '' });

        page.runPreSubmitGates(cardForm);

        expect(document.querySelector('input.mp-document').getAttribute('aria-invalid')).toBe('true');
      });

      test('when it blocks, then the component is asked to describe the error', () => {
        const page = loadPageForGates();
        setupDom({ cardError: false, installments: '3', docDisplay: 'block', docValue: '' });
        const componente = document.querySelector('input-document');
        componente.markInvalidFromSubmit = jest.fn();

        page.runPreSubmitGates(cardForm);

        expect(componente.markInvalidFromSubmit).toHaveBeenCalledTimes(1);
      });

      test('when the component has not been upgraded yet, then the gate still blocks without throwing', () => {
        const page = loadPageForGates();
        setupDom({ cardError: false, installments: '3', docDisplay: 'block', docValue: '' });
        // A plain element has no component method at all.
        delete document.querySelector('input-document').markInvalidFromSubmit;

        expect(() => page.runPreSubmitGates(cardForm)).not.toThrow();
        expect(page.runPreSubmitGates(cardForm)).toEqual({ passed: false, gate: 'document', reason: 'empty_field' });
      });
    });

    test('Given the document container shows an error and the field has a value, When called, Then blocks on the document gate with reason invalid_format', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '3', docDisplay: 'none', docValue: '123', docContainerError: true });

      const result = page.runPreSubmitGates(cardForm);

      expect(result).toEqual({ passed: false, gate: 'document', reason: 'invalid_format' });
      expect(sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_DOCUMENT_VALIDATION_BLOCKED',
        'invalid_format',
        'mp_custom_document_validation'
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

    // PSW-4385 (context: docs/agent/traps.md). iOS: picker selects without firing `change` → empty hidden.
    test('Given the select has a value but the hidden #cardInstallments is empty (iOS, no change), When called, Then the hidden is synced from the select and all gates pass', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '3', hiddenInstallments: '', docDisplay: 'none' });

      const result = page.runPreSubmitGates(cardForm);

      expect(document.getElementById('cardInstallments').value).toBe('3');
      expect(result).toEqual({ passed: true });
    });

    // PSW-4385. No select value → nothing to mirror; the placeholder still blocks the gate.
    test('Given the select is on the placeholder (empty), When called, Then the hidden stays empty and the installments gate blocks', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '', hiddenInstallments: '' });

      const result = page.runPreSubmitGates(cardForm);

      expect(document.getElementById('cardInstallments').value).toBe('');
      expect(result).toEqual({ passed: false, gate: 'installments', reason: 'not_selected' });
    });

    // PSW-4385. Stale hidden (installments reload leaves it behind) → the visible select wins.
    test('Given the hidden holds a stale value diverging from the select, When called, Then the select value wins (posted value matches what the buyer sees)', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '3', hiddenInstallments: '6', docDisplay: 'none' });

      const result = page.runPreSubmitGates(cardForm);

      expect(document.getElementById('cardInstallments').value).toBe('3');
      expect(result).toEqual({ passed: true });
    });

    // PSW-4385. Explicit choice (change keeps select=hidden) → the mirror is an idempotent no-op.
    test('Given the select and hidden already match (explicit choice), When called, Then the value is preserved', () => {
      const page = loadPageForGates();
      setupDom({ cardError: false, installments: '3', hiddenInstallments: '3', docDisplay: 'none' });

      const result = page.runPreSubmitGates(cardForm);

      expect(document.getElementById('cardInstallments').value).toBe('3');
      expect(result).toEqual({ passed: true });
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

describe('CheckoutPage - error state exposed to screen readers', () => {
  const CHECKOUT_ELEMENTS = {
    customContent: '.mp-checkout-custom-container',
    fcCardholderName: '#form-checkout__cardholderName',
    fcCardNumberContainer: '#form-checkout__cardNumber-container',
    fcCardExpirationDateContainer: '#form-checkout__expirationDate-container',
    fcSecurityNumberContainer: '#form-checkout__securityCode-container',
    mpCardholderNameInputLabel: '#mp-card-holder-div .mp-input-label',
  };

  function loadPageWithForm() {
    document.body.innerHTML = `
      <div class="mp-checkout-custom-container">
        <div id="mp-card-holder-div">
          <label class="mp-input-label" for="form-checkout__cardholderName">Titular</label>
          <input id="form-checkout__cardholderName" aria-describedby="mp-card-holder-name-helper-info" />
        </div>
        <div id="form-checkout__cardNumber-container"></div>
        <div id="form-checkout__expirationDate-container"></div>
        <div id="form-checkout__securityCode-container"></div>
      </div>
    `;

    return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
      wc_mercadopago_custom_checkout_params: { site_id: 'MLB', input_helper_message: { installments: {} } },
      wc_mercadopago_custom_page_params: { installments_select_placeholder_text: '' },
      CheckoutElements: CHECKOUT_ELEMENTS,
    });
  }

  afterEach(() => {
    delete window.mpCustomCheckoutHandler;
    document.body.innerHTML = '';
  });

  describe('given a field with a control of our own', () => {
    test('when it enters the error state, then the control is flagged invalid and points at the error message', () => {
      const page = loadPageWithForm();

      page.setDisplayOfError('fcCardholderName', 'add', 'mp-error');

      const input = document.getElementById('form-checkout__cardholderName');
      expect(input.getAttribute('aria-invalid')).toBe('true');
      expect(input.getAttribute('aria-describedby')).toBe('mp-card-holder-name-helper mp-card-holder-name-example');
    });

    test('when the error is cleared, then the control is no longer flagged and the informative helper is described again', () => {
      const page = loadPageWithForm();

      page.setDisplayOfError('fcCardholderName', 'add', 'mp-error');
      page.setDisplayOfError('fcCardholderName', 'remove', 'mp-error');

      const input = document.getElementById('form-checkout__cardholderName');
      expect(input.getAttribute('aria-invalid')).toBe('false');
      expect(input.getAttribute('aria-describedby')).toBe('mp-card-holder-name-helper-info mp-card-holder-name-example');
    });

    test('when the error is toggled, then the expected-format example is never dropped from the description', () => {
      const page = loadPageWithForm();
      const input = document.getElementById('form-checkout__cardholderName');

      page.setDisplayOfError('fcCardholderName', 'add', 'mp-error');
      expect(input.getAttribute('aria-describedby')).toContain('mp-card-holder-name-example');

      page.setDisplayOfError('fcCardholderName', 'remove', 'mp-error');
      expect(input.getAttribute('aria-describedby')).toContain('mp-card-holder-name-example');
    });

    test('when the error classes are swapped across a focus transition, then the resulting DOM decides the flag', () => {
      const page = loadPageWithForm();

      page.setDisplayOfError('fcCardholderName', 'add', 'mp-error-2px');
      page.setDisplayOfError('fcCardholderName', 'remove', 'mp-error');

      const input = document.getElementById('form-checkout__cardholderName');
      expect(input.getAttribute('aria-invalid')).toBe('true');
    });
  });

  describe('given a cosmetic label class', () => {
    test('when it is applied, then no field is flagged invalid', () => {
      const page = loadPageWithForm();

      page.setDisplayOfError('mpCardholderNameInputLabel', 'add', 'mp-label-error');

      const input = document.getElementById('form-checkout__cardholderName');
      expect(input.hasAttribute('aria-invalid')).toBe(false);
    });
  });

  describe('given a field whose control lives in the SDK iframe', () => {
    test('when it enters the error state, then the validity is pushed into the iframe through the SDK', () => {
      const update = jest.fn();
      window.mpCustomCheckoutHandler = { cardForm: { formMounted: true, form: { update } } };
      const page = loadPageWithForm();

      page.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');

      expect(update).toHaveBeenCalledWith('cardNumber', { invalid: true });
    });

    test('when the error is cleared, then the SDK is told the field is valid again', () => {
      const update = jest.fn();
      window.mpCustomCheckoutHandler = { cardForm: { formMounted: true, form: { update } } };
      const page = loadPageWithForm();

      page.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
      page.setDisplayOfError('fcCardNumberContainer', 'remove', 'mp-error');

      expect(update).toHaveBeenLastCalledWith('cardNumber', { invalid: false });
    });

    test('when the SDK is not mounted yet, then the checkout is not broken by the accessibility hint', () => {
      const page = loadPageWithForm();

      expect(() => page.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error')).not.toThrow();
    });

    test('when the SDK form object remains after unmount, then clearInputs does not update any missing secure field', () => {
      const update = jest.fn();
      window.mpCustomCheckoutHandler = { cardForm: { formMounted: false, form: { update } } };
      const page = loadPageWithForm();

      page.clearInputs();

      expect(update).not.toHaveBeenCalled();
    });

    test('when the SDK throws, then the failure is swallowed instead of blocking the flow', () => {
      window.mpCustomCheckoutHandler = {
        cardForm: { formMounted: true, form: { update: () => { throw new Error('field not mounted'); } } },
      };
      const page = loadPageWithForm();

      expect(() => page.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error')).not.toThrow();
    });
  });
});

describe('CheckoutPage - accessible instruction of the SDK secure fields', () => {
  const CHECKOUT_ELEMENTS = {
    customContent: '.mp-checkout-custom-container',
    fcCardNumberContainer: '#form-checkout__cardNumber-container',
    fcCardExpirationDateContainer: '#form-checkout__expirationDate-container',
    fcSecurityNumberContainer: '#form-checkout__securityCode-container',
    mpDetectedCardAnnouncement: '#mp-detected-card-announcement',
  };

  const PARAMS = {
    installments_select_placeholder_text: '',
    detected_card_label: 'Cartão',
    card_number_instruction: 'Enter the {digits} numbers on your card.',
    card_expiration_instruction: 'Enter two digits for the month and two digits for the year.',
    security_code_instruction: 'Enter your {digits} digit code.',
  };

  // The iframes are what the SDK injects; the instruction goes on the iframe element
  // itself, so a container without one must be tolerated.
  function loadPageWithSecureFields({ withIframes = true } = {}) {
    const iframe = withIframes ? '<iframe></iframe>' : '';
    document.body.innerHTML = `
      <div class="mp-checkout-custom-container">
        <div id="form-checkout__cardNumber-container">${iframe}</div>
        <div id="form-checkout__expirationDate-container">${iframe}</div>
        <div id="form-checkout__securityCode-container">${iframe}</div>
        <span id="mp-detected-card-announcement"></span>
      </div>
    `;

    return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
      wc_mercadopago_custom_checkout_params: { site_id: 'MLB', input_helper_message: { installments: {} } },
      wc_mercadopago_custom_page_params: PARAMS,
      CheckoutElements: CHECKOUT_ELEMENTS,
    });
  }

  const titleOf = (id) => document.getElementById(id).querySelector('iframe').getAttribute('title');

  afterEach(() => {
    document.body.innerHTML = '';
  });

  describe('given the secure fields were just mounted', () => {
    test('when no brand is known yet, then each field describes the default digit counts', () => {
      const page = loadPageWithSecureFields();

      page.setSecureFieldInstructions();

      expect(titleOf('form-checkout__cardNumber-container')).toBe('Enter the 16 numbers on your card.');
      expect(titleOf('form-checkout__expirationDate-container')).toBe('Enter two digits for the month and two digits for the year.');
      expect(titleOf('form-checkout__securityCode-container')).toBe('Enter your 3 digit code.');
    });
  });

  describe('given a brand with different digit counts is detected', () => {
    test('when the instructions are refreshed, then they describe that brand', () => {
      const page = loadPageWithSecureFields();

      page.setSecureFieldInstructions(15, 4);

      expect(titleOf('form-checkout__cardNumber-container')).toBe('Enter the 15 numbers on your card.');
      expect(titleOf('form-checkout__securityCode-container')).toBe('Enter your 4 digit code.');
    });
  });

  describe('given a brand with different digit counts was detected and the card is cleared', () => {
    test('when the state is reset, then the instructions stop describing the card that is gone', () => {
      const page = loadPageWithSecureFields();
      page.setSecureFieldInstructions(15, 4);

      page.setSecureFieldInstructions();

      expect(titleOf('form-checkout__cardNumber-container')).toBe('Enter the 16 numbers on your card.');
      expect(titleOf('form-checkout__securityCode-container')).toBe('Enter your 3 digit code.');
    });
  });

  describe('given the payment method reports a digit length of zero', () => {
    test('when the instructions are refreshed, then the default length is announced instead of the raw placeholder', () => {
      const page = loadPageWithSecureFields();

      page.setSecureFieldInstructions(0, 0);

      expect(titleOf('form-checkout__cardNumber-container')).toBe('Enter the 16 numbers on your card.');
      expect(titleOf('form-checkout__securityCode-container')).toBe('Enter your 3 digit code.');
      expect(titleOf('form-checkout__securityCode-container')).not.toContain('{digits}');
    });
  });

  describe('given the SDK has not injected the iframes yet', () => {
    test('when the instructions are applied, then nothing is thrown and no title is invented', () => {
      const page = loadPageWithSecureFields({ withIframes: false });

      expect(() => page.setSecureFieldInstructions()).not.toThrow();
      expect(document.getElementById('form-checkout__cardNumber-container').querySelector('iframe')).toBeNull();
    });
  });

  describe('given a store whose translations are missing', () => {
    test('when the instructions are applied, then the iframe is left without a title instead of showing a broken string', () => {
      document.body.innerHTML = '<div id="form-checkout__cardNumber-container"><iframe></iframe></div>';
      const page = loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLB', input_helper_message: { installments: {} } },
        wc_mercadopago_custom_page_params: { installments_select_placeholder_text: '' },
        CheckoutElements: CHECKOUT_ELEMENTS,
      });

      page.setSecureFieldInstructions();

      expect(titleOf('form-checkout__cardNumber-container')).toBeNull();
    });
  });
});

describe('CheckoutPage - error message of the SDK secure fields', () => {
  const CHECKOUT_ELEMENTS = {
    customContent: '.mp-checkout-custom-container',
    fcCardNumberContainer: '#form-checkout__cardNumber-container',
    mpSecurityCodeInfo: '#mp-security-code-info',
  };

  function loadPage() {
    document.body.innerHTML = `
      <div class="mp-checkout-custom-container">
        <div id="form-checkout__cardNumber-container" aria-labelledby="mp-card-number-label"></div>
        <span id="mp-card-number-helper">Preencha este campo.</span>
      </div>
    `;

    return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
      wc_mercadopago_custom_checkout_params: { site_id: 'MLB', input_helper_message: { installments: {} } },
      wc_mercadopago_custom_page_params: { installments_select_placeholder_text: '' },
      CheckoutElements: CHECKOUT_ELEMENTS,
    });
  }

  afterEach(() => {
    document.body.innerHTML = '';
  });

  // The control lives in a cross-origin iframe: the SDK forwards aria-invalid but
  // cannot carry the message text, so the container is described instead.
  describe('given a field whose control lives in the SDK iframe', () => {
    test('when it enters the error state, then the container points at the visible message', () => {
      const page = loadPage();

      page.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');

      expect(document.getElementById('form-checkout__cardNumber-container').getAttribute('aria-describedby'))
        .toBe('mp-card-number-helper');
    });

    test('when the error is cleared, then the description is dropped instead of lingering', () => {
      const page = loadPage();
      page.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');

      page.setDisplayOfError('fcCardNumberContainer', 'remove', 'mp-error');

      expect(document.getElementById('form-checkout__cardNumber-container').hasAttribute('aria-describedby'))
        .toBe(false);
    });

    test('when it enters the error state, then the label association is untouched', () => {
      const page = loadPage();

      page.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');

      expect(document.getElementById('form-checkout__cardNumber-container').getAttribute('aria-labelledby'))
        .toBe('mp-card-number-label');
    });
  });
});

describe('CheckoutPage - security code hint per brand', () => {
  const CHECKOUT_ELEMENTS = {
    customContent: '.mp-checkout-custom-container',
    mpSecurityCodeInfo: '#mp-security-code-info',
  };

  const PARAMS = {
    installments_select_placeholder_text: '',
    security_code_tooltip_text_3_digits: 'É um número de 3 dígitos.',
    security_code_tooltip_text_4_digits: 'É um número de 4 dígitos.',
  };

  function loadPage() {
    document.body.innerHTML = `
      <div class="mp-checkout-custom-container">
        <span id="mp-security-code-info" role="tooltip"
              aria-label="É um número de 3 dígitos." data-tooltip="É um número de 3 dígitos.">?</span>
      </div>
    `;

    return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
      wc_mercadopago_custom_checkout_params: { site_id: 'MLB', input_helper_message: { installments: {} } },
      wc_mercadopago_custom_page_params: PARAMS,
      CheckoutElements: CHECKOUT_ELEMENTS,
    });
  }

  const tooltip = () => document.getElementById('mp-security-code-info');

  afterEach(() => {
    document.body.innerHTML = '';
  });

  // data-tooltip is the visual text, aria-label is what the screen reader reads.
  // Updating only the first left Amex announcing "3 digits" while showing "4".
  describe('given a brand with a four digit security code', () => {
    test('when the hint is refreshed, then both the visual and the announced text change', () => {
      const page = loadPage();

      page.setCvvHint(4);

      expect(tooltip().getAttribute('data-tooltip')).toBe('É um número de 4 dígitos.');
      expect(tooltip().getAttribute('aria-label')).toBe('É um número de 4 dígitos.');
    });
  });

  describe('given a brand with a three digit security code', () => {
    test('when the hint is refreshed, then both texts go back to three digits', () => {
      const page = loadPage();
      page.setCvvHint(4);

      page.setCvvHint(3);

      expect(tooltip().getAttribute('data-tooltip')).toBe('É um número de 3 dígitos.');
      expect(tooltip().getAttribute('aria-label')).toBe('É um número de 3 dígitos.');
    });
  });

  describe('given a store whose tooltip element is absent', () => {
    test('when the hint is refreshed, then the checkout is not broken', () => {
      document.body.innerHTML = '<div class="mp-checkout-custom-container"></div>';
      const page = loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
        wc_mercadopago_custom_checkout_params: { site_id: 'MLB', input_helper_message: { installments: {} } },
        wc_mercadopago_custom_page_params: PARAMS,
        CheckoutElements: CHECKOUT_ELEMENTS,
      });

      expect(() => page.setCvvHint(4)).not.toThrow();
    });
  });
});
