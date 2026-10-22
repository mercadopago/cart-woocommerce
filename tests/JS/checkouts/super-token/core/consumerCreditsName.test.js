const { resolveConsumerCreditsName } = require('@super-token/core/shared/consumerCreditsName');
const { ConsumerCreditsMethod } = require('@super-token/core/paymentMethods/ConsumerCreditsMethod');
const { buildPaymentMethodRow } = require('@super-token/adapters/view/shared/paymentMethodRow');
const { resolvePaymentMethodView } = require('@super-token/adapters/view/shared/paymentMethodPresentation');
const { buildConfig, consumerCredits, installment } = require('./fixtures');
const { buildViewDeps } = require('../adapters/view/fixtures');

const presentation = {
  mercadoPagoCreditCard: () => null,
  accountMoneyRowClasses: () => [],
};

describe('Fast Payment Credits product names', () => {
  it.each([
    ['MLA', 'Mercado Crédito'],
    ['MLM', 'Mercado Crédito'],
    ['MLC', 'Mercado Crédito'],
    ['MCO', 'Mercado Crédito'],
    ['MPE', 'Mercado Crédito'],
    ['MLU', 'Mercado Crédito'],
    ['MLB', 'Mercado Parcelado'],
  ])('Given %s without a renaming flag and English store copy, Then domain and row use %s', (siteId, expectedName) => {
    const thumbnail = '/assets/images/icons/icon-credits-fast-payment.svg';
    const config = buildConfig({ siteId, thumbnails: { yellowMoneyPath: thumbnail } });
    const deps = buildViewDeps({
      siteId,
      thumbnails: { yellowMoneyPath: thumbnail },
      copy: { interestFreePartOneText: 'Up to', interestFreePartTwoText: 'interest free' },
    });
    const method = consumerCredits({
      installments: [installment({ installments: 3, installment_rate: 0 })],
    });
    const installments = method.installments;

    const decorated = new ConsumerCreditsMethod(config).decorate(method);
    const view = resolvePaymentMethodView(decorated, deps, presentation);
    const row = buildPaymentMethodRow(decorated, deps, presentation);

    expect(decorated.name).toBe(expectedName.replace(/\u00a0/g, '&nbsp;'));
    expect(view.name).toBe(expectedName);
    expect(decorated.thumbnail).toBe(thumbnail);
    expect(view.thumbnail).toBe(thumbnail);
    expect(row.querySelector('img').getAttribute('src')).toBe(thumbnail);
    expect(row.textContent).toContain(expectedName);
    expect(row.textContent).toContain(`Up to 3${siteId === 'MLB' ? 'x' : ''} interest free`);
    expect(row.getAttribute('aria-label')).toContain(expectedName.replace(/\u00a0/g, ' '));
    expect(decorated.installments).toBe(installments);
  });

  it.each(['UNKNOWN', '', null, undefined, '<img src=x onerror=alert(1)>'])('Given an unknown or missing site %p, When resolved, Then it uses Mercado Crédito without reflecting the input', (siteId) => {
    expect(resolveConsumerCreditsName(siteId)).toBe('Mercado Crédito');
  });
});
