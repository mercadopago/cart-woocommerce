import { MLC_COPY } from './resolveMlcCopy';

// Called only with the fixed, approved catalog strings below.
function privacyCopy(prefix: string, url: string, label = 'cómo cuidamos tu privacidad'): string {
  return `${prefix}&nbsp;<a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a>.`;
}

/** Approved privacy link per regional site: the legacy footer uses the same prefix and URL as the catalog. */
export const REGIONAL_PRIVACY = {
  MLU: { prefix: 'Conocé', url: 'https://www.mercadopago.com.uy/privacidad' },
  MPE: { prefix: 'Conoce', url: 'https://www.mercadopago.com.pe/privacidad' },
} as const;

/** Approved regional Fast Payments copy for older plugins (MLU and MPE); MLC keeps its own policy. */
export const REGIONAL_COPY = {
  MLU: {
    ...MLC_COPY,
    flat: {
      ...MLC_COPY.flat,
      mp_methods_title: "Además, podés utilizar",
      account_money_available_text: "Dinero disponible en Mercado Pago",
      security_code_tooltip_text_3_digits: "Es un número de 3 dígitos. Lo encontrás al dorso de tu tarjeta o en la app de tu banco o billetera.",
      security_code_tooltip_text_4_digits: "Es un número de 4 dígitos. Lo encontrás al frente de tu tarjeta o en la app de tu banco o billetera.",
      security_code_error_message_text: "Código de seguridad obligatorio",
      mercadopago_privacy_policy: privacyCopy(REGIONAL_PRIVACY.MLU.prefix, REGIONAL_PRIVACY.MLU.url),
      update_security_code_with_retry_error_text: "No se pudo hacer el pago. Intentá de nuevo o pagá con otro medio.",
      update_security_code_no_retry_error_text: "No se pudo hacer el pago. Por favor, pagá con otro medio.",
      authorize_payment_method_with_retry_error_text: "No fue posible validar tu identidad. Intentá de nuevo o pagá con otro medio.",
      authorize_payment_method_no_retry_error_text: "No fue posible validar tu identidad. Por favor, pagá con otro medio.",
      select_payment_method_error_text: "Seleccioná un medio de pago para finalizar tu compra.",
      account_money_text: "Dinero disponible en Mercado Pago",
      mercado_pago_card_name: "Tarjeta Prepaga Mercado Pago",
    },
    placeholders: {
      ...MLC_COPY.placeholders,
      installments: "Seleccioná una opción",
    },
    installments: {
      ...MLC_COPY.installments,
      required: "Seleccioná una opción.",
    },
    securityCode: {
      ...MLC_COPY.securityCode,
      invalid_type: "Completá este campo.",
      invalid_length: "Ingresá el código completo.",
    },
  },
  MPE: {
    ...MLC_COPY,
    flat: {
      ...MLC_COPY.flat,
      interest_free_part_two_text: "cuotas sin intereses",
      interest_free_option_text: "sin intereses",
      mercado_pago_card_name: "Tarjeta Prepaga Mercado Pago",
      mercadopago_privacy_policy: privacyCopy(REGIONAL_PRIVACY.MPE.prefix, REGIONAL_PRIVACY.MPE.url),
    },
    installments: {
      ...MLC_COPY.installments,
      interest_free_option_text: "sin intereses",
    },
  },
} as const;

/** Exact historical values not already present in the regional catalogs. */
const historicalCopy: Record<string, string[]> = {
  "payment_methods_list_text": ["Saved payment methods"],
  "payment_methods_list_alt_text": ["Saved payment methods in your Mercado Pago account."],
  "last_digits_text": ["ending in"],
  "new_card_text": ["New card"],
  "saved_cards_title": ["Saved cards"],
  "saved_card_title": ["Saved card"],
  "mp_methods_title": ["You can also use"],
  "account_money_balance_text": ["Enough to pay for this purchase."],
  "saved_payment_method_title": ["Saved payment method"],
  "account_money_available_text": ["Dinero disponible en Mercado Pago", "Money available at Mercado Pago"],
  "interest_free_part_one_text": ["Up to"],
  "interest_free_part_two_text": ["interest-free installments", "meses sin intereses"],
  "interest_free_option_text": ["interest-free installments", "sin intereses"],
  "input_helper_message.installments.interest_free_option_text": ["interest-free installments", "sin intereses"],
  "input_helper_message.installments.required": ["Select an option."],
  "input_title.installments": ["Installments", "Meses"],
  "input_helper_message.installments.bank_interest_hint_text": ["If there is any interest, it will be applied and charged by your bank."],
  "placeholders.installments": ["Select an option"],
  "security_code_input_title_text": ["Security code"],
  "security_code_placeholder_text_3_digits": ["E.g.: 123"],
  "security_code_placeholder_text_4_digits": ["E.g.: 1234"],
  "security_code_tooltip_text_3_digits": ["It’s a 3 digit number. You can find it on the back of your card or on the app of your bank or digital wallet."],
  "security_code_tooltip_text_4_digits": ["It’s a 4 digit number. You can find it on the front of your card or on the app of your bank or digital wallet."],
  "security_code_error_message_text": ["Security code is required"],
  "input_helper_message.securityCode.invalid_type": ["Fill out this field."],
  "input_helper_message.securityCode.invalid_length": ["Type in the complete code."],
  "mercadopago_privacy_policy": [privacyCopy("Conoce", "https://www.mercadopago.com.uy/privacidad"), privacyCopy("Learn more about", "https://www.mercadopago.com.uy/privacidad", "how we protect your privacy"), privacyCopy("Learn more about", "{link}", "how we protect your privacy")],
  "update_security_code_with_retry_error_text": ["The payment could not be completed. Please try again or pay with another method."],
  "update_security_code_no_retry_error_text": ["The payment could not be completed. Please pay with another method."],
  "authorize_payment_method_with_retry_error_text": ["It was not possible to validate your identity. Please try again or pay with another method."],
  "authorize_payment_method_no_retry_error_text": ["It was not possible to validate your identity. Please pay with another method."],
  "select_payment_method_error_text": ["Select a payment method to complete your purchase."],
  "account_money_text": ["Account Money", "Account Money in Mercado Pago", "Account Money in Mercado&nbsp;Pago", "Account Money in Mercado Pago", "Saldo en Mercado Pago Wallet"],
  "mercado_pago_card_name": ["Mercado Pago Prepaid Card", "Tarjeta Debit Mastercard® Mercado Pago", "Tarjeta Prepago Mercado Pago"],
  "mercado_pago_credit_card_name": ["Mercado Pago Credit Card"],
};

// Include approved predecessors too, so cached consumers can be repaired independently of params.
[MLC_COPY, ...Object.values(REGIONAL_COPY)].forEach((copy) => {
  const groups = {
    '': copy.flat,
    'input_title.': copy.inputTitle,
    'placeholders.': copy.placeholders,
    'input_helper_message.installments.': copy.installments,
    'input_helper_message.securityCode.': copy.securityCode,
  };
  Object.entries(groups).forEach(([prefix, fields]) => {
    Object.entries(fields).forEach(([key, value]) => {
      const values = historicalCopy[prefix + key];
      if (!values.includes(value)) values.push(value);
    });
  });
});

export const LEGACY_COPY_VALUES: Readonly<Record<string, readonly string[]>> = historicalCopy;
