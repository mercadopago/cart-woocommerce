const {
  V21AccountMoneyDecoration,
} = require('@super-token/adapters/view/v2.1/AccountMoneyDecoration');
const { V21_STYLES } = require('@super-token/adapters/view/v2.1/styles');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { buildViewDeps } = require('../fixtures');

const ANIMATION_FALLBACK_MS = 350;

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

describe('V21AccountMoneyDecoration (RN-2: v2.1 decorates the account-money selection)', () => {
  let container;
  let decoration;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
    decoration = new V21AccountMoneyDecoration(buildViewDeps());
    // Run the animation frame synchronously so the open-state assertions are deterministic.
    jest.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      callback();
      return 0;
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
    jest.useRealTimers();
    document.body.innerHTML = '';
  });

  it('Given an account-money row, When decorated, Then a polite balance line and the balance aria-label are added', () => {
    const row = buildAccountMoneyRow();
    container.appendChild(row);

    decoration.decorate(row);

    const balanceLine = row.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`);
    expect(balanceLine.textContent).toBe('Saldo disponível');
    expect(balanceLine.getAttribute('aria-live')).toBe('polite');
    expect(row.getAttribute('aria-label')).toContain('Saldo disponível');
  });

  it('Given a connected, selected row, When the frame runs, Then the open classes are applied', () => {
    const row = buildAccountMoneyRow();
    container.appendChild(row);

    decoration.decorate(row);

    expect(row.classList.contains(V21_STYLES.ACCOUNT_MONEY_ROW_OPEN)).toBe(true);
    expect(
      row.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE_OPEN}`),
    ).not.toBeNull();
  });

  it('Given a non-account-money row, When decorated, Then nothing is added (no-op)', () => {
    const row = buildAccountMoneyRow();
    row.dataset.type = 'credit_card';
    container.appendChild(row);

    decoration.decorate(row);

    expect(row.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)).toBeNull();
  });

  it('Given a fast re-selection, When decorated twice, Then only one balance line exists', () => {
    const row = buildAccountMoneyRow();
    container.appendChild(row);

    decoration.decorate(row);
    decoration.decorate(row);

    expect(container.querySelectorAll(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)).toHaveLength(1);
  });

  it('Given a second account-money row elsewhere on the page, When one row is decorated, Then the other row balance line is untouched', () => {
    const rowA = buildAccountMoneyRow();
    const rowB = buildAccountMoneyRow();
    const containerB = document.createElement('div');
    document.body.appendChild(containerB);
    container.appendChild(rowA);
    containerB.appendChild(rowB);

    decoration.decorate(rowB);
    decoration.decorate(rowA);

    expect(rowB.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)).not.toBeNull();
    expect(rowA.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)).not.toBeNull();
  });

  it('Given a decorated row, When cleared, Then the aria-label is restored and the row loses its open class', () => {
    const row = buildAccountMoneyRow();
    container.appendChild(row);
    decoration.decorate(row);

    decoration.clear(container);

    expect(row.getAttribute('aria-label')).toBe('Dinheiro na conta');
    expect(row.classList.contains(V21_STYLES.ACCOUNT_MONEY_ROW_OPEN)).toBe(false);
  });

  it('Given a clearing balance line, When the max-height transition ends, Then the node is removed', () => {
    const row = buildAccountMoneyRow();
    container.appendChild(row);
    decoration.decorate(row);
    const balanceLine = container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`);

    decoration.clear(container);
    dispatchTransitionEnd(balanceLine, 'max-height');

    expect(container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)).toBeNull();
  });

  it('Given no transitionend fires, When the fallback timer elapses, Then the balance line is still removed', () => {
    jest.useFakeTimers();
    jest.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      callback();
      return 0;
    });
    const row = buildAccountMoneyRow();
    container.appendChild(row);
    decoration.decorate(row);

    decoration.clear(container);
    jest.advanceTimersByTime(ANIMATION_FALLBACK_MS);

    expect(container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)).toBeNull();
  });
});
