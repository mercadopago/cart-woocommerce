const { MpSdkAdapter } = require('@super-token/adapters/platform/MpSdkAdapter');

function buildFakeSdk() {
  return {
    authenticator: jest.fn().mockResolvedValue({ authorizePayment: jest.fn() }),
    getAccountPaymentMethods: jest.fn().mockResolvedValue({ data: [] }),
    getAccountPaymentMethod: jest.fn().mockResolvedValue({ data: {} }),
    getCardId: jest.fn().mockResolvedValue({ card_id: 'CARD_1' }),
    updatePseudotoken: jest.fn().mockResolvedValue(undefined),
    renderCreditsContract: jest.fn().mockResolvedValue({ update: jest.fn() }),
    getSDKInstanceId: jest.fn().mockReturnValue('SDK_1'),
    fields: { createCardToken: jest.fn(), create: jest.fn() },
  };
}

describe('MpSdkAdapter', () => {
  it('Given an injected SDK, When authenticator is called, Then it delegates the exact arguments to the SDK', async () => {
    const sdk = buildFakeSdk();
    const adapter = new MpSdkAdapter(sdk);

    await adapter.authenticator('10.50', 'buyer@example.com', { platformId: 'BP1', version: 2 });

    expect(sdk.authenticator).toHaveBeenCalledWith('10.50', 'buyer@example.com', { platformId: 'BP1', version: 2 });
  });

  it('Given an injected SDK, When getCardId is called, Then it returns the SDK result unchanged', async () => {
    const sdk = buildFakeSdk();
    const adapter = new MpSdkAdapter(sdk);

    const result = await adapter.getCardId('SUPER_1', 'PM_1');

    expect(sdk.getCardId).toHaveBeenCalledWith('SUPER_1', 'PM_1');
    expect(result).toEqual({ card_id: 'CARD_1' });
  });

  it('Given an injected SDK, When updatePseudotoken is called, Then it forwards all positional arguments', async () => {
    const sdk = buildFakeSdk();
    const adapter = new MpSdkAdapter(sdk);

    await adapter.updatePseudotoken('SUPER_1', 'PM_1', 'CARD_TOKEN_1');

    expect(sdk.updatePseudotoken).toHaveBeenCalledWith('SUPER_1', 'PM_1', 'CARD_TOKEN_1');
  });

  it('Given an injected SDK, When fields is accessed, Then it exposes the SDK fields namespace directly', () => {
    const sdk = buildFakeSdk();
    const adapter = new MpSdkAdapter(sdk);

    expect(adapter.fields).toBe(sdk.fields);
  });

  it('Given an injected SDK, When getSDKInstanceId is called, Then it returns the SDK instance id', () => {
    const sdk = buildFakeSdk();
    const adapter = new MpSdkAdapter(sdk);

    expect(adapter.getSDKInstanceId()).toBe('SDK_1');
  });
});
