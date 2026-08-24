const { LoadPaymentMethods } = require('@super-token/useCases/LoadPaymentMethods');
const { buildConfig, creditCard, accountMoney } = require('../core/fixtures');

const buildRenderer = () => ({ renderAccountPaymentMethods: jest.fn() });

describe('LoadPaymentMethods', () => {
  it('Given the fetch returns no methods, When executed, Then it returns an empty list and renders nothing', async () => {
    const gateway = { fetchAccountPaymentMethods: jest.fn(() => Promise.resolve(null)) };
    const renderer = buildRenderer();

    const result = await new LoadPaymentMethods(buildConfig()).execute({
      gateway,
      renderer,
      amount: '100.00',
      buyerEmail: 'buyer@example.com',
    });

    expect(result).toEqual([]);
    expect(renderer.renderAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given the fetch returns an empty list, When executed, Then it renders nothing', async () => {
    const gateway = { fetchAccountPaymentMethods: jest.fn(() => Promise.resolve([])) };
    const renderer = buildRenderer();

    const result = await new LoadPaymentMethods(buildConfig()).execute({
      gateway,
      renderer,
      amount: '100.00',
      buyerEmail: 'buyer@example.com',
    });

    expect(result).toEqual([]);
    expect(renderer.renderAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given fetched methods, When executed, Then it orders and decorates them via the domain core and renders the normalized list', async () => {
    const gateway = {
      fetchAccountPaymentMethods: jest.fn(() =>
        Promise.resolve([creditCard({ issuer: { name: 'Itau' } }), accountMoney()]),
      ),
    };
    const renderer = buildRenderer();

    const result = await new LoadPaymentMethods(buildConfig({ siteId: 'MLB' })).execute({
      gateway,
      renderer,
      amount: '100.00',
      buyerEmail: 'buyer@example.com',
    });

    const decoratedCredit = result.find((pm) => pm.type === 'credit_card');
    expect(decoratedCredit.name).toBe('Itau Crédito');
    expect(renderer.renderAccountPaymentMethods).toHaveBeenCalledWith(result, '100.00');
  });
});
