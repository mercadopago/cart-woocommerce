const { syncMlcCopyToLegacy } = require('@super-token/adapters/legacy/syncMlcCopyToLegacy');
const { resolveMlcCopy } = require('@super-token/adapters/platform/resolveMlcCopy');

function storeParams(overrides = {}) {
  return {
    site_id: 'MLC',
    mp_methods_title: 'Además, podés utilizar',
    saved_cards_title: 'Tarjetas guardadas',
    account_money_text: 'Texto genérico es.mo',
    account_money_balance_text: 'Tenés saldo suficiente.',
    payment_methods_list_alt_text: 'Métodos de pago guardados',
    mercadopago_privacy_policy: 'Conocé cómo cuidamos tu privacidad.',
    untouched: 'keep me',
    input_title: { installments: 'Cuotas' },
    placeholders: { installments: 'Seleccioná una opción' },
    input_helper_message: {
      installments: { required: 'Seleccioná una opción.', interest_free_option_text: 'sin interés' },
      securityCode: { invalid_type: 'Completá este campo.' },
    },
    ...overrides,
  };
}

describe('syncMlcCopyToLegacy', () => {
  afterEach(() => {
    document.body.replaceChildren();
  });

  it('Given MLC on a plugin that sends store_language_copy, When synced, Then the legacy controller is left untouched', () => {
    const original = storeParams({ store_language_copy: true });
    const controller = { MP_METHODS_TITLE: 'Además, podés utilizar' };

    syncMlcCopyToLegacy(original, resolveMlcCopy(storeParams()), controller, undefined);

    expect(controller.MP_METHODS_TITLE).toBe('Además, podés utilizar');
    expect(original.mp_methods_title).toBe('Además, podés utilizar');
  });

  it('Given original and localized are the same object reference (resolver no-op), When synced, Then nothing is mutated', () => {
    const params = storeParams({ site_id: 'MLA' });

    syncMlcCopyToLegacy(params, params, {}, undefined);

    expect(params.mp_methods_title).toBe('Además, podés utilizar');
  });

  it('Given no legacy controller was built yet, When synced, Then it does not throw and updates the original params object in place', () => {
    const original = storeParams();

    expect(() => syncMlcCopyToLegacy(original, resolveMlcCopy(original), undefined, undefined)).not.toThrow();
    expect(original.mp_methods_title).toBe('Además, puedes utilizar');
    expect(original.untouched).toBe('keep me');
  });

  it('Given an initialized MLC controller and rendered copy, When synced, Then copy fields and DOM are refreshed without replacing its state', () => {
    document.body.innerHTML = `
      <div id="mp-checkout-super-token-root" aria-label="Métodos de pago guardados">
        <section class="mp-super-token-block" aria-label="Además, podés utilizar">
          <span class="mp-super-token-block__title">Además, podés utilizar</span>
        </section>
        <div data-type="account_money" aria-label="Texto genérico es.mo. Tenés saldo suficiente." data-base-aria-label="Texto genérico es.mo">
          <span class="mp-super-token-payment-method__title">Texto genérico es.mo</span>
          <span class="mp-super-token-am-balance-text">Tenés saldo suficiente.</span>
        </div>
        <select id="mp-super-token-installments-select-1"><option>Seleccioná una opción</option></select>
        <input-helper input-id="mp-super-token-installments-error-1" message="Seleccioná una opción."></input-helper>
        <div class="mp-installments-bank-interest-hint">*old hint</div>
        <footer id="mp-super-token-privacy-policy-footer"><span>Conocé cómo cuidamos tu privacidad.</span></footer>
      </div>`;
    const original = storeParams();
    const localized = resolveMlcCopy(original);
    const paymentState = { selected: 'account_money' };
    const controller = { paymentState, variantView: { savedCardsView: { deps: { copy: {} } } } };
    const customParams = { input_helper_message: { installments: { other: 'keep me' } } };

    syncMlcCopyToLegacy(original, localized, controller, customParams);

    expect(controller.paymentState).toBe(paymentState);
    expect(controller.MP_METHODS_TITLE).toBe('Además, puedes utilizar');
    expect(controller.INSTALLMENTS_PLACEHOLDER).toBe('Selecciona una opción');
    expect(controller.variantView.savedCardsView.deps.copy.savedCardsTitle).toBe('Tarjetas guardadas');
    expect(document.querySelector('.mp-super-token-block__title').textContent).toBe('Además, puedes utilizar');
    expect(document.querySelector('.mp-super-token-block').getAttribute('aria-label')).toBe('Además, puedes utilizar');
    const accountMoneyRow = document.querySelector('[data-type="account_money"]');
    expect(accountMoneyRow.querySelector('.mp-super-token-payment-method__title').textContent).toBe('Dinero disponible en Mercado Pago');
    expect(accountMoneyRow.getAttribute('aria-label')).toContain('Suficiente para pagar esta compra.');
    expect(accountMoneyRow.dataset.baseAriaLabel).toBe('Dinero disponible en Mercado Pago');
    expect(document.querySelector('select option').textContent).toBe('Selecciona una opción');
    expect(document.querySelector('input-helper').getAttribute('message')).toBe('Selecciona una opción.');
    expect(document.querySelector('.mp-installments-bank-interest-hint').textContent).toBe('*Si hay intereses, los aplicará y cobrará tu banco.');
    const privacyLink = document.querySelector('#mp-super-token-privacy-policy-footer a');
    expect(privacyLink.textContent).toBe('cómo cuidamos tu privacidad');
    expect(privacyLink.href).toBe('https://www.mercadopago.cl/privacidad');
    expect(privacyLink.rel).toBe('noopener noreferrer');
    expect(customParams.input_helper_message.installments).toEqual({
      other: 'keep me',
      bank_interest_hint_text: 'Si hay intereses, los aplicará y cobrará tu banco.',
    });
  });
});
