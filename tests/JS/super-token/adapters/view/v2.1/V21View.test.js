const mockRender = jest.fn();
const mockReset = jest.fn();
const mockDecorate = jest.fn();
const mockClear = jest.fn();
let lastSavedCardsDeps;
let lastDecorationDeps;

jest.mock('@super-token/adapters/view/v2.1/SavedCardsView', () => ({
  V21SavedCardsView: function (deps) {
    lastSavedCardsDeps = deps;
    this.render = mockRender;
    this.reset = mockReset;
  },
}));
jest.mock('@super-token/adapters/view/v2.1/AccountMoneyDecoration', () => ({
  V21AccountMoneyDecoration: function (deps) {
    lastDecorationDeps = deps;
    this.decorate = mockDecorate;
    this.clear = mockClear;
  },
}));

const { V21View } = require('@super-token/adapters/view/v2.1/V21View');
const { buildViewDeps } = require('../fixtures');

describe('V21View (delegates to the grouped-blocks saved-cards view + account-money decoration)', () => {
  let view;
  const deps = buildViewDeps();

  beforeEach(() => {
    jest.clearAllMocks();
    view = new V21View(deps);
  });

  it('Given the deps, When constructed, Then both collaborators receive them (the decoration needs copy/thumbnails)', () => {
    expect(lastSavedCardsDeps).toBe(deps);
    expect(lastDecorationDeps).toBe(deps);
  });

  it('Given a render context, When renderSavedPaymentMethods is called, Then it delegates to the saved-cards view', () => {
    const context = { container: document.createElement('div'), paymentMethods: [] };
    view.renderSavedPaymentMethods(context);
    expect(mockRender).toHaveBeenCalledWith(context);
  });

  it('When decorateSelection is called, Then it delegates to the account-money decoration with the row', () => {
    const row = document.createElement('div');
    view.decorateSelection(row);
    expect(mockDecorate).toHaveBeenCalledWith(row);
  });

  it('When clearSelectionDecoration is called, Then it delegates to the account-money decoration with the container', () => {
    const container = document.createElement('div');
    view.clearSelectionDecoration(container);
    expect(mockClear).toHaveBeenCalledWith(container);
  });

  it('When reset is called, Then it delegates to the saved-cards view with the container', () => {
    const container = document.createElement('div');
    view.reset(container);
    expect(mockReset).toHaveBeenCalledWith(container);
  });
});
