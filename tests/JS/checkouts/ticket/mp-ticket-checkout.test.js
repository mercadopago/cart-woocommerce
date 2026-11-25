const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');
const { createElement, createJQueryHarness, createDomReadyHarness, timers } = require('../fixtures/payment-method-harness');
const { createAdminRuntime } = require('../../admin/helpers/admin-runtime');
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
  let observers;
  function load(site = 'MLB', construct = true) {
    events = createJQueryHarness();
    lifecycle = createDomReadyHarness();
    const elements = loadFile(resolveAlias('assets/js/checkouts/ticket/mp-ticket-elements.js'), 'CheckoutTicketElements');
    const page = loadFile(resolveAlias('assets/js/checkouts/ticket/mp-ticket-page.js'), 'CheckoutTicketPage', { CheckoutTicketElements: elements });
    Controller = loadFile(source, 'CheckoutTicketPageController', {
      ...timers(), document: new Proxy(lifecycle.document, {
        get(target, key) { return key === 'readyState' ? 'loading' : Reflect.get(target, key); },
      }), jQuery: events.jQuery,
      CheckoutTicketElements: elements, CheckoutTicketPage: page,
      wc_mercadopago_ticket_checkout_params: { site_id: site, error_messages: messages },
      MutationObserver: class extends window.MutationObserver {
        constructor(callback) { super(callback); observers.push(this); }
      },
    });
    if (construct) controller = new Controller({ site_id: site, error_messages: messages });
  }
  const field = name => document.getElementById(`form-checkout__address_${name}`);
  const submit = () => events.emit(document.querySelector('form.checkout'), event)[0];
  const input = name => field(name).dispatchEvent(new Event('input', { bubbles: true }));
  beforeEach(() => { jest.useFakeTimers(); observers = []; renderTicket(); load(); });
  afterEach(() => {
    observers.forEach(observer => observer.disconnect());
    jest.clearAllTimers(); jest.useRealTimers(); jest.restoreAllMocks();
    delete window.addressFieldsFromTicketRowAreValid;
    document.body.replaceChildren();
  });

  test('Given valid MLB fields, When the registered gate succeeds then checkout_error fires, Then the normalized document is restored for display', () => {
    const documentInput = document.querySelector('.mp-document');
    expect(events.callbacks(document.querySelector('form.checkout'), event)).toHaveLength(1);
    expect(submit()).toBe(true);
    expect(documentInput.value).toBe('12ABC3450001XY');
    events.emit(document.body, 'checkout_error');
    expect(documentInput.value).toBe('12.abc.345/0001-xy');
  });
  test('Given a display value that normalizes to empty, When checkout fails, Then the empty input is preserved', () => {
    const documentInput = document.querySelector('.mp-document');
    documentInput.value = '---';
    controller.normalizeDocumentNumber();
    expect(documentInput.value).toBe('');
    events.emit(document.body, 'checkout_error');
    expect(documentInput.value).toBe('');
  });
  test('Given Order Pay and invalid MLB address, When the registered submit gate executes, Then it returns false, stops later submit handlers and removes the overlay', () => {
    renderTicket();
    document.body.append(createElement('form', { id: 'order_review' }), createElement('div', { class: 'blockOverlay' }));
    load();
    field('zip_code').value = '';
    const submitEvent = { stopImmediatePropagation: jest.fn() };
    expect(events.callbacks(document.querySelector('form#order_review'), 'submit')).toHaveLength(1);
    expect(events.emit(document.querySelector('form#order_review'), 'submit', submitEvent)[0]).toBe(false);
    expect(submitEvent.stopImmediatePropagation).toHaveBeenCalledTimes(1);
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
  ])('Given MLB payment radio %s, When DOM ready fires, Then the real address contract is published', radioId => {
    renderTicket();
    document.getElementById('payment_method_woo-mercado-pago-ticket').id = radioId;
    load('MLB', false);
    expect(window.addressFieldsFromTicketRowAreValid).toBeUndefined();
    lifecycle.runReady();
    expect(typeof window.addressFieldsFromTicketRowAreValid).toBe('function');
    expect(window.addressFieldsFromTicketRowAreValid()).toBe(true);
    field('city').value = '';
    expect(window.addressFieldsFromTicketRowAreValid()).toBe(false);
    expect(events.callbacks(document.querySelector('form.checkout'), event)).toHaveLength(1);
  });
});

