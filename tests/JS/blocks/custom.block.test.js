/**
 * The Custom Blocks component runs with the real React renderer and real
 * CheckoutPage gates. WooCommerce lifecycle/transport and SDK calls are the
 * external boundaries; the disabled fieldset is native DOM, not a focus stub.
 */
const path = require('path');
const React = require('react');
const { createRoot } = require('react-dom/client');
const { act } = require('react');
const { loadFile } = require('../helpers/load-file');

const PAYMENT_METHOD_NAME = 'woo-mercado-pago-custom';
const FIELD_ID = 'form-checkout__cardholderName';
const CUSTOM_PATH = path.resolve(__dirname, '../../../assets/js/checkouts/custom');
const mockRegisterPaymentMethod = jest.fn();
const mockExtensionCartUpdate = jest.fn();
const mockHasValidationErrors = jest.fn(() => false);
const mockSettings = {
  title: 'Card',
  supports: ['products'],
  params: {
    icon: 'card.svg',
    currencyRatio: 1,
    content: `
      <div class="mp-checkout-custom-container">
        <div id="form-checkout__cardNumber-container"></div>
        <div id="mp-card-holder-div" style="margin: 7px !important">
          <label class="mp-input-label" for="${FIELD_ID}">Cardholder</label>
          <input id="${FIELD_ID}" aria-invalid="false"
            aria-describedby="mp-card-holder-name-helper-info mp-card-holder-name-example" />
          <span id="mp-card-holder-name-helper-info">Name on the card</span>
          <span id="mp-card-holder-name-helper" style="display: none">Enter the cardholder name</span>
          <span id="mp-card-holder-name-example">Example</span>
        </div>
        <div id="mp-doc-div" style="display: none">
          <div id="form-checkout__identificationNumber-container">
            <input type="hidden" id="form-checkout__identificationNumber" />
          </div>
        </div>
        <select id="form-checkout__installments"><option value="1">1</option></select>
        <div id="mercadopago-utilities">
          <input type="hidden" id="mp_checkout_type" value="custom" />
          <input type="hidden" id="cardTokenId" name="cardTokenId" />
          <input type="hidden" id="cardInstallments" name="cardInstallments" value="1" />
        </div>
      </div>
    `,
  },
};

jest.mock('@wordpress/element', () => require('react'), { virtual: true });
jest.mock('@wordpress/html-entities', () => ({ decodeEntities: (value) => value }), { virtual: true });
jest.mock('@woocommerce/blocks-registry', () => ({
  registerPaymentMethod: (method) => mockRegisterPaymentMethod(method),
}), { virtual: true });
jest.mock('@woocommerce/blocks-checkout', () => ({
  extensionCartUpdate: (...args) => mockExtensionCartUpdate(...args),
}), { virtual: true });
jest.mock('@woocommerce/settings', () => ({
  getSetting: () => mockSettings,
}), { virtual: true });
jest.mock('@woocommerce/block-data', () => ({ VALIDATION_STORE_KEY: 'validation' }), { virtual: true });
jest.mock('@wordpress/data', () => ({
  select: () => ({ hasValidationErrors: mockHasValidationErrors }),
}), { virtual: true });

let paymentMethod;
let view;

beforeAll(() => {
  global.React = React;
  global.IS_REACT_ACT_ENVIRONMENT = true;
  require('../../../assets/js/blocks/custom.block.js');
  paymentMethod = mockRegisterPaymentMethod.mock.calls[0][0];
});

afterAll(() => {
  delete global.React;
  delete global.IS_REACT_ACT_ENVIRONMENT;
});

