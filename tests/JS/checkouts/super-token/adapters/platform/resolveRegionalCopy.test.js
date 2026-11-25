const fs = require('fs');
const path = require('path');
const { resolveRegionalCopy, regionalCopySite } = require('@super-token/adapters/platform/resolveRegionalCopy');
const { REGIONAL_COPY } = require('@super-token/adapters/platform/regionalCopy');

describe('regional Fast Payments compatibility', () => {
  // Extracted from StoreTranslations and the generic es.mo at 072b84420de5e2a235c89398e66a6c8cb8f83287.
  // That source used __('en-US'), whose compiled catalog returned es-MX, before store_language_copy.
  test.each([
    ['es-UY', 'Dinero disponible en Mercado\u00a0Pago'],
    ['es-MX', 'Saldo en Mercado Pago Wallet'],
  ])('resolves the historical gettext contract for a store using %s', (htmlLanguage, expectedBalance) => {
    const historicalParams = { site_id: 'MLU', account_money_text: 'Saldo en Mercado Pago Wallet' };
    const localized = resolveRegionalCopy(historicalParams, htmlLanguage, 'es-MX');
    expect(localized.account_money_text).toBe(expectedBalance);
    if (htmlLanguage === 'es-MX') expect(localized).toBe(historicalParams);
  });

  test('MLU accepts each WordPress flag representation without requiring a plugin update', () => {
    const site = 'MLU';
    const locale = 'es_UY';
    [undefined, false, '', true, 1, '1'].forEach((flag) => {
      const params = {
        site_id: site, store_language_copy: flag, currency: 'unchanged',
        input_title: { installments: 'Installments', custom: 'keep' },
        input_helper_message: { installments: { interest_free_option_text: 'sin interés' }, other: 'keep' },
      };
      const resolved = resolveRegionalCopy(params, locale, locale);
      expect(resolved.input_title).toEqual({ ...REGIONAL_COPY[site].inputTitle, custom: 'keep' });
      expect(resolved.input_helper_message.installments.interest_free_option_text).toBe('sin interés');
      expect(resolved.currency).toBe('unchanged');
      expect(resolved.input_helper_message.other).toBe('keep');
      expect(params.input_title.installments).toBe('Installments');
    });
  });

  test.each([
    ['MLU', undefined, 'es-UY', 'es-AR', 'MLU'],
    ['MPE', '', 'es_PE', 'es-AR', 'MPE'],
    ['MLU', true, 'es-UY', 'es-AR', 'MLU'],
    ['MLU', undefined, 'es-UY', 'es-MX', 'MLU'],
    ['MLU', true, 'es-UY', 'es-MX', 'MLU'],
    ['MLU', true, 'es-MX', 'es-MX', undefined],
    ['MLU', undefined, 'es-MX', 'es-MX', undefined],
    ['MLU', true, 'en-US', 'es-MX', undefined],
    ['MPE', true, 'es-PE', 'es-MX', 'MPE'],
    ['MPE', true, 'es-PE', 'es-PE', 'MPE'],
    ['MLU', true, 'en-US', 'es-UY', 'MLU'],
    ['MPE', true, 'es-PE', 'en-US', undefined],
    ['MLU', true, 'es-UY', 'pt-BR', undefined],
    ['MPE', true, 'es-PE', undefined, 'MPE'],
    ['MLU', true, '', 'es-UY', 'MLU'],
    ['MPE', undefined, '', undefined, undefined],
    ['MLU', undefined, 'es-AR', undefined, undefined],
    ['MPE', undefined, 'es-MX', undefined, undefined],
    ['MLC', false, 'es-UY', 'es-UY', undefined],
    ['MCO', true, 'es-PE', 'es-PE', undefined],
  ])('uses the effective language: %s / flag %p / document %p / SDK %p', (site, flag, html, sdk, expected) => {
    const params = { site_id: site, store_language_copy: flag };
    expect(regionalCopySite(params, html, sdk)).toBe(expected);
    if (!expected) expect(resolveRegionalCopy(params, html, sdk)).toBe(params);
  });

  test.each([
    ['es_UY', 'en-US', 'es-MX', 'MLU'],
    ['es_MX', 'es-UY', 'es-MX', undefined],
    ['es_AR', 'es-UY', 'es-AR', undefined],
    ['en_US', 'es-UY', 'es-UY', undefined],
    ['pt_BR', 'es-UY', 'es-AR', undefined],
  ])('preserves the explicit plugin catalog locale %s over document %s and SDK %s', (locale, html, sdk, expected) => {
    const params = { site_id: 'MLU', store_locale: locale, store_language_copy: true };
    expect(regionalCopySite(params, html, sdk)).toBe(expected);
    if (!expected) expect(resolveRegionalCopy(params, html, sdk)).toBe(params);
  });

  test('MLU preserves custom text, current copy and non-copy references', () => {
    const site = 'MLU';
    const locale = 'es-UY';
    const state = { selected: 'card', amount: 2074.00 };
    const params = {
      site_id: site, state, mp_methods_title: 'Merchant-specific heading',
      saved_card_title: REGIONAL_COPY[site].flat.saved_card_title,
      placeholders: { installments: 'Merchant-specific prompt' },
      input_helper_message: { securityCode: { invalid_type: 'Custom validation' } },
    };
    const localized = resolveRegionalCopy(params, locale);
    expect(localized.state).toBe(state);
    expect(localized.mp_methods_title).toBe('Merchant-specific heading');
    expect(localized.saved_card_title).toBe(params.saved_card_title);
    expect(localized.placeholders.installments).toBe('Merchant-specific prompt');
    expect(localized.input_helper_message.securityCode.invalid_type).toBe('Custom validation');
    expect(resolveRegionalCopy(localized, locale)).toEqual(localized);
  });

  test.each([
    ['es_PE', 'MPE'],
    ['es_UY', undefined],
    ['en_US', undefined],
  ])('MPE follows only the explicit catalog locale %s', (locale, expected) => {
    const params = { site_id: 'MPE', store_locale: locale, store_language_copy: true };
    expect(regionalCopySite(params, 'es-PE', 'es-PE')).toBe(expected);
    if (!expected) expect(resolveRegionalCopy(params, 'es-PE', 'es-PE')).toBe(params);
  });

  test('MPE replaces recognized Peruvian copy and keeps merchant text', () => {
    const params = {
      site_id: 'MPE', store_locale: 'es_PE', store_language_copy: true,
      mp_methods_title: 'Merchant-specific heading',
      interest_free_part_two_text: 'meses sin intereses',
      input_title: { installments: 'Meses', custom: 'keep' },
      input_helper_message: { installments: { interest_free_option_text: 'sin intereses' } },
    };
    const localized = resolveRegionalCopy(params, 'es-PE', 'es-PE');
    expect(localized.interest_free_part_two_text).toBe('cuotas sin intereses');
    expect(localized.input_title).toEqual({ installments: 'Cuotas', custom: 'keep' });
    expect(localized.mp_methods_title).toBe('Merchant-specific heading');
    expect(localized.input_helper_message.installments.interest_free_option_text).toBe('sin intereses');
  });
});

