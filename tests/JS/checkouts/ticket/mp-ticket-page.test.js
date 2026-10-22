const { createElement } = require('../fixtures/payment-method-harness');
const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');

describe('Ticket payment-method error helpers', () => {
  let page;
  beforeEach(() => {
    document.body.replaceChildren(
      createElement('div', { class: 'mp-checkout-ticket-container' },
        '\n      ',
        createElement('div', { 'data-cy': 'input-table-list' }),
        '\n      ',
        createElement('input-helper', { 'input-id': 'mp-payment-method-helper' },
          createElement('div', { style: 'display:none' })
        ),
        '\n    '
      ),
      createElement('div', { 'data-cy': 'input-table-list', id: 'other-checkout' })
    );
    const elements = loadFile(resolveAlias('assets/js/checkouts/ticket/mp-ticket-elements.js'), 'CheckoutTicketElements');
    page = loadFile(resolveAlias('assets/js/checkouts/ticket/mp-ticket-page.js'), 'CheckoutTicketPage', { CheckoutTicketElements: elements });
  });
  afterEach(() => { document.body.replaceChildren(); });
  test('Given the real payment table, When error is set then cleared, Then only the Ticket target changes', () => {
    const table = document.querySelector('.mp-checkout-ticket-container [data-cy]');
    page.setDisplayOfError('fcInputTableContainer', 'add', 'mp-error');
    expect(table).toHaveClass('mp-error');
    expect(document.getElementById('other-checkout')).not.toHaveClass('mp-error');
    page.setDisplayOfError('fcInputTableContainer', 'remove', 'mp-error');
    expect(table).not.toHaveClass('mp-error');
  });
  test('Given the real payment helper, When feedback is shown then hidden, Then it follows the API', () => {
    const helper = document.querySelector('input-helper div');
    page.setDisplayOfInputHelper('mp-payment-method', 'flex');
    expect(helper.style.display).toBe('flex');
    page.setDisplayOfInputHelper('mp-payment-method', 'none');
    expect(helper.style.display).toBe('none');
  });
  test('Given optional targets are missing, When helper API executes, Then it does not throw', () => {
    document.querySelector('.mp-checkout-ticket-container [data-cy]').remove();
    document.querySelector('input-helper').remove();
    expect(() => page.setDisplayOfError('fcInputTableContainer', 'add', 'mp-error')).not.toThrow();
    expect(() => page.setDisplayOfInputHelper('mp-payment-method', 'flex')).not.toThrow();
  });
});
