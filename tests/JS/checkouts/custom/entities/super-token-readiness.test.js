const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const { ClassicCheckout } = require('@super-token/adapters/checkout/ClassicCheckout');
const { FinalizeSuperTokenPayment } = require('@super-token/useCases/FinalizeSuperTokenPayment');
const { AuthorizePayment } = require('@super-token/useCases/AuthorizePayment');
const { MPSuperTokenErrorCodes } = require('@super-token/core/checkoutSession/ErrorClassification');
require('assets/js/checkouts/custom/entities/event-handler.js');

const deferred = () => {
  let resolve;
  let reject;
  const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
};
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

// The jQuery boundary reads successful controls from the real DOM and records only
// transport effects. Production guards, finalizers, authorization sequencing and
// native selection listeners are not replaced.
describe.each(['event-handler.js', 'event-handler.min.js'])('Super Token readiness (%s)', (asset) => {
  describe.each(['refactored', 'legacy'])('%s finalization', (mode) => {
    let handler;
    let form;
    let method;
    let paymentMethods;
    let authenticator;
    let triggerHandler;
    let metrics;
    let session;
    let sdkAuthorize;
    let cardForm;
    let submit;
    let post;
    let unblock;
    let checkout;
    let originalScroll;
    let nativeListeners;
    const input = (name) => form.elements.namedItem(`mercadopago_custom[${name}]`);
    const event = () => ({ preventDefault: jest.fn() });

    const addInput = (name, value) => {
      const element = document.createElement('input');
      element.type = 'hidden';
      element.name = `mercadopago_custom[${name}]`;
      element.value = value;
      form.append(element);
      return element;
    };

    beforeEach(() => {
      document.body.replaceChildren();
      document.body.className = '';
      originalScroll = Element.prototype.scrollIntoView;
      Element.prototype.scrollIntoView = jest.fn();
      nativeListeners = jest.spyOn(document, 'addEventListener');
      form = document.createElement('form');
      form.id = 'checkout';
      form.className = 'checkout';
      document.body.append(form);
      Object.entries({
        checkout_type: 'super_token', token: '', amount: '10.00',
        payment_method_id: 'visa', payment_type_id: 'credit_card', installments: '1',
        authorized_pseudotoken: 'fixture-method-a', super_token_validation: 'true',
      }).forEach(([name, value]) => addInput(name, value));
      const radio = addInput('unused', 'woo-mercado-pago-custom');
      radio.type = 'radio';
      radio.id = 'payment_method_woo-mercado-pago-custom';
      radio.name = 'payment_method';
      radio.checked = true;
      method = { token: 'fixture-method-a', id: 'visa', type: 'credit_card' };
      metrics = {
        registerClickOnPlaceOrderButton: jest.fn(), sendMetric: jest.fn(), errorToAuthorizePayment: jest.fn(),
      };
      sdkAuthorize = jest.fn().mockResolvedValue(undefined);
      session = {
        getStoredAuthenticator: () => ({}), getSimplifiedAuth: jest.fn().mockResolvedValue(true),
        authorizePaymentOnSdk: sdkAuthorize,
        storeAuthorizedPseudotoken: (token) => { input('authorized_pseudotoken').value = token; },
      };
      authenticator = {
        authorizePayment: jest.fn((pseudotoken) => new AuthorizePayment().execute({ session, metrics, pseudotoken })),
        setSuperTokenValidation: jest.fn((value) => { input('super_token_validation').value = String(value); }),
      };
      paymentMethods = {
        getActivePaymentMethod: () => method, isSelectedPaymentMethodValid: () => true,
        validateInstallmentSelection: jest.fn().mockReturnValue(true),
        updateSecurityCode: jest.fn().mockResolvedValue(undefined), unmountCardForm: jest.fn(),
        hideSuperTokenError: jest.fn(),
      };
      triggerHandler = {
        resetSuperTokenOnError: jest.fn(), setLastException: jest.fn(),
        isSuperTokenPaymentMethodsLoaded: () => false, loadSuperToken: jest.fn().mockResolvedValue(),
      };
      cardForm = {
        form: { createCardToken: jest.fn().mockResolvedValue({ token: 'fixture-new-card-token' }), unmount: jest.fn() },
        removeLoadSpinner: jest.fn(), removeBlockOverlay: jest.fn(), createLoadSpinner: jest.fn(),
        initCardForm: jest.fn(), formMounted: false,
        getAmount: () => '10.00', amount: '10.00',
      };
      post = jest.fn(() => ({ done: () => ({ error: jest.fn() }) }));
      unblock = jest.fn();
      submit = jest.fn(() => {
        if (handler.mercadoPagoFormHandler(event(), checkout) === true) post();
      });
      const jq = (selector) => {
        const elements = typeof selector === 'string'
          ? Array.from(document.querySelectorAll(selector)) : [selector];
        return Object.assign(elements, {
          serializeArray: () => elements.flatMap((element) => element?.tagName === 'FORM'
            ? Array.from(new FormData(element), ([name, value]) => ({ name, value })) : []),
          serialize: () => new URLSearchParams(new FormData(elements[0])).toString(),
          on: jest.fn(), ready: jest.fn(),
          submit: (callback) => { if (typeof callback !== 'function') return submit(); }, trigger: submit,
          block: jest.fn(), unblock,
          prepend: (node) => elements[0]?.prepend(node),
          find: (query) => ({ val: (value) => { elements[0].querySelector(query).value = value; } }),
        });
      };
      jq.post = post;
      const checkoutPage = loadFile(resolveAlias('assets/js/checkouts/custom/mp-custom-page.js'), 'CheckoutPage', {
        wc_mercadopago_custom_page_params: { installments_select_placeholder_text: 'Fixture installments' },
      });
      const Handler = loadFile(resolveAlias(`assets/js/checkouts/custom/entities/${asset}`), 'MPEventHandler', {
        jQuery: jq, MobileCheckoutClassicObserver: class {}, MPSuperTokenErrorCodes, CheckoutPage: checkoutPage,
        wc_mercadopago_custom_event_handler_params: { is_mobile: false },
        setTimeout, clearTimeout, AbortController,
      });
      handler = new Handler(cardForm, { set3dsStatusValidationListener: jest.fn(), load3DSFlow: jest.fn() });
      handler.setSuperTokenDependencies({
        triggerHandler, authenticator, paymentMethods, metrics, errorHandler: { handleError: jest.fn() },
      });
      checkout = { $checkout_form: { trigger: submit } };
      handler.bindEvents();
      window.wc_mercadopago_custom_checkout_params = { error_messages: { default: 'Fixture localized error' } };
      delete window.wc_mercadopago_checkout_update_params;
      if (mode === 'refactored') {
        window.mpSuperTokenFinalizeClassic = (deps) => new ClassicCheckout({
          ...deps, finalize: new FinalizeSuperTokenPayment(),
        }).finalize();
      } else {
        delete window.mpSuperTokenFinalizeClassic;
      }
    });

    afterEach(() => {
      nativeListeners.mock.calls.forEach(([name, listener, options]) => document.removeEventListener(name, listener, options));
      nativeListeners.mockRestore();
      document.body.replaceChildren();
      delete window.mpSuperTokenFinalizeClassic;
      delete window.wc_mercadopago_custom_checkout_params;
      delete window.wc_mercadopago_checkout_update_params;
      delete window.mpResolveCheckoutValidation;
      delete window.fetch;
      Element.prototype.scrollIntoView = originalScroll;
    });

    it('releases a complete same-method retry without reauthorization or Custom tokenization', () => {
      handler.mercado_pago_submit = true;
      expect(handler.mercadoPagoFormHandler(event(), checkout)).toBe(true);
      expect(handler.hasToken).toBe(false);
      expect(authenticator.authorizePayment).not.toHaveBeenCalled();
      expect(cardForm.form.createCardToken).not.toHaveBeenCalled();
    });

    it.each(['authorized_pseudotoken', 'amount', 'payment_method_id', 'payment_type_id', 'installments'])
    ('does not release the prepared shortcut with missing %s', async (name) => {
      input(name).value = '';
      handler.mercado_pago_submit = true;
      handler.hasToken = true;
      expect(handler.mercadoPagoFormHandler(event(), checkout)).toBe(false);
      await flush();
      if (name === 'authorized_pseudotoken') {
        expect(post).toHaveBeenCalledTimes(1);
        expect(input(name).value).toBe(method.token);
      } else {
        expect(post).not.toHaveBeenCalled();
        expect(handler.mercado_pago_submit).toBe(false);
        expect(handler.hasToken).toBe(false);
      }
      expect(authenticator.authorizePayment).toHaveBeenCalledTimes(1);
    });

    it.each(['removed', 'disabled', 'fieldset', 'duplicate', 'outside', 'whitespace'])
    ('rejects %s authorization controls after completion without recursive authorization', async (variant) => {
      const proof = input('authorized_pseudotoken');
      if (variant === 'removed') proof.remove();
      if (variant === 'disabled') proof.disabled = true;
      if (variant === 'fieldset') {
        const fieldset = document.createElement('fieldset');
        fieldset.disabled = true;
        form.append(fieldset);
        fieldset.append(proof);
      }
      if (variant === 'duplicate') form.append(proof.cloneNode());
      if (variant === 'outside') document.body.append(proof);
      session.storeAuthorizedPseudotoken = (token) => { proof.value = variant === 'whitespace' ? '  ' : token; };
      await handler.handleWithSuperTokenSubmit(event(), checkout);
      expect(authenticator.authorizePayment).toHaveBeenCalledTimes(1);
      expect(submit).not.toHaveBeenCalled();
      expect(post).not.toHaveBeenCalled();
      expect(handler.mercado_pago_submit).toBe(false);
      expect(handler.hasToken).toBe(false);
      expect(cardForm.removeLoadSpinner).toHaveBeenCalled();
      expect(cardForm.removeBlockOverlay).toHaveBeenCalled();
      expect(unblock).toHaveBeenCalled();
      expect(document.querySelector('.woocommerce-error').textContent).toBe('Fixture localized error');
    });

    it('clears old proof and stops simplified-auth expiry without a submit loop, then permits a user retry', async () => {
      session.getSimplifiedAuth.mockResolvedValueOnce(false);
      await handler.handleWithSuperTokenSubmit(event(), checkout);
      expect(input('authorized_pseudotoken').value).toBe('');
      expect(authenticator.authorizePayment).toHaveBeenCalledTimes(1);
      expect(sdkAuthorize).not.toHaveBeenCalled();
      expect(submit).not.toHaveBeenCalled();
      expect(post).not.toHaveBeenCalled();
      expect(handler.mercado_pago_submit).toBe(false);
      expect(input('super_token_validation').value).toBe('false');
      expect(unblock).toHaveBeenCalled();
      await handler.handleWithSuperTokenSubmit(event(), checkout);
      expect(post).toHaveBeenCalledTimes(1);
    });

    it('reconciles a prepared retry when its complete amount context changes silently', async () => {
      await handler.handleWithSuperTokenSubmit(event(), checkout);
      expect(post).toHaveBeenCalledTimes(1);
      input('amount').value = '20.00';
      session.getSimplifiedAuth.mockResolvedValueOnce(false);
      expect(handler.mercadoPagoFormHandler(event(), checkout)).toBe(false);
      await flush();
      expect(authenticator.authorizePayment).toHaveBeenCalledTimes(2);
      expect(post).toHaveBeenCalledTimes(1);
      expect(handler.mercado_pago_submit).toBe(false);
    });

    if (mode === 'refactored') {
      it('recovers a synchronous unexpected finalization failure with a fixed non-sensitive metric', async () => {
        window.mpSuperTokenFinalizeClassic = () => { throw new Error('fixture-sensitive-detail'); };
        await handler.handleWithSuperTokenSubmit(event(), checkout);
        expect(metrics.sendMetric).toHaveBeenCalledWith(
          'super_token_finalize_unexpected_error', 'unexpected_error', expect.any(String),
        );
        expect(post).not.toHaveBeenCalled();
        expect(handler.mercado_pago_submit).toBe(false);
        expect(unblock).toHaveBeenCalled();
      });
    }

    it.each(['credit_card', 'debit_card', 'account_money', 'prepaid_card', 'digital_currency'])
    ('preserves complete %s finalization and zero-amount presence', async (type) => {
      method.type = type;
      input('payment_type_id').value = type;
      input('amount').value = '0';
      if (type !== 'credit_card') input('installments').remove();
      await handler.handleWithSuperTokenSubmit(event(), checkout);
      expect(post).toHaveBeenCalledTimes(1);
      expect(authenticator.authorizePayment).toHaveBeenCalledTimes(1);
      expect(input('authorized_pseudotoken').value).toBe(method.token);
    });

    it.each(['', '0', '-1', 'not-a-number'])('does not release credit with installments %s', async (value) => {
      input('installments').value = value;
      await handler.handleWithSuperTokenSubmit(event(), checkout);
      expect(post).not.toHaveBeenCalled();
      expect(handler.mercado_pago_submit).toBe(false);
    });

    it('invalidates old preparation on actual saved-method selection, preserving a fresh attempt before settled event', async () => {
      handler.mercado_pago_submit = true;
      document.dispatchEvent(new CustomEvent('mp_super_token_payment_method_selected'));
      // The early event precedes the new selection, as in SelectSavedPaymentMethod.
      method = { ...method, token: 'fixture-method-b' };
      expect(handler.mercado_pago_submit).toBe(false);
      const pending = deferred();
      sdkAuthorize.mockReturnValueOnce(pending.promise);
      const run = handler.handleWithSuperTokenSubmit(event(), checkout);
      await flush();
      document.dispatchEvent(new CustomEvent('supertoken_payment_method_selected'));
      pending.resolve();
      await run;
      expect(post).toHaveBeenCalledTimes(1);
      expect(input('authorized_pseudotoken').value).toBe('fixture-method-b');
    });

    it('invalidates readiness on new-card selection and a real Custom remount while preserving ST-owned fields', () => {
      handler.mercado_pago_submit = true;
      document.dispatchEvent(new CustomEvent('mp_super_token_payment_method_selected'));
      expect(handler.mercado_pago_submit).toBe(false);
      expect(input('authorized_pseudotoken').value).toBe('fixture-method-a');
      input('checkout_type').value = 'custom';
      handler.mercado_pago_submit = true;
      handler.hasToken = true;
      document.dispatchEvent(new CustomEvent('mp_card_form_mounted'));
      expect(handler.mercado_pago_submit).toBe(false);
      expect(handler.hasToken).toBe(false);
      input('checkout_type').value = 'super_token';
      handler.mercado_pago_submit = true;
      document.dispatchEvent(new CustomEvent('mp_card_form_mounted'));
      expect(handler.mercado_pago_submit).toBe(true);
      expect(input('authorized_pseudotoken').value).toBe('fixture-method-a');
    });

    it('does not bind the readiness listeners twice or invalidate on an ordinary settled event', () => {
      handler.bindEvents();
      handler.mercado_pago_submit = true;
      document.dispatchEvent(new CustomEvent('supertoken_payment_method_selected'));
      expect(handler.mercado_pago_submit).toBe(true);
      const before = handler.paymentSelectionGeneration;
      document.dispatchEvent(new CustomEvent('mp_super_token_payment_method_selected'));
      expect(handler.paymentSelectionGeneration).toBe(before + 1);
    });

    it('preserves a complete ST preparation through the real ordinary checkout update handler', async () => {
      handler.mercado_pago_submit = true;
      cardForm.formMounted = true;
      handler.REMOVE_LOAD_SPINNER_DELAY = 0;
      await handler.handleUpdatedCheckout();
      await flush();
      expect(triggerHandler.loadSuperToken).toHaveBeenCalledWith('10.00');
      expect(cardForm.form.unmount).not.toHaveBeenCalled();
      expect(cardForm.initCardForm).not.toHaveBeenCalled();
      expect(handler.mercado_pago_submit).toBe(true);
      expect(input('authorized_pseudotoken').value).toBe(method.token);
      expect(handler.mercadoPagoFormHandler(event(), checkout)).toBe(true);
    });

    it.each(['selection', 'new_card', 'gateway', 'form', 'amount', 'checkout_error'])
    ('does not submit an authorization completing after %s changes', async (change) => {
      const pending = deferred();
      sdkAuthorize.mockReturnValueOnce(pending.promise);
      const run = handler.handleWithSuperTokenSubmit(event(), checkout);
      await flush();
      if (change === 'selection' || change === 'new_card') {
        document.dispatchEvent(new CustomEvent('mp_super_token_payment_method_selected'));
        method = { ...method, token: 'fixture-method-b' };
        if (change === 'new_card') input('checkout_type').value = 'custom';
      }
      if (change === 'gateway') {
        form.querySelector('[name="payment_method"]').checked = false;
        handler.handlePaymentMethodSelected();
      }
      if (change === 'form') form.replaceWith(form.cloneNode(true));
      if (change === 'amount') input('amount').value = '20.00';
      if (change === 'checkout_error') handler.handleCheckoutError();
      pending.resolve();
      await run;
      expect(post).not.toHaveBeenCalled();
      expect(submit).not.toHaveBeenCalled();
      expect(handler.mercado_pago_submit).toBe(false);
      expect(handler.superTokenRequest).toBeNull();
      expect(unblock).toHaveBeenCalled();
    });

    it('does not authorize an obsolete method after a pending CVV update', async () => {
      const pending = deferred();
      paymentMethods.updateSecurityCode.mockReturnValueOnce(pending.promise);
      const run = handler.handleWithSuperTokenSubmit(event(), checkout);
      document.dispatchEvent(new CustomEvent('mp_super_token_payment_method_selected'));
      method = { ...method, token: 'fixture-method-b' };
      pending.resolve();
      await run;
      expect(authenticator.authorizePayment).not.toHaveBeenCalled();
      expect(post).not.toHaveBeenCalled();
    });

    it('deduplicates an in-flight authorization', async () => {
      const pending = deferred();
      sdkAuthorize.mockReturnValueOnce(pending.promise);
      const first = handler.handleWithSuperTokenSubmit(event(), checkout);
      await flush();
      await handler.handleWithSuperTokenSubmit(event(), checkout);
      expect(authenticator.authorizePayment).toHaveBeenCalledTimes(1);
      pending.resolve();
      await first;
      expect(post).toHaveBeenCalledTimes(1);
    });

    it.each(['resolve', 'reject'])('suppresses an obsolete %s without clearing the newer attempt lock or resetting its UI', async (outcome) => {
      const old = deferred();
      const fresh = deferred();
      sdkAuthorize.mockReturnValueOnce(old.promise).mockReturnValueOnce(fresh.promise);
      const first = handler.handleWithSuperTokenSubmit(event(), checkout);
      await flush();
      document.dispatchEvent(new CustomEvent('mp_super_token_payment_method_selected'));
      method = { ...method, token: 'fixture-method-b' };
      const second = handler.handleWithSuperTokenSubmit(event(), checkout);
      await flush();
      const currentRequest = handler.superTokenRequest;
      const removals = unblock.mock.calls.length;
      if (outcome === 'resolve') old.resolve();
      else old.reject(new Error('fixture obsolete error'));
      await first;
      expect(handler.superTokenRequest).toBe(currentRequest);
      expect(triggerHandler.resetSuperTokenOnError).not.toHaveBeenCalled();
      expect(unblock).toHaveBeenCalledTimes(removals);
      expect(post).not.toHaveBeenCalled();
      fresh.resolve();
      await second;
      expect(post).toHaveBeenCalledTimes(1);
      expect(input('authorized_pseudotoken').value).toBe(method.token);
    });

    it.each(['same_token', 'different_token'])('serializes %s authorizations so an obsolete writer cannot mask a new auth expiry', async (variant) => {
      const old = deferred();
      sdkAuthorize.mockReturnValueOnce(old.promise);
      session.getSimplifiedAuth.mockResolvedValueOnce(true).mockResolvedValueOnce(false);
      const first = handler.handleWithSuperTokenSubmit(event(), checkout);
      await flush();
      document.dispatchEvent(new CustomEvent('mp_super_token_payment_method_selected'));
      if (variant === 'different_token') method = { ...method, token: 'fixture-method-b' };
      input('amount').value = '20.00';
      const second = handler.handleWithSuperTokenSubmit(event(), checkout);
      await flush();
      expect(authenticator.authorizePayment).toHaveBeenCalledTimes(1);
      old.resolve();
      await first;
      await second;
      expect(authenticator.authorizePayment).toHaveBeenCalledTimes(2);
      expect(sdkAuthorize).toHaveBeenCalledTimes(1);
      expect(input('authorized_pseudotoken').value).toBe('');
      expect(post).not.toHaveBeenCalled();
      expect(handler.mercado_pago_submit).toBe(false);
    });

    it('keeps the current exception after a recoverable reset changes the selection and flow', async () => {
      sdkAuthorize.mockRejectedValueOnce(new Error('USER_CANCELLED'));
      triggerHandler.resetSuperTokenOnError.mockImplementation(() => {
        input('checkout_type').value = 'custom';
        method = null;
      });
      await handler.handleWithSuperTokenSubmit(event(), checkout);
      expect(triggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(true);
      expect(triggerHandler.setLastException).toHaveBeenCalledWith(
        expect.objectContaining({ message: MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED }),
      );
      expect(post).not.toHaveBeenCalled();
      expect(handler.mercado_pago_submit).toBe(false);
      expect(unblock).toHaveBeenCalled();
    });

    it('discards a pre-validation continuation after selection changes', async () => {
      const pending = deferred();
      window.wc_mercadopago_checkout_update_params = { validationEndpoint: '/fixture-validation' };
      window.fetch = jest.fn().mockReturnValue(pending.promise);
      window.mpResolveCheckoutValidation = () => ({ action: 'PROCEED' });
      handler.mercadoPagoFormHandler(event(), checkout);
      document.dispatchEvent(new CustomEvent('mp_super_token_payment_method_selected'));
      method = { ...method, token: 'fixture-method-b' };
      pending.resolve({ json: async () => ({}) });
      await flush();
      expect(authenticator.authorizePayment).not.toHaveBeenCalled();
      expect(post).not.toHaveBeenCalled();
    });

    it.each([true, false])('protects direct Order Pay transport with complete=%s', (complete) => {
      form.id = 'order_review';
      form.className = '';
      if (!complete) input('authorized_pseudotoken').value = '';
      handler.handle3dsPayOrderFormSubmission();
      expect(post).toHaveBeenCalledTimes(complete ? 1 : 0);
    });

    it.each([true, false])('guards Order Pay completion with simplified auth=%s', async (hasAuth) => {
      form.id = 'order_review';
      form.className = '';
      session.getSimplifiedAuth.mockResolvedValue(hasAuth);
      let overlay = false;
      cardForm.removeBlockOverlay.mockImplementation(() => { overlay = false; });
      const run = handler.handleWithSuperTokenSubmit(event());
      // Model WooCommerce's later blockOnSubmit listener in this submit dispatch.
      overlay = true;
      await run;
      await flush();
      expect(post).toHaveBeenCalledTimes(hasAuth ? 1 : 0);
      expect(submit).not.toHaveBeenCalled();
      if (!hasAuth) expect(overlay).toBe(false);
    });

    it('preserves the Order Pay 3DS response delegation for complete Super Token data', async () => {
      form.id = 'order_review';
      form.className = '';
      post.mockImplementationOnce(() => ({
        done: (callback) => {
          callback({ three_ds_flow: true, last_four_digits: '1234' });
          return { error: jest.fn() };
        },
      }));
      await handler.handleWithSuperTokenSubmit(event());
      expect(post).toHaveBeenCalledTimes(1);
      expect(handler.threeDSHandler.load3DSFlow).toHaveBeenCalledWith('1234');
      expect(submit).not.toHaveBeenCalled();
    });

    it('preserves Wallet Button and other-gateway direct Order Pay transport', () => {
      form.id = 'order_review';
      form.className = '';
      input('checkout_type').value = 'wallet_button';
      input('authorized_pseudotoken').value = '';
      input('amount').value = '';
      handler.handle3dsPayOrderFormSubmission();
      form.querySelector('[name="payment_method"]').checked = false;
      input('checkout_type').value = 'super_token';
      handler.handle3dsPayOrderFormSubmission();
      expect(post).toHaveBeenCalledTimes(2);
    });
  });
});
