const { createBundleRuntime } = require('../../helpers/bundle-runtime');

// Literal module paths let Jest discover this suite with --findRelatedTests.
const VARIANTS = [
  ['local', () => jest.requireActual('../../../../build/super-token/bootstrap.ts.js')],
  ['v2', () => jest.requireActual('../../../../assets/js/checkouts/super-token/super-token-v2.bundle.js')],
  ['v2.1', () => jest.requireActual('../../../../assets/js/checkouts/super-token/super-token-v2.1.bundle.js')],
];
const THUMBNAIL = '/assets/images/icons/icon-credits-fast-payment.svg';

describe.each(VARIANTS)('Fast Payment Credits in the generated %s bundle', (variant, loadVariant) => {
  let runtime;

  beforeEach(() => { runtime = createBundleRuntime(); });
  afterEach(() => { runtime.cleanup(); });

  async function loadBundle(siteId, overrides = {}) {
    window.wc_mercadopago_supertoken_bundle_params = {
      site_id: siteId,
      intl: 'en-US',
      currency: 'USD',
      store_language_copy: true,
      self_construct: true,
      super_token_version: variant === 'v2.1' ? 'v2.1' : 'v2',
      yellow_money_path: THUMBNAIL,
      input_helper_message: { installments: {} },
      ...overrides,
    };
    jest.isolateModules(loadVariant);
    await runtime.waitForReady();
  }

  test.each([
    ['MLA', 'Mercado Crédito'],
    ['MLM', 'Mercado Crédito'],
    ['MLB', 'Mercado Parcelado'],
  ])('Given %s without a flag in an English store, Then the entry names Credits %s', async (siteId, expectedName) => {
    await loadBundle(siteId);
    expect(window.wc_mercadopago_supertoken_bundle_params).not.toHaveProperty('credits_renamed');
    const installments = [{ installments: 3, installment_rate: 0 }];
    const result = window.mpSuperTokenOrderAndDecorate([
      { id: 'consumer_credits', type: 'digital_currency', installments },
    ]);
    expect(result).toHaveLength(1);
    expect(result[0].name).toBe(expectedName);
    expect(result[0].thumbnail).toBe(THUMBNAIL);
    expect(result[0].installments).toBe(installments);
  });

  test.each([
    ['MLA', 'Mercado Crédito'],
    ['MLM', 'Mercado Crédito'],
    ['MLB', 'Mercado Parcelado'],
  ])('Given an old plugin for %s with its PNG and no flag, Then the updated bundle uses %s', async (siteId, expectedName) => {
    await loadBundle(siteId, { yellow_money_path: '/icons/icon-yellow-money.png' });
    const result = window.mpSuperTokenOrderAndDecorate([
      { id: 'consumer_credits', type: 'digital_currency', installments: [] },
    ]);
    expect(result[0].name).toBe(expectedName);
    expect(result[0].thumbnail).toBe('/icons/icon-yellow-money.png');
  });

  test.each([false, true, '1', '', '0'])('Given retired flag value %p, Then it no longer controls the Mexican product name', async (value) => {
    await loadBundle('MLM', { credits_renamed: value });
    expect(window.mpSuperTokenOrderAndDecorate([
      { id: 'consumer_credits', type: 'digital_currency', installments: [] },
    ])[0].name).toBe('Mercado Crédito');
  });

  test.each(['MCO', 'MPE', 'MLU', 'UNKNOWN'])('Given %s, Then the entry uses Mercado Crédito as the default name', async (siteId) => {
    await loadBundle(siteId);
    expect(window.mpSuperTokenOrderAndDecorate([
      { id: 'consumer_credits', type: 'digital_currency', installments: [] },
    ])[0].name).toBe('Mercado Crédito');
  });

  test('Given MLC, Then Credits stays excluded', async () => {
    await loadBundle('MLC');
    expect(window.mpSuperTokenOrderAndDecorate([
      { id: 'consumer_credits', type: 'digital_currency', installments: [] },
    ])).toEqual([]);
  });

  test.each([
    ['MLB', 'Mercado Parcelado'],
    ['MLM', 'Mercado Crédito'],
  ])('Given %s, When Credits is selected, Then the rendered name is %s and the due date keeps its bold markers', async (siteId, expectedName) => {
    await loadBundle(siteId, {
      consumer_credits_due_date: 'La primera mensualidad <b>vence el</b>',
      months_abbreviated: { nov: 'nov' },
      input_title: { installments: 'Meses' },
      placeholders: { installments: 'Elige' },
      input_helper_message: { installments: {}, securityCode: {} },
    });
    const previousSdk = window.mpSdkInstance;
    const previousJquery = window.jQuery;
    const previousCss = window.CSS;
    const contract = Promise.resolve({ update: jest.fn() });
    const container = document.createElement('div');
    document.body.appendChild(container);
    try {
      // jsdom lacks CSS.escape; this fixture uses only simple, fixed identifiers.
      window.CSS = {
        escape: (value) => {
          expect(value).toMatch(/^[A-Za-z0-9_-]+$/);
          return value;
        },
      };
      window.jQuery = jest.fn(() => ({ val: () => '', on: jest.fn() }));
      window.mpSdkInstance = { renderCreditsContract: jest.fn(() => contract) };
      document.dispatchEvent(new Event('mp_sdk_instance_ready'));
      expect(window.mpSuperTokenPaymentMethods).toBeDefined();
      const renderError = jest.spyOn(window.mpSuperTokenMetrics, 'errorToRenderAccountPaymentMethods');
      window.mpSuperTokenPaymentMethods.onCustomCheckoutWasRendered(container, [{
        id: 'consumer_credits',
        type: 'digital_currency',
        next_due_date: '2026-11-17',
        installments: [{
          installments: 1,
          installment_rate: 0,
          installment_amount: 20.05,
          total_amount: 20.05,
        }],
      }]);
      expect(renderError).not.toHaveBeenCalled();
      renderError.mockRestore();
      await contract;
      const select = container.querySelector('select');
      expect(select).not.toBeNull();
      select.value = '1';
      select.dispatchEvent(new Event('change'));
      const row = container.querySelector('[data-id="consumer_credits"]');
      window.mpSuperTokenPaymentMethods.selectPaymentMethod(row);
      expect(row.getAttribute('aria-selected')).toBe('true');
      expect(row.querySelector('.mp-super-token-payment-method__title').textContent).toBe(expectedName);
      const dueDate = container.querySelector('#mp-consumer-credits-due-date');
      expect(dueDate.textContent).toBe('La primera mensualidad vence el 17/nov.');
      expect([...dueDate.querySelectorAll('b')].map((node) => node.textContent)).toEqual(['vence el', '17/nov']);
      expect(dueDate.textContent).not.toContain('<b>');
    } finally {
      if (previousSdk === undefined) delete window.mpSdkInstance;
      else window.mpSdkInstance = previousSdk;
      if (previousJquery === undefined) delete window.jQuery;
      else window.jQuery = previousJquery;
      if (previousCss === undefined) delete window.CSS;
      else window.CSS = previousCss;
      container.remove();
    }
  });
});
