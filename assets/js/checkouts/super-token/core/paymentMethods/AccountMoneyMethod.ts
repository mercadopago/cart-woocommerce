/**
 * Account money (wallet) — no installments. Decorated with the yellow wallet icon
 * and a site-aware name; only Mexico distinguishes wallet vs. invested balance.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): buildAccountMoneyName 997-1016,
 * normalize account-money branch 2259-2262.
 */

import type { AccountMoneyPaymentMethod, PaymentMethod } from '@super-token/types/external-globals';
import { MEXICO_ACCRONYM } from '@super-token/core/constants';
import {
  isAccountMoney,
  userHasAccountMoney,
  userHasAccountMoneyInvested,
} from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { BasePaymentMethod } from '@super-token/core/paymentMethods/BasePaymentMethod';

export class AccountMoneyMethod extends BasePaymentMethod {
  matches(paymentMethod: PaymentMethod): boolean {
    return isAccountMoney(paymentMethod);
  }

  decorate(paymentMethod: PaymentMethod): PaymentMethod {
    if (!isAccountMoney(paymentMethod)) return paymentMethod;

    paymentMethod.thumbnail = this.config.thumbnails.yellowWalletPath;
    paymentMethod.name = this.buildAccountMoneyName(paymentMethod);
    return paymentMethod;
  }

  private buildAccountMoneyName(paymentMethod: AccountMoneyPaymentMethod): string {
    const { copy } = this.config;

    if (this.config.siteId !== MEXICO_ACCRONYM) {
      return copy.accountMoneyText;
    }

    if (userHasAccountMoney(paymentMethod) && userHasAccountMoneyInvested(paymentMethod)) {
      return copy.accountMoneyWalletWithInvestmentText;
    }

    if (userHasAccountMoney(paymentMethod)) {
      return copy.accountMoneyWalletText;
    }

    if (userHasAccountMoneyInvested(paymentMethod)) {
      return copy.accountMoneyInvestmentText;
    }

    return copy.accountMoneyAvailableText;
  }
}
