const {
  CoordinateAccountPaymentMethods,
} = require('@super-token/useCases/CoordinateAccountPaymentMethods');

describe('CoordinateAccountPaymentMethods', () => {
  const context = (fetch, overrides = {}) => ({
    amount: '100.00',
    buyerEmail: 'buyer@example.com',
    fetch,
    ...overrides,
  });

  it('deduplicates concurrent and sequential consumers for the same buyer and amount', async () => {
    const methods = [
      { type: 'credit_card' },
      {
        id: 'smart-transfer-bb',
        name: 'Banco do Brasil',
        thumbnail: '',
        token: 'smart-transfer-token',
        type: 'smart_transfer',
        open_finance_account_id: 'open-finance-account',
      },
    ];
    const fetch = jest.fn(async () => methods);
    const coordinator = new CoordinateAccountPaymentMethods();

    const [forSuperToken, forSmartPix] = await Promise.all([
      coordinator.execute(context(fetch)),
      coordinator.execute(context(fetch)),
    ]);
    const replay = await coordinator.execute(context(fetch));

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(forSuperToken).toEqual(methods);
    expect(forSmartPix).toEqual(methods);
    expect(replay).toEqual(methods);
    expect(forSuperToken).not.toBe(forSmartPix);
  });

  it('loads again when buyer identity or amount changes', async () => {
    const fetch = jest.fn(async () => [{ type: 'credit_card' }]);
    const coordinator = new CoordinateAccountPaymentMethods();

    await coordinator.execute(context(fetch));
    await coordinator.execute(context(fetch, { amount: '200.00' }));
    await coordinator.execute(context(fetch, { buyerEmail: 'other@example.com' }));

    expect(fetch).toHaveBeenCalledTimes(3);
  });

  it('serializes different identities so shared authentication state cannot be overwritten concurrently', async () => {
    let resolveFirst;
    const first = jest.fn(() => new Promise((resolve) => { resolveFirst = resolve; }));
    const second = jest.fn(async () => [{
      id: 'smart-transfer-bb',
      name: 'Banco do Brasil',
      thumbnail: '',
      token: 'smart-transfer-token',
      type: 'smart_transfer',
      open_finance_account_id: 'open-finance-account',
    }]);
    const coordinator = new CoordinateAccountPaymentMethods();

    const firstRequest = coordinator.execute(context(first));
    const secondRequest = coordinator.execute(context(second, { buyerEmail: 'other@example.com' }));
    await Promise.resolve();
    await Promise.resolve();

    expect(first).toHaveBeenCalledTimes(1);
    expect(second).not.toHaveBeenCalled();

    resolveFirst([{ type: 'credit_card' }]);
    await firstRequest;
    await secondRequest;

    expect(second).toHaveBeenCalledTimes(1);
  });

  it('does not cache unavailable or rejected requests', async () => {
    const coordinator = new CoordinateAccountPaymentMethods();
    const unavailable = jest.fn(async () => null);

    await coordinator.execute(context(unavailable));
    await coordinator.execute(context(unavailable));
    expect(unavailable).toHaveBeenCalledTimes(2);

    const rejected = jest.fn(async () => { throw new Error('sdk failure'); });
    await expect(coordinator.execute(context(rejected))).rejects.toThrow('sdk failure');
    await expect(coordinator.execute(context(rejected))).rejects.toThrow('sdk failure');
    expect(rejected).toHaveBeenCalledTimes(2);
  });

  it('drops the cached identity when explicitly invalidated', async () => {
    const fetch = jest.fn(async () => [{ type: 'credit_card' }]);
    const coordinator = new CoordinateAccountPaymentMethods();

    await coordinator.execute(context(fetch));
    coordinator.invalidate();
    await coordinator.execute(context(fetch));

    expect(fetch).toHaveBeenCalledTimes(2);
  });

  it('keeps a replacement request cached when an invalidated request for the same identity settles later', async () => {
    let resolveFirst;
    const methods = [{ type: 'credit_card' }];
    const fetch = jest.fn()
      .mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve; }))
      .mockResolvedValue(methods);
    const coordinator = new CoordinateAccountPaymentMethods();

    const invalidatedRequest = coordinator.execute(context(fetch));
    await Promise.resolve();
    await Promise.resolve();
    coordinator.invalidate();
    const replacementRequest = coordinator.execute(context(fetch));

    resolveFirst(null);
    await invalidatedRequest;
    await expect(replacementRequest).resolves.toEqual(methods);
    await expect(coordinator.execute(context(fetch))).resolves.toEqual(methods);

    expect(fetch).toHaveBeenCalledTimes(2);
  });

  it('releases the queue and aborts the stale generation when an active request stalls', async () => {
    let staleSignal;
    const stalled = jest.fn((signal) => {
      staleSignal = signal;
      return new Promise(() => {});
    });
    const replacementMethods = [{ type: 'smart_transfer', bank: {} }];
    const replacement = jest.fn(async () => replacementMethods);
    const coordinator = new CoordinateAccountPaymentMethods();

    coordinator.execute(context(stalled));
    await Promise.resolve();
    await Promise.resolve();
    coordinator.invalidate();

    await expect(coordinator.execute(context(replacement, {
      buyerEmail: 'other@example.com',
    }))).resolves.toEqual(replacementMethods);

    expect(staleSignal.aborted).toBe(true);
    expect(replacement).toHaveBeenCalledTimes(1);
  });

  it('suppresses a stale request rejection after its generation is invalidated', async () => {
    let rejectStale;
    const stalePromise = new Promise((resolve, reject) => {
      rejectStale = reject;
    });
    const stale = jest.fn(() => stalePromise);
    const coordinator = new CoordinateAccountPaymentMethods();

    const request = coordinator.execute(context(stale));
    await Promise.resolve();
    await Promise.resolve();
    coordinator.invalidate();
    rejectStale(new Error('late sdk failure'));

    await expect(request).resolves.toBeNull();
  });
});
