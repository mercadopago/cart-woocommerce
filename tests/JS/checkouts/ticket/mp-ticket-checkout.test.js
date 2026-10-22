const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');
const { createElement, createJQueryHarness, createDomReadyHarness, timers } = require('../fixtures/payment-method-harness');
const source = resolveAlias('assets/js/checkouts/ticket/mp-ticket-checkout.js');
const event = 'checkout_place_order_woo-mercado-pago-ticket';

const messages = {
  postalcode_error_empty: 'Postal code required', postalcode_error_invalid: 'Invalid postal code', postalcode_error_partial: 'Incomplete postal code',
  state_error_unselected: 'State required', city_error_empty: 'City required', city_error_invalid: 'Invalid city',
  neighborhood_error_empty: 'Neighborhood required', neighborhood_error_invalid: 'Invalid neighborhood',
  address_error_empty: 'Street required', address_error_invalid: 'Invalid street',
  number_error_empty: 'Number required', number_error_invalid: 'Invalid number',
};
const fields = {
  zip_code: '12345-678', city: 'Sao Paulo', neighborhood: 'Centro', street_name: 'Rua Teste', street_number: '42', complement: 'Apt 1',
};

function renderTicket() {
  const address = Object.entries(fields).flatMap(([name, value]) => {
    const input = createElement('input', { class: 'mp-checkout-andes-input', id: `form-checkout__address_${name}`, value });
    const control = name === 'street_number'
      ? createElement('div', { class: 'mp-checkout-ticket-billing-input-number' }, input,
        createElement('input', { id: 'form-checkout__address_number_toggle', type: 'checkbox' })) : input;
    return [control, createElement('input-helper', {},
      createElement('div', { id: `form-checkout__address_${name}_error`, style: 'display:none' },
        createElement('span'), createElement('span')))];
  });
  document.body.replaceChildren(
    createElement('form', { class: 'checkout' },
      '\n    ',
      createElement('input', { id: 'payment_method_woo-mercado-pago-ticket', type: 'radio', value: 'woo-mercado-pago-ticket', checked: '' }),
      '\n    ',
      createElement('div', { class: 'mp-checkout-ticket-container' },
        '\n      ',
        createElement('input', { id: 'form-checkout__address_checkbox', type: 'checkbox' }),
        '\n      ',
        createElement('input-document', {},
          createElement('div', { class: 'mp-input' },
            createElement('input', { class: 'mp-document', name: 'mercadopago_ticket[doc_number]', value: '12.abc.345/0001-xy' })
          ),
          '\n        ',
          createElement('input-helper', {}, createElement('div', { class: 'mp-helper', style: 'display:none' }))
        ),
        '\n      ',
        createElement('div', { 'data-cy': 'input-table-list' },
          createElement('label', { class: 'mp-input-table-label' },
            createElement('input', { class: 'mp-input-radio-radio', type: 'radio', checked: '' })
          )
        ),
        '\n      ',
        createElement('input-helper', { 'input-id': 'mp-payment-method-helper' },
          createElement('div', { style: 'display:none' })
        ),
        '\n      ',
        createElement('select', { class: 'mp-checkout-andes-input', id: 'form-checkout__address_federal_unit' },
          createElement('option', { value: '' }, 'Select'),
          createElement('option', { value: 'SP', selected: '' }, 'Sao Paulo')
        ),
        '\n      ',
        createElement('input-helper', {},
          createElement('div', { id: 'form-checkout__address_federal_unit_error', style: 'display:none' },
            createElement('span'),
            createElement('span')
          )
        ),
        '\n      ',
        ...address,
        '\n    '
      )
    )
  );
}

