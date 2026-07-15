const { resolveAlias } = require('../../../../helpers/path-resolver');
const { loadFile } = require('../../../../helpers/load-file');

const superTokenPaymentMethodsPath = resolveAlias(
  `assets/js/checkouts/super-token/${global.SUPER_TOKEN_VERSION}/entities/super-token-payment-methods.js`
);

// PSW-4220 — captcha tokens (reCAPTCHA / hCaptcha / Cloudflare Turnstile) are single-use. They must
// be omitted from the Super Token pre-validation serialize (so woocommerce_checkout_process does not
// consume them server side) but kept in the real submit serialize. See excludeRecaptchaFromPreValidation.
describe('MPSuperTokenPaymentMethods - excludeRecaptchaFromPreValidation (PSW-4220)', () => {
  // Matches every supported captcha field, same as the source.
  const CAPTCHA_SELECTOR =
    '[name^="g-recaptcha-response"], [name^="h-captcha-response"], [name^="cf-turnstile-response"]';

  let MPSuperTokenPaymentMethods;
  let instance;
  let metrics;
  let capture;

  // Minimal-but-complete bundle params so the class fields initialize without throwing.
  const mockBundleParams = {
    input_helper_message: { installments: {}, securityCode: {} },
    input_title: { installments: '' },
    placeholders: { installments: '' },
    security_code_input_title_text: '',
    site_id: 'MLB',
    currency: 'BRL',
    intl: 'pt-BR',
  };

  // Builds a Classic checkout form; captchaName=null renders a form with no captcha field.
  const setupClassicCheckout = (captchaName = 'g-recaptcha-response') => {
    const captcha = captchaName ? `<textarea name="${captchaName}">token-123</textarea>` : '';
    document.body.innerHTML =
      '<form class="checkout"><input name="billing_first_name" value="John" />' + captcha + '</form>';
  };

  // Mock jQuery.fn.serialize that records whether the captcha field was disabled at the exact moment
  // the original serialize ran — this is what makes jQuery.serialize skip the field.
  const installOriginalSerialize = () => {
    const original = jest.fn(function () {
      const field = document.querySelector(CAPTCHA_SELECTOR);
      capture.disabledDuringSerialize = field ? field.disabled : null;
      capture.fieldName = field ? field.name : null;
      return 'billing_first_name=John';
    });
    global.window.jQuery = { fn: { serialize: original } };
    return original;
  };

  // .serialize() is called on a jQuery collection; the source reads `this[0]` (the serialized form).
  const runSerializeOn = (element) => global.window.jQuery.fn.serialize.call({ 0: element, length: 1 });
  const runSerialize = () => runSerializeOn(document.querySelector('form.checkout'));

  beforeAll(() => {
    global.wc_mercadopago_supertoken_bundle_params = mockBundleParams;
    global.Intl = Intl;
    MPSuperTokenPaymentMethods = loadFile(superTokenPaymentMethodsPath, 'MPSuperTokenPaymentMethods', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    document.body.innerHTML = '';
    capture = {};
    metrics = {
      errorToExcludeRecaptchaFromPreValidation: jest.fn(),
      captchaFieldToggledOnPreValidation: jest.fn(),
    };
    instance = new MPSuperTokenPaymentMethods(null, metrics);
    global.window.mpEventHandler = { mercado_pago_submit: false };
  });

  afterEach(() => {
    document.body.innerHTML = '';
    delete global.window.jQuery;
    delete global.window.mpEventHandler;
  });

  describe('gating — runs only on the standard Classic checkout', () => {
    test('Given a standard Classic checkout with a captcha field, When excludeRecaptchaFromPreValidation runs, Then should install the serialize spy', () => {
      setupClassicCheckout();
      const original = installOriginalSerialize();

      instance.excludeRecaptchaFromPreValidation();

      expect(global.window.jQuery.fn.serialize).not.toBe(original);
      expect(global.window.jQuery.fn.serialize.__mpRecaptchaSpy).toBe(true);
    });

    test('Given a Blocks checkout (no form.checkout), When excludeRecaptchaFromPreValidation runs, Then should not patch serialize', () => {
      document.body.innerHTML =
        '<div class="wc-block-checkout__form"><textarea name="g-recaptcha-response">t</textarea></div>';
      const original = installOriginalSerialize();

      instance.excludeRecaptchaFromPreValidation();

      expect(global.window.jQuery.fn.serialize).toBe(original);
    });

    test('Given the order-pay page (form#order_review, no form.checkout), When excludeRecaptchaFromPreValidation runs, Then should not patch serialize', () => {
      document.body.innerHTML =
        '<form id="order_review"><textarea name="g-recaptcha-response">t</textarea></form>';
      const original = installOriginalSerialize();

      instance.excludeRecaptchaFromPreValidation();

      expect(global.window.jQuery.fn.serialize).toBe(original);
    });

    test('Given a Classic checkout with no captcha field, When excludeRecaptchaFromPreValidation runs, Then should not patch serialize', () => {
      setupClassicCheckout(null);
      const original = installOriginalSerialize();

      instance.excludeRecaptchaFromPreValidation();

      expect(global.window.jQuery.fn.serialize).toBe(original);
    });

    test('Given a captcha only outside the checkout form, When excludeRecaptchaFromPreValidation runs, Then should not patch serialize', () => {
      document.body.innerHTML =
        '<form class="checkout"><input name="billing_first_name" /></form>' +
        '<form id="secondary-login"><textarea name="g-recaptcha-response">t</textarea></form>';
      const original = installOriginalSerialize();

      instance.excludeRecaptchaFromPreValidation();

      expect(global.window.jQuery.fn.serialize).toBe(original);
    });

    test('Given another form is serialized, When it is not the checkout form, Then should not touch the checkout captcha nor emit a metric', () => {
      document.body.innerHTML =
        '<form class="checkout"><input name="billing_first_name" /><textarea name="g-recaptcha-response">t</textarea></form>' +
        '<form id="secondary-login"><input name="log" /></form>';
      installOriginalSerialize();
      instance.excludeRecaptchaFromPreValidation();

      // A non-checkout form is serialized (this[0] = the login form).
      runSerializeOn(document.querySelector('#secondary-login'));

      expect(document.querySelector('[name^="g-recaptcha-response"]').disabled).toBe(false);
      expect(metrics.captchaFieldToggledOnPreValidation).not.toHaveBeenCalled();
    });
  });

  describe('supported captcha types (RN-1)', () => {
    test.each([
      'g-recaptcha-response',
      'h-captcha-response',
      'cf-turnstile-response',
    ])(
      'Given the %s field in the checkout form, When the pre-validation serialize runs, Then should omit it during serialize and re-enable it after',
      (captchaName) => {
        setupClassicCheckout(captchaName);
        installOriginalSerialize();
        instance.excludeRecaptchaFromPreValidation();

        runSerialize();

        expect(capture.disabledDuringSerialize).toBe(true);
        expect(capture.fieldName).toBe(captchaName);
        expect(document.querySelector(CAPTCHA_SELECTOR).disabled).toBe(false);
      }
    );

    test('Given multiple captcha fields in the same checkout form, When the pre-validation serialize runs, Then should disable and re-enable all of them and emit a metric per field', () => {
      document.body.innerHTML =
        '<form class="checkout">' +
        '<input name="billing_first_name" value="John" />' +
        '<textarea name="g-recaptcha-response">a</textarea>' +
        '<textarea name="cf-turnstile-response">b</textarea>' +
        '</form>';

      const disabledDuring = {};
      global.window.jQuery = {
        fn: {
          serialize: jest.fn(function () {
            disabledDuring.recaptcha = document.querySelector('[name^="g-recaptcha-response"]').disabled;
            disabledDuring.turnstile = document.querySelector('[name^="cf-turnstile-response"]').disabled;
            return 'x=1';
          }),
        },
      };
      instance.excludeRecaptchaFromPreValidation();

      runSerialize();

      // Both fields were disabled at serialize time (so both are omitted from the body)...
      expect(disabledDuring.recaptcha).toBe(true);
      expect(disabledDuring.turnstile).toBe(true);
      // ...and both were re-enabled afterwards.
      expect(document.querySelector('[name^="g-recaptcha-response"]').disabled).toBe(false);
      expect(document.querySelector('[name^="cf-turnstile-response"]').disabled).toBe(false);
      // One success metric per field, per action.
      expect(metrics.captchaFieldToggledOnPreValidation).toHaveBeenCalledWith('disabled', 'g-recaptcha-response');
      expect(metrics.captchaFieldToggledOnPreValidation).toHaveBeenCalledWith('disabled', 'cf-turnstile-response');
      expect(metrics.captchaFieldToggledOnPreValidation).toHaveBeenCalledWith('enabled', 'g-recaptcha-response');
      expect(metrics.captchaFieldToggledOnPreValidation).toHaveBeenCalledWith('enabled', 'cf-turnstile-response');
    });
  });

  describe('token exclusion (RN-1)', () => {
    test('Given mercado_pago_submit is false, When the pre-validation serialize runs, Then should disable the field during serialize and re-enable it after', () => {
      setupClassicCheckout();
      installOriginalSerialize();
      instance.excludeRecaptchaFromPreValidation();

      const result = runSerialize();

      // Disabled controls are skipped by jQuery.serialize → the token is omitted from the body.
      expect(capture.disabledDuringSerialize).toBe(true);
      // Re-enabled right after, so the live form keeps the token for the real submit.
      expect(document.querySelector(CAPTCHA_SELECTOR).disabled).toBe(false);
      // Original serialize output is returned untouched.
      expect(result).toBe('billing_first_name=John');
    });

    test('Given mercado_pago_submit is true, When the real submit serialize runs, Then should keep the field enabled so the token is sent', () => {
      setupClassicCheckout();
      installOriginalSerialize();
      instance.excludeRecaptchaFromPreValidation();

      global.window.mpEventHandler.mercado_pago_submit = true;
      runSerialize();

      expect(capture.disabledDuringSerialize).toBe(false);
      expect(document.querySelector(CAPTCHA_SELECTOR).disabled).toBe(false);
    });
  });

  describe('success metric', () => {
    test('Given the pre-validation serialize, When it runs, Then should emit the disabled and enabled toggle metrics with the field name', () => {
      setupClassicCheckout('g-recaptcha-response');
      installOriginalSerialize();
      instance.excludeRecaptchaFromPreValidation();

      runSerialize();

      expect(metrics.captchaFieldToggledOnPreValidation).toHaveBeenCalledWith('disabled', 'g-recaptcha-response');
      expect(metrics.captchaFieldToggledOnPreValidation).toHaveBeenCalledWith('enabled', 'g-recaptcha-response');
    });

    test('Given the real submit serialize, When it runs, Then should not emit any toggle metric', () => {
      setupClassicCheckout();
      installOriginalSerialize();
      instance.excludeRecaptchaFromPreValidation();

      global.window.mpEventHandler.mercado_pago_submit = true;
      runSerialize();

      expect(metrics.captchaFieldToggledOnPreValidation).not.toHaveBeenCalled();
    });
  });

  describe('no sticky state (RN-2) — regression for the real-submit token leak', () => {
    test('Given a pre-validation followed by the real submit, When each serialize runs, Then should omit the token only from the pre-validation and keep it on the real submit', () => {
      setupClassicCheckout();
      const original = installOriginalSerialize();
      instance.excludeRecaptchaFromPreValidation();

      // Pre-validation pass
      global.window.mpEventHandler.mercado_pago_submit = false;
      runSerialize();
      expect(capture.disabledDuringSerialize).toBe(true);

      // Real submit pass (flow re-triggers the form after tokenization)
      global.window.mpEventHandler.mercado_pago_submit = true;
      runSerialize();
      expect(capture.disabledDuringSerialize).toBe(false);

      expect(original).toHaveBeenCalledTimes(2);
      expect(document.querySelector(CAPTCHA_SELECTOR).disabled).toBe(false);
    });

    test('Given the original serialize throws, When the form is serialized, Then should still re-enable the field', () => {
      setupClassicCheckout();
      global.window.jQuery = {
        fn: {
          serialize: jest.fn(function () {
            throw new Error('serialize boom');
          }),
        },
      };
      instance.excludeRecaptchaFromPreValidation();

      expect(() => runSerialize()).toThrow('serialize boom');
      expect(document.querySelector(CAPTCHA_SELECTOR).disabled).toBe(false);
    });
  });

  describe('idempotency', () => {
    test('Given the spy is already installed, When excludeRecaptchaFromPreValidation runs again, Then should not reinstall it', () => {
      setupClassicCheckout();
      installOriginalSerialize();

      instance.excludeRecaptchaFromPreValidation();
      const firstPatched = global.window.jQuery.fn.serialize;
      instance.excludeRecaptchaFromPreValidation();

      expect(global.window.jQuery.fn.serialize).toBe(firstPatched);
    });
  });

  describe('resilience and observability', () => {
    test('Given jQuery.fn.serialize is unavailable, When excludeRecaptchaFromPreValidation runs, Then should emit the serialize_unavailable metric without throwing', () => {
      setupClassicCheckout();
      global.window.jQuery = { fn: {} };

      expect(() => instance.excludeRecaptchaFromPreValidation()).not.toThrow();
      expect(metrics.errorToExcludeRecaptchaFromPreValidation).toHaveBeenCalledWith(
        'serialize_unavailable',
        expect.any(String)
      );
    });

    test('Given an unexpected failure, When excludeRecaptchaFromPreValidation runs, Then should emit the setup metric without throwing', () => {
      setupClassicCheckout();
      Object.defineProperty(global.window, 'jQuery', {
        configurable: true,
        get() {
          throw new Error('unexpected');
        },
      });

      expect(() => instance.excludeRecaptchaFromPreValidation()).not.toThrow();
      expect(metrics.errorToExcludeRecaptchaFromPreValidation).toHaveBeenCalledWith('setup', expect.any(Error));
    });
  });
});
