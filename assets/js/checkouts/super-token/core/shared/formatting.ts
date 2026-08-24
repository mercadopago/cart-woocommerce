/**
 * Pure currency formatting shared by the installment rules. Uses the standard
 * language `Intl` (not a window/app global), so the core stays platform-free.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): formatCurrency 1106-1120.
 */

import { MEXICO_ACCRONYM } from '@super-token/core/constants';

export interface CurrencyFormatOptions {
  /** BCP-47 locale tag. */
  intl: string;
  /** ISO currency code. */
  currency: string;
  /** Uppercase site id — MLM inserts a space after the currency symbol. */
  siteId: string;
}

export const formatCurrency = (value: number, options: CurrencyFormatOptions): string => {
  const formatter = new Intl.NumberFormat(options.intl, {
    currency: options.currency,
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
  });

  const formattedValue = formatter.format(value);

  if (options.siteId === MEXICO_ACCRONYM) {
    return formattedValue.replace(/^(\D+)/, '$1 ');
  }

  return formattedValue;
};
