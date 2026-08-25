/**
 * Builds the interactive consumer-credits (digital_currency) row in the tree: presentation +
 * selection wiring (buildInteractiveRow), the detail accordion (installments `<select>` with a
 * placeholder, plus hint / due-date / debit-auto / legal slots) and the asynchronous credits
 * behaviour. Ported from the legacy `createPaymentMethodElement` consumer-credits branch
 * (payment-methods.js:2081-2192), `buildConsumerCreditsDetailsInnerHTML` (1552-1606),
 * `buildConsumerCreditsDetailsDueDate` (1608-1623), `buildMLBConsumerCreditsLegalText` (1700-1710)
 * and `formatDateToDayAndMonth` (1080-1113).
 *
 * The site-specific hint (pure) is injected from the domain core; the SDK contract, the fast-payment
 * token and the metrics stay behind the injected session.
 */
import type { Installment, PaymentMethod } from '@super-token/types/external-globals';
import type { RenderRowSession } from '@super-token/ports';
import type { InstallmentOption } from '@super-token/core/paymentMethods/BasePaymentMethodWithInstallments';
import { isConsumerCredits } from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { BRAZIL_ACCRONYM } from '@super-token/core/constants';
import type { VariantViewDeps } from '../VariantViewDeps';
import { el } from './dom';
import { SHARED_STYLES } from './styles';
import type { RowPresentation } from './paymentMethodPresentation';
import { buildInteractiveRow } from './interactiveRow';
import { buildInputHelper } from './cardRow';
import { findInstallmentsSelect, installmentsErrorHelperId, installmentsSelectId, installmentsWasSelected, setInstallmentsErrorState, syncCardInstallments } from './installmentsDom';

const DISPATCHER_MISSING_CONTEXT = 'super_token_consumer_credits_installments_setup';
const CONTRACT_CUSTOMIZATION = { textColor: '#000000', textSize: '13px', linkColor: '#3483FA' };
const HINT_ID = 'mp-consumer-credits-hint';
const DUE_DATE_ID = 'mp-consumer-credits-due-date';
const DEBIT_AUTO_ID = 'mp-consumer-credits-debit-auto-text';
const LEGAL_TEXT_ID = 'mp-consumer-credits-legal-text';
const NO_HINT_CONTENT = 'no_hint_content_to_render';

const MONTHS_MAPPING: Record<string, string> = {
  '01': 'jan', '02': 'feb', '03': 'mar', '04': 'apr', '05': 'may', '06': 'jun',
  '07': 'jul', '08': 'aug', '09': 'sep', '10': 'oct', '11': 'nov', '12': 'dec',
};

function formatDateToDayAndMonth(isoDate: string | undefined, deps: VariantViewDeps): string {
  if (!isoDate) {
    return '';
  }
  const dateParts = isoDate.split('-');
  if (dateParts.length !== 3) {
    return isoDate;
  }
  const [, month, day] = dateParts;
  const monthText = deps.monthsAbbreviated[MONTHS_MAPPING[month]] ?? month;
  return `${parseInt(day, 10)}/${monthText}`;
}

function buildDetailsSection(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  options: InstallmentOption[],
): HTMLElement {
  const selectId = installmentsSelectId(paymentMethod);
  return el('section', {
    classes: [SHARED_STYLES.PAYMENT_METHOD_DETAILS, SHARED_STYLES.PAYMENT_METHOD_HIDE],
    children: [
      el('div', {
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
            children: [
              el('option', { attrs: { disabled: '', selected: '', value: '' }, text: deps.copy.installmentsPlaceholder }),
              ...options.map((option) => el('option', { attrs: { value: option.value }, text: option.title })),
            ],
          }),
          buildInputHelper(deps.copy.installmentsRequiredMessage, installmentsErrorHelperId(paymentMethod)),
        ],
      }),
      el('div', { attrs: { id: HINT_ID, style: 'display: none;' } }),
      el('div', { classes: ['mp-consumer-credits-due-date'], attrs: { id: DUE_DATE_ID, style: 'display: none;' } }),
      el('div', {
        classes: ['mp-consumer-credits-debit-auto-text'],
        attrs: { id: DEBIT_AUTO_ID, style: 'display: none; text-align: center;' },
      }),
      el('div', { attrs: { id: LEGAL_TEXT_ID, style: 'display: none;' } }),
    ],
  });
}

// Fills the due-date slot via DOM APIs (the date comes from the SDK, so it must never reach an
// HTML sink); throws when the slot is absent (mirrors the legacy method, whose throw is caught
// by the change handler to record the due-date failure metric).
function renderDueDate(paymentMethod: PaymentMethod, deps: VariantViewDeps): void {
  const element = document.getElementById(DUE_DATE_ID);
  if (!element) {
    throw new Error('Consumer credits due date element not found');
  }
  const nextDueDate = 'next_due_date' in paymentMethod ? paymentMethod.next_due_date : undefined;
  element.textContent = '';
  element.appendChild(
    el('span', {
      attrs: { style: 'font-weight: 400 !important;' },
      children: [
        document.createTextNode(`${deps.copy.consumerCreditsDueDateText} `),
        el('b', {
          attrs: { style: 'font-weight: 600 !important;' },
          text: formatDateToDayAndMonth(nextDueDate, deps),
        }),
        document.createTextNode('.'),
      ],
    }),
  );
}

