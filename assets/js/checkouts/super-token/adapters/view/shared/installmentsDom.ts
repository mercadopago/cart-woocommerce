/**
 * Shared DOM helpers for the installments `<select>` of card and consumer-credits rows: the id
 * conventions and the error-state / selected / shared-field operations. All checkout-DOM only (no
 * globals). Ported from the legacy `setInstallmentsErrorState` (payment-methods.js:1681-1698),
 * `installmentsWasSelected` (1675-1678) and the `#cardInstallments` mirroring in the change handler.
 */
import type { PaymentMethod } from '@super-token/types/external-globals';
import { paymentMethodIdentifier } from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { SHARED_STYLES } from './styles';

const CARD_INSTALLMENTS_FIELD_ID = 'cardInstallments';

export function installmentsSelectId(paymentMethod: PaymentMethod): string {
  return `mp-super-token-installments-select-${paymentMethodIdentifier(paymentMethod)}`;
}

export function installmentsErrorHelperId(paymentMethod: PaymentMethod): string {
  return `mp-super-token-installments-error-${paymentMethodIdentifier(paymentMethod)}`;
}

export function taxInfoElementId(paymentMethod: PaymentMethod): string {
  return `mp-super-token-installments-tax-info-${paymentMethodIdentifier(paymentMethod)}`;
}

export function findInstallmentsSelect(row: HTMLElement, paymentMethod: PaymentMethod): HTMLSelectElement | null {
  return row.querySelector<HTMLSelectElement>(`#${CSS.escape(installmentsSelectId(paymentMethod))}`);
}

export function setInstallmentsErrorState(paymentMethod: PaymentMethod, hasError: boolean): void {
  const selectId = installmentsSelectId(paymentMethod);
  const select = document.getElementById(selectId);
  const label = document.querySelector(`label[for="${selectId}"]`);
  const errorHelper = document.getElementById(installmentsErrorHelperId(paymentMethod));
  if (!select || !label || !errorHelper) {
    return;
  }
  if (hasError) {
    (errorHelper as HTMLElement).style.display = 'flex';
    select.classList.add(SHARED_STYLES.INSTALLMENTS_ERROR);
    label.classList.add(SHARED_STYLES.INSTALLMENTS_LABEL_ERROR);
  } else {
    (errorHelper as HTMLElement).style.display = 'none';
    select.classList.remove(SHARED_STYLES.INSTALLMENTS_ERROR);
    label.classList.remove(SHARED_STYLES.INSTALLMENTS_LABEL_ERROR);
  }
}

export function installmentsWasSelected(paymentMethod: PaymentMethod): boolean {
  const select = document.getElementById(installmentsSelectId(paymentMethod));
  return !!(select as HTMLSelectElement | null)?.value;
}

export function syncCardInstallments(value: string): void {
  const field = document.getElementById(CARD_INSTALLMENTS_FIELD_ID) as HTMLInputElement | null;
  if (field) {
    field.value = value;
  }
}
