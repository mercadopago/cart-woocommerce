const {
  mpCardThumbnailPath,
  buildAccountMoneyName,
  buildConsumerCreditsName,
  installmentsWithoutFee,
  resolvePaymentMethodView,
} = require('@super-token/adapters/view/shared/paymentMethodPresentation');
const { buildViewDeps } = require('../fixtures');
const {
  creditCard,
  debitCard,
  prepaidCard,
  accountMoney,
  consumerCredits,
  installment,
} = require('../../../core/fixtures');

const NBSP = '\u00a0';

// v2.1-style presentation seam: MP credit cards get a dedicated presentation, account-money rows
// get an extra class. v2 would return null / [] respectively.
const presentation = (overrides = {}) => ({
  mercadoPagoCreditCard: overrides.mercadoPagoCreditCard ?? (() => ({ name: 'MP Crédito', thumbnail: '/mp.png' })),
  accountMoneyRowClasses: overrides.accountMoneyRowClasses ?? (() => ['am-row']),
});

describe('mpCardThumbnailPath', () => {
  it.each(['MLA', 'MLM'])('Given a blue-card site (%s), When resolved, Then it returns the blue MP logo', (siteId) => {
    const deps = buildViewDeps({ siteId });
    expect(mpCardThumbnailPath(deps)).toBe(deps.thumbnails.mpLogoBluePath);
  });

  it.each(['MLB', 'MCO', 'MLC', 'MLU', 'MPE'])('Given a non-blue site (%s), When resolved, Then it returns the dark MP logo', (siteId) => {
    const deps = buildViewDeps({ siteId });
    expect(mpCardThumbnailPath(deps)).toBe(deps.thumbnails.mpLogoDarkPath);
  });
});

describe('buildAccountMoneyName', () => {
  it('Given a non-MLM site, When built, Then it returns the plain account-money copy', () => {
    const deps = buildViewDeps({ siteId: 'MLB' });
    expect(buildAccountMoneyName(accountMoney(), deps)).toBe(deps.copy.accountMoneyText);
  });

  describe('MLM balance-aware copy', () => {
    const deps = buildViewDeps({ siteId: 'MLM' });

    it('Given money and investment, Then it returns the wallet-with-investment copy', () => {
      const pm = accountMoney({ has_account_money: true, has_account_money_invested: true });
      expect(buildAccountMoneyName(pm, deps)).toBe(deps.copy.accountMoneyWalletWithInvestmentText);
    });

    it('Given money only, Then it returns the wallet copy', () => {
      const pm = accountMoney({ has_account_money: true, has_account_money_invested: false });
      expect(buildAccountMoneyName(pm, deps)).toBe(deps.copy.accountMoneyWalletText);
    });

    it('Given investment only, Then it returns the investment copy', () => {
      const pm = accountMoney({ has_account_money: false, has_account_money_invested: true });
      expect(buildAccountMoneyName(pm, deps)).toBe(deps.copy.accountMoneyInvestmentText);
    });

    it('Given neither money nor investment, Then it returns the available copy', () => {
      const pm = accountMoney({ has_account_money: false, has_account_money_invested: false });
      expect(buildAccountMoneyName(pm, deps)).toBe(deps.copy.accountMoneyAvailableText);
    });
  });
});

describe('buildConsumerCreditsName', () => {
  it('Given MLM, Then it returns the Mexican copy with a non-breaking space', () => {
    expect(buildConsumerCreditsName('MLM')).toBe(`Meses sin Tarjeta con Mercado${NBSP}Pago`);
  });

  it('Given MLB, Then it returns the Brazilian copy with a non-breaking space', () => {
    expect(buildConsumerCreditsName('MLB')).toBe(`Linha de Crédito Mercado${NBSP}Pago`);
  });

  it('Given any other site, Then it returns the default Spanish copy', () => {
    expect(buildConsumerCreditsName('MLA')).toBe(`Cuotas sin Tarjeta con Mercado${NBSP}Pago`);
  });
});

