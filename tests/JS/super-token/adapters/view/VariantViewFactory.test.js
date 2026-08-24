const { createVariantView } = require('@super-token/adapters/view/VariantViewFactory');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { V21_STYLES } = require('@super-token/adapters/view/v2.1/styles');
const { V2_STYLES } = require('@super-token/adapters/view/v2/styles');
const { buildViewDeps } = require('./fixtures');
const { creditCard } = require('../../core/fixtures');

const render = (view) => {
  const container = document.createElement('div');
  view.renderSavedPaymentMethods({ container, paymentMethods: [creditCard()] });
  return container;
};

describe('VariantViewFactory (RN-4: single variant decision point)', () => {
  it('Given the variant "v2", When a view is created, Then it renders the flat list with a list header', () => {
    const container = render(createVariantView('v2', buildViewDeps()));

    expect(container.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`)).not.toBeNull();
    expect(container.querySelector(`.${V21_STYLES.BLOCK}`)).toBeNull();
  });

  it('Given the variant "v2.1", When a view is created, Then it renders grouped blocks (no v2 list header)', () => {
    const container = render(createVariantView('v2.1', buildViewDeps()));

    expect(container.querySelector(`.${V21_STYLES.BLOCK}`)).not.toBeNull();
    expect(container.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`)).toBeNull();
  });

  it.each(['v3', '', 'V2.1', undefined])(
    'Given the unknown variant %p, When a view is created, Then it falls back to v2',
    (unknownVariant) => {
      const container = render(createVariantView(unknownVariant, buildViewDeps()));

      expect(container.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`)).not.toBeNull();
      expect(container.querySelector(`.${V21_STYLES.BLOCK}`)).toBeNull();
    },
  );

  it('Given any variant, When the view is created, Then it exposes the full VariantViewPort seam', () => {
    const view = createVariantView('v2.1', buildViewDeps());

    expect(typeof view.renderSavedPaymentMethods).toBe('function');
    expect(typeof view.decorateSelection).toBe('function');
    expect(typeof view.clearSelectionDecoration).toBe('function');
    expect(typeof view.reset).toBe('function');
  });

  it('Given a rendered method, When any row is inspected, Then it carries the shared payment-method class', () => {
    const container = render(createVariantView('v2.1', buildViewDeps()));

    expect(container.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD}`)).not.toBeNull();
  });
});