beforeEach(() => {
  jest.useFakeTimers();
  mockExtensionCartUpdate.mockReset().mockResolvedValue({ totals: { total_price: 1234 } });
  window.sendMetric = jest.fn();
  const elements = loadFile(path.join(CUSTOM_PATH, 'mp-custom-elements.js'), 'CheckoutElements', {});
  global.CheckoutPage = loadFile(path.join(CUSTOM_PATH, 'mp-custom-page.js'), 'CheckoutPage', {
    CheckoutElements: elements,
    wc_mercadopago_custom_checkout_params: { site_id: 'MLB', input_helper_message: {} },
    wc_mercadopago_custom_page_params: {},
    sendMetric: window.sendMetric,
  });
  window.mpCustomCheckoutHandler = {
    cardForm: {
      formMounted: false,
      form: { createCardToken: jest.fn().mockResolvedValue({ token: 'test-token' }) },
      initCardForm: jest.fn().mockResolvedValue(undefined),
      createLoadSpinner: jest.fn(),
      removeLoadSpinner: jest.fn(),
      removeBlockOverlay: jest.fn(),
      scrollToCardForm: jest.fn(),
      getCardValidationReason: jest.fn(() => 'invalid_length'),
      getCardValidationDetail: jest.fn(() => ''),
    },
  };
  window.mpSuperTokenTriggerHandler = { loadSuperToken: jest.fn().mockResolvedValue(undefined) };
  window.mpHiddenInputDataFromBlocksCheckout = { external: 'preserved' };
  window.jQuery = () => ({ prop: jest.fn() });
  Element.prototype.scrollIntoView = jest.fn();
});

afterEach(async () => {
  if (view) {
    await view.unmount();
    view = null;
  }
  jest.clearAllTimers();
  jest.useRealTimers();
  jest.restoreAllMocks();
  delete global.CheckoutPage;
  delete window.sendMetric;
  delete window.mpCustomCheckoutHandler;
  delete window.mpSuperTokenTriggerHandler;
  delete window.mpSuperTokenPaymentMethods;
  delete window.mpSuperTokenAuthenticator;
  delete window.mpSuperTokenMetrics;
  delete window.mpSuperTokenFinalizeBlocks;
  delete window.mpHiddenInputDataFromBlocksCheckout;
  delete window.jQuery;
  delete Element.prototype.scrollIntoView;
  document.body.innerHTML = '';
});

async function renderCustom() {
  const container = document.createElement('div');
  document.body.appendChild(container);
  const root = createRoot(container);
  let setup;
  let props = {
    activePaymentMethod: PAYMENT_METHOD_NAME,
    checkoutStatus: { isIdle: true },
    billing: { cartTotal: { value: 1234 }, currency: { minorUnit: 2 } },
    eventRegistration: {
      onPaymentSetup: jest.fn((callback) => { setup = callback; return jest.fn(); }),
      onCheckoutSuccess: jest.fn(() => jest.fn()),
      onCheckoutFail: jest.fn(() => jest.fn()),
    },
    emitResponse: {
      responseTypes: { SUCCESS: 'success', ERROR: 'error', FAIL: 'fail' },
      noticeContexts: { CHECKOUT: 'checkout', PAYMENTS: 'payments' },
    },
    onSubmit: jest.fn(),
  };
  const render = async (changes = {}) => {
    props = { ...props, ...changes };
    await act(async () => root.render(React.createElement(
      'fieldset',
      { disabled: !props.checkoutStatus.isIdle },
      React.cloneElement(paymentMethod.content, props)
    )));
  };

  await render();
  const outside = document.createElement('button');
  outside.textContent = 'Continue';
  outside.style.setProperty('color', 'purple', 'important');
  outside.setAttribute('aria-label', 'External checkout control');
  document.body.appendChild(outside);

  const rendered = {
    container,
    outside,
    render,
    field: () => container.querySelector(`#${FIELD_ID}`),
    fieldset: () => container.querySelector('fieldset'),
    setup: async () => {
      let result;
      await act(async () => { result = await setup(); });
      return result;
    },
    processing: () => render({ checkoutStatus: { isIdle: false } }),
    idle: () => render({ checkoutStatus: { isIdle: true } }),
    unmount: async () => {
      await act(async () => root.unmount());
      container.remove();
    },
  };
  return rendered;
}

