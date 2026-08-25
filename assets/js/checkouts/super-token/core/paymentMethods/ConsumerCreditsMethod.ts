/**
 * Consumer credits ("digital_currency") — has installments (RN-8). Decorated with the
 * yellow money icon and a site-specific name. The names are hardcoded literals in the
 * legacy code (not localized params), preserved verbatim including the &nbsp; entity.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): buildConsumerCreditsName 1018-1027,
 * normalize consumer-credits branch 2264-2267.
 */

import type { Installment, PaymentMethod } from '@super-token/types/external-globals';
import { BRAZIL_ACCRONYM, MEXICO_ACCRONYM } from '@super-token/core/constants';
import { isConsumerCredits } from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { escapeHtml } from '@super-token/core/shared/escapeHtml';
import { BasePaymentMethodWithInstallments } from '@super-token/core/paymentMethods/BasePaymentMethodWithInstallments';

const TWO_DECIMALS = 2;

export class ConsumerCreditsMethod extends BasePaymentMethodWithInstallments {
  matches(paymentMethod: PaymentMethod): boolean {
    return isConsumerCredits(paymentMethod);
  }

  decorate(paymentMethod: PaymentMethod): PaymentMethod {
    if (!isConsumerCredits(paymentMethod)) return paymentMethod;

    paymentMethod.thumbnail = this.config.thumbnails.yellowMoneyPath;
    paymentMethod.name = this.buildConsumerCreditsName();
    return paymentMethod;
  }

  private buildConsumerCreditsName(): string {
    switch (this.config.siteId) {
      case MEXICO_ACCRONYM:
        return 'Meses sin Tarjeta con Mercado&nbsp;Pago';
      case BRAZIL_ACCRONYM:
        return 'Linha de Crédito Mercado&nbsp;Pago';
      default:
        return 'Cuotas sin Tarjeta con Mercado&nbsp;Pago';
    }
  }

  /**
   * The site-specific legal hint (an HTML string, assigned via innerHTML downstream) for the
   * selected consumer-credits installment. Ported from the legacy `buildConsumerCreditsHint`
   * (payment-methods.js:1472-1550); throws when the installment carries no conditions.
   */
  buildConsumerCreditsHint(installment: Installment): string {
    const rawLabels =
      installment?.labels?.find((label) => label.toLowerCase().includes('|'))?.toLowerCase() ?? '';
    const conditions = rawLabels.split('|').reduce<Record<string, string>>((accumulator, label) => {
      const [key, value] = label.split('_');
      accumulator[key] = value;
      return accumulator;
    }, {});

    if (!installment?.consumer_credits?.conditions) {
      throw new Error('no_installment_conditions');
    }

    const copy = this.config.copy.consumerCreditsHint;

    switch (this.config.siteId) {
      case BRAZIL_ACCRONYM: {
        const parts: string[] = [];
        if (conditions.tem && conditions.tea) {
          parts.push(`${copy.interestRateMlb}: ${escapeHtml(conditions.tem)} ${copy.perMonth} ${escapeHtml(conditions.tea)} ${copy.perYear}`);
        }
        if (conditions.cetm && conditions.ceta) {
          parts.push(`${copy.effectiveTotalCostMlb}: ${escapeHtml(conditions.cetm)} ${copy.perMonth} ${escapeHtml(conditions.ceta)} ${copy.perYear}`);
        }
        if (conditions.iof) {
          const iofAmount = installment.installment_iof_amount || 0;
          if (iofAmount > 0) {
            const iofAmountFormatted = iofAmount.toFixed(TWO_DECIMALS).replace('.', ',');
            parts.push(`${copy.iofMlb}: R$ ${iofAmountFormatted} (${escapeHtml(conditions.iof)})`);
          }
        }
        const borrowedAmount = installment.total_amount - (installment.installment_iof_amount || 0);
        parts.push(`${copy.borrowedAmountMlb}: R$ ${borrowedAmount.toFixed(TWO_DECIMALS).replace('.', ',')}`);
        return `${parts.join('. ')}.`;
      }
      case MEXICO_ACCRONYM: {
        const mexParts: string[] = [];
        if (conditions.cat) {
          mexParts.push(`${copy.catMlm}: ${escapeHtml(conditions.cat)} ${copy.noIvaMlm}`);
        }
        if (conditions.tna) {
          mexParts.push(`${copy.tnaMlm}: ${escapeHtml(conditions.tna)}`);
        }
        if (mexParts.length > 0) {
          mexParts.push(`${copy.systemAmortizationMlm}`);
          return `${mexParts.join('. ')}.`;
        }
        return '';
      }
      default: {
        const argParts: string[] = [];
        if (conditions.cftea) {
          argParts.push(`<strong>${copy.cfteaMla}: ${escapeHtml(conditions.cftea)}</strong>`);
        }
        if (conditions.tna) {
          argParts.push(`${copy.tnaMla}: ${escapeHtml(conditions.tna)}`);
        }
        if (conditions.tea) {
          argParts.push(`${copy.teaMla}: ${escapeHtml(conditions.tea)}`);
        }
        if (argParts.length > 0) {
          return `${argParts.join(' - ')}. ${copy.fixedRate}`;
        }
        return '';
      }
    }
  }
}
