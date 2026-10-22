const { resolveMlcCopy, usesLegacyMlcCopy } = require('@super-token/adapters/platform/resolveMlcCopy');

function storeParams(overrides = {}) {
  return {
    site_id: 'MLC',
    mp_methods_title: 'Además, podés utilizar',
    account_money_text: 'Texto genérico es.mo',
    mercadopago_privacy_policy: 'Conocé cómo cuidamos tu privacidad.',
    untouched: 'keep me',
    input_title: { installments: 'Cuotas', other: 'keep me' },
    placeholders: { installments: 'Seleccioná una opción', other: 'keep me' },
    input_helper_message: {
      installments: { required: 'Seleccioná una opción.', other: 'keep me' },
      securityCode: { invalid_type: 'Completá este campo.', other: 'keep me' },
      other: 'keep me',
    },
    ...overrides,
  };
}

describe('resolveMlcCopy', () => {
  it.each([true, 1, '1'])('Given MLC with store_language_copy=%p, When resolved, Then the same params are returned unchanged', (storeLanguageCopy) => {
    const params = storeParams({ store_language_copy: storeLanguageCopy });

    expect(resolveMlcCopy(params)).toBe(params);
  });

  it.each(['MLA', 'MLB', 'MLM'])('Given %s on an older plugin, When resolved, Then the same params are returned unchanged', (siteId) => {
    const params = storeParams({ site_id: siteId });

    expect(resolveMlcCopy(params)).toBe(params);
  });

  it('Given MLC on an older plugin, When resolved, Then the bundle Chilean copy replaces the plugin copy without mutating it', () => {
    const params = storeParams({ site_id: 'mlc' });

    const resolved = resolveMlcCopy(params);

    expect(resolved).not.toBe(params);
    expect(resolved).toMatchObject({
      mp_methods_title: 'Además, puedes utilizar',
      account_money_text: 'Dinero disponible en Mercado Pago',
      untouched: 'keep me',
      input_title: { installments: 'Cuotas', other: 'keep me' },
      placeholders: { installments: 'Selecciona una opción', other: 'keep me' },
      input_helper_message: {
        installments: {
          required: 'Selecciona una opción.',
          interest_free_option_text: 'sin interés',
          bank_interest_hint_text: 'Si hay intereses, los aplicará y cobrará tu banco.',
          other: 'keep me',
        },
        securityCode: { invalid_type: 'Completa este campo.', invalid_length: 'Ingresa el código completo.', other: 'keep me' },
        other: 'keep me',
      },
    });
    expect(resolved.mercadopago_privacy_policy).toContain('https://www.mercadopago.cl/privacidad');
    expect(params.mp_methods_title).toBe('Además, podés utilizar');
  });
});

describe('usesLegacyMlcCopy', () => {
  it.each([
    [{ site_id: 'MLC' }, true],
    [{ site_id: 'mlc', store_language_copy: false }, true],
    [{ site_id: 'MLC', store_language_copy: true }, false],
    [{ site_id: 'MLC', store_language_copy: 1 }, false],
    [{ site_id: 'MLC', store_language_copy: '1' }, false],
    [{ site_id: 'MLA' }, false],
    [{}, false],
  ])('Given %p, When checked, Then it returns %p', (params, expected) => {
    expect(usesLegacyMlcCopy(params)).toBe(expected);
  });
});
