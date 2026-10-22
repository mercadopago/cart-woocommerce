/**
 * Chilean copy for plugin versions released before the plugin catalogs carried it (CDN 1.2.8).
 * The CDN bundle is shared by every installed plugin version, so it stays until those versions are gone.
 */
export const MLC_COPY = {
  flat: {
    payment_methods_list_text: 'Medios de pago guardados',
    payment_methods_list_alt_text: 'Medios de pago guardados en tu cuenta de Mercado Pago.',
    saved_cards_title: 'Tarjetas guardadas',
    saved_card_title: 'Tarjeta guardada',
    mp_methods_title: 'Además, puedes utilizar',
    saved_payment_method_title: 'Medio de pago guardado',
    last_digits_text: 'terminada en',
    new_card_text: 'Nueva tarjeta',
    account_money_text: 'Dinero disponible en Mercado Pago',
    account_money_balance_text: 'Suficiente para pagar esta compra.',
    mercado_pago_card_name: 'Tarjeta Prepago Mercado Pago',
    mercado_pago_credit_card_name: 'Tarjeta de Crédito Mercado Pago',
    interest_free_part_one_text: 'Hasta',
    interest_free_part_two_text: 'cuotas sin interés',
    interest_free_option_text: 'sin interés',
    security_code_input_title_text: 'Código de seguridad',
    security_code_placeholder_text_3_digits: 'Ej.: 123',
    security_code_placeholder_text_4_digits: 'Ej.: 1234',
    security_code_tooltip_text_3_digits:
      'Es un número de 3 dígitos. Lo encuentras al dorso de tu tarjeta o en la app de tu banco o billetera.',
    security_code_tooltip_text_4_digits:
      'Es un número de 4 dígitos. Lo encuentras al frente de tu tarjeta o en la app de tu banco o billetera.',
    mercadopago_privacy_policy:
      'Conoce&nbsp;<a href="https://www.mercadopago.cl/privacidad" target="_blank" rel="noopener noreferrer">cómo cuidamos tu privacidad</a>.',
    update_security_code_with_retry_error_text: 'No se pudo hacer el pago. Intenta de nuevo o paga con otro medio.',
    update_security_code_no_retry_error_text: 'No se pudo hacer el pago. Por favor, paga con otro medio.',
    authorize_payment_method_with_retry_error_text:
      'No fue posible validar tu identidad. Intenta de nuevo o paga con otro medio.',
    authorize_payment_method_no_retry_error_text: 'No fue posible validar tu identidad. Por favor, paga con otro medio.',
    select_payment_method_error_text: 'Selecciona un medio de pago para finalizar tu compra.',
  },
  inputTitle: {
    installments: 'Cuotas',
  },
  placeholders: {
    installments: 'Selecciona una opción',
  },
  installments: {
    required: 'Selecciona una opción.',
    interest_free_option_text: 'sin interés',
    bank_interest_hint_text: 'Si hay intereses, los aplicará y cobrará tu banco.',
  },
  securityCode: {
    invalid_type: 'Completa este campo.',
    invalid_length: 'Ingresa el código completo.',
  },
} as const;

export type MlcCopyFlatKey = keyof typeof MLC_COPY.flat;

/** Values observed after WordPress serializes `store_language_copy` for JavaScript. */
export type StoreLanguageCopyFlag = boolean | 1 | '1' | '';

interface MlcCopyParams {
  site_id: string;
  store_language_copy?: StoreLanguageCopyFlag;
  input_title?: Record<string, unknown>;
  placeholders?: Record<string, unknown>;
  input_helper_message?: {
    installments?: Record<string, unknown>;
    securityCode?: Record<string, unknown>;
    [key: string]: unknown;
  };
}

/**
 * True only for MLC on a plugin that does not send `store_language_copy`: newer plugins already
 * deliver the store-language copy for every site, so the bundle must leave it untouched.
 */
export function usesLegacyMlcCopy(params: { site_id?: unknown; store_language_copy?: unknown }): boolean {
  const storeLanguageCopyEnabled =
    params.store_language_copy === true ||
    params.store_language_copy === 1 ||
    params.store_language_copy === '1';

  return (
    !storeLanguageCopyEnabled &&
    typeof params.site_id === 'string' &&
    params.site_id.toUpperCase() === 'MLC'
  );
}

/** Returns the same object whenever the legacy override does not apply. */
export function resolveMlcCopy<T extends { site_id: string }>(params: T): T {
  if (!usesLegacyMlcCopy(params)) {
    return params;
  }

  const source = params as T & MlcCopyParams;
  const helper = source.input_helper_message ?? {};

  return {
    ...params,
    ...MLC_COPY.flat,
    input_title: { ...source.input_title, ...MLC_COPY.inputTitle },
    placeholders: { ...source.placeholders, ...MLC_COPY.placeholders },
    input_helper_message: {
      ...helper,
      installments: { ...helper.installments, ...MLC_COPY.installments },
      securityCode: { ...helper.securityCode, ...MLC_COPY.securityCode },
    },
  } as T;
}
