const { createElement } = require('../fixtures/payment-method-harness');
const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');

describe('PSE page helper API', () => {
  let page;
  beforeEach(() => {
    document.body.replaceChildren(
      createElement('div', { class: 'mp-checkout-pse-container' },
        '\n      ',
        createElement('input-helper', { 'input-id': 'mp-doc-number-helper' },
          createElement('div', { style: 'display:none' })
        ),
        '\n    '
      )
    );
    const elements = loadFile(resolveAlias('assets/js/checkouts/pse/mp-pse-elements.js'), 'CheckoutPseElements');
    page = loadFile(resolveAlias('assets/js/checkouts/pse/mp-pse-page.js'), 'CheckoutPsePage', { CheckoutPseElements: elements });
  });
  afterEach(() => { document.body.replaceChildren(); });
  test('Given the document helper, When the API shows then hides it, Then its actual DOM visibility changes', () => {
    const helper = document.querySelector('input-helper div');
    page.setDisplayOfInputHelper('mp-doc-number', 'flex');
    expect(helper.style.display).toBe('flex');
    page.setDisplayOfInputHelper('mp-doc-number', 'none');
    expect(helper.style.display).toBe('none');
  });
  test('Given optional targets are absent, When the helper API is called, Then it is a no-op', () => {
    expect(() => page.setDisplayOfInputHelper('not-rendered', 'flex')).not.toThrow();
    expect(() => page.setDisplayOfError('not-rendered', 'add', 'mp-error')).not.toThrow();
    expect(document.querySelector('.mp-checkout-pse-container').className).toBe('mp-checkout-pse-container');
  });
});
