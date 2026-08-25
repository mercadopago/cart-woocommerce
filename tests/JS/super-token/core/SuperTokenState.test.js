const { SuperTokenState } = require('@super-token/core/checkoutSession/SuperTokenState');
const { creditCard, accountMoney } = require('./fixtures');

describe('SuperTokenState', () => {
  it('Given three attempts of the same error code, When checking retry, Then the third is not allowed (RN-2)', () => {
    const state = new SuperTokenState();

    state.storeAttemptByErrorCode('E1');
    state.storeAttemptByErrorCode('E1');
    expect(state.shouldAllowRetry(state.getAttemptByErrorCode('E1'))).toBe(true);

    state.storeAttemptByErrorCode('E1');
    expect(state.shouldAllowRetry(state.getAttemptByErrorCode('E1'))).toBe(false);
  });

  it('Given the attempt counter, When reading it, Then it is capped at the max (RN-2)', () => {
    const state = new SuperTokenState();

    for (let i = 0; i < 10; i += 1) state.storeAttemptByErrorCode('E1');

    expect(state.getAttemptByErrorCode('E1')).toBe(3);
  });

  it('Given attempts are counted per error code, When one code fails, Then another code is unaffected (RN-2)', () => {
    const state = new SuperTokenState();

    state.storeAttemptByErrorCode('E1');
    state.storeAttemptByErrorCode('E1');
    state.storeAttemptByErrorCode('E1');

    expect(state.getAttemptByErrorCode('E1')).toBe(3);
    expect(state.getAttemptByErrorCode('E2')).toBe(0);
  });

  it('Given stored payment methods, When queried, Then presence and contents are reported', () => {
    const state = new SuperTokenState();
    expect(state.hasStoredPaymentMethods()).toBe(false);

    const methods = [creditCard(), accountMoney()];
    state.storePaymentMethodsInMemory(methods);

    expect(state.hasStoredPaymentMethods()).toBe(true);
    expect(state.getStoredPaymentMethods()).toBe(methods);
  });

  it('Given a preloaded selection, When resolving it against the stored list, Then the matching method is found by identifier', () => {
    const state = new SuperTokenState();
    const stored = creditCard({ id: 'cc1', card: { card_number: { last_four_digits: '1234' } } });
    state.storePaymentMethodsInMemory([accountMoney(), stored]);
    state.storeSelectedPreloadedPaymentMethod(
      creditCard({ id: 'cc1', card: { card_number: { last_four_digits: '1234' } } }),
    );

    expect(state.getSelectedPreloadedPaymentMethodFromActivePaymentMethods()).toBe(stored);
  });

  it('Given an active selection, When stored, Then it also becomes the last chosen method', () => {
    const state = new SuperTokenState();
    const card = creditCard();

    state.storeActivePaymentMethod(card);

    expect(state.getActivePaymentMethod()).toBe(card);
    expect(state.getLastPaymentMethodChoosen()).toBe(card);
  });

  it('Given a previously chosen method, When the active selection is cleared, Then the last chosen method is preserved', () => {
    const state = new SuperTokenState();
    const card = creditCard();
    state.storeActivePaymentMethod(card);

    state.storeActivePaymentMethod(null);

    expect(state.getActivePaymentMethod()).toBeNull();
    expect(state.getLastPaymentMethodChoosen()).toBe(card);
  });

  it('Given a checkout in progress, When reset runs, Then session state clears but the preloaded selection survives', () => {
    const state = new SuperTokenState();
    state.storePaymentMethodsInMemory([creditCard()]);
    state.storeActivePaymentMethod(creditCard());
    state.storeAttemptByErrorCode('E1');
    const preloaded = creditCard({ id: 'cc9' });
    state.storeSelectedPreloadedPaymentMethod(preloaded);

    state.reset();

    expect(state.hasStoredPaymentMethods()).toBe(false);
    expect(state.getActivePaymentMethod()).toBeNull();
    expect(state.getAttemptByErrorCode('E1')).toBe(0);
    expect(state.getSelectedPreloadedPaymentMethod()).toBe(preloaded);
  });
});