async function blockOnEmptyCardholder() {
  await view.processing();
  view.outside.focus();
  const result = await view.setup();
  expect(result).toEqual({ type: 'error' });
  expect(view.field().matches(':disabled')).toBe(true);
  expect(view.field().getAttribute('aria-invalid')).toBe('true');
  expect(window.mpCustomCheckoutHandler.cardForm.form.createCardToken).not.toHaveBeenCalled();
}

describe('Custom Blocks cardholder focus after a setup validation error', () => {
  it('focuses the invalid field only after Blocks enables the payment fieldset, once', async () => {
    view = await renderCustom();
    const input = view.field();
    const focus = jest.spyOn(input, 'focus');
    await blockOnEmptyCardholder();
    expect(document.activeElement).toBe(view.outside);

    await view.idle();

    expect(input.matches(':disabled')).toBe(false);
    expect(document.activeElement).toBe(input);
    expect(focus).toHaveBeenLastCalledWith({ preventScroll: true });
    expect(input.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById('mp-card-holder-name-helper').style.display).toBe('flex');
    expect(input.parentElement.style.getPropertyValue('margin')).toBe('7px');
    expect(input.parentElement.style.getPropertyPriority('margin')).toBe('important');
    expect(view.outside.style.getPropertyPriority('color')).toBe('important');
    expect(view.outside.getAttribute('aria-label')).toBe('External checkout control');

    const focusedCalls = focus.mock.calls.length;
    view.outside.focus();
    await view.processing();
    await view.idle();
    expect(focus).toHaveBeenCalledTimes(focusedCalls);
    expect(document.activeElement).toBe(view.outside);
  });

  it('discards the pending focus when the name was filled before checkout becomes idle', async () => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    view.field().value = 'María Peña';

    await view.idle();
    expect(document.activeElement).toBe(view.outside);
    view.field().value = '';
    await view.processing();
    await view.idle();
    expect(document.activeElement).toBe(view.outside);
  });

  it('does not queue a second focus when the shared gate could already focus the enabled input', async () => {
    view = await renderCustom();
    await view.processing();
    view.fieldset().disabled = false;

    expect(await view.setup()).toEqual({ type: 'error' });
    expect(document.activeElement).toBe(view.field());
    view.outside.focus();
    await view.idle();

    expect(document.activeElement).toBe(view.outside);
  });

  it('preserves focus the buyer moved to another control before checkout becomes idle', async () => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    const otherControl = document.createElement('input');
    document.body.appendChild(otherControl);
    otherControl.focus();

    await view.idle();

    expect(document.activeElement).toBe(otherControl);
  });

  it('still restores focus when Blocks leaves focus on the checkout submit button', async () => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    const submit = document.createElement('button');
    submit.className = 'wc-block-components-checkout-place-order-button';
    document.body.appendChild(submit);
    submit.focus();

    await view.idle();

    expect(document.activeElement).toBe(view.field());
  });

  it('discards the pending focus when another payment gateway is selected', async () => {
    view = await renderCustom();
    await blockOnEmptyCardholder();

    await view.render({ activePaymentMethod: 'woo-mercado-pago-pix' });
    await view.render({ activePaymentMethod: PAYMENT_METHOD_NAME, checkoutStatus: { isIdle: true } });

    expect(document.activeElement).toBe(view.outside);
  });

  it.each(['super_token', 'wallet_button'])('does not restore Custom focus after switching to %s', async (type) => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    document.getElementById('mp_checkout_type').value = type;

    await view.idle();

    expect(document.activeElement).toBe(view.outside);
  });

  it('ignores an input removed before the idle commit', async () => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    view.field().remove();

    await view.idle();

    expect(document.activeElement).toBe(view.outside);
  });

  it.each([
    ['display', 'none'],
    ['visibility', 'hidden'],
  ])('does not focus a field hidden by an ancestor with %s:%s', async (property, value) => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    const parent = view.field().parentElement;
    parent.style.setProperty(property, value, 'important');

    await view.idle();

    expect(document.activeElement).toBe(view.outside);
    expect(parent.style.getPropertyValue(property)).toBe(value);
    expect(parent.style.getPropertyPriority(property)).toBe('important');
  });

  it('does not override a disabled control after the fieldset is enabled', async () => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    view.field().disabled = true;

    await view.idle();

    expect(document.activeElement).toBe(view.outside);
    expect(view.field().disabled).toBe(true);
  });

  it('does not throw when the page helper disappears before the idle commit', async () => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    delete global.CheckoutPage.isHiddenByStyle;

    await view.idle();

    expect(document.activeElement).toBe(view.outside);
  });

  it('finds the current input inside this component rather than an outside duplicate', async () => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    const outsideDuplicate = document.createElement('input');
    outsideDuplicate.id = FIELD_ID;
    document.body.prepend(outsideDuplicate);

    await view.idle();

    expect(document.activeElement).toBe(view.field());
    expect(document.activeElement).not.toBe(outsideDuplicate);
  });

  it('drops the pending focus when the component is unmounted', async () => {
    view = await renderCustom();
    await blockOnEmptyCardholder();
    const outside = view.outside;
    await view.unmount();
    view = null;

    view = await renderCustom();
    outside.focus();
    await view.processing();
    await view.idle();

    expect(document.activeElement).toBe(outside);
  });

  it('does not queue cardholder focus when the real card gate blocks first', async () => {
    view = await renderCustom();
    document.getElementById('form-checkout__cardNumber-container').classList.add('mp-error');
    await view.processing();
    view.outside.focus();

    expect(await view.setup()).toEqual({ type: 'error' });
    await view.idle();

    expect(document.activeElement).toBe(view.outside);
    expect(window.mpCustomCheckoutHandler.cardForm.form.createCardToken).not.toHaveBeenCalled();
  });

  it('preserves the successful tokenization path after a filled name passes the real gate', async () => {
    view = await renderCustom();
    view.field().value = "Maria Sant'Anna-Souza";
    await view.processing();
    view.outside.focus();

    const response = await view.setup();
    await view.idle();

    expect(response.type).toBe('success');
    expect(response.meta.paymentMethodData.external).toBe('preserved');
    expect(document.getElementById('cardTokenId').value).toBe('test-token');
    expect(window.mpCustomCheckoutHandler.cardForm.form.createCardToken).toHaveBeenCalledTimes(1);
    expect(document.activeElement).toBe(view.outside);
  });

  it('keeps a Super Token setup error independent of the Custom gate and focus', async () => {
    view = await renderCustom();
    document.getElementById('mp_checkout_type').value = 'super_token';
    window.mpSuperTokenPaymentMethods = { hideSuperTokenError: jest.fn() };
    window.mpSuperTokenAuthenticator = {};
    window.mpSuperTokenMetrics = {};
    window.mpSuperTokenFinalizeBlocks = jest.fn().mockResolvedValue({ type: 'error' });
    const gate = jest.spyOn(global.CheckoutPage, 'runPreSubmitGates');
    await view.processing();
    view.outside.focus();

    expect(await view.setup()).toEqual({ type: 'error' });
    await view.idle();

    expect(gate).not.toHaveBeenCalled();
    expect(window.mpCustomCheckoutHandler.cardForm.form.createCardToken).not.toHaveBeenCalled();
    expect(document.activeElement).toBe(view.outside);
  });

  it('keeps Wallet Button setup independent of the Custom gate and focus', async () => {
    view = await renderCustom();
    document.getElementById('mp_checkout_type').value = 'wallet_button';
    const gate = jest.spyOn(global.CheckoutPage, 'runPreSubmitGates');
    await view.processing();
    view.outside.focus();

    expect((await view.setup()).type).toBe('success');
    await view.idle();

    expect(gate).not.toHaveBeenCalled();
    expect(window.mpCustomCheckoutHandler.cardForm.form.createCardToken).not.toHaveBeenCalled();
    expect(document.activeElement).toBe(view.outside);
  });
});
