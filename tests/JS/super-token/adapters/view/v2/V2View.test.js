const mockRender = jest.fn();
const mockReset = jest.fn();
const mockDecorate = jest.fn();
const mockClear = jest.fn();
let lastSavedCardsDeps;

jest.mock('@super-token/adapters/view/v2/SavedCardsView', () => ({
  V2SavedCardsView: function (deps) {
    lastSavedCardsDeps = deps;
    this.render = mockRender;
    this.reset = mockReset;
  },
}));
jest.mock('@super-token/adapters/view/v2/AccountMoneyDecoration', () => ({
  V2AccountMoneyDecoration: function () {
    this.decorate = mockDecorate;
    this.clear = mockClear;
  },
}));

const { V2View } = require('@super-token/adapters/view/v2/V2View');
const { buildViewDeps } = require('../fixtures');

describe('V2View (delegates to the flat-list saved-cards view + no-op account-money decoration)', () => {
  let view;
  const deps = buildViewDeps();

  beforeEach(() => {
    jest.clearAllMocks();
    view = new V2View(deps);
  });

  it('Given the deps, When constructed, Then the saved-cards view receives them', () => {
    expect(lastSavedCardsDeps).toBe(deps);
  });

  it('Given a render context, When renderSavedPaymentMethods is called, Then it delegates to the saved-cards view', () => {
    const context = { container: document.createElement('div'), paymentMethods: [] };
    view.renderSavedPaymentMethods(context);
    expect(mockRender).toHaveBeenCalledWith(context);
  });

  it('When decorateSelection is called, Then it delegates to the account-money decoration (v2 takes no row)', () => {
    view.decorateSelection(document.createElement('div'));
    expect(mockDecorate).toHaveBeenCalledTimes(1);
    expect(mockDecorate).toHaveBeenCalledWith();
  });

  it('When clearSelectionDecoration is called, Then it delegates to the account-money decoration', () => {
    view.clearSelectionDecoration(document.createElement('div'));
    expect(mockClear).toHaveBeenCalledTimes(1);
    expect(mockClear).toHaveBeenCalledWith();
  });

  it('When reset is called, Then it delegates to the saved-cards view with the container', () => {
    const container = document.createElement('div');
    view.reset(container);
    expect(mockReset).toHaveBeenCalledWith(container);
  });
});
