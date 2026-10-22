const { createAdminRuntime: createRuntime } = require('./helpers/admin-runtime');
const { element } = require('./fixtures/settings-markup');

describe.each(['source', 'minified'])('Admin notices — real delegated handlers (%s)', assetVariant => {
  const createAdminRuntime = (markup, globals) => createRuntime(markup, globals, assetVariant);
  let runtime;
  let clearInfo;

  beforeEach(() => {
    jest.useFakeTimers();
    runtime = createAdminRuntime(element("div", { id: "message-feedback-info" }, "Saved"), {
      woocommerce_mercadopago_admin_notice_js_params: { nonce: 'synthetic-notice-nonce' },
    });
    clearInfo = runtime.load('mp-admin-notices.js', 'clearInfo');
    runtime.dispatchReady();
  });

  afterEach(() => runtime.cleanup());

  it.each([
    [{ class: 'mp-rating-notice' }, '.mp-rating-notice button', 'mp_review_notice_dismiss'],
    [{ id: 'saved-cards-notice' }, '#saved-cards-notice button', 'mp_saved_cards_notice_dismiss'],
  ])('delegates a newly inserted notice click to %s', (attributes, selector, action) => {
    document.body.appendChild(element("div", attributes, element("button", {}, "Dismiss")));
    document.querySelector(selector).click();
    expect(runtime.requests).toHaveLength(1);
    expect(runtime.requests[0].url).toBe('/wp-admin/admin-ajax.php');
    expect(runtime.requests[0].payload).toEqual({ action, nonce: 'synthetic-notice-nonce' });
  });

  it('does not dismiss anything when an unrelated element is clicked', () => {
    document.body.click();
    expect(runtime.requests).toHaveLength(0);
  });

  it('retains feedback for ten seconds then removes the real element', () => {
    clearInfo();
    jest.advanceTimersByTime(9999);
    expect(document.getElementById('message-feedback-info')).not.toBeNull();
    jest.advanceTimersByTime(1);
    expect(document.getElementById('message-feedback-info')).toBeNull();
  });
});
