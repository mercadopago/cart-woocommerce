const { resolveLegacyPresentationCopy } = require('@super-token/adapters/platform/resolveLegacyPresentationCopy');

describe('legacy v2.1 presentation', () => {
  it.each([
    ['pt-BR', 'Cartões salvos', 'Você também pode usar'],
    ['es-AR', 'Tarjetas guardadas', 'Además, podés utilizar'],
    ['es-MX', 'Tarjetas guardadas', 'Además, puedes utilizar'],
    ['es-CL', 'Tarjetas guardadas', 'Además, puedes utilizar'],
    ['en-US', 'Saved cards', 'You can also use'],
    ['fr-FR', 'Saved cards', 'You can also use'],
    [undefined, 'Saved cards', 'You can also use'],
  ])('fills old plugin texts using store language %s', (language, title, methodsTitle) => {
    const result = resolveLegacyPresentationCopy({}, language);
    expect(result.saved_cards_title).toBe(title);
    expect(result.mp_methods_title).toBe(methodsTitle);
    expect(Object.values(result)).toHaveLength(6);
    expect(Object.values(result).every((text) => text.trim().length > 0)).toBe(true);
  });

  it.each([
    ['pt-BR', 'es-AR', 'Cartões salvos'],
    ['es-AR', 'pt-BR', 'Tarjetas guardadas'],
    ['es-MX', '', 'Tarjetas guardadas'],
    ['en-US', 'pt-BR', 'Saved cards'],
    ['fr-FR', 'pt-BR', 'Saved cards'],
    [undefined, 'pt-BR', 'Cartões salvos'],
    [' ', 'es-AR', 'Tarjetas guardadas'],
    [null, '', 'Saved cards'],
  ])('prefers catalog locale %s over HTML %s', (locale, html, expected) => {
    expect(resolveLegacyPresentationCopy({}, html, locale).saved_cards_title).toBe(expected);
  });

  it('preserves supplied translations without mutating plugin params or inventing image paths', () => {
    const params = { saved_cards_title: 'My cards', saved_card_title: ' ', mp_methods_title: '', mp_logo_blue_path: '/existing.png' };
    const result = resolveLegacyPresentationCopy(params, 'pt_BR');
    expect(result.saved_cards_title).toBe('My cards');
    expect(result.saved_card_title).toBe('Cartão salvo');
    expect(result.mp_methods_title).toBe('Você também pode usar');
    expect(result).not.toHaveProperty('mp_logo_blue_path');
    expect(params.saved_card_title).toBe(' ');
  });
});
