/** Copy introduced in 8.8.0, absent from earlier plugins that already load the CDN. */
const ENGLISH = {
  saved_cards_title: 'Saved cards',
  saved_card_title: 'Saved card',
  mp_methods_title: 'You can also use',
  saved_payment_method_title: 'Saved payment method',
  account_money_balance_text: 'Enough to pay for this purchase.',
  mercado_pago_credit_card_name: 'Mercado Pago Credit Card',
};

const SPANISH = {
  saved_cards_title: 'Tarjetas guardadas',
  saved_card_title: 'Tarjeta guardada',
  mp_methods_title: 'Además, puedes utilizar',
  saved_payment_method_title: 'Medio de pago guardado',
  account_money_balance_text: 'Suficiente para pagar esta compra.',
  mercado_pago_credit_card_name: 'Tarjeta de Crédito Mercado Pago',
};

const PORTUGUESE = {
  saved_cards_title: 'Cartões salvos',
  saved_card_title: 'Cartão salvo',
  mp_methods_title: 'Você também pode usar',
  saved_payment_method_title: 'Meio de pagamento salvo',
  account_money_balance_text: 'Suficiente para pagar esta compra.',
  mercado_pago_credit_card_name: 'Cartão de Crédito Mercado Pago',
};

type PresentationCopy = typeof ENGLISH;
type LegacyParams = { [Key in keyof PresentationCopy]?: string };

/**
 * Uses the plugin catalog locale first, then the theme's html language, never the credential country.
 * Unsupported/missing
 * languages use the English source strings, as an untranslated WordPress catalog would.
 * Images deliberately stay untouched: old plugins can use the SDK's original thumbnail.
 */
export function resolveLegacyPresentationCopy(
  params: LegacyParams,
  htmlLanguage?: string,
  pluginLocale?: unknown,
): PresentationCopy {
  const catalogLocale = typeof pluginLocale === 'string' ? pluginLocale.trim() : '';
  const language = (catalogLocale || htmlLanguage || '').trim().toLowerCase().replace(/_/g, '-');
  const defaults = language === 'pt' || language.startsWith('pt-')
    ? PORTUGUESE
    : language === 'es' || language.startsWith('es-')
      ? { ...SPANISH, ...(language === 'es-ar' ? { mp_methods_title: 'Además, podés utilizar' } : {}) }
      : ENGLISH;

  const result = { ...defaults };
  (Object.keys(defaults) as (keyof PresentationCopy)[]).forEach((key) => {
    const supplied = params[key];
    if (typeof supplied === 'string' && supplied.trim()) {
      result[key] = supplied;
    }
  });
  return result;
}
