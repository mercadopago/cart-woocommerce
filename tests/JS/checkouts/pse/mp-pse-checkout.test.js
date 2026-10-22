const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');
const { createElement, createJQueryHarness } = require('../fixtures/payment-method-harness');
const elementsPath = resolveAlias('assets/js/checkouts/pse/mp-pse-elements.js');
const source = resolveAlias('assets/js/checkouts/pse/mp-pse-checkout.js');
const event = 'checkout_place_order_woo-mercado-pago-pse';

describe('PSE checkout submit gate', () => {
  let events;
  function submit(target = 'form.checkout', name = event) { return events.emit(target, name)[0]; }
  beforeEach(() => {
    document.body.replaceChildren(
      createElement('form', { class: 'checkout' },
        createElement('input', { id: 'payment_method_woo-mercado-pago-pse', type: 'radio', checked: '' }),
        '\n      ',
        createElement('div', { class: 'mp-checkout-pse-container' },
          '\n        ',
          createElement('div', { class: 'mp-input-document' },
            createElement('div', { class: 'mp-input' },
              createElement('input', { class: 'mp-document', value: '123456789' })
            ),
            '\n          ',
            createElement('input-helper', {}, createElement('div', { style: 'display:none' }))
          ),
          '\n        ',
          createElement('div', { class: 'mp-checkout-pse-bank' },
            createElement('div', {},
              createElement('select', { id: 'mercadopago_pse[bank]' },
                '\n          ',
                createElement('option', { value: '' }, 'Select a bank'),
                createElement('option', { value: 'Choose' }, 'Choose'),
                createElement('option', { value: '1001', selected: '' }, 'Bank'),
                '\n        '
              )
            ),
            createElement('input-helper', {}, createElement('div', { style: 'display:none' }))
          ),
          '\n      '
        )
      ),
      createElement('form', { id: 'order_review' })
    );
    events = createJQueryHarness();
    const elements = loadFile(elementsPath, 'CheckoutPseElements');
    loadFile(source, 'undefined', {
      jQuery: events.jQuery, CheckoutPseElements: elements,
      wc_mercadopago_pse_checkout_params: { financial_placeholder: 'Choose' },
    });
    events.runReady();
  });
  afterEach(() => { document.body.replaceChildren(); jest.restoreAllMocks(); });

  test.each([['form.checkout', event], ['form#order_review', 'submit']])('Given valid PSE, When the callback registered on %s executes, Then it returns true', (target, name) => {
    expect(events.callbacks(target, name)).toHaveLength(1);
    expect(submit(target, name)).toBe(true);
  });
  test('Given another payment method, When the gate is called, Then PSE fields do not block checkout', () => {
    document.getElementById('payment_method_woo-mercado-pago-pse').checked = false;
    document.querySelector('.mp-checkout-pse-container').remove();
    expect(submit()).toBe(true);
  });
  test('Given an empty document, When the registered gate executes, Then the document feedback is visible and the result is false', () => {
    document.querySelector('.mp-document').value = '';
    expect(submit()).toBe(false);
    expect(document.querySelector('.mp-input')).toHaveClass('mp-error');
    expect(document.querySelector('.mp-input-document input-helper div')).toHaveStyle({ display: 'flex' });
  });
  test.each(['', 'Choose'])('Given invalid bank %s, When submit then bank change occur, Then the gate blocks and its error clears', value => {
    const bank = document.getElementById('mercadopago_pse[bank]');
    bank.value = value;
    expect(submit()).toBe(false);
    expect(bank.parentElement).toHaveClass('mp-error');
    expect(document.querySelector('.mp-checkout-pse-bank input-helper div')).toHaveStyle({ display: 'flex' });
    bank.value = '1001';
    bank.dispatchEvent(new Event('change', { bubbles: true }));
    expect(bank.parentElement).not.toHaveClass('mp-error');
    expect(document.querySelector('.mp-checkout-pse-bank input-helper div')).toHaveStyle({ display: 'none' });
    expect(submit()).toBe(true);
  });
  test('Given a document helper already displays a component error, When values are non-empty, Then the gate still blocks', () => {
    document.querySelector('.mp-input-document input-helper div').style.display = 'flex';
    expect(submit()).toBe(false);
  });
});
