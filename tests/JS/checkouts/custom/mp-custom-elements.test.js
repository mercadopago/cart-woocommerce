const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');
const { mountCustomCheckout } = require('./fixtures/custom-checkout-dom');

const elementsPath = resolveAlias('assets/js/checkouts/custom/mp-custom-elements.js');
const pagePath = resolveAlias('assets/js/checkouts/custom/mp-custom-page.js');

describe('Custom checkout DOM contract', () => {
  let elements;
  let page;

  beforeEach(() => {
    document.body.replaceChildren();
    elements = loadFile(elementsPath, 'CheckoutElements');
    page = loadFile(pagePath, 'CheckoutPage', {
      CheckoutElements: elements,
      wc_mercadopago_custom_page_params: {
        installments_select_placeholder_text: 'Choose installments',
        detected_card_label: 'Card',
      },
      wc_mercadopago_custom_checkout_params: { site_id: 'MLB' },
    });
    mountCustomCheckout();
  });

  afterEach(() => {
    document.body.replaceChildren();
  });

  test.each([
    ['cardTokenId', 'INPUT'],
    ['cardInstallments', 'INPUT'],
    ['paymentMethodId', 'INPUT'],
    ['fcCardholderName', 'INPUT'],
    ['fcCardNumberContainer', 'DIV'],
    ['fcCardExpirationDateContainer', 'DIV'],
    ['fcSecurityNumberContainer', 'DIV'],
    ['fcIdentificationNumber', 'INPUT'],
    ['fcIdentificationNumberInput', 'INPUT'],
    ['mpDocumentComponent', 'INPUT-DOCUMENT'],
    ['fcIdentificationNumberContainer', 'DIV'],
    ['mpCardholderNameInputLabel', 'LABEL'],
    ['mpDocumentInputLabel', 'LABEL'],
    ['mpCardHolderNameHelper', 'DIV'],
    ['mpCardHolderNameHelperInfo', 'DIV'],
    ['mpInstallmentsContainer', 'DIV'],
    ['mpIssuerContainer', 'DIV'],
    ['mpSecurityCodeInfo', 'SPAN'],
    ['mpDetectedCardAnnouncement', 'SPAN'],
  ])('%s finds the expected node in the real template and components', (key, tagName) => {
    const node = document.querySelector(elements[key]);

    expect(node).not.toBeNull();
    expect(node.tagName).toBe(tagName);
  });

  test.each([
    ['cardTokenId', 'mercadopago_custom[token]'],
    ['cardInstallments', 'mercadopago_custom[installments]'],
    ['paymentMethodId', 'mercadopago_custom[payment_method_id]'],
  ])('writes %s to its semantic submission field', (key, fieldName) => {
    const fields = [...document.querySelectorAll('#mercadopago-utilities input')];
    const previousValues = new Map(fields.map((field) => [field, field.value]));

    page.setValueOn(key, 'synthetic-value');

    const field = document.querySelector(`input[name="${fieldName}"]`);
    expect(field.type).toBe('hidden');
    expect(field.value).toBe('synthetic-value');
    fields.filter((otherField) => otherField !== field).forEach((otherField) => {
      expect(otherField.value).toBe(previousValues.get(otherField));
    });
  });

  test('validates the cardholder through the real label and helper selectors', () => {
    const input = document.querySelector(elements.fcCardholderName);
    const label = document.querySelector(elements.mpCardholderNameInputLabel);
    const errorHelper = document.querySelector(elements.mpCardHolderNameHelper);

    input.value = 'A';
    expect(page.verifyCardholderName()).toBe(false);
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(label).toHaveClass('mp-label-error');
    expect(errorHelper.style.display).toBe('flex');

    input.value = 'João Silva';
    expect(page.verifyCardholderName()).toBe(true);
    expect(input).toHaveAttribute('aria-invalid', 'false');
    expect(label).not.toHaveClass('mp-label-error');
    expect(errorHelper.style.display).toBe('none');
  });

  test('targets the visible Custom document control even when another gateway comes first', () => {
    const otherGateway = document.createElement('div');
    const otherInput = document.createElement('input');
    otherInput.className = 'mp-document';
    otherInput.setAttribute('aria-invalid', 'false');
    otherGateway.append(otherInput);
    document.body.prepend(otherGateway);
    const visible = document.querySelector(elements.fcIdentificationNumberInput);
    const hidden = document.querySelector(elements.fcIdentificationNumber);

    page.setDisplayOfError('fcIdentificationNumberContainer', 'add', 'mp-error');

    expect(visible).toHaveAttribute('aria-invalid', 'true');
    expect(hidden.type).toBe('hidden');
    expect(hidden).not.toHaveAttribute('aria-invalid');
    expect(otherGateway.firstElementChild).toHaveAttribute('aria-invalid', 'false');

    page.setDisplayOfError('fcIdentificationNumberContainer', 'remove', 'mp-error');
    expect(visible).toHaveAttribute('aria-invalid', 'false');
  });

  test.each([
    ['cardNumber', 'mp-card-number-helper'],
    ['cardholderName', 'mp-card-holder-name-helper'],
    ['expirationDate', 'mp-expiration-date-helper'],
    ['securityCode', 'mp-security-code-helper'],
    ['identificationNumber', 'mp-doc-number-helper'],
  ])('%s helper prefix resolves to its real component', (field, helperId) => {
    const prefix = page.inputHelperName(field);
    const helper = page.getInputHelperElement(prefix, 'helper');

    expect(helper).not.toBeNull();
    expect(helper.getAttribute('input-id')).toBe(helperId);
    expect(helper.querySelector('div').id).toBe(helperId);
  });

  test('announces a detected card using the template live region', () => {
    page.announceDetectedCard('Visa');
    expect(document.querySelector(elements.mpDetectedCardAnnouncement).textContent).toBe('Card Visa');
  });

  test('guarded consumers tolerate a checkout with no Custom nodes', () => {
    const otherGateway = document.createElement('div');
    otherGateway.className = 'other-gateway';
    const otherInput = document.createElement('input');
    otherInput.setAttribute('value', 'unchanged');
    otherGateway.append(otherInput);
    document.body.replaceChildren(otherGateway);
    const previousDOM = document.body.cloneNode(true);

    expect(() => {
      page.setValueOn('cardTokenId', 'synthetic-value');
      page.setDisplayOfError('fcCardholderName', 'add', 'mp-error');
      page.announceDetectedCard('Visa');
    }).not.toThrow();
    expect(page.getInputHelperElement('mp-card-holder-name', 'helper')).toBeUndefined();
    expect(document.body.isEqualNode(previousDOM)).toBe(true);
  });
});
