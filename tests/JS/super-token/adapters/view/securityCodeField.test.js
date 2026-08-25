const { buildSecurityCodeField } = require('@super-token/adapters/view/shared/securityCodeField');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { buildViewDeps } = require('./fixtures');
const { creditCard, prepaidCard } = require('../../core/fixtures');

describe('buildSecurityCodeField', () => {
  it('Given a card that requires a CVV, When built, Then it renders the container with the token-keyed mount point', () => {
    const field = buildSecurityCodeField(creditCard({ token: 'tok-1' }), buildViewDeps());

    expect(field.classList.contains(SHARED_STYLES.SECURITY_CODE_CONTAINER)).toBe(true);
    expect(field.id).toBe('mp-super-token-security-code-container-tok-1');
    expect(field.querySelector(`#mp-super-token-security-code-input-tok-1`)).not.toBeNull();
    expect(field.querySelector(`.${SHARED_STYLES.SECURITY_CODE_LABEL}`).textContent).toBe('Código de segurança');
  });

  it.each([
    [3, '3 dígitos no verso'],
    [4, '4 dígitos na frente'],
  ])('Given a %s-digit CVV, When built, Then the matching tooltip text is used', (length, tooltip) => {
    const field = buildSecurityCodeField(
      creditCard({ security_code_settings: { mode: 'mandatory', length } }),
      buildViewDeps(),
    );

    expect(field.querySelector(`.${SHARED_STYLES.SECURITY_CODE_TOOLTIP}`).getAttribute('aria-label')).toBe(tooltip);
  });

  it('Given a card that does not require a CVV, When built, Then it returns null', () => {
    expect(buildSecurityCodeField(prepaidCard(), buildViewDeps())).toBeNull();
    expect(
      buildSecurityCodeField(creditCard({ security_code_settings: { mode: 'optional', length: 3 } }), buildViewDeps()),
    ).toBeNull();
  });

  it('Given the token, When built, Then it lands only in id attributes, never as markup (SEC-3)', () => {
    const field = buildSecurityCodeField(creditCard({ token: 'a"><img src=x>' }), buildViewDeps());

    expect(field.querySelector('img')).toBeNull();
    expect(field.id).toBe('mp-super-token-security-code-container-a"><img src=x>');
  });
});