function renderMlbLegalText(deps: VariantViewDeps): void {
  const element = document.getElementById(DEBIT_AUTO_ID);
  if (!element) {
    return;
  }
  element.textContent = '';
  element.appendChild(el('span', { text: deps.copy.consumerCreditsDebitAutoText }));
}

function wireConsumerCredits(
  row: HTMLElement,
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  session: RenderRowSession,
  hint: (installment: Installment) => string,
): void {
  const select = findInstallmentsSelect(row, paymentMethod);
  if (!select || !isConsumerCredits(paymentMethod)) {
    return;
  }

  select.addEventListener('blur', () => {
    setInstallmentsErrorState(paymentMethod, !installmentsWasSelected(paymentMethod));
  });

  session.reportInstallmentDispatcherMissing(DISPATCHER_MISSING_CONTEXT);

  const parameters = {
    fastPaymentToken: session.getFastPaymentToken(),
    pricingId: paymentMethod.credits_pricing_id,
    pseudotoken: paymentMethod.token,
    customization: CONTRACT_CUSTOMIZATION,
  };

  session
    .renderCreditsContract(LEGAL_TEXT_ID, parameters)
    .then((contractController) => {
      session.recordCreditsContractRendered(true);

      document.getElementById(LEGAL_TEXT_ID)?.addEventListener('click', (event) => {
        const target = event.target as HTMLElement;
        if (target.tagName === 'A') {
          session.recordOpenCreditsInfoModal(target.textContent?.trim() ?? '');
        }
      });

      select.addEventListener('change', (event) => {
        const selectedValue = (event.target as HTMLSelectElement).value;
        if (!selectedValue) {
          return;
        }
        session.dispatchInstallmentsFilledField();

        if (deps.siteId === BRAZIL_ACCRONYM) {
          renderMlbLegalText(deps);
          const debitAuto = document.getElementById(DEBIT_AUTO_ID);
          if (debitAuto) {
            debitAuto.style.display = 'block';
          }
        }

        syncCardInstallments(`${parseInt(selectedValue, 10)}`);

        const selectedInstallment = (paymentMethod.installments ?? []).find(
          (installment) => installment.installments === parseInt(selectedValue, 10),
        );
        if (selectedInstallment) {
          applySelectedInstallmentDetails(paymentMethod, deps, session, hint, selectedInstallment);
        }

        session.updateCreditsContract(contractController, selectedValue);
      });

      // Restore the selection (e.g. after a payment error) once the change listener is live.
      if (select.value) {
        select.dispatchEvent(new Event('change'));
      }
    })
    .catch((error) => {
      session.recordCreditsContractRendered(false, error);
    });
}

function applySelectedInstallmentDetails(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  session: RenderRowSession,
  hint: (installment: Installment) => string,
  selectedInstallment: Installment,
): void {
  const hintElement = document.getElementById(HINT_ID);
  if (hintElement) {
    try {
      const hintContent = hint(selectedInstallment);
      hintElement.innerHTML = hintContent;
      hintElement.style.display = hintContent ? 'block' : 'none';
      session.recordConsumerCreditsHint(!!hintContent, hintContent ? undefined : NO_HINT_CONTENT);
    } catch (error) {
      hintElement.style.display = 'none';
      session.recordConsumerCreditsHint(false, error);
    }
  }

  try {
    renderDueDate(paymentMethod, deps);
    session.recordConsumerCreditsDueDate(true);
    const dueDate = document.getElementById(DUE_DATE_ID);
    if (dueDate) {
      dueDate.style.display = 'block';
    }
  } catch (error) {
    session.recordConsumerCreditsDueDate(false, error);
  }

  const legal = document.getElementById(LEGAL_TEXT_ID);
  if (legal) {
    legal.style.display = 'block';
  }
}

export function buildConsumerCreditsRow(
  paymentMethod: PaymentMethod,
  deps: VariantViewDeps,
  presentation: RowPresentation,
  session: RenderRowSession,
  installmentOptions: (paymentMethod: PaymentMethod) => InstallmentOption[],
  hint: (installment: Installment) => string,
): HTMLElement {
  const row = buildInteractiveRow(paymentMethod, deps, presentation, session);
  row.appendChild(buildDetailsSection(paymentMethod, deps, installmentOptions(paymentMethod)));
  wireConsumerCredits(row, paymentMethod, deps, session, hint);
  return row;
}
