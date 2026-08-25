/**
 * Builds an interactive card row (credit / debit / prepaid) entirely in the tree: the presentation
 * skeleton and selection wiring from `buildInteractiveRow`, plus the detail accordion — the
 * installments `<select>` (credit only) with its tax-info line, and the security-code field — and
 * the installments behaviour. Ported from the legacy `createPaymentMethodElement` detail + wiring
 * (payment-methods.js:2015-2079) and `buildCreditCardDetailsInnerHTML` (1625-1673).
 *
 * DOM-only work (the row's own select, its error state and the shared #cardInstallments field) is
 * done through `installmentsDom`; the platform globals (CheckoutPage / MPCheckoutFieldsDispatcher /
 * sendMetric) stay behind the injected session.
 */
import type { Installment, PaymentMethod } from '@super-token/types/external-globals';
import type { RenderRowSession } from '@super-token/ports';
import { isCreditCard } from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import type { InstallmentOption } from '@super-token/core/paymentMethods/BasePaymentMethodWithInstallments';
import type { VariantViewDeps } from '../VariantViewDeps';
import { el } from './dom';
import { SHARED_STYLES } from './styles';
import type { RowPresentation } from './paymentMethodPresentation';
import { buildInteractiveRow } from './interactiveRow';
import { buildSecurityCodeField } from './securityCodeField';
import {
  findInstallmentsSelect,
  installmentsErrorHelperId,
  installmentsSelectId,
  installmentsWasSelected,
  setInstallmentsErrorState,
  syncCardInstallments,
  taxInfoElementId,
} from './installmentsDom';

const INSTALLMENTS_FILLED_METHOD_TYPE = 'credit_card';
const DISPATCHER_MISSING_CONTEXT = 'super_token_installments_setup';

// `<input-helper>` is a custom element (outside HTMLElementTagNameMap), so it is built directly.
export function buildInputHelper(message: string, inputId: string): HTMLElement {
  const inputHelper = document.createElement('input-helper');
  inputHelper.setAttribute('isVisible', 'false');
  inputHelper.setAttribute('type', 'error');
  inputHelper.setAttribute('message', message);
  inputHelper.setAttribute('input-id', inputId);
  return inputHelper;
}

function buildInstallmentsField(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  options: InstallmentOption[],
): HTMLElement {
  const selectId = installmentsSelectId(paymentMethod);
  return el('div', {
    classes: [SHARED_STYLES.INSTALLMENTS_SELECT_CONTAINER],
    children: [
      el('label', {
        classes: [SHARED_STYLES.INPUT_LABEL],
        attrs: { for: selectId },
        text: deps.copy.installmentsInputTitle,
      }),
      el('select', {
        classes: [SHARED_STYLES.SELECT_INPUT],
        attrs: { 'data-checkout': 'installments', name: 'installments', id: selectId },
        children: options.map((option, index) =>
          el('option', {
            attrs: index === 0 ? { value: option.value, selected: 'selected' } : { value: option.value },
            text: option.title,
          }),
        ),
      }),
      buildInputHelper(deps.copy.installmentsRequiredMessage, installmentsErrorHelperId(paymentMethod)),
      el('div', {
        classes: [SHARED_STYLES.INSTALLMENTS_TAX_INFO],
        attrs: { id: taxInfoElementId(paymentMethod), style: 'display: none;' },
      }),
    ],
  });
}

function buildCardDetailsSection(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  installmentOptions: (paymentMethod: PaymentMethod) => InstallmentOption[],
): HTMLElement {
  const wrapperChildren: (Node | null)[] = [];

  if (isCreditCard(paymentMethod) && paymentMethod.installments?.length) {
    wrapperChildren.push(buildInstallmentsField(paymentMethod, deps, installmentOptions(paymentMethod)));
  }
  wrapperChildren.push(buildSecurityCodeField(paymentMethod, deps));

  const wrapper = el('div', {
    classes: [SHARED_STYLES.METHOD_DETAILS_WRAPPER],
    children: wrapperChildren,
  });

  return el('section', {
    classes: [SHARED_STYLES.PAYMENT_METHOD_DETAILS, SHARED_STYLES.PAYMENT_METHOD_HIDE],
    children: [wrapper],
  });
}

function wireInstallments(
  row: HTMLElement,
  paymentMethod: PaymentMethod,
  installments: Installment[],
  session: RenderRowSession,
): void {
  const dropdown = findInstallmentsSelect(row, paymentMethod);
  if (!dropdown) {
    return;
  }

  session.reportInstallmentDispatcherMissing(DISPATCHER_MISSING_CONTEXT);

  dropdown.addEventListener('change', (event) => {
    const selected = (event.target as HTMLSelectElement).value;
    if (!selected) {
      return;
    }
    session.installmentSelected(INSTALLMENTS_FILLED_METHOD_TYPE);
    setInstallmentsErrorState(paymentMethod, false);
    session.updateInstallmentsTaxInfo(selected, taxInfoElementId(paymentMethod), installments);
    syncCardInstallments(selected);
  });

  dropdown.addEventListener('blur', () => {
    setInstallmentsErrorState(paymentMethod, !installmentsWasSelected(paymentMethod));
  });

  // Restore the tax info + shared field when a value is already selected (e.g. after a payment error).
  if (dropdown.value) {
    syncCardInstallments(dropdown.value);
    session.updateInstallmentsTaxInfo(dropdown.value, taxInfoElementId(paymentMethod), installments);
  }
}

export function buildCardRow(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  presentation: RowPresentation,
  session: RenderRowSession,
  installmentOptions: (paymentMethod: PaymentMethod) => InstallmentOption[],
): HTMLElement {
  const row = buildInteractiveRow(paymentMethod, deps, presentation, session);
  row.appendChild(buildCardDetailsSection(paymentMethod, deps, installmentOptions));

  if (isCreditCard(paymentMethod) && paymentMethod.installments?.length) {
    wireInstallments(row, paymentMethod, paymentMethod.installments, session);
  }

  return row;
}
