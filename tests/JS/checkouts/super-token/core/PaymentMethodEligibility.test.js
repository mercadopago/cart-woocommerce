const {
  securityCodeIsRequired,
  shouldFetchPaymentMethodAgain,
  hasMissingEsc,
  getSkipReason,
} = require('@super-token/core/checkoutSession/PaymentMethodEligibility');
const { creditCard, accountMoney } = require('./fixtures');

describe('PaymentMethodEligibility', () => {
  describe('securityCodeIsRequired', () => {
    it('Given mandatory security code settings, When checked, Then the CVV is required', () => {
      expect(securityCodeIsRequired({ mode: 'mandatory', length: 3 })).toBe(true);
    });

    it('Given optional or missing settings, When checked, Then the CVV is not required', () => {
      expect(securityCodeIsRequired({ mode: 'optional', length: 3 })).toBe(false);
      expect(securityCodeIsRequired(undefined)).toBe(false);
    });
  });

  describe('shouldFetchPaymentMethodAgain (RN-3)', () => {
    it('Given a mandatory-CVV card with ESC available and not yet double-checked, When evaluated, Then a re-fetch is required', () => {
      const card = creditCard({ has_esc: true });

      expect(shouldFetchPaymentMethodAgain(card, false)).toBe(true);
    });

    it('Given the element was already double-checked, When evaluated, Then no re-fetch happens (guard as parameter)', () => {
      const card = creditCard({ has_esc: true });

      expect(shouldFetchPaymentMethodAgain(card, true)).toBe(false);
    });

    it('Given a card without ESC available, When evaluated, Then no re-fetch happens', () => {
      const card = creditCard({ has_esc: false });

      expect(shouldFetchPaymentMethodAgain(card, false)).toBe(false);
    });

    it('Given no payment method, When evaluated, Then it throws PAYMENT_METHOD_NOT_EXISTS', () => {
      expect(() => shouldFetchPaymentMethodAgain(null, false)).toThrow('PAYMENT_METHOD_NOT_EXISTS');
    });
  });

  describe('hasMissingEsc (RN-3)', () => {
    it('Given a mandatory-CVV card with an undefined ESC, When evaluated, Then the ESC is missing', () => {
      const card = creditCard({ has_esc: undefined });

      expect(hasMissingEsc(card)).toBe(true);
    });

    it('Given the ESC has been resolved, When evaluated, Then it is not missing', () => {
      expect(hasMissingEsc(creditCard({ has_esc: true }))).toBe(false);
      expect(hasMissingEsc(creditCard({ has_esc: false }))).toBe(false);
    });

    it('Given a non-card method, When evaluated, Then the ESC concept does not apply', () => {
      expect(hasMissingEsc(accountMoney())).toBe(false);
    });
  });

  describe('getSkipReason (RN-3)', () => {
    it('Given the element was already double-checked, When asked, Then the reason is already_checked', () => {
      expect(getSkipReason(creditCard({ has_esc: true }), true)).toBe('already_checked');
    });

    it('Given a non-card method, When asked, Then the reason is not_card', () => {
      expect(getSkipReason(accountMoney(), false)).toBe('not_card');
    });

    it('Given a card without a required security code, When asked, Then the reason is security_code_not_required', () => {
      const card = creditCard({ security_code_settings: { mode: 'optional', length: 3 } });

      expect(getSkipReason(card, false)).toBe('security_code_not_required');
    });

    it('Given a mandatory-CVV card without ESC enabled, When asked, Then the reason is esc_disabled', () => {
      expect(getSkipReason(creditCard({ has_esc: false }), false)).toBe('esc_disabled');
    });

    it('Given a mandatory-CVV card with ESC available, When asked, Then the reason is unknown', () => {
      expect(getSkipReason(creditCard({ has_esc: true }), false)).toBe('unknown');
    });
  });
});