describe('Ticket checkout controller', () => {
  let controller;
  let Controller;
  let events;
  let lifecycle;
  function load(site = 'MLB', construct = true) {
    events = createJQueryHarness();
    lifecycle = createDomReadyHarness();
    const elements = loadFile(resolveAlias('assets/js/checkouts/ticket/mp-ticket-elements.js'), 'CheckoutTicketElements');
    const page = loadFile(resolveAlias('assets/js/checkouts/ticket/mp-ticket-page.js'), 'CheckoutTicketPage', { CheckoutTicketElements: elements });
    Controller = loadFile(source, 'CheckoutTicketPageController', {
      ...timers(), document: lifecycle.document, jQuery: events.jQuery,
      CheckoutTicketElements: elements, CheckoutTicketPage: page,
      wc_mercadopago_ticket_checkout_params: { site_id: site, error_messages: messages },
    });
    if (construct) controller = new Controller({ site_id: site, error_messages: messages });
  }
  const field = name => document.getElementById(`form-checkout__address_${name}`);
  const submit = () => events.emit('form.checkout', event)[0];
  const input = name => field(name).dispatchEvent(new Event('input', { bubbles: true }));
  beforeEach(() => { jest.useFakeTimers(); renderTicket(); load(); });
  afterEach(() => {
    jest.clearAllTimers(); jest.useRealTimers(); jest.restoreAllMocks();
    delete window.addressFieldsFromTicketRowAreValid;
    document.body.replaceChildren();
  });

  test('Given valid MLB fields, When the registered gate succeeds then checkout_error fires, Then the normalized document is restored for display', () => {
    const documentInput = document.querySelector('.mp-document');
    expect(events.callbacks('form.checkout', event)).toHaveLength(1);
    expect(submit()).toBe(true);
    expect(documentInput.value).toBe('12ABC3450001XY');
    events.emit(document.body, 'checkout_error');
    expect(documentInput.value).toBe('12.abc.345/0001-xy');
  });
  test('Given Order Pay and invalid MLB address, When the registered submit gate executes, Then it returns false and removes the overlay', () => {
    document.body.append(createElement('form', { id: 'order_review' }), createElement('div', { class: 'blockOverlay' }));
    field('zip_code').value = '';
    expect(events.callbacks('form#order_review', 'submit')).toHaveLength(1);
    expect(events.emit('form#order_review', 'submit')[0]).toBe(false);
    expect(document.querySelector('.blockOverlay').style.display).toBe('none');
    expect(document.querySelector('.mp-document').value).toBe('12.abc.345/0001-xy');
  });
  test('Given another method is selected, When the gate executes, Then it bypasses missing Ticket DOM', () => {
    document.getElementById('payment_method_woo-mercado-pago-ticket').checked = false;
    document.querySelector('.mp-checkout-ticket-container').remove();
    expect(submit()).toBe(true);
  });
  test('Given an empty document, When MLB submit occurs, Then it is blocked with the component helper visible', () => {
    document.querySelector('.mp-document').value = '';
    expect(submit()).toBe(false);
    expect(document.querySelector('input-document .mp-input')).toHaveClass('mp-error');
    expect(document.querySelector('input-document .mp-helper').style.display).toBe('flex');
  });
  test('Given no selected offline method, When the buyer selects one after a failure, Then the actual table/helper error clears', () => {
    const option = document.querySelector('.mp-input-radio-radio');
    option.checked = false;
    expect(submit()).toBe(false);
    expect(document.querySelector('[data-cy=input-table-list]')).toHaveClass('mp-error');
    expect(document.querySelector('[input-id=mp-payment-method-helper] div').style.display).toBe('flex');
    option.checked = true;
    document.querySelector('.mp-input-table-label').dispatchEvent(new Event('click', { bubbles: true }));
    expect(document.querySelector('[data-cy=input-table-list]')).not.toHaveClass('mp-error');
    expect(document.querySelector('[input-id=mp-payment-method-helper] div').style.display).toBe('none');
    expect(submit()).toBe(true);
  });
  test('Given a single offline method in a hidden input, When no radio exists, Then submit remains valid', () => {
    document.querySelector('[data-cy=input-table-list]').remove();
    document.querySelector('.mp-checkout-ticket-container').append(
      createElement('input', { type: 'hidden', name: 'mercadopago_ticket[payment_method_id]', value: 'bolbradesco' }));
    expect(submit()).toBe(true);
  });
  test.each([
    ['zip_code', '', 'Postal code required'], ['zip_code', '00000-000', 'Invalid postal code'], ['zip_code', '123', 'Incomplete postal code'],
    ['federal_unit', '', 'State required'], ['city', '', 'City required'], ['city', 'AB', 'Invalid city'],
    ['neighborhood', '', 'Neighborhood required'], ['neighborhood', 'A', 'Invalid neighborhood'],
    ['street_name', '', 'Street required'], ['street_name', 'AB', 'Invalid street'],
    ['street_number', '', 'Number required'], ['street_number', '0', 'Invalid number'],
  ])('Given %s=%s, When the buyer submits, Then it is blocked with %s and recovers after correction', (name, invalid, message) => {
    const control = field(name);
    const original = control.value;
    control.value = invalid;
    expect(submit()).toBe(false);
    const error = field(`${name}_error`);
    expect(error.style.display).toBe('flex');
    expect(error.childNodes[1].textContent).toBe(message);
    control.value = original;
    expect(submit()).toBe(true);
    expect(error.style.display).toBe('none');
  });
  test('Given no house number, When the buyer toggles no-number, Then the input is disabled and the gate permits that case', () => {
    const toggle = field('number_toggle');
    toggle.click();
    expect(field('street_number').disabled).toBe(true);
    expect(field('street_number').value).toBe('');
    expect(submit()).toBe(true);
    toggle.click();
    expect(field('street_number').disabled).toBe(false);
    expect(document.querySelector('.mp-checkout-ticket-billing-input-number')).not.toHaveClass('mp-checkout-ticket-billing-input-number-disabled');
    expect(submit()).toBe(false);
  });
  test('Given a bad house number, When focus and correction events occur, Then feedback and focus state follow the actual handlers', () => {
    const number = field('street_number');
    const container = document.querySelector('.mp-checkout-ticket-billing-input-number');
    number.value = '';
    number.dispatchEvent(new Event('focusout'));
    expect(container).toHaveClass('mp-checkout-andes-input-error');
    number.dispatchEvent(new Event('focus'));
    expect(container).toHaveClass('mp-checkout-ticket-billing-input-number-focused-error');
    number.dispatchEvent(new Event('focus'));
    expect(container).toHaveClass('mp-checkout-ticket-billing-input-number-focused-error-on-focus');
    number.value = '43';
    input('street_number');
    expect(field('street_number_error').style.display).toBe('none');
    number.dispatchEvent(new Event('focusout'));
    expect(container).not.toHaveClass('mp-checkout-ticket-billing-input-number-focused-error-on-focus');
  });
  test.each(['city', 'federal_unit', 'street_name', 'complement'])('Given shipping-copy enabled, When %s is edited, Then it no longer claims to use the copied address', name => {
    field('checkbox').checked = true;
    input(name);
    expect(field('checkbox').checked).toBe(false);
  });
  test('Given the buyer edits postal code, When input then blur occur, Then digits are masked and incomplete input receives feedback only on blur', () => {
    field('checkbox').checked = true;
    field('zip_code').value = '12x34';
    input('zip_code');
    expect(field('zip_code').value).toBe('1234');
    expect(field('zip_code_error').style.display).toBe('none');
    field('zip_code').dispatchEvent(new Event('focusout'));
    expect(field('zip_code_error').style.display).toBe('flex');
    field('zip_code').value = '12345678';
    input('zip_code');
    expect(field('zip_code').value).toBe('12345-678');
    expect(field('zip_code_error').style.display).toBe('none');
  });
  test.each(['billing', 'shipping'])('Given %s address values, When copy is enabled, Then the real Woo IDs are used with a masked postal code', prefix => {
    document.body.append(
      createElement('input', { id: `${prefix}_city`, value: 'Rio de Janeiro' }),
      createElement('input', { id: `${prefix}_postcode`, value: '22041001' }), '\n      ',
      createElement('input', { id: `${prefix}_address_1`, value: 'Rua Woo' }),
      createElement('input', { id: `${prefix}_address_2`, value: 'Apt Woo' }),
      createElement('input', { id: `${prefix}_state`, value: 'SP' }));
    field('checkbox').click();
    field('checkbox').dispatchEvent(new Event('input'));
    expect(field('city').value).toBe('Rio de Janeiro');
    expect(field('zip_code').value).toBe('22041-001');
    expect(field('street_name').value).toBe('Rua Woo');
    expect(field('complement').value).toBe('Apt Woo');
    expect(field('federal_unit').value).toBe('SP');
  });
  test('Given both billing and shipping fields, When copy is enabled, Then billing wins and unchecking clears copied values', () => {
    document.body.append(createElement('input', { id: 'billing-city', value: 'Billing City' }),
      createElement('input', { id: 'shipping_city', value: 'Shipping City' }));
    field('checkbox').checked = true;
    field('checkbox').dispatchEvent(new Event('input'));
    expect(field('city').value).toBe('Billing City');
    field('checkbox').checked = false;
    field('checkbox').dispatchEvent(new Event('input'));
    expect(field('city').value).toBe('');
    expect(field('zip_code').value).toBe('');
  });
  test('Given Woo provides a state label instead of abbreviation, When copy is enabled, Then the matching option is selected', () => {
    document.body.append(createElement('input', { id: 'components-form-token-input-1', value: 'Sao Paulo' }));
    field('federal_unit').value = '';
    field('checkbox').checked = true;
    field('checkbox').dispatchEvent(new Event('input'));
    expect(field('federal_unit').value).toBe('SP');
  });
  test('Given optional document input or container disappears, When document maintenance is called, Then no normalized value or error is invented', () => {
    document.querySelector('.mp-document').remove();
    expect(() => controller.normalizeDocumentNumber()).not.toThrow();
    expect(() => controller.restoreDocumentMaskOnFailure()).not.toThrow();
    document.querySelector('.mp-checkout-ticket-container').remove();
    expect(controller.checkForErrors()).toBe(false);
  });
  test.each([
    'payment_method_woo-mercado-pago-ticket',
    'radio-control-wc-payment-method-options-woo-mercado-pago-ticket',
    'theme-ticket-radio',
  ])('Given MLB payment radio %s, When the first bootstrap interval fires, Then the real address contract is published', radioId => {
    renderTicket();
    document.getElementById('payment_method_woo-mercado-pago-ticket').id = radioId;
    load('MLB', false);
    lifecycle.runReady();
    expect(window.addressFieldsFromTicketRowAreValid).toBeUndefined();
    jest.advanceTimersByTime(1000);
    expect(typeof window.addressFieldsFromTicketRowAreValid).toBe('function');
    expect(window.addressFieldsFromTicketRowAreValid()).toBe(true);
    field('city').value = '';
    expect(window.addressFieldsFromTicketRowAreValid()).toBe(false);
    expect(events.callbacks('form.checkout', event)).toHaveLength(1);
  });
});
