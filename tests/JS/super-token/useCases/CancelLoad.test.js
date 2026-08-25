const { CancelLoad } = require('@super-token/useCases/CancelLoad');

const buildSession = (overrides = {}) => ({
  bumpLoadGeneration: jest.fn(),
  setFetching: jest.fn(),
  resetPaymentMethods: jest.fn(),
  ...overrides,
});

const run = (session) => new CancelLoad().execute({ session });

describe('CancelLoad', () => {
  it('Given an in-flight load, When cancelled, Then it bumps the generation, clears the fetching flag and resets the stored methods', () => {
    const session = buildSession();

    run(session);

    expect(session.bumpLoadGeneration).toHaveBeenCalledTimes(1);
    expect(session.setFetching).toHaveBeenCalledWith(false);
    expect(session.resetPaymentMethods).toHaveBeenCalledTimes(1);
  });

  it('Given the cancel, When executed, Then it bumps the generation before clearing the flag so an awaiting fetch drops its stale result', () => {
    const calls = [];
    const session = buildSession({
      bumpLoadGeneration: jest.fn(() => calls.push('bump')),
      setFetching: jest.fn(() => calls.push('fetching')),
      resetPaymentMethods: jest.fn(() => calls.push('reset')),
    });

    run(session);

    expect(calls).toEqual(['bump', 'fetching', 'reset']);
  });
});
