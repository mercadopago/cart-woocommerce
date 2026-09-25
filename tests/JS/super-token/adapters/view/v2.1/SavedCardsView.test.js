const { V21SavedCardsView } = require('@super-token/adapters/view/v2.1/SavedCardsView');
const { V21_STYLES } = require('@super-token/adapters/view/v2.1/styles');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { buildViewDeps, buildEmailListener } = require('../fixtures');
const { creditCard, debitCard, accountMoney, consumerCredits } = require('../../../core/fixtures');

describe('V21SavedCardsView (RN-2: v2.1 renders grouped blocks with an e-mail header)', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
  });

  it('Given cards and other methods, When rendered, Then the saved-cards block sits above the other-methods block', () => {
    const view = new V21SavedCardsView(buildViewDeps());

    view.render({ container, paymentMethods: [creditCard({ id: 'a' }), accountMoney({ id: 'am' })] });

    const blocks = container.querySelectorAll(`.${V21_STYLES.BLOCK}`);
    expect(blocks).toHaveLength(2);
    expect(blocks[0].classList.contains(V21_STYLES.BLOCK_SAVED_CARDS)).toBe(true);
    expect(blocks[1].classList.contains(V21_STYLES.BLOCK_OTHER_MP_METHODS)).toBe(true);
  });

  it('Given more than MAX_CREDIT_CARDS cards, When grouped, Then the saved-cards block is capped at three', () => {
    const view = new V21SavedCardsView(buildViewDeps());
    const cards = [
      creditCard({ id: 'a' }),
      creditCard({ id: 'b' }),
      debitCard({ id: 'c' }),
      creditCard({ id: 'd' }),
    ];

    view.render({ container, paymentMethods: cards });

    const savedBlock = container.querySelector(`.${V21_STYLES.BLOCK_SAVED_CARDS}`);
    expect(savedBlock.querySelectorAll(`.${SHARED_STYLES.PAYMENT_METHOD}`)).toHaveLength(3);
  });

  it.each([
    [[creditCard({ id: 'a' })], 'Cartão salvo'],
    [[creditCard({ id: 'a' }), creditCard({ id: 'b' })], 'Cartões salvos'],
  ])('Given %# saved card(s), When titled, Then the singular/plural title is used', (cards, expectedTitle) => {
    const view = new V21SavedCardsView(buildViewDeps());

    view.render({ container, paymentMethods: cards });

    const title = container.querySelector(`.${V21_STYLES.BLOCK_SAVED_CARDS} .${V21_STYLES.BLOCK_TITLE}`);
    expect(title.textContent).toBe(expectedTitle);
  });

  it('Given no cards and a single other method, When titled, Then the saved-payment-method title is used', () => {
    const view = new V21SavedCardsView(buildViewDeps());

    view.render({ container, paymentMethods: [accountMoney({ id: 'am' })] });

    const title = container.querySelector(`.${V21_STYLES.BLOCK_OTHER_MP_METHODS} .${V21_STYLES.BLOCK_TITLE}`);
    expect(title.textContent).toBe('Meio de pagamento salvo');
    expect(container.querySelector(`.${V21_STYLES.BLOCK_SAVED_CARDS}`)).toBeNull();
  });

  it('Given no cards and several other methods, When titled, Then the generic list title is used', () => {
    const view = new V21SavedCardsView(buildViewDeps());

    view.render({ container, paymentMethods: [accountMoney({ id: 'am' }), consumerCredits({ id: 'cc' })] });

    const title = container.querySelector(`.${V21_STYLES.BLOCK_OTHER_MP_METHODS} .${V21_STYLES.BLOCK_TITLE}`);
    expect(title.textContent).toBe('Meios de pagamento');
  });

  it('Given a valid e-mail listener, When rendered, Then the block header shows the e-mail', () => {
    const view = new V21SavedCardsView(buildViewDeps({ emailListener: buildEmailListener() }));

    view.render({ container, paymentMethods: [creditCard({ id: 'a' })] });

    expect(container.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`).textContent).toBe('buyer@example.com');
  });

  it('Given the listener yields no e-mail, When rendered, Then the currentUserEmail fallback is shown', () => {
    const emailListener = buildEmailListener({ getEmail: () => '' });
    const view = new V21SavedCardsView(buildViewDeps({ emailListener, currentUserEmail: 'fallback@example.com' }));

    view.render({ container, paymentMethods: [creditCard({ id: 'a' })] });

    expect(container.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`).textContent).toBe('fallback@example.com');
  });

  it('Given no e-mail listener, When rendered, Then no e-mail span is shown', () => {
    const view = new V21SavedCardsView(buildViewDeps({ emailListener: null }));

    view.render({ container, paymentMethods: [creditCard({ id: 'a' })] });

    expect(container.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`)).toBeNull();
  });

  it('Given an invalid e-mail, When rendered, Then no e-mail span is shown', () => {
    const emailListener = buildEmailListener({ getEmail: () => 'not-an-email' });
    const view = new V21SavedCardsView(buildViewDeps({ emailListener, currentUserEmail: 'not-an-email' }));

    view.render({ container, paymentMethods: [creditCard({ id: 'a' })] });

    expect(container.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`)).toBeNull();
  });

  it('Given a malicious e-mail, When rendered, Then it is inert text, not markup (RN-3/SEC-3)', () => {
    const payload = 'x@y.com"><img src=z onerror=alert(1)>';
    const emailListener = buildEmailListener({ getEmail: () => payload });
    const view = new V21SavedCardsView(buildViewDeps({ emailListener, currentUserEmail: payload }));

    view.render({ container, paymentMethods: [creditCard({ id: 'a' })] });

    const emailSpan = container.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`);
    expect(emailSpan.textContent).toBe(payload);
    expect(emailSpan.querySelector('img')).toBeNull();
  });

  it('Given a Mercado Pago credit card on a blue site, When rendered, Then the MP name/thumbnail apply and last four is hidden (RN-7)', () => {
    const view = new V21SavedCardsView(buildViewDeps({ siteId: 'MLA' }));
    const mpCredit = creditCard({ id: 'mp', issuer: { name: 'Mercado Pago' } });

    view.render({ container, paymentMethods: [mpCredit] });

    const row = container.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD}`);
    expect(row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_TITLE}`).textContent).toBe(
      'Cartão de crédito Mercado Pago',
    );
    expect(row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_THUMBNAIL} img`).src).toContain('/mp-blue.png');
    expect(row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_LAST_FOUR_DIGITS}`)).toBeNull();
  });

  it('Given an account-money row, When rendered, Then it carries the account-money row class', () => {
    const view = new V21SavedCardsView(buildViewDeps());

    view.render({ container, paymentMethods: [accountMoney({ id: 'am' })] });

    expect(container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_ROW}`)).not.toBeNull();
  });

  it('Given a rendered set of blocks, When reset is called, Then all blocks are removed', () => {
    const view = new V21SavedCardsView(buildViewDeps());
    view.render({ container, paymentMethods: [creditCard({ id: 'a' }), accountMoney({ id: 'am' })] });

    view.reset(container);

    expect(container.querySelectorAll(`.${V21_STYLES.BLOCK}`)).toHaveLength(0);
  });

  it('Given render throws after inserting the first block, When it fails, Then the container is left clean (no partial block) and the error propagates', () => {
    const view = new V21SavedCardsView(buildViewDeps());
    // Succeed for the first block (other-methods) so it gets inserted, then throw while building
    // the second block (saved-cards): the legacy seam's catch would re-run organize on the same
    // container, so a leftover partial block would be duplicated.
    const buildRow = jest
      .fn()
      .mockImplementationOnce(() => {
        const row = document.createElement('article');
        row.classList.add(SHARED_STYLES.PAYMENT_METHOD);
        return row;
      })
      .mockImplementationOnce(() => {
        throw new Error('row build failed');
      });

    expect(() =>
      view.render({ container, paymentMethods: [creditCard({ id: 'a' }), accountMoney({ id: 'am' })], buildRow }),
    ).toThrow('row build failed');

    expect(container.querySelectorAll(`.${V21_STYLES.BLOCK}`)).toHaveLength(0);
  });

  it('Given repeated renders, When the e-mail header listener is set up, Then it registers only once (idempotent)', () => {
    const onEmailChange = jest.fn();
    const view = new V21SavedCardsView(buildViewDeps({ emailListener: buildEmailListener({ onEmailChange }) }));

    view.render({ container, paymentMethods: [creditCard({ id: 'a' })] });
    view.render({ container, paymentMethods: [creditCard({ id: 'a' })] });

    expect(onEmailChange).toHaveBeenCalledTimes(1);
  });

  it('Given a registered e-mail listener, When the e-mail changes, Then the header span updates then is removed on invalid', () => {
    let capturedCallback;
    const emailListener = buildEmailListener({
      getEmail: () => 'buyer@example.com',
      onEmailChange: (cb) => {
        capturedCallback = cb;
      },
    });
    const view = new V21SavedCardsView(buildViewDeps({ emailListener }));
    view.render({ container, paymentMethods: [creditCard({ id: 'a' })] });

    capturedCallback('new@example.com', true);
    expect(container.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`).textContent).toBe('new@example.com');

    capturedCallback('', false);
    expect(container.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`)).toBeNull();
  });

  it('Given an injected buildRow, When rendered, Then each row comes from the injected factory (the row stays legacy)', () => {
    const view = new V21SavedCardsView(buildViewDeps());
    const buildRow = jest.fn((paymentMethod) => {
      const row = document.createElement('article');
      row.classList.add(SHARED_STYLES.PAYMENT_METHOD);
      row.dataset.id = paymentMethod.id;
      return row;
    });

    view.render({ container, paymentMethods: [creditCard({ id: 'a' }), accountMoney({ id: 'am' })], buildRow });

    expect(buildRow).toHaveBeenCalledTimes(2);
    const savedBlock = container.querySelector(`.${V21_STYLES.BLOCK_SAVED_CARDS}`);
    const otherBlock = container.querySelector(`.${V21_STYLES.BLOCK_OTHER_MP_METHODS}`);
    expect(savedBlock.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD}`).dataset.id).toBe('a');
    expect(otherBlock.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD}`).dataset.id).toBe('am');
    // The presentation row builder must not run when a row factory is injected.
    expect(savedBlock.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_TITLE}`)).toBeNull();
  });

  it('Given a rowSession, When rendering, Then the account-money row is tree-built and wires selection while cards stay legacy', () => {
    const onSelectPaymentMethod = jest.fn();
    const legacyCardRow = () => {
      const row = document.createElement('article');
      row.classList.add(SHARED_STYLES.PAYMENT_METHOD);
      row.dataset.id = 'legacy-card';
      return row;
    };
    const buildRow = jest.fn(legacyCardRow);
    const view = new V21SavedCardsView(buildViewDeps());

    view.render({
      container,
      paymentMethods: [creditCard({ id: 'a' }), accountMoney({ id: 'am' })],
      buildRow,
      rowSession: { onSelectPaymentMethod },
    });

    // The card still comes from the legacy factory; the account-money row does not.
    expect(buildRow).toHaveBeenCalledTimes(1);
    expect(buildRow).toHaveBeenCalledWith(expect.objectContaining({ id: 'a' }));

    const amRow = container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_ROW}`);
    expect(amRow.dataset.id).toBe('am');
    amRow.click();
    expect(onSelectPaymentMethod).toHaveBeenCalledWith(amRow, expect.objectContaining({ id: 'am' }));
  });

  it('Given a rowSession and installmentOptions, When rendering a credit card, Then the tree builds the card row (installments select) instead of the legacy factory', () => {
    const buildRow = jest.fn(() => document.createElement('article'));
    const view = new V21SavedCardsView(buildViewDeps());

    view.render({
      container,
      paymentMethods: [creditCard({ id: 'cc1', card: { card_number: { last_four_digits: '1234' } }, installments: [{ installments: 1, installment_amount: 100, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'], total_amount: 100 }] })],
      buildRow,
      rowSession: {
        onSelectPaymentMethod: jest.fn(),
        updateInstallmentsTaxInfo: jest.fn(),
        installmentSelected: jest.fn(),
        reportInstallmentDispatcherMissing: jest.fn(),
      },
      installmentOptions: () => [{ value: '1', title: '1x R$100' }],
    });

    expect(buildRow).not.toHaveBeenCalled();
    expect(container.querySelector('#mp-super-token-installments-select-cc11234')).not.toBeNull();
  });

  it('Given a rowSession and installmentOptions, When rendering a debit card, Then the tree builds it (security-code, no installments) instead of the legacy factory', () => {
    const buildRow = jest.fn(() => document.createElement('article'));
    const view = new V21SavedCardsView(buildViewDeps());

    view.render({
      container,
      paymentMethods: [debitCard({ id: 'dc1', token: 'tok-dc' })],
      buildRow,
      rowSession: {
        onSelectPaymentMethod: jest.fn(),
        updateInstallmentsTaxInfo: jest.fn(),
        installmentSelected: jest.fn(),
        reportInstallmentDispatcherMissing: jest.fn(),
      },
      installmentOptions: () => [],
    });

    expect(buildRow).not.toHaveBeenCalled();
    expect(container.querySelector('.mp-super-token-security-code-container')).not.toBeNull();
    expect(container.querySelector('select[data-checkout="installments"]')).toBeNull();
  });

  it('Given a re-render into a replacement container, When the e-mail changes, Then the current container is updated, not the detached one', () => {
    let capturedCallback;
    const emailListener = buildEmailListener({
      getEmail: () => 'buyer@example.com',
      onEmailChange: (cb) => {
        capturedCallback = cb;
      },
    });
    const view = new V21SavedCardsView(buildViewDeps({ emailListener }));

    const firstContainer = document.createElement('div');
    view.render({ container: firstContainer, paymentMethods: [creditCard({ id: 'a' })] });

    // WooCommerce rebuilds the checkout DOM and the view re-renders into a new container.
    const secondContainer = document.createElement('div');
    view.render({ container: secondContainer, paymentMethods: [creditCard({ id: 'a' })] });

    capturedCallback('new@example.com', true);

    expect(secondContainer.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`).textContent).toBe('new@example.com');
    expect(firstContainer.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`).textContent).toBe('buyer@example.com');
  });
});