describe('Ticket checkout lifecycle', () => {
  let jQuery;
  let ready;
  let observers;
  let runtime;

  function bootstrap(site = 'MLB', readyState = 'loading') {
    ready = createDomReadyHarness();
    const pageDocument = new Proxy(ready.document, {
      get(target, key) { return key === 'readyState' ? readyState : Reflect.get(target, key); },
    });
    const elements = loadFile(resolveAlias('assets/js/checkouts/ticket/mp-ticket-elements.js'), 'CheckoutTicketElements');
    const page = loadFile(resolveAlias('assets/js/checkouts/ticket/mp-ticket-page.js'), 'CheckoutTicketPage', { CheckoutTicketElements: elements });
    const Controller = loadFile(source, 'CheckoutTicketPageController', {
      ...timers(), document: pageDocument, jQuery,
      CheckoutTicketElements: elements, CheckoutTicketPage: page,
      wc_mercadopago_ticket_checkout_params: { site_id: site, error_messages: messages },
      MutationObserver: class extends window.MutationObserver {
        constructor(callback) { super(callback); observers.push(this); }
      },
    });
    if (readyState === 'loading') ready.runReady();
    return Controller;
  }

  const form = () => document.querySelector('form.checkout');
  const count = (element, name) => jQuery._data(element, 'events')?.[name]?.length || 0;
  const submit = () => jQuery(form()).triggerHandler(event);
  const helper = () => document.querySelector('[input-id=mp-payment-method-helper] div');
  const flushMutations = async () => { await Promise.resolve(); await Promise.resolve(); };

  beforeEach(() => {
    jest.useFakeTimers();
    observers = [];
    renderTicket();
    runtime = createAdminRuntime(document.querySelector('form.checkout'));
    jQuery = runtime.$;
  });
  afterEach(() => {
    observers.forEach(observer => observer.disconnect());
    delete window.addressFieldsFromTicketRowAreValid;
    runtime.cleanup();
  });

  test.each(['MLA', 'MLU', 'MLM', 'MCO', 'MPE', 'MLC'])('Given %s without the Brazil address, When Ticket bootstraps, Then it blocks missing choice and accepts a selection', site => {
    document.querySelectorAll('[id^="form-checkout__address_"]').forEach(element => {
      (element.closest('input-helper') ?? element).remove();
    });
    if (site !== 'MLU') document.querySelector('input-document').remove();
    document.querySelector('.mp-input-radio-radio').checked = false;
    bootstrap(site);
    expect(count(form(), event)).toBe(1);
    const attempt = jQuery.Event(event);
    jQuery(form()).trigger(attempt);
    expect(attempt.isDefaultPrevented()).toBe(true);
    expect(helper().style.display).toBe('flex');
    document.querySelector('.mp-input-radio-radio').click();
    expect(helper().style.display).toBe('none');
    expect(submit()).toBe(true);
  });

  test('Given repeated checkout updates, When the buyer edits and submits, Then each own handler executes once and other handlers remain', () => {
    const otherHandler = jest.fn();
    jQuery(form()).on(event, otherHandler);
    const Controller = bootstrap();
    const zip = document.getElementById('form-checkout__address_zip_code');
    const mask = jest.spyOn(Controller.prototype, 'validateZipCodeMask');
    for (let cycle = 0; cycle < 5; cycle += 1) jQuery(document.body).trigger('updated_checkout');
    jest.advanceTimersByTime(6000);
    expect(count(form(), event)).toBe(2);
    expect(count(document.body, 'checkout_error')).toBe(1);
    zip.value = '12345678';
    zip.dispatchEvent(new Event('input', { bubbles: true }));
    expect(zip.value).toBe('12345-678');
    expect(mask).toHaveBeenCalledTimes(1);
    const inputBindings = jest.spyOn(zip, 'addEventListener');
    jQuery(document.body).trigger('updated_checkout');
    expect(inputBindings).not.toHaveBeenCalled();
    submit();
    expect(otherHandler).toHaveBeenCalledTimes(1);
  });

  test('Given Ticket is mounted after DOM ready, When its nodes appear, Then the real gate is registered without a timer', async () => {
    document.body.replaceChildren();
    bootstrap('MLA');
    renderTicket();
    document.getElementById('form-checkout__address_checkbox').remove();
    await flushMutations();
    expect(count(form(), event)).toBe(1);
    document.querySelector('.mp-input-radio-radio').checked = false;
    expect(submit()).toBe(false);
  });

  test('Given a Classic form that stays mounted, When only the Ticket payment box is replaced, Then new controls validate once and recover after selection', async () => {
    bootstrap('MLA');
    const originalForm = form();
    const oldContainer = document.querySelector('.mp-checkout-ticket-container');
    const replacement = oldContainer.cloneNode(true);
    replacement.querySelector('.mp-input-radio-radio').checked = false;
    oldContainer.replaceWith(replacement);
    jQuery(document.body).trigger('updated_checkout');
    await flushMutations();
    expect(form()).toBe(originalForm);
    expect(count(form(), event)).toBe(1);
    const attempt = jQuery.Event(event);
    jQuery(form()).trigger(attempt);
    expect(attempt.isDefaultPrevented()).toBe(true);
    expect(helper().style.display).toBe('flex');
    replacement.querySelector('.mp-input-radio-radio').click();
    expect(helper().style.display).toBe('none');
    expect(submit()).toBe(true);
    expect(count(form(), event)).toBe(1);
  });

  test('Given a failed empty selection, When an offline label is appended later, Then clicking it clears the error and permits submission', async () => {
    bootstrap('MLA');
    document.querySelector('.mp-input-radio-radio').checked = false;
    expect(submit()).toBe(false);
    const option = createElement('input', { class: 'mp-input-radio-radio', type: 'radio', value: 'rapipago' });
    const label = createElement('label', { class: 'mp-input-table-label' }, option);
    document.querySelector('[data-cy=input-table-list]').append(label);
    await flushMutations();
    option.click();
    expect(helper().style.display).toBe('none');
    expect(submit()).toBe(true);
    expect(count(form(), event)).toBe(1);
  });

  test('Given the script loads after DOM ready, When evaluated, Then the initial form is validated immediately', () => {
    bootstrap('MLA', 'complete');
    expect(count(form(), event)).toBe(1);
    document.querySelector('.mp-input-radio-radio').checked = false;
    expect(submit()).toBe(false);
  });

  test('Given a normalized old document, When the form is replaced, Then new fields are bound once and the old mask is not restored into them', async () => {
    bootstrap();
    expect(submit()).toBe(true);
    renderTicket();
    const newDocument = document.querySelector('.mp-document');
    newDocument.value = 'fresh-document';
    await flushMutations();
    jQuery(document.body).trigger('checkout_error');
    expect(newDocument.value).toBe('fresh-document');
    expect(count(form(), event)).toBe(1);
    document.getElementById('form-checkout__address_city').value = '';
    expect(window.addressFieldsFromTicketRowAreValid()).toBe(false);
    expect(submit()).toBe(false);
    expect(count(document.body, 'checkout_error')).toBe(1);
  });

  test('Given Order Pay with no overlay, When address is invalid, Then submit is blocked without a secondary exception', () => {
    const orderForm = createElement('form', { id: 'order_review' });
    document.body.append(orderForm);
    bootstrap();
    document.getElementById('form-checkout__address_city').value = '';
    expect(jQuery(orderForm).triggerHandler('submit')).toBe(false);
  });

  test('Given Order Pay with a valid Ticket, When the buyer submits, Then WooCommerce keeps its submit handler', () => {
    const orderForm = createElement('form', { id: 'order_review' });
    document.body.append(orderForm);
    bootstrap();
    const wooSubmit = jest.fn();
    jQuery(orderForm).on('submit', wooSubmit);
    expect(jQuery(orderForm).triggerHandler('submit')).toBe(true);
    expect(wooSubmit).toHaveBeenCalledTimes(1);
  });

  test('Given Order Pay with an invalid Ticket, When the buyer submits, Then WooCommerce does not block the form', () => {
    const orderForm = createElement('form', { id: 'order_review' });
    document.body.append(orderForm);
    bootstrap();
    // WooCommerce binds after the Ticket gate: its handler blocks the form, and the block adds the overlay.
    const wooSubmit = jest.fn(() => document.body.append(createElement('div', { class: 'blockOverlay' })));
    jQuery(orderForm).on('submit', wooSubmit);
    document.getElementById('form-checkout__address_city').value = '';
    expect(jQuery(orderForm).triggerHandler('submit')).toBe(false);
    expect(wooSubmit).not.toHaveBeenCalled();
    expect(document.querySelector('.blockOverlay')).toBeNull();
  });

  test('Given MLU with an empty document, When an option is selected, Then the gate still blocks the missing document', () => {
    document.getElementById('form-checkout__address_checkbox').remove();
    document.querySelector('.mp-document').value = '';
    bootstrap('MLU');
    expect(submit()).toBe(false);
    expect(document.querySelector('input-document .mp-helper').style.display).toBe('flex');
  });

  test.each([
    'payment_method_woo-mercado-pago-ticket',
    'radio-control-wc-payment-method-options-woo-mercado-pago-ticket',
    'theme-ticket-radio',
  ])('Given Ticket radio %s, When the buyer submits without an offline choice, Then the real gateway event is prevented', radioId => {
    document.getElementById('payment_method_woo-mercado-pago-ticket').id = radioId;
    document.querySelector('.mp-input-radio-radio').checked = false;
    bootstrap('MLA');
    const attempt = jQuery.Event(event);
    jQuery(form()).trigger(attempt);
    expect(attempt.isDefaultPrevented()).toBe(true);
    expect(submit()).toBe(false);
    expect(helper().style.display).toBe('flex');
    document.querySelector('.mp-input-radio-radio').click();
    expect(submit()).toBe(true);
    expect(helper().style.display).toBe('none');
  });

  test('Given an unchecked standard radio and a checked theme radio, When Ticket submits, Then the selected gateway still validates the offline choice', () => {
    document.getElementById('payment_method_woo-mercado-pago-ticket').checked = false;
    form().append(createElement('input', {
      id: 'theme-ticket-radio', type: 'radio', value: 'woo-mercado-pago-ticket', checked: '',
    }));
    document.querySelector('.mp-input-radio-radio').checked = false;
    bootstrap('MLA');
    expect(submit()).toBe(false);
    expect(helper().style.display).toBe('flex');
  });

  test.each(['MLB', 'MLU'])('Given %s without the required document input, When Ticket submits, Then it blocks without throwing and shows the available helper', site => {
    document.querySelector('.mp-document').remove();
    bootstrap(site);
    expect(submit()).toBe(false);
    expect(document.querySelector('input-document .mp-helper').style.display).toBe('flex');
  });

  test.each(['MLB', 'MLU'])('Given %s without the document component, When Ticket submits, Then it blocks incomplete data without throwing', site => {
    document.querySelector('input-document').remove();
    bootstrap(site);
    expect(submit()).toBe(false);
  });

  test('Given the same normalized document in a replacement input, When updated_checkout precedes checkout_error, Then its mask is restored', async () => {
    bootstrap();
    const oldInput = document.querySelector('.mp-document');
    const maskedValue = oldInput.value;
    expect(submit()).toBe(true);
    const newInput = oldInput.cloneNode(true);
    newInput.value = oldInput.value;
    oldInput.replaceWith(newInput);
    jQuery(document.body).trigger('updated_checkout');
    await flushMutations();
    jQuery(document.body).trigger('checkout_error');
    expect(newInput.value).toBe(maskedValue);
    expect(count(form(), event)).toBe(1);
  });

  test.each(['different-synthetic-value', ''])('Given a replacement document with value %j, When checkout fails after a refresh, Then the old document is not copied into it', async value => {
    bootstrap();
    expect(submit()).toBe(true);
    const oldInput = document.querySelector('.mp-document');
    const newInput = oldInput.cloneNode(true);
    newInput.value = value;
    oldInput.replaceWith(newInput);
    jQuery(document.body).trigger('updated_checkout');
    await flushMutations();
    jQuery(document.body).trigger('checkout_error');
    expect(newInput.value).toBe(value);
  });

  test.each(['different-synthetic-value', ''])('Given the buyer changes the submitted document to %j, When checkout fails, Then the newer value is preserved', value => {
    bootstrap();
    expect(submit()).toBe(true);
    const input = document.querySelector('.mp-document');
    input.value = value;
    jQuery(document.body).trigger('checkout_error');
    expect(input.value).toBe(value);
  });

  test('Given two submissions of the same normalized document, When checkout fails, Then the original mask is retained', () => {
    bootstrap();
    const input = document.querySelector('.mp-document');
    const maskedValue = input.value;
    expect(submit()).toBe(true);
    expect(submit()).toBe(true);
    jQuery(document.body).trigger('checkout_error');
    expect(input.value).toBe(maskedValue);
  });

  test('Given one thousand unrelated inserted elements, When mutations are delivered, Then Ticket is not refreshed', async () => {
    const Controller = bootstrap('MLA');
    const refresh = jest.spyOn(Controller.prototype, 'refresh');
    const unrelated = document.createDocumentFragment();
    for (let index = 0; index < 1000; index += 1) {
      unrelated.append(document.createElement('span'));
    }
    document.body.append(unrelated);
    await flushMutations();
    expect(refresh).not.toHaveBeenCalled();
  });

  test('Given Ticket markup disappears, When Ticket is selected, Then incomplete data is not accepted or thrown', () => {
    bootstrap('MLA');
    document.querySelector('.mp-checkout-ticket-container').remove();
    expect(submit()).toBe(false);
    document.getElementById('payment_method_woo-mercado-pago-ticket').checked = false;
    expect(submit()).toBe(true);
  });

  test('Given a single hidden payment method, When it is empty or filled, Then only the filled method is accepted', () => {
    document.querySelector('[data-cy=input-table-list]').remove();
    const method = createElement('input', { type: 'hidden', name: 'mercadopago_ticket[payment_method_id]', value: '' });
    document.querySelector('.mp-checkout-ticket-container').append(method);
    bootstrap('MLA');
    expect(submit()).toBe(false);
    method.value = 'pagofacil';
    expect(submit()).toBe(true);
  });
});
