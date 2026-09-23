/**
 * Parity (RN-NEG-11 / RN-1 / RN-2): from the SAME domain input, the v2 and v2.1 views must
 * render the SAME set of saved-method rows (unified code, nothing changes for the buyer),
 * differing ONLY at the documented A/B points — header/e-mail, grouped blocks vs. flat list,
 * and the Mercado Pago credit-card presentation. Driven through the public VariantViewPort
 * seam via createVariantView, so it also exercises the V2View/V21View delegation.
 */
const { createVariantView } = require('@super-token/adapters/view/VariantViewFactory');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { V2_STYLES } = require('@super-token/adapters/view/v2/styles');
const { V21_STYLES } = require('@super-token/adapters/view/v2.1/styles');
const { buildViewDeps, buildEmailListener } = require('./fixtures');
const { creditCard, debitCard, accountMoney } = require('../../core/fixtures');

const renderVariant = (variant, deps, paymentMethods) => {
  const container = document.createElement('div');
  createVariantView(variant, deps).renderSavedPaymentMethods({ container, paymentMethods });
  return container;
};

// The variant-agnostic skeleton of every rendered row: type + id, independent of the chrome
// (headers/blocks) that legitimately differs between variants.
const extractRowSkeleton = (container) =>
  Array.from(container.querySelectorAll(`.${SHARED_STYLES.PAYMENT_METHOD}`))
    .map((row) => `${row.dataset.type}:${row.dataset.id}`)
    .sort();

describe('SavedCardsView parity (v2 × v2.1 render the same methods, differing only in the A/B chrome)', () => {
  it('Given the same methods, When rendered by each variant, Then both produce the identical row skeleton (RN-NEG-11)', () => {
    const deps = buildViewDeps();
    // <= MAX_CREDIT_CARDS cards so v2.1 does not cap and both render every method.
    const paymentMethods = [creditCard({ id: 'a' }), debitCard({ id: 'b' }), accountMoney({ id: 'am' })];

    const v2 = renderVariant('v2', deps, paymentMethods);
    const v21 = renderVariant('v2.1', deps, paymentMethods);

    expect(extractRowSkeleton(v2)).toEqual(extractRowSkeleton(v21));
    expect(extractRowSkeleton(v2)).toHaveLength(3);
  });

  it('Given the same methods, When rendered, Then v2 uses a flat list header and v2.1 uses grouped blocks (mutually exclusive chrome)', () => {
    const deps = buildViewDeps();
    const paymentMethods = [creditCard({ id: 'a' }), accountMoney({ id: 'am' })];

    const v2 = renderVariant('v2', deps, paymentMethods);
    const v21 = renderVariant('v2.1', deps, paymentMethods);

    expect(v2.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`)).not.toBeNull();
    expect(v2.querySelector(`.${V21_STYLES.BLOCK}`)).toBeNull();

    expect(v21.querySelector(`.${V21_STYLES.BLOCK}`)).not.toBeNull();
    expect(v21.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`)).toBeNull();
  });

  it('Given a valid e-mail listener, When rendered, Then only v2.1 shows the buyer e-mail in the header; v2 never shows one', () => {
    const deps = buildViewDeps({ emailListener: buildEmailListener() });
    const paymentMethods = [creditCard({ id: 'a' })];

    const v2 = renderVariant('v2', deps, paymentMethods);
    const v21 = renderVariant('v2.1', deps, paymentMethods);

    expect(v2.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`)).toBeNull();
    expect(v2.textContent).not.toContain('buyer@example.com');

    expect(v21.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`).textContent).toBe('buyer@example.com');
  });

  it('Given a Mercado Pago credit card, When rendered, Then v2 shows it as a regular card (issuer name + last four) and v2.1 applies the MP name/thumbnail with last four hidden (RN-7)', () => {
    const deps = buildViewDeps({ siteId: 'MLA' });
    const mpCredit = creditCard({ id: 'mp', issuer: { name: 'Mercado Pago' } });

    const v2Row = renderVariant('v2', deps, [mpCredit]).querySelector(`.${SHARED_STYLES.PAYMENT_METHOD}`);
    const v21Row = renderVariant('v2.1', deps, [mpCredit]).querySelector(`.${SHARED_STYLES.PAYMENT_METHOD}`);

    expect(v2Row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_TITLE}`).textContent).toBe('Mercado Pago Crédito');
    expect(v2Row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_LAST_FOUR_DIGITS}`)).not.toBeNull();

    expect(v21Row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_TITLE}`).textContent).toBe(
      'Cartão de crédito Mercado Pago',
    );
    expect(v21Row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_LAST_FOUR_DIGITS}`)).toBeNull();
    expect(v21Row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_THUMBNAIL} img`).src).toContain('/mp-blue.png');
  });

  it('Given a rendered list, When reset is called, Then each variant removes only its own chrome', () => {
    const deps = buildViewDeps();
    const paymentMethods = [creditCard({ id: 'a' }), accountMoney({ id: 'am' })];

    const v2View = createVariantView('v2', deps);
    const v2 = document.createElement('div');
    v2View.renderSavedPaymentMethods({ container: v2, paymentMethods });
    v2View.reset(v2);

    const v21View = createVariantView('v2.1', deps);
    const v21 = document.createElement('div');
    v21View.renderSavedPaymentMethods({ container: v21, paymentMethods });
    v21View.reset(v21);

    expect(v2.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`)).toBeNull();
    expect(v21.querySelector(`.${V21_STYLES.BLOCK}`)).toBeNull();
  });
});
