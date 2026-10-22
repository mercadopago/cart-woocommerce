const { createAdminRuntime: createRuntime } = require('../helpers/admin-runtime');
const { element, fragment } = require('../fixtures/settings-markup');

const metabox = () =>
  fragment(
    '\n  ',
    element(
      'div',
      { id: 'mp_payment_status_sync' },
      element('div', { class: 'postbox-header' }, element('h2', {}, 'Status')),
      '\n    ',
      element(
        'div',
        { id: 'mp-payment-status-container' },
        element(
          'div',
          { id: 'mp-payment-status-content' },
          '\n      ',
          element(
            'button',
            { id: 'mp-sync-payment-status-button' },
            element('span', {}, 'Sync'),
            '\n        ',
            element('div', { class: 'mp-status-sync-metabox-small-loader', style: 'display:none' }),
            '\n      ',
          ),
          '\n    ',
        ),
      ),
      '\n  ',
    ),
    element('div', { id: 'woocommerce-order-data' }, 'Order data'),
  );

describe.each(['source', 'minified'])('Admin order status sync — real lifecycle and HTTP callbacks (%s)', assetVariant => {
  const createAdminRuntime = (markup, globals) => createRuntime(markup, globals, assetVariant);
  let runtime;
  beforeEach(() => {
    jest.useFakeTimers();
    runtime = createAdminRuntime(metabox(), {
      mp_payment_status_sync_params: { nonce: 'synthetic-order-nonce', order_id: 4242 },
    });
    runtime.load('order/payment-status-sync.js');
    runtime.dispatchLoad();
  });
  afterEach(() => runtime.cleanup());
  const click = () => document.getElementById('mp-sync-payment-status-button').click();

  it('moves the metabox after order data and styles the real title on load', () => {
    expect(document.getElementById('woocommerce-order-data').nextElementSibling.id).toBe('mp_payment_status_sync');
    expect(document.querySelector('#mp_payment_status_sync h2').style.fontSize).toBe('18px');
  });

  it('sends exactly one request while two clicks are still pending', () => {
    click();
    click();
    expect(runtime.requests).toHaveLength(1);
    expect(runtime.requests[0].payload).toEqual({ action: 'mp_sync_payment_status', nonce: 'synthetic-order-nonce', order_id: 4242 });
    expect(document.querySelector('#mp-sync-payment-status-button span').style.display).toBe('none');
    expect(document.querySelector('.mp-status-sync-metabox-small-loader').style.display).toBe('block');
  });

  it('shows a successful message as text and reloads after synchronization', () => {
    click();
    runtime.requests[0].deferred.resolve({ success: true, data: '<b>Synchronized</b>' });
    const alert = document.querySelector('.mp-alert');
    expect(alert).toHaveClass('mp-alert-success');
    expect(alert.textContent).toBe('<b>Synchronized</b>');
    expect(alert.querySelector('b')).toBeNull();
    expect(runtime.navigation.reload).toHaveBeenCalledTimes(1);
  });

  it.each(['business', 'http'])('restores the loader and permits retry after a %s failure', failure => {
    click();
    const pending = runtime.requests[0].deferred;
    if (failure === 'http') pending.reject({ responseJSON: { data: 'Unavailable' } });
    else pending.resolve({ success: false, data: 'Unavailable' });
    expect(document.querySelector('.mp-alert')).toHaveClass('mp-alert-danger');
    expect(document.querySelector('.mp-alert').textContent).toBe('Unavailable');
    expect(document.querySelector('#mp-sync-payment-status-button span').style.display).toBe('block');
    expect(document.querySelector('.mp-status-sync-metabox-small-loader').style.display).toBe('none');
    expect(runtime.navigation.reload).not.toHaveBeenCalled();
    jest.advanceTimersByTime(2999);
    expect(document.querySelector('.mp-alert')).not.toBeNull();
    jest.advanceTimersByTime(1);
    expect(document.querySelector('.mp-alert')).toBeNull();
    click();
    expect(runtime.requests).toHaveLength(2);
  });

  it('loads on unrelated screens without an order metabox or sync button', () => {
    document.body.replaceChildren(element("div", { id: "unrelated" }, "Other screen"));
    expect(() => runtime.dispatchLoad()).not.toThrow();
    expect(runtime.eventErrors).toEqual([]);
    expect(runtime.requests).toHaveLength(0);
  });

  it('still sends the request if optional loader children are absent', () => {
    document.querySelector('#mp-sync-payment-status-button span').remove();
    click();
    expect(runtime.requests).toHaveLength(1);
    runtime.requests[0].deferred.resolve({ success: false, data: 'Rejected' });
    expect(document.querySelector('.mp-alert').textContent).toBe('Rejected');
  });
});
