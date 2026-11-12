import type {
  AccountPaymentMethod,
  PaymentMethod,
  SmartTransferPaymentMethod,
} from '@super-token/types/external-globals';

const SUPER_TOKEN_TYPES = new Set([
  'credit_card',
  'debit_card',
  'account_money',
  'prepaid_card',
  'digital_currency',
]);

function hasObjectShape(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function hasNonEmptyString(value: unknown): value is string {
  return typeof value === 'string' && value.trim().length > 0;
}

export function isSmartTransferPaymentMethod(
  value: unknown,
): value is SmartTransferPaymentMethod {
  return hasObjectShape(value)
    && value.type === 'smart_transfer'
    && hasNonEmptyString(value.id)
    && hasNonEmptyString(value.name)
    && typeof value.thumbnail === 'string'
    && hasNonEmptyString(value.token)
    && hasNonEmptyString(value.open_finance_account_id);
}

function isSuperTokenPaymentMethod(value: unknown): value is PaymentMethod {
  return hasObjectShape(value)
    && typeof value.type === 'string'
    && SUPER_TOKEN_TYPES.has(value.type);
}

export interface AccountPaymentMethodsPartition {
  superToken: PaymentMethod[];
  smartTransfer: SmartTransferPaymentMethod[];
}

/**
 * Splits one raw SDK response into isolated consumer views. Unknown or malformed
 * entries fail closed instead of reaching either renderer.
 */
export function partitionAccountPaymentMethods(
  methods: readonly AccountPaymentMethod[],
): AccountPaymentMethodsPartition {
  const superToken: PaymentMethod[] = [];
  const smartTransfer: SmartTransferPaymentMethod[] = [];

  methods.forEach((method) => {
    if (isSmartTransferPaymentMethod(method)) {
      smartTransfer.push(method);
      return;
    }

    if (isSuperTokenPaymentMethod(method)) {
      superToken.push(method);
    }
  });

  return { superToken, smartTransfer };
}
