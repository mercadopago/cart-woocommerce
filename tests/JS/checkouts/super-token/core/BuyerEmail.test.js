const {
  isValidBuyerEmail,
  normalizeBuyerEmail,
} = require('@super-token/core/checkoutSession/BuyerEmail');

describe('shared fast-payments buyer email', () => {
  it.each([
    [' buyer@example.com ', true],
    ['not-an-email', false],
    ['', false],
    [`${'a'.repeat(65)}@example.com`, false],
  ])('normalizes and validates %p for every consumer', (email, expected) => {
    expect(isValidBuyerEmail(email)).toBe(expected);
  });

  it('does not coerce non-string input into buyer identity', () => {
    expect(normalizeBuyerEmail({ toString: () => 'buyer@example.com' })).toBe('');
    expect(isValidBuyerEmail({ toString: () => 'buyer@example.com' })).toBe(false);
  });
});
