/**
 * v2.1 account-money selection decoration: shows a balance line under the account-money row
 * when selected and animates it away on deselect. Ported 1:1 from the legacy
 * `applyAccountMoneySelectionDecoration` / `removeAccountMoneyBalanceLine` (v2.1).
 */
import { ACCOUNT_MONEY_TYPE } from '@super-token/core/constants';
import type { VariantViewDeps } from '../VariantViewDeps';
import { el } from '../shared/dom';
import { SHARED_STYLES } from '../shared/styles';
import { V21_STYLES } from './styles';

const ACCOUNT_MONEY_ANIMATION_MS = 300;
const TRANSITION_END_FALLBACK_MS = ACCOUNT_MONEY_ANIMATION_MS + 50;
const CLOSING_FLAG = '1';
const BALANCE_TRANSITION_PROPERTY = 'max-height';

export class V21AccountMoneyDecoration {
  constructor(private readonly deps: VariantViewDeps) {}

  decorate(row: HTMLElement): void {
    if (row?.dataset?.type !== ACCOUNT_MONEY_TYPE) {
      return;
    }

    // Remove any leftover balance line synchronously before appending the new one, so a fast
    // AM -> other -> AM toggle can't leave two balance nodes coexisting. Scoped to this row (the
    // balance line is always appended as its descendant) — matching clear()'s bounded scope — so
    // a second Super Token widget on the same page can't have its balance line removed by this one.
    row
      .querySelectorAll(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)
      .forEach((node) => node.remove());

    const content = row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_CONTENT}`);
    if (!content) {
      return;
    }

    const balanceLine = el('p', {
      classes: [V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE],
      attrs: { 'aria-live': 'polite' },
      text: this.deps.copy.accountMoneyBalanceText,
    });
    content.appendChild(balanceLine);

    // Trigger the row/balance transitions in the next frame. Stale-frame guard: if another method
    // was selected before this frame runs, the AM row is no longer selected/connected — skip it,
    // to avoid stranding an --open state on a deselected row.
    requestAnimationFrame(() => {
      if (!row.isConnected || !row.classList.contains(SHARED_STYLES.PAYMENT_METHOD_SELECTED)) {
        return;
      }
      row.classList.add(V21_STYLES.ACCOUNT_MONEY_ROW_OPEN);
      balanceLine.classList.add(V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE_OPEN);
    });

    const balanceText = this.deps.copy.accountMoneyBalanceText;
    const currentLabel = row.getAttribute('aria-label') ?? '';
    if (balanceText && !currentLabel.includes(balanceText)) {
      row.setAttribute('aria-label', `${currentLabel}. ${balanceText}`);
    }
  }

  clear(container: HTMLElement): void {
    // Restore each account-money row's aria-label (the balance text is appended on selection).
    container.querySelectorAll(`.${V21_STYLES.ACCOUNT_MONEY_ROW}`).forEach((row) => {
      const baseAriaLabel = (row as HTMLElement).dataset?.baseAriaLabel;
      if (baseAriaLabel !== undefined) {
        row.setAttribute('aria-label', baseAriaLabel);
      }
    });
    this.removeBalanceLine(container);
  }

  private removeBalanceLine(container: HTMLElement): void {
    const openRow =
      container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_ROW_OPEN}`) ??
      container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_ROW}`);
    openRow?.classList.remove(V21_STYLES.ACCOUNT_MONEY_ROW_OPEN);

    const balanceLine = container.querySelector<HTMLElement>(
      `.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`,
    );
    if (!balanceLine) {
      return;
    }

    // Already closing — avoid duplicate listeners/timers on the same node (fast toggle).
    if (balanceLine.dataset.closing === CLOSING_FLAG) {
      return;
    }
    balanceLine.dataset.closing = CLOSING_FLAG;
    balanceLine.classList.remove(V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE_OPEN);

    // Remove the node only after the close transition finishes (event-driven), so the DOM removal
    // never lands a frame before the animation ends. A timeout fallback guarantees cleanup if
    // transitionend never fires (reduced motion, detached node, etc.).
    let removed = false;
    let fallbackTimer: ReturnType<typeof setTimeout>;
    const finalize = (): void => {
      if (removed) {
        return;
      }
      removed = true;
      balanceLine.remove();
    };
    const onTransitionEnd = (event: TransitionEvent): void => {
      if (event.target === balanceLine && event.propertyName === BALANCE_TRANSITION_PROPERTY) {
        clearTimeout(fallbackTimer);
        balanceLine.removeEventListener('transitionend', onTransitionEnd);
        finalize();
      }
    };
    balanceLine.addEventListener('transitionend', onTransitionEnd);
    fallbackTimer = setTimeout(finalize, TRANSITION_END_FALLBACK_MS);
  }
}
