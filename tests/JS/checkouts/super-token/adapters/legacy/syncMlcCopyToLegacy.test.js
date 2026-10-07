const { syncMlcCopyToLegacy } = require('@super-token/adapters/legacy/syncMlcCopyToLegacy');

const NEW_ACCOUNT_MONEY_TEXT = 'Dinero disponible en Mercado Pago';
const NEW_BALANCE_TEXT = 'Saldo suficiente';
const NEW_BANK_HINT = 'Si hay intereses, los aplicará y cobrará tu banco.';

function buildLocalized(overrides = {}) {
  return {
    site_id: 'MLC',
    account_money_text: NEW_ACCOUNT_MONEY_TEXT,
    account_money_balance_text: NEW_BALANCE_TEXT,
    interest_free_part_one_text: 'Sin interés',
    interest_free_part_two_text: 'en 3 cuotas',
    input_helper_message: {
      installments: {
        interest_free_option_text: 'sin interés',
        bank_interest_hint_text: NEW_BANK_HINT,
      },
    },
    ...overrides,
  };
}

describe('syncMlcCopyToLegacy', () => {
  it('Given a non-MLC site, When synced, Then the legacy controller is left untouched', () => {
    const original = { site_id: 'MLB', account_money_text: 'Dinheiro na conta' };
    const controller = { ACCOUNT_MONEY_TEXT: 'Dinheiro na conta' };

    syncMlcCopyToLegacy(original, buildLocalized({ site_id: 'MLB' }), controller, undefined);

    expect(controller.ACCOUNT_MONEY_TEXT).toBe('Dinheiro na conta');
  });

  it('Given original and localized are the same object reference (resolver no-op guard), When synced, Then nothing is mutated', () => {
    const params = { site_id: 'MLC', account_money_text: 'same-object' };

    syncMlcCopyToLegacy(params, params, { ACCOUNT_MONEY_TEXT: 'same-object' }, undefined);

    expect(params.account_money_text).toBe('same-object');
  });

  it('Given an already-built legacy controller (hybrid guard tripped), When synced, Then its copy fields are overwritten with the bundle MLC copy', () => {
    // Simulates an older plugin whose CDN bundle already constructed the legacy controller
    // before this bundle's guard skipped building a second one (RN-2).
    const original = {
      site_id: 'MLC',
      account_money_text: 'Texto genérico es.mo',
      account_money_balance_text: 'Saldo genérico',
    };
    const controller = {
      ACCOUNT_MONEY_TEXT: 'Texto genérico es.mo',
      ACCOUNT_MONEY_BALANCE_TEXT: 'Saldo genérico',
    };

    syncMlcCopyToLegacy(original, buildLocalized(), controller, undefined);

    expect(controller.ACCOUNT_MONEY_TEXT).toBe(NEW_ACCOUNT_MONEY_TEXT);
    expect(controller.ACCOUNT_MONEY_BALANCE_TEXT).toBe(NEW_BALANCE_TEXT);
    expect(controller.INTEREST_FREE_PART_ONE_TEXT).toBe('Sin interés');
    expect(controller.INTEREST_FREE_PART_TWO_TEXT).toBe('en 3 cuotas');
    expect(controller.INSTALLMENTS_INTEREST_FREE_OPTION_TEXT).toBe('sin interés');
    expect(controller.BANK_INTEREST_HINT_TEXT).toBe(NEW_BANK_HINT);
  });

  it('Given no legacy controller was built yet, When synced, Then it does not throw and still updates the original params object', () => {
    const original = { site_id: 'MLC', account_money_text: 'Texto genérico es.mo' };

    expect(() => syncMlcCopyToLegacy(original, buildLocalized(), undefined, undefined)).not.toThrow();
    expect(original.account_money_text).toBe(NEW_ACCOUNT_MONEY_TEXT);
  });

  it('Given custom-checkout bank-hint params, When synced, Then the hint is merged in without dropping unrelated keys', () => {
    const original = { site_id: 'MLC', account_money_text: 'Texto genérico es.mo' };
    const customParams = { input_helper_message: { installments: { unrelatedKey: 'kept' } } };

    syncMlcCopyToLegacy(original, buildLocalized(), undefined, customParams);

    expect(customParams.input_helper_message.installments).toEqual({
      unrelatedKey: 'kept',
      bank_interest_hint_text: NEW_BANK_HINT,
    });
  });

  it('Given the account-money row already rendered by the legacy bundle (v2.1 markup), When synced, Then its visible title, balance line, aria-label and base aria-label are refreshed', () => {
    document.body.innerHTML = `
      <div class="mp-super-token-account-money-row" data-type="account_money"
        data-base-aria-label="Texto genérico es.mo" aria-label="Texto genérico es.mo, Saldo genérico">
        <span class="mp-super-token-payment-method__title">Texto genérico es.mo</span>
        <span class="mp-super-token-am-balance-text">Saldo genérico</span>
      </div>
    `;
    const original = {
      site_id: 'MLC',
      account_money_text: 'Texto genérico es.mo',
      account_money_balance_text: 'Saldo genérico',
    };

    syncMlcCopyToLegacy(original, buildLocalized(), undefined, undefined);

    const row = document.querySelector('[data-type="account_money"]');
    expect(row.querySelector('.mp-super-token-payment-method__title').textContent).toBe(NEW_ACCOUNT_MONEY_TEXT);
    expect(row.querySelector('.mp-super-token-am-balance-text').textContent).toBe(NEW_BALANCE_TEXT);
    expect(row.getAttribute('aria-label')).toBe(`${NEW_ACCOUNT_MONEY_TEXT}, ${NEW_BALANCE_TEXT}`);
    expect(row.dataset.baseAriaLabel).toBe(NEW_ACCOUNT_MONEY_TEXT);
  });

  it('Given the account-money row rendered with v1/v2 markup (no .mp-super-token-account-money-row class), When synced, Then the title and aria-label are still refreshed via the shared data-type marker', () => {
    document.body.innerHTML = `
      <article data-type="account_money" data-base-aria-label="Texto genérico es.mo" aria-label="Texto genérico es.mo">
        <span class="mp-super-token-payment-method__title">Texto genérico es.mo</span>
      </article>
    `;
    const original = { site_id: 'MLC', account_money_text: 'Texto genérico es.mo' };

    syncMlcCopyToLegacy(original, buildLocalized(), undefined, undefined);

    const row = document.querySelector('[data-type="account_money"]');
    expect(row.querySelector('.mp-super-token-payment-method__title').textContent).toBe(NEW_ACCOUNT_MONEY_TEXT);
    expect(row.getAttribute('aria-label')).toBe(NEW_ACCOUNT_MONEY_TEXT);
    expect(row.dataset.baseAriaLabel).toBe(NEW_ACCOUNT_MONEY_TEXT);
  });

  it('Given the plugin catalog text carries a literal &nbsp; entity while the rendered DOM has a real NBSP character, When synced, Then the title is still matched and replaced', () => {
    document.body.innerHTML = `
      <article data-type="account_money">
        <span class="mp-super-token-payment-method__title">Mercado Pago disponible</span>
      </article>
    `;
    const original = { site_id: 'MLC', account_money_text: 'Mercado&nbsp;Pago disponible' };

    syncMlcCopyToLegacy(original, buildLocalized(), undefined, undefined);

    const title = document.querySelector('[data-type="account_money"] .mp-super-token-payment-method__title');
    expect(title.textContent).toBe(NEW_ACCOUNT_MONEY_TEXT);
  });

  it('Given a row synced then selected then deselected, When the legacy decoration restores aria-label from data-base-aria-label, Then it restores the new copy, not the stale one', () => {
    document.body.innerHTML = `
      <article data-type="account_money" data-base-aria-label="Texto genérico es.mo" aria-label="Texto genérico es.mo">
        <span class="mp-super-token-payment-method__title">Texto genérico es.mo</span>
      </article>
    `;
    const original = { site_id: 'MLC', account_money_text: 'Texto genérico es.mo' };

    syncMlcCopyToLegacy(original, buildLocalized(), undefined, undefined);

    const row = document.querySelector('[data-type="account_money"]');
    // Simulates V21AccountMoneyDecoration.decorate() appending the balance line while selected...
    row.setAttribute('aria-label', `${row.getAttribute('aria-label')}. ${NEW_BALANCE_TEXT}`);
    // ...then V21AccountMoneyDecoration.clear() restoring from the dataset on deselect.
    row.setAttribute('aria-label', row.dataset.baseAriaLabel);

    expect(row.getAttribute('aria-label')).toBe(NEW_ACCOUNT_MONEY_TEXT);
  });

  it('Given a visible bank-interest hint node (rendered with the shared class, no id — matches cardRow.ts/updateBankInterestHint), When synced, Then it is prefixed with the asterisk and refreshed via textContent', () => {
    document.body.innerHTML = '<div class="mp-installments-bank-interest-hint">old hint</div>';
    const original = { site_id: 'MLC', account_money_text: 'Texto genérico es.mo' };

    syncMlcCopyToLegacy(original, buildLocalized(), undefined, undefined);

    expect(document.querySelector('.mp-installments-bank-interest-hint').textContent).toBe(`*${NEW_BANK_HINT}`);
  });

  it('Given no bank-interest hint node in the DOM (non-qualifying installment — both renderers remove the node rather than leaving it empty), When synced, Then it does not throw and creates nothing', () => {
    document.body.innerHTML = '';
    const original = { site_id: 'MLC', account_money_text: 'Texto genérico es.mo' };

    expect(() => syncMlcCopyToLegacy(original, buildLocalized(), undefined, undefined)).not.toThrow();
    expect(document.querySelector('.mp-installments-bank-interest-hint')).toBeNull();
  });

  it('Given two cards each with their own bank-interest hint (a previously selected card hidden via PAYMENT_METHOD_HIDE, plus the currently active card), When synced, Then both hints are refreshed, not just the first one in document order', () => {
    document.body.innerHTML = `
      <article class="mp-super-token-payment-method mp-super-token-hide">
        <div class="mp-installments-bank-interest-hint">old hint</div>
      </article>
      <article class="mp-super-token-payment-method">
        <div class="mp-installments-bank-interest-hint">old hint</div>
      </article>
    `;
    const original = { site_id: 'MLC', account_money_text: 'Texto genérico es.mo' };

    syncMlcCopyToLegacy(original, buildLocalized(), undefined, undefined);

    document.querySelectorAll('.mp-installments-bank-interest-hint').forEach((hint) => {
      expect(hint.textContent).toBe(`*${NEW_BANK_HINT}`);
    });
  });
});
