/* globals wc_mercadopago_supertoken_authenticator_params, MPSuperTokenErrorCodes */
/* eslint-disable no-unused-vars */
class MPSuperTokenAuthenticator {
  AMOUNT_ELEMENT_ID = 'mp-amount';
  PLATFORM_ID = wc_mercadopago_supertoken_bundle_params.platform_id;
  SUPER_TOKEN_VALIDATION_ELEMENT_ID = 'super_token_validation';
  AUTHORIZED_PSEUDOTOKEN_ELEMENT_ID = 'authorized_pseudotoken';

  // Attributes
  amountUsed = null;
  authenticator = null;
  fastPaymentToken = null;

  // Dependencies
  mpSdkInstance = null;
  mpSuperTokenPaymentMethods = null;
  mpSuperTokenMetrics = null;

  constructor(mpSdkInstance, mpSuperTokenPaymentMethods, mpSuperTokenMetrics) {
      this.mpSdkInstance = mpSdkInstance;
      this.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
      this.mpSuperTokenMetrics = mpSuperTokenMetrics;
  }

  reset() {
    this.authenticator = null;
    this.fastPaymentToken = null;
  }

  setSuperTokenValidation(value) {
    const superTokenValidationElement = document.getElementById(this.SUPER_TOKEN_VALIDATION_ELEMENT_ID);
    if (superTokenValidationElement) {
      superTokenValidationElement.value = value ? 'true' : 'false';
    }
  }

  getAmountUsed() {
      return this.amountUsed;
  }

  storeAuthenticator(authenticator) {
      this.authenticator = authenticator;
  }

  getStoredAuthenticator() {
      return this.authenticator;
  }

  storeFastPaymentToken(token) {
      this.fastPaymentToken = token;
  }

  getStoredFastPaymentToken() {
      return this.fastPaymentToken;
  }

  formatAmount(amount = '') {
    const rawValue = amount?.replace(/[^\d.,]/g, '');
    if (!rawValue) return null;

    const lastCommaIndex = rawValue.lastIndexOf(',');
    const lastDotIndex = rawValue.lastIndexOf('.');

    const isEuropean = lastCommaIndex > lastDotIndex;
    const normalizedValue = rawValue.replace(/[.,]/g, (match, offset) => {
        if (isEuropean) {
            return match === ',' ? '.' : '';
        } else {
            return match === '.' ? '.' : '';
        }
    });

    const value = parseFloat(normalizedValue);

    return isNaN(value) ? null : value.toFixed(2);
  }

  async buildAuthenticator(amount, buyerEmail) {
    try {
      this.amountUsed = amount;

      const authenticator = await this.mpSdkInstance
          .authenticator(amount, buyerEmail, { platformId: this.PLATFORM_ID, version: 2 });

      return authenticator;
    } catch (error) {
      this.mpSuperTokenMetrics.errorToBuildAuthenticator(error);
      return null;
    }
  }

  async getSimplifiedAuth(authenticator) {
    try {
      if (!authenticator) return false;

      return await authenticator.getSimplifiedAuth();
    } catch (error) {
      this.mpSuperTokenMetrics.errorToGetSimplifiedAuth(error);
      return false;
    }
  }

  async getFastPaymentToken(authenticator) {
      try {
        if (!authenticator) return null;

        return await authenticator.getFastPaymentToken();
      } catch (error) {
        this.mpSuperTokenMetrics.errorToGetFastPaymentToken(error);
        return null;
      }
  }

  storeAuthorizedPseudotoken(pseudotoken) {
    const authorizedPseudotokenElement = document.getElementById(this.AUTHORIZED_PSEUDOTOKEN_ELEMENT_ID);

    this.mpSuperTokenMetrics.registerAuthorizedPseudotoken(pseudotoken, authorizedPseudotokenElement ? true : false);

    if (authorizedPseudotokenElement) {
      authorizedPseudotokenElement.value = pseudotoken;
    }
  }

  async getAccountPaymentMethods(amount, buyerEmail) {
      try {
          const authenticator = await this.buildAuthenticator(amount, buyerEmail);
          if (!authenticator) return null;

          this.storeAuthenticator(authenticator);

          const isSimplified = await this.getSimplifiedAuth(authenticator);
          if (!isSimplified) return null;

          document.dispatchEvent(new CustomEvent('mp-behavior-tracking-super-token-init'));

          this.mpSuperTokenMetrics.canUseSuperToken(true);

          const fastPaymentToken = await this.getFastPaymentToken(authenticator);
          if (!fastPaymentToken) return null;

          this.storeFastPaymentToken(fastPaymentToken);

          const accountPaymentMethods = await this.mpSuperTokenPaymentMethods.getAccountPaymentMethods(fastPaymentToken);
          if (!accountPaymentMethods?.data?.length) {
              throw new Error(MPSuperTokenErrorCodes.EMPTY_ACCOUNT_PAYMENT_METHODS);
          }

          return accountPaymentMethods.data;

      } catch (error) {
          this.mpSuperTokenMetrics.errorToGetAccountPaymentMethods(error);
          return null;
      }
  }

  async authorizePayment(pseudotoken) {
      try {
          const authenticator = this.getStoredAuthenticator();
          if (!authenticator) throw new Error(MPSuperTokenErrorCodes.AUTHENTICATOR_NOT_FOUND);

          const hasSimplified = await this.getSimplifiedAuth(authenticator);
          if (!hasSimplified) return;

          await authenticator.authorizePayment(pseudotoken);

          this.storeAuthorizedPseudotoken(pseudotoken);
      } catch (error) {
        this.mpSuperTokenMetrics.errorToAuthorizePayment(error);

        if (error?.message?.includes('USER_CANCELLED')) throw new Error(MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED);

        throw new Error(MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR);
      }
  }
}