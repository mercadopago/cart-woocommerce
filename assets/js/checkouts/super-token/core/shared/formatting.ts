/**
 * Pure currency formatting shared by the installment rules. Uses the standard
 * language `Intl` (not a window/app global), so the core stays platform-free.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): formatCurrency 1106-1120.
 */

import { MEXICO_ACCRONYM, SITES_WITHOUT_DECIMAL_AMOUNTS } from '@super-token/core/constants';

export interface CurrencyFormatOptions {
  /** BCP-47 locale tag. */
  intl: string;
  /** ISO currency code. */
  currency: string;
  /** Uppercase site id — MLM inserts a space after the currency symbol; MLC/MCO drop the decimals. */
  siteId: string;
}

export const formatCurrency = (value: number, options: CurrencyFormatOptions): string => {
  // Intl already drops CLP decimals, but formats COP with two; both sites show integer amounts.
  const fractionDigits = SITES_WITHOUT_DECIMAL_AMOUNTS.includes(options.siteId)
    ? { minimumFractionDigits: 0, maximumFractionDigits: 0 }
    : {};
  const formatter = new Intl.NumberFormat(options.intl, {
    currency: options.currency,
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    ...fractionDigits,
  });

  const formattedValue = formatter.format(value);

  if (options.siteId === MEXICO_ACCRONYM) {
    return formattedValue.replace(/^(\D+)/, '$1 ');
  }

  return formattedValue;
};
