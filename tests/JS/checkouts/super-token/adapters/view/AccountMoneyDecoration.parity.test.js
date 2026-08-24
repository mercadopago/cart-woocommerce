/**
 * Parity (RN-2): the account-money selection decoration is the documented A/B difference —
 * v2.1 shows a balance line when the account-money row is selected, v2 is a no-op. Both
 * honour the same VariantViewPort contract (decorateSelection/clearSelectionDecoration never
 * throw). Driven through createVariantView so the V2View/V21View delegation is exercised too.
 */
const { createVariantView } = require('@super-token/adapters/view/VariantViewFactory');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { V21_STYLES } = require('@super-token/adapters/view/v2.1/styles');
const { buildViewDeps } = require('./fixtures');

const buildAccountMoneyRow = () => {
  const row = document.createElement('article');
  row.classList.add(
    SHARED_STYLES.PAYMENT_METHOD,
    V21_STYLES.ACCOUNT_MONEY_ROW,
    SHARED_STYLES.PAYMENT_METHOD_SELECTED,
  );
  row.dataset.type = 'account_money';
  row.dataset.baseAriaLabel = 'Dinheiro na conta';
  row.setAttribute('aria-label', 'Dinheiro na conta');
  const content = document.createElement('article');
  content.classList.add(SHARED_STYLES.PAYMENT_METHOD_CONTENT);
  row.appendChild(content);
  return row;
};

const dispatchTransitionEnd = (node, propertyName) => {
  const event = new Event('transitionend');
  Object.defineProperty(event, 'propertyName', { value: propertyName });
  node.dispatchEvent(event);
};

describe('AccountMoneyDecoration parity (v2.1 decorates the account-money selection, v2 is a no-op)', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
    // Run the animation frame synchronously so the decoration is deterministic.
    jest.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      callback();
      return 0;
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
    document.body.innerHTML = '';
  });

  it('Given the same selected account-money row, When decorated, Then v2.1 adds a balance line and v2 adds nothing (both no-throw)', () => {
    const deps = buildViewDeps();

    const v2Row = buildAccountMoneyRow();
    container.appendChild(v2Row);
    expect(() => createVariantView('v2', deps).decorateSelection(v2Row)).not.toThrow();
    expect(v2Row.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)).toBeNull();

    const v21Row = buildAccountMoneyRow();
    container.appendChild(v21Row);
    createVariantView('v2.1', deps).decorateSelection(v21Row);
    expect(v21Row.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`).textContent).toBe('Saldo disponível');
  });

  it('Given a decorated selection, When cleared, Then v2.1 removes the balance line and v2 is a safe no-op', () => {
    const deps = buildViewDeps();

    const v21View = createVariantView('v2.1', deps);
    const v21Row = buildAccountMoneyRow();
    container.appendChild(v21Row);
    v21View.decorateSelection(v21Row);
    v21View.clearSelectionDecoration(container);
    dispatchTransitionEnd(container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`), 'max-height');
    expect(container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)).toBeNull();

    expect(() => createVariantView('v2', deps).clearSelectionDecoration(container)).not.toThrow();
  });
});
