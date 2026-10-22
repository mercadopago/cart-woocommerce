const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');
const { createElement, createJQueryHarness, flushPromises } = require('../fixtures/payment-method-harness');
const source = resolveAlias('assets/js/checkouts/yape/mp-yape-checkout.js');
const event = 'checkout_place_order_woo-mercado-pago-yape';

describe('Yape checkout token and layout', () => {
  let api;
  let events;
  let create;
  let sdk;
  let MercadoPago;
  let observers;
  let phoneValidate;
  let codeValidate;
  let originalObserver;
  beforeEach(() => {
    document.body.replaceChildren(
      createElement('form', { class: 'checkout' },
        createElement('input', { id: 'payment_method_woo-mercado-pago-yape', type: 'radio', checked: '' }),
        '\n      ',
        createElement('div', { class: 'payment_method_woo-mercado-pago-yape' },
          createElement('input-field', {},
            createElement('input', { id: 'checkout__yapePhoneNumber', value: '111 111 111' })
          ),
          '\n        ',
          createElement('input-code', {},
            createElement('div', { class: 'mp-yape-input-container', style: 'column-gap:8px' },
              ...'123456'.split('').map(value => createElement('input', { class: 'mp-yape-code-input', value }))
            )
          ),
          '\n        ',
          createElement('input-helper', {}, createElement('div', { style: 'display:none' }))
        ),
        createElement('input', { id: 'yapeToken', value: '' })
      )
    );
    phoneValidate = jest.fn(); codeValidate = jest.fn();
    document.querySelector('input-field').validate = phoneValidate;
    document.querySelector('input-code').validate = codeValidate;
    jest.spyOn(document.getElementById('checkout__yapePhoneNumber'), 'getBoundingClientRect').mockReturnValue({ width: 300 });
    create = jest.fn().mockResolvedValue({ id: 'synthetic-yape-token' });
    sdk = { yape: jest.fn().mockReturnValue({ create }) };
    MercadoPago = jest.fn(function () { return sdk; });
    observers = [];
    originalObserver = window.ResizeObserver;
    const Observer = jest.fn(function (callback) {
      this.observe = jest.fn(); this.disconnect = jest.fn(); this.callback = callback;
      observers.push(this);
    });
    window.ResizeObserver = Observer;
    events = createJQueryHarness();
    api = loadFile(source, '({ mercadoPagoFormHandlerYape, isFormValid, getForm, updateYapeCodeLayout, setupYapeCodeLayout })', {
      jQuery: events.jQuery, MercadoPago, ResizeObserver: Observer,
      wc_mercadopago_yape_checkout_params: { public_key: 'synthetic-public-key' },
    });
  });
  afterEach(() => {
    delete window.mpSdkInstance; delete window.callSdkWithMetrics;
    if (originalObserver === undefined) delete window.ResizeObserver;
    else window.ResizeObserver = originalObserver;
    jest.restoreAllMocks(); document.body.replaceChildren();
  });
  const helper = () => document.querySelector('input-helper div');

  test('Given valid values, When the real handler tokenizes, Then it passes unspaced phone and ordered OTP and reuses the SDK', async () => {
    expect(await api.mercadoPagoFormHandlerYape()).toBe(true);
    expect(MercadoPago).toHaveBeenCalledWith('synthetic-public-key');
    expect(sdk.yape).toHaveBeenCalledWith({ otp: '123456', phoneNumber: '111111111' });
    expect(document.getElementById('yapeToken').value).toBe('synthetic-yape-token');
    expect(phoneValidate).toHaveBeenCalledTimes(1); expect(codeValidate).toHaveBeenCalledTimes(1);
    await api.mercadoPagoFormHandlerYape();
    expect(MercadoPago).toHaveBeenCalledTimes(1);
  });
  test('Given the metrics wrapper is present, When tokenization occurs, Then the actual SDK call runs through that boundary', async () => {
    window.callSdkWithMetrics = jest.fn(fn => fn());
    expect(await api.mercadoPagoFormHandlerYape()).toBe(true);
    expect(window.callSdkWithMetrics).toHaveBeenCalledWith(expect.any(Function), 'yape.create');
    expect(create).toHaveBeenCalledTimes(1);
  });
  test('Given another method is selected, When Yape fields are absent, Then its handler returns true without SDK work', async () => {
    document.getElementById('payment_method_woo-mercado-pago-yape').checked = false;
    document.querySelector('.payment_method_woo-mercado-pago-yape').remove();
    expect(await api.mercadoPagoFormHandlerYape()).toBe(true);
    expect(MercadoPago).not.toHaveBeenCalled();
  });
  test('Given an invalid component helper, When the registered Classic callback executes, Then it returns false and never signals resubmit', async () => {
    helper().style.display = 'flex';
    const trigger = jest.fn();
    expect(events.callbacks('form.checkout', event)).toHaveLength(1);
    expect(events.emit('form.checkout', event, new Event(event), { $checkout_form: { trigger } })[0]).toBe(false);
    await flushPromises();
    expect(phoneValidate).toHaveBeenCalled(); expect(codeValidate).toHaveBeenCalled();
    expect(create).not.toHaveBeenCalled(); expect(trigger).not.toHaveBeenCalled();
  });
  test('Given SDK rejection, When the registered Classic callback runs, Then it does not write a token or signal resubmit', async () => {
    create.mockRejectedValueOnce(new Error('synthetic SDK failure'));
    const trigger = jest.fn();
    expect(events.emit('form.checkout', event, {}, { $checkout_form: { trigger } })[0]).toBe(false);
    await flushPromises();
    expect(trigger).not.toHaveBeenCalled();
    expect(document.getElementById('yapeToken').value).toBe('');
    expect(events.jQuery('#yapeToken').data('fresh')).toBeUndefined();
  });
  test('Given valid Classic Yape, When its two callbacks run around token resolution, Then the first blocks and the fresh second permits without retokenizing', async () => {
    const trigger = jest.fn();
    const woo = { $checkout_form: { trigger } };
    expect(events.emit('form.checkout', event, {}, woo)[0]).toBe(false);
    expect(trigger).not.toHaveBeenCalled();
    await flushPromises();
    expect(document.getElementById('yapeToken').value).toBe('synthetic-yape-token');
    expect(trigger).toHaveBeenCalledTimes(1);
    expect(trigger).toHaveBeenCalledWith('submit');
    expect(events.jQuery('#yapeToken').data('fresh')).toBe('true');
    expect(events.emit('form.checkout', event, {}, woo)[0]).toBe(true);
    expect(events.jQuery('#yapeToken').data('fresh')).toBe(null);
    expect(create).toHaveBeenCalledTimes(1);
  });
  test('Given a valid Order Pay form, When its real callback executes, Then it explicitly prevents the initial event and submits after the token resolves', async () => {
    const form = document.querySelector('form');
    form.className = ''; form.id = 'order_review';
    const nativeSubmit = jest.spyOn(form, 'submit').mockImplementation(() => {});
    const submitEvent = new Event('submit', { cancelable: true });
    const callback = events.callbacks('form#order_review', 'submit')[0];
    const pending = callback.call(form, submitEvent);
    expect(submitEvent.defaultPrevented).toBe(true);
    expect(nativeSubmit).not.toHaveBeenCalled();
    await pending;
    expect(document.getElementById('yapeToken').value).toBe('synthetic-yape-token');
    expect(nativeSubmit).toHaveBeenCalledTimes(1);
  });
  test.each(['missing form', 'missing Yape container'])('Given %s, When validation executes, Then it rejects without calling components', missing => {
    if (missing === 'missing form') document.querySelector('form').remove();
    else document.querySelector('.payment_method_woo-mercado-pago-yape').remove();
    expect(api.isFormValid()).toBe(false);
    expect(phoneValidate).not.toHaveBeenCalled(); expect(codeValidate).not.toHaveBeenCalled();
  });
  test.each([[267, 'mp-yape-code-columns-3'], [268, 'mp-yape-code-columns-6']])('Given width %s and gap8, When layout updates, Then the container has %s', (width, className) => {
    document.getElementById('checkout__yapePhoneNumber').getBoundingClientRect.mockReturnValue({ width });
    api.updateYapeCodeLayout();
    const container = document.querySelector('.mp-yape-input-container');
    expect(container).toHaveClass(className);
    expect(container.classList.length).toBe(2);
  });
  test('Given ready then updated_checkout, When observers are replaced, Then the prior one disconnects and the new callback recalculates', () => {
    events.runReady();
    const phone = document.getElementById('checkout__yapePhoneNumber');
    expect(observers[0].observe).toHaveBeenCalledWith(phone);
    events.emit(document.body, 'updated_checkout');
    expect(observers[0].disconnect).toHaveBeenCalledTimes(1);
    expect(observers[1].observe).toHaveBeenCalledWith(phone);
    phone.getBoundingClientRect.mockReturnValue({ width: 150 });
    observers[1].callback();
    expect(document.querySelector('.mp-yape-input-container')).toHaveClass('mp-yape-code-columns-3');
  });
  test('Given ResizeObserver is unavailable, When ready fires, Then layout still computes without observation', () => {
    delete window.ResizeObserver;
    events.runReady();
    expect(observers).toHaveLength(0);
    expect(document.querySelector('.mp-yape-input-container')).toHaveClass('mp-yape-code-columns-6');
  });
  test.each(['phone', 'container'])('Given missing %s, When layout runs, Then optional DOM is tolerated', missing => {
    document.querySelector(missing === 'phone' ? '#checkout__yapePhoneNumber' : '.mp-yape-input-container').remove();
    expect(() => api.updateYapeCodeLayout()).not.toThrow();
  });
});
