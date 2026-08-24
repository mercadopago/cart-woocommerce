const { buildPaymentMethodRow } = require('@super-token/adapters/view/shared/paymentMethodRow');
const {
  mpCardThumbnailPath,
  installmentsWithoutFee,
  buildConsumerCreditsName,
} = require('@super-token/adapters/view/shared/paymentMethodPresentation');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { buildViewDeps } = require('../fixtures');
const { creditCard, debitCard, accountMoney, installment } = require('../../../core/fixtures');

// A permissive presentation so the row builder can be tested in isolation from the variants.
const passthroughPresentation = {
  mercadoPagoCreditCard: () => null,
  accountMoneyRowClasses: () => [],
};

describe('buildPaymentMethodRow (shared row skeleton)', () => {
  it('Given a saved card, When the row is built, Then the name and last four render as text with the shared class', () => {
    const row = buildPaymentMethodRow(
      debitCard({ name: 'Itau', issuer: { name: 'Itau' } }),
      buildViewDeps(),
      passthroughPresentation,
    );

    expect(row.classList.contains(SHARED_STYLES.PAYMENT_METHOD)).toBe(true);
    expect(row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_TITLE}`).textContent).toBe('Itau Débito');
    expect(row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_LAST_FOUR_DIGITS}`).textContent).toBe(
      '**** 5678',
    );
  });

  it('Given a malicious name and thumbnail, When the row is built, Then they are inert text/attributes, not markup (RN-3/SEC-3)', () => {
    const row = buildPaymentMethodRow(
      debitCard({
        name: '<img src=x onerror=alert(1)>',
        issuer: { name: '<script>alert(1)</script>' },
        thumbnail: '"><svg onload=alert(1)>',
      }),
      buildViewDeps(),
      passthroughPresentation,
    );

    expect(row.querySelector('script')).toBeNull();
    expect(row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_TITLE} img`)).toBeNull();
    // The raw payload survives only as text / a plain attribute value, never as executable nodes.
    const thumbnail = row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_THUMBNAIL} img`);
    expect(thumbnail.getAttribute('src')).toBe('"><svg onload=alert(1)>');
    expect(thumbnail.querySelector('svg')).toBeNull();
  });

  it('Given a credit card with more than one interest-free installment, When built, Then the value-prop pill shows the count', () => {
    const row = buildPaymentMethodRow(
      creditCard({
        installments: [installment({ installments: 1 }), installment({ installments: 6 })],
      }),
      buildViewDeps({ siteId: 'MLB' }),
      passthroughPresentation,
    );

    const pill = row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_VALUE_PROP}`);
    expect(pill.textContent).toContain('6x');
  });

  it('Given a method with no id, When built, Then a temporary id keeps the row identifiable', () => {
    const row = buildPaymentMethodRow(accountMoney({ id: '' }), buildViewDeps(), passthroughPresentation);

    expect(row.id).not.toBe('');
    expect(row.dataset.baseAriaLabel).toBeDefined();
  });
});

describe('mpCardThumbnailPath (RN-7: blue for MLA/MLM, dark elsewhere)', () => {
  // siteId arrives already normalized to uppercase from the factory composition point.
  it.each(['MLA', 'MLM'])('Given the blue site %p, When resolved, Then the blue icon is used', (siteId) => {
    const deps = buildViewDeps({ siteId });
    expect(mpCardThumbnailPath(deps)).toBe(deps.thumbnails.mpLogoBluePath);
  });

  it.each(['MLB', 'MCO', ''])('Given the non-blue site %p, When resolved, Then the dark icon is used', (siteId) => {
    const deps = buildViewDeps({ siteId });
    expect(mpCardThumbnailPath(deps)).toBe(deps.thumbnails.mpLogoDarkPath);
  });
});

describe('buildConsumerCreditsName (non-breaking space in the name)', () => {
  it('Given BR, When built, Then the name uses a real NBSP, not the literal entity (renders right via textContent)', () => {
    const name = buildConsumerCreditsName('MLB');

    expect(name).toContain('\u00a0');
    expect(name).not.toContain('&nbsp;');
    expect(name).toBe('Linha de Crédito Mercado\u00a0Pago');
  });
});

describe('installmentsWithoutFee (value-prop count)', () => {
  it('Given a debit card, When counted, Then it is zero (only credit / consumer credits offer installments)', () => {
    expect(installmentsWithoutFee(debitCard())).toBe(0);
  });

  it('Given credit installments with a fee on the largest, When counted, Then only fee-free MERCADOPAGO installments count', () => {
    const paymentMethod = creditCard({
      installments: [
        installment({ installments: 3, installment_rate: 0 }),
        installment({ installments: 6, installment_rate: 2 }),
      ],
    });

    expect(installmentsWithoutFee(paymentMethod)).toBe(3);
  });

  it('Given no fee-free MERCADOPAGO installment, When counted, Then it is zero instead of throwing', () => {
    const paymentMethod = creditCard({
      installments: [installment({ installments: 6, installment_rate_collector: ['BANK'] })],
    });

    expect(installmentsWithoutFee(paymentMethod)).toBe(0);
  });

  it('Given a fee-free installment missing its rate collector, When counted, Then it is zero instead of throwing', () => {
    const paymentMethod = creditCard({
      installments: [installment({ installments: 4, installment_rate: 0, installment_rate_collector: undefined })],
    });

    expect(() => installmentsWithoutFee(paymentMethod)).not.toThrow();
    expect(installmentsWithoutFee(paymentMethod)).toBe(0);
  });
});