function readCatalog(locale) {
  const catalogPath = path.join(__dirname, '../../../../../../i18n/languages', `woocommerce-mercadopago-${locale}.po`);
  const entries = new Map();
  fs.readFileSync(catalogPath, 'utf8').split(/\n\s*\n/).forEach((block) => {
    const fields = {};
    let field;
    block.split('\n').forEach((line) => {
      const match = line.match(/^(msgctxt|msgid|msgstr) (".*")$/);
      if (match) { field = match[1]; fields[field] = JSON.parse(match[2]); }
      else if (line.startsWith('"') && field) fields[field] += JSON.parse(line);
    });
    if (fields.msgid) entries.set(`${fields.msgctxt || ''}\0${fields.msgid}`, fields.msgstr);
  });
  return entries;
}

test.each([['MLU', 'es_UY']])('%s runtime copy agrees with the plugin catalog producer', (site, locale) => {
  const catalog = readCatalog(locale);
  const translationsPath = path.join(__dirname, '../../../../../../src/Translations/StoreTranslations.php');
  const php = fs.readFileSync(translationsPath, 'utf8').split('private function setSuperTokenCheckoutTranslations(): void')[1].split('\n    /**')[0];
  const translations = new Map([...php.matchAll(/'([^']+)'\s*=> _x\('([^']*)', '([^']*)'/g)]
    .map(([, key, msgid, context]) => [key, [context, msgid]]));
  translations.set('account_money_text', ['', 'Account Money']);
  translations.set('mercado_pago_card_name', ['', 'Mercado Pago Prepaid Card']);
  translations.set('mercado_pago_credit_card_name', ['', 'Mercado Pago Credit Card']);
  const copy = REGIONAL_COPY[site];
  const fields = {
    ...copy.flat, card_installments_label: copy.inputTitle.installments,
    placeholders_installments: copy.placeholders.installments,
    installments_required: copy.installments.required,
    card_installments_interest_text: copy.installments.bank_interest_hint_text,
    input_helper_message_security_code_invalid_type: copy.securityCode.invalid_type,
    input_helper_message_security_code_invalid_length: copy.securityCode.invalid_length,
  };
  Object.entries(fields).forEach(([key, actual]) => {
    const [context, msgid] = translations.get(key);
    const translated = catalog.get(`${context}\0${msgid}`) || catalog.get(`\0${msgid}`);
    const url = 'https://www.mercadopago.com.uy/privacidad';
    const expected = translated.replace('{link}', url);
    expect(actual).toBe(key === 'mercadopago_privacy_policy' ? expected : expected.replace(/&nbsp;/g, '\u00a0'));
  });
  expect(copy.installments.interest_free_option_text).toBe(copy.flat.interest_free_option_text);
});
