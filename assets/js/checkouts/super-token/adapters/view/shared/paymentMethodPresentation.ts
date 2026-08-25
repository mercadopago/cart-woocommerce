/**
 * Presentation resolution shared by both variant views: the display name and thumbnail
 * a saved method is rendered with, plus the interest-free installment count. Ported 1:1
 * from the legacy `normalizeAccountPaymentMethods`, `buildAccountMoneyName`,
 * `buildConsumerCreditsName` and `numberOfInstallmentsWithoutFee`
 * (v2/v2.1 entities/super-token-payment-methods.js) — but non-mutating: it returns a
 * view model instead of writing back onto the payment method.
 *
 * The ONLY per-variant difference in a row lives in `RowPresentation`: how a Mercado
 * Pago credit card is presented and whether an account-money row gets its extra class.
 * Everything else is identical across v2/v2.1, so it stays here with no `if (variant)`.
 */
import type { AccountMoneyPaymentMethod, PaymentMethod } from '@super-token/types/external-globals';
import {
  isAccountMoney,
  isConsumerCredits,
  isCreditCard,
  isDebitCard,
  isMercadoPagoCard,
  isMercadoPagoCreditCard,
  isPrepaidCard,
  userHasAccountMoney,
  userHasAccountMoneyInvested,
} from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { BRAZIL_ACCRONYM, MEXICO_ACCRONYM, MP_CARD_BLUE_SITES } from '@super-token/core/constants';
import type { VariantViewDeps } from '../VariantViewDeps';

/** Non-breaking space, kept in the consumer-credits copy so "Mercado Pago" never wraps. */
const NBSP = '\u00a0';

/** The per-variant row seam. v2 = no MP credit-card case, no account-money class. */
export interface RowPresentation {
  /**
   * How to present a Mercado Pago credit card. v2: null (treat as a regular card). The concrete
   * strategies close over the view deps they need, so none are passed in here.
   */
  mercadoPagoCreditCard(paymentMethod: PaymentMethod): { name: string; thumbnail: string } | null;
  /** Extra CSS classes for an account-money row. v2: []; v2.1: [ACCOUNT_MONEY_ROW]. */
  accountMoneyRowClasses(): string[];
}

export interface PaymentMethodViewModel {
  name: string;
  thumbnail: string;
  suppressLastFour: boolean;
  extraClasses: string[];
}

/** MLA/MLM use the blue Mercado Pago card icon; every other site uses the dark one (RN-7). */
export function mpCardThumbnailPath(deps: VariantViewDeps): string {
  return MP_CARD_BLUE_SITES.includes(deps.siteId)
    ? deps.thumbnails.mpLogoBluePath
    : deps.thumbnails.mpLogoDarkPath;
}

export function buildAccountMoneyName(
  paymentMethod: AccountMoneyPaymentMethod,
  deps: VariantViewDeps,
): string {
  if (deps.siteId !== MEXICO_ACCRONYM) {
    return deps.copy.accountMoneyText;
  }
  const hasMoney = userHasAccountMoney(paymentMethod);
  const hasInvested = userHasAccountMoneyInvested(paymentMethod);
  if (hasMoney && hasInvested) return deps.copy.accountMoneyWalletWithInvestmentText;
  if (hasMoney) return deps.copy.accountMoneyWalletText;
  if (hasInvested) return deps.copy.accountMoneyInvestmentText;
  return deps.copy.accountMoneyAvailableText;
}

// Hardcoded copy in the legacy source. The legacy emitted the &nbsp; entity inside innerHTML;
// here the name is rendered via textContent, so NBSP is the real non-breaking-space character.
export function buildConsumerCreditsName(siteId: string): string {
  switch (siteId) {
    case MEXICO_ACCRONYM:
      return `Meses sin Tarjeta con Mercado${NBSP}Pago`;
    case BRAZIL_ACCRONYM:
      return `Linha de Crédito Mercado${NBSP}Pago`;
    default:
      return `Cuotas sin Tarjeta con Mercado${NBSP}Pago`;
  }
}

/** Largest interest-free installment count offered (drives the value-prop pill). Pure. */
export function installmentsWithoutFee(paymentMethod: PaymentMethod): number {
  if (!isCreditCard(paymentMethod) && !isConsumerCredits(paymentMethod)) {
    return 0;
  }
  const installments = 'installments' in paymentMethod ? paymentMethod.installments : undefined;
  if (!installments?.length) {
    return 0;
  }
  if (isConsumerCredits(paymentMethod)) {
    // Temporary: filters by rate only, since installment_rate_collector is not yet in the API.
    const free = installments.filter((installment) => installment.installment_rate === 0);
    return free.length > 0 ? free[free.length - 1].installments : 0;
  }
  const free = installments.filter(
    (installment) =>
      installment.installment_rate === 0 &&
      installment.installment_rate_collector?.includes('MERCADOPAGO'),
  );
  return free.length > 0 ? free[free.length - 1].installments : 0;
}

function resolveCardThumbnail(paymentMethod: PaymentMethod, deps: VariantViewDeps): string {
  return (
    deps.thumbnails.paymentMethodsThumbnails[paymentMethod.id] ||
    paymentMethod.thumbnail ||
    deps.thumbnails.whiteCardPath
  );
}

export function resolvePaymentMethodView(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  presentation: RowPresentation,
): PaymentMethodViewModel {
  if (isAccountMoney(paymentMethod)) {
    return {
      name: buildAccountMoneyName(paymentMethod, deps),
      thumbnail: deps.thumbnails.yellowWalletPath,
      suppressLastFour: false,
      extraClasses: presentation.accountMoneyRowClasses(),
    };
  }

  if (isConsumerCredits(paymentMethod)) {
    return {
      name: buildConsumerCreditsName(deps.siteId),
      thumbnail: deps.thumbnails.yellowMoneyPath,
      suppressLastFour: false,
      extraClasses: [],
    };
  }

  if (isMercadoPagoCard(paymentMethod)) {
    return {
      name: deps.copy.mercadoPagoCardName,
      thumbnail: resolveCardThumbnail(paymentMethod, deps),
      suppressLastFour: false,
      extraClasses: [],
    };
  }

  if (isPrepaidCard(paymentMethod)) {
    return {
      name: paymentMethod.name,
      thumbnail: resolveCardThumbnail(paymentMethod, deps),
      suppressLastFour: false,
      extraClasses: [],
    };
  }

  if (isCreditCard(paymentMethod) || isDebitCard(paymentMethod)) {
    const mpCreditCard = isMercadoPagoCreditCard(paymentMethod)
      ? presentation.mercadoPagoCreditCard(paymentMethod)
      : null;
    if (mpCreditCard) {
      return { ...mpCreditCard, suppressLastFour: true, extraClasses: [] };
    }
    const cardKind = isCreditCard(paymentMethod) ? 'Crédito' : 'Débito';
    return {
      name: `${paymentMethod.issuer?.name ?? paymentMethod.name} ${cardKind}`,
      thumbnail: resolveCardThumbnail(paymentMethod, deps),
      suppressLastFour: false,
      extraClasses: [],
    };
  }

  // Unreachable for the known SDK union; defensive for an unexpected runtime type.
  const unknownMethod = paymentMethod as { name?: string; thumbnail?: string };
  return {
    name: unknownMethod.name ?? '',
    thumbnail: unknownMethod.thumbnail ?? '',
    suppressLastFour: false,
    extraClasses: [],
  };
}