describe('installmentsWithoutFee', () => {
  it('Given a non-card, non-credits method, Then it returns 0', () => {
    expect(installmentsWithoutFee(debitCard())).toBe(0);
    expect(installmentsWithoutFee(accountMoney())).toBe(0);
  });

  it('Given a credit card without installments, Then it returns 0', () => {
    expect(installmentsWithoutFee(creditCard({ id: 'cc1' }))).toBe(0);
  });

  it('Given a credit card, Then it returns the highest interest-free count collected by Mercado Pago', () => {
    const pm = creditCard({
      installments: [
        installment({ installments: 1, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'] }),
        installment({ installments: 3, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'] }),
        installment({ installments: 6, installment_rate: 2.5, installment_rate_collector: ['MERCADOPAGO'] }),
      ],
    });
    expect(installmentsWithoutFee(pm)).toBe(3);
  });

  it('Given a credit card whose free installments are not collected by Mercado Pago, Then they are excluded', () => {
    const pm = creditCard({
      installments: [
        installment({ installments: 12, installment_rate: 0, installment_rate_collector: ['ISSUER'] }),
      ],
    });
    expect(installmentsWithoutFee(pm)).toBe(0);
  });

  it('Given consumer credits, Then it filters by rate only (collector is ignored)', () => {
    const pm = consumerCredits({
      installments: [
        installment({ installments: 2, installment_rate: 0, installment_rate_collector: ['ISSUER'] }),
        installment({ installments: 4, installment_rate: 0, installment_rate_collector: ['ISSUER'] }),
        installment({ installments: 9, installment_rate: 1 }),
      ],
    });
    expect(installmentsWithoutFee(pm)).toBe(4);
  });

  it('Given consumer credits with no interest-free option, Then it returns 0', () => {
    const pm = consumerCredits({
      installments: [installment({ installments: 6, installment_rate: 3 })],
    });
    expect(installmentsWithoutFee(pm)).toBe(0);
  });
});

describe('resolvePaymentMethodView', () => {
  const deps = buildViewDeps({ siteId: 'MLB' });

  it('Given account money, Then it uses the account-money name, yellow wallet and the presentation classes', () => {
    const view = resolvePaymentMethodView(accountMoney(), deps, presentation());
    expect(view.name).toBe(deps.copy.accountMoneyText);
    expect(view.thumbnail).toBe(deps.thumbnails.yellowWalletPath);
    expect(view.suppressLastFour).toBe(false);
    expect(view.extraClasses).toEqual(['am-row']);
  });

  it('Given consumer credits, Then it uses the credits name, yellow money, no extra classes and keeps the last four', () => {
    const view = resolvePaymentMethodView(consumerCredits(), deps, presentation());
    expect(view.name).toBe(`Linha de Crédito Mercado${NBSP}Pago`);
    expect(view.thumbnail).toBe(deps.thumbnails.yellowMoneyPath);
    expect(view.extraClasses).toEqual([]);
    expect(view.suppressLastFour).toBe(false);
  });

  it('Given a Mercado Pago prepaid card, Then it uses the MP card name and does not suppress the last four', () => {
    const pm = prepaidCard({ issuer: { name: 'Mercado Pago' } });
    const view = resolvePaymentMethodView(pm, deps, presentation());
    expect(view.name).toBe(deps.copy.mercadoPagoCardName);
    expect(view.suppressLastFour).toBe(false);
    expect(view.extraClasses).toEqual([]);
  });

  it('Given a non-MP prepaid card, Then it uses the raw name, the card thumbnail and keeps the last four', () => {
    const pm = prepaidCard({ name: 'Gift Card', issuer: { name: 'Some Bank' }, thumbnail: '' });
    const view = resolvePaymentMethodView(pm, deps, presentation());
    expect(view.name).toBe('Gift Card');
    // Distinguishes the prepaid branch from the defensive fallback: the prepaid branch resolves the
    // card thumbnail (white fallback), the unknown branch would return the empty raw thumbnail.
    expect(view.thumbnail).toBe(deps.thumbnails.whiteCardPath);
    expect(view.suppressLastFour).toBe(false);
    expect(view.extraClasses).toEqual([]);
  });

  it('Given a Mercado Pago credit card and a presentation, Then it uses the presentation and suppresses the last four', () => {
    const pm = creditCard({ issuer: { name: 'mercado pago' } });
    const view = resolvePaymentMethodView(pm, deps, presentation());
    expect(view.name).toBe('MP Crédito');
    expect(view.thumbnail).toBe('/mp.png');
    expect(view.suppressLastFour).toBe(true);
  });

  it('Given a Mercado Pago credit card but the presentation returns null (v2), Then it falls back to the issuer + kind name', () => {
    const pm = creditCard({ issuer: { name: 'mercado pago' } });
    const view = resolvePaymentMethodView(pm, deps, presentation({ mercadoPagoCreditCard: () => null }));
    expect(view.name).toBe('mercado pago Crédito');
    expect(view.suppressLastFour).toBe(false);
  });

  it('Given a regular credit card, Then it names it "{issuer} Crédito"', () => {
    const view = resolvePaymentMethodView(creditCard({ issuer: { name: 'Itau' } }), deps, presentation());
    expect(view.name).toBe('Itau Crédito');
  });

  it('Given a regular debit card, Then it names it "{issuer} Débito"', () => {
    const view = resolvePaymentMethodView(debitCard({ issuer: { name: 'Itau' } }), deps, presentation());
    expect(view.name).toBe('Itau Débito');
  });

  it('Given a credit card with no issuer, Then it falls back to the method name (issuer is read optionally)', () => {
    const view = resolvePaymentMethodView(creditCard({ issuer: undefined, name: 'Visa' }), deps, presentation());
    expect(view.name).toBe('Visa Crédito');
  });

  it('Given a card thumbnail override in deps, Then it wins over the raw thumbnail and the white fallback', () => {
    const localDeps = buildViewDeps({
      siteId: 'MLB',
      thumbnails: { paymentMethodsThumbnails: { cc1: '/override.png' } },
    });
    const view = resolvePaymentMethodView(creditCard({ id: 'cc1', thumbnail: '/raw.png' }), localDeps, presentation());
    expect(view.thumbnail).toBe('/override.png');
  });

  it('Given an unknown method type, Then it defensively falls back to its name/thumbnail with no chrome', () => {
    const view = resolvePaymentMethodView({ id: 'x', type: 'mystery', name: 'Mystery', thumbnail: '/m.png' }, deps, presentation());
    expect(view.name).toBe('Mystery');
    expect(view.thumbnail).toBe('/m.png');
    expect(view.suppressLastFour).toBe(false);
    expect(view.extraClasses).toEqual([]);
  });

  it('Given an unknown method with no name or thumbnail, Then it falls back to empty strings', () => {
    const view = resolvePaymentMethodView({ id: 'x', type: 'mystery' }, deps, presentation());
    expect(view.name).toBe('');
    expect(view.thumbnail).toBe('');
  });
});
