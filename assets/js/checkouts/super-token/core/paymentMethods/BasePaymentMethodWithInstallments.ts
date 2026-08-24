/**
 * Common installment behavior for the only two methods that have installments —
 * credit card and consumer credits (RN-8). Encodes interest-free counting (RN-4)
 * and the installment title, including the third-party bank-interest asterisk (RN-5).
 * Pure.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): numberOfInstallmentsWithoutFee
 * 1029-1046, needsBankInterestDisclaimer 1067-1069, buildInstallmentTitle 1122-1142.
 */

import type { Installment, PaymentMethod } from '@super-token/types/external-globals';
import {
  COLOMBIA_ACCRONYM,
  COLOMBIA_INSTALLMENTS_LIMIT,
  COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER,
} from '@super-token/core/constants';
import {
  isConsumerCredits,
  isCreditCard,
} from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { formatCurrency } from '@super-token/core/shared/formatting';
import { BasePaymentMethod } from '@super-token/core/paymentMethods/BasePaymentMethod';

/** One option of the installment `<select>`: the number of installments and its display title. */
export interface InstallmentOption {
  value: string;
  title: string;
}

export abstract class BasePaymentMethodWithInstallments extends BasePaymentMethod {
  requiresInstallments(): boolean {
    return true;
  }

  /**
   * Highest interest-free installment count (RN-4). Consumer credits filter by
   * installment_rate === 0 (the API has no rate collector yet); credit cards also
   * require the MERCADOPAGO collector.
   */
  numberOfInstallmentsWithoutFee(paymentMethod: PaymentMethod): number {
    if (!isCreditCard(paymentMethod) && !isConsumerCredits(paymentMethod)) {
      return 0;
    }

    if (!paymentMethod.installments || !paymentMethod.installments.length) {
      return 0;
    }

    if (isConsumerCredits(paymentMethod)) {
      const installmentsWithoutFee = paymentMethod.installments.filter(
        (installment) => installment.installment_rate === 0,
      );
      return installmentsWithoutFee.length > 0
        ? installmentsWithoutFee[installmentsWithoutFee.length - 1].installments
        : 0;
    }

    const installmentsWithoutFee = paymentMethod.installments.filter(
      (installment) =>
        installment.installment_rate === 0 &&
        installment.installment_rate_collector.includes('MERCADOPAGO'),
    );
    return installmentsWithoutFee.length > 0
      ? installmentsWithoutFee[installmentsWithoutFee.length - 1].installments
      : 0;
  }

  needsBankInterestDisclaimer(): boolean {
    return COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER.includes(this.config.siteId);
  }

  /** Colombia caps the installment options; every other site keeps them all (RN, legacy
   *  getInstallmentsLimit 1153-1157). */
  getInstallmentsLimit(installments: Installment[]): Installment[] {
    return this.config.siteId === COLOMBIA_ACCRONYM
      ? installments.slice(0, Math.min(COLOMBIA_INSTALLMENTS_LIMIT, installments.length))
      : installments;
  }

  /** The value/title pairs for the installment `<select>` options (legacy normalizeInstallments
   *  1159-1176; the MLA taxInfo it also attached is never read by the select, so it is dropped). */
  normalizedInstallments(installments: Installment[]): InstallmentOption[] {
    return this.getInstallmentsLimit(installments).map((installment) => ({
      value: `${installment.installments}`,
      title: this.buildInstallmentTitle(installment),
    }));
  }

  /**
   * Title shown for an installment option (RN-5). The trailing asterisk marks a
   * third-party interest-free installment on sites that show the bank disclaimer.
   */
  buildInstallmentTitle(installment: Installment): string {
    const installmentNumber = installment.installments;
    const installmentAmount = this.formatAmount(installment.installment_amount);
    const hasRate = installment.installment_rate !== 0;
    const isThirdParty = installment.installment_rate_collector.includes('THIRD_PARTY');
    const totalAmount = this.formatAmount(installment.total_amount);

    if (installmentNumber === 1) {
      return `${installmentNumber}x ${totalAmount}`;
    }

    if (hasRate) {
      return `${installmentNumber}x ${installmentAmount} (${totalAmount})`;
    }

    if (this.needsBankInterestDisclaimer() && isThirdParty && !hasRate) {
      return `${installmentNumber}x ${installmentAmount} (${totalAmount})*`;
    }

    return `${installmentNumber}x ${installmentAmount} ${this.config.copy.installmentsInterestFreeOptionText}`;
  }

  private formatAmount(value: number): string {
    return formatCurrency(value, {
      intl: this.config.intl,
      currency: this.config.currency,
      siteId: this.config.siteId,
    });
  }
}
