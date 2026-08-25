/**
 * Ordering and capping of the fetched account payment methods (RN-1). Caps saved
 * cards at MAX_CREDIT_CARDS (the single source, also reused by the view's grouping)
 * and orders cards-first (default) or account-money-first per store preference. Pure.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): reorderAccountPaymentMethods 2241-2255.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';
import {
  MAX_CREDIT_CARDS,
  PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST,
  PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST,
} from '@super-token/core/constants';
import {
  isAccountMoney,
  isConsumerCredits,
  isCreditCard,
  isDebitCard,
  isPrepaidCard,
} from '@super-token/core/checkoutSession/PaymentMethodClassifier';

export class PaymentMethodCatalog {
  private readonly order: string;

  constructor(paymentMethodsOrder?: string) {
    this.order = paymentMethodsOrder || PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST;
  }

  reorderAccountPaymentMethods(accountPaymentMethods: PaymentMethod[]): PaymentMethod[] {
    const limitedCards = this.limitCardOptions(accountPaymentMethods);
    const accountMoneyOption = accountPaymentMethods.find((pm) => isAccountMoney(pm));
    const consumerCreditsOption = accountPaymentMethods.find((pm) => isConsumerCredits(pm));

    const isAccountMoneyFirst =
      this.order === PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST && !!accountMoneyOption;

    const moneySpecializedOptions: PaymentMethod[] = [];
    if (accountMoneyOption) moneySpecializedOptions.push(accountMoneyOption);
    if (consumerCreditsOption) moneySpecializedOptions.push(consumerCreditsOption);

    const result: (PaymentMethod | PaymentMethod[])[] = isAccountMoneyFirst
      ? [moneySpecializedOptions, ...limitedCards]
      : [...limitedCards, moneySpecializedOptions];

    return result.flat();
  }

  /** Caps card-type methods (credit, debit, prepaid) at MAX_CREDIT_CARDS (RN-1). */
  private limitCardOptions(accountPaymentMethods: PaymentMethod[]): PaymentMethod[] {
    return accountPaymentMethods
      .filter((pm) => isCreditCard(pm) || isDebitCard(pm) || isPrepaidCard(pm))
      .slice(0, MAX_CREDIT_CARDS);
  }
}
