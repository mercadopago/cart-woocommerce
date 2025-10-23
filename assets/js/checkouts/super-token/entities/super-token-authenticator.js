/* globals wc_mercadopago_supertoken_authenticator_params */
/* eslint-disable no-unused-vars */
class MPSuperTokenAuthenticator {
    AMOUNT_ELEMENT_ID = 'mp-amount';
    PLATFORM_ID = wc_mercadopago_supertoken_authenticator_params.platform_id;

    // Attributes
    ableToUseSuperToken = null;
    amountUsed = null;
    authenticator = null;
    userClosedModal = false;

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
        this.ableToUseSuperToken = null;
    }

    isAbleToUseSuperToken() {
        return this.ableToUseSuperToken === true;
    }

    emailAlreadyVerified() {
        return this.ableToUseSuperToken !== null;
    }

    getAmountUsed() {
        return this.amountUsed;
    }

    isUserClosedModalError(error) {
        return error?.errorCode === 'NO_USER_CONFIRMATION';
    }

    storeUserClosedModal() {
        this.userClosedModal = true;
    }

    isUserClosedModal() {
        return this.userClosedModal;
    }

    storeAuthenticator(authenticator) {
        this.authenticator = authenticator;
    }

    getStoredAuthenticator() {
        return this.authenticator;
    }

    async buildAuthenticator(amount, buyerEmail) {
        this.amountUsed = amount;

        const authenticator = await this.mpSdkInstance
            .authenticator(amount, buyerEmail, { platformId: this.PLATFORM_ID });

        return authenticator;
    }

    async canUseSuperTokenFlow(amount, buyerEmail) {
        try {
            const authenticator = await this.buildAuthenticator(amount, buyerEmail);

            this.ableToUseSuperToken = true;
            this.mpSuperTokenMetrics.canUseSuperToken(true);

            return !!authenticator;
        } catch (error) {
            this.ableToUseSuperToken = false;
            return false;
        }
    }

    async renderAccountPaymentMethods(token) {
        try {
            const accountPaymentMethods = await this.mpSuperTokenPaymentMethods.getAccountPaymentMethods(token);

            if (!accountPaymentMethods?.data.length) {
                throw new Error('EMPTY_ACCOUNT_PAYMENT_METHODS');
            }

            this.mpSuperTokenPaymentMethods.renderAccountPaymentMethods(accountPaymentMethods.data, this.amountUsed);
        } catch (error) {
            this.mpSuperTokenMetrics.errorToRenderAccountPaymentMethods(error);
        }
    }

    async showAuthenticator(authenticator, showOptions = null) {
        try {
            const token = await authenticator.show(showOptions);

            await this.renderAccountPaymentMethods(token);
        } catch (error) {
            if (this.isUserClosedModalError(error)) {
                this.storeUserClosedModal();
            }

            this.mpSuperTokenMetrics.errorToShowAuthenticator(error);
        }
    }

    async authenticate(amount, buyerEmail, showOptions = null) {
        if (this.ableToUseSuperToken === false) return;

        const authenticator = await this.buildAuthenticator(amount, buyerEmail);

        this.mpSuperTokenMetrics.canUseSuperToken(true);

        await this.showAuthenticator(authenticator, showOptions);
    }

    async getPreloadedPaymentMethods(amount, buyerEmail) {
        try {
            const authenticator = await this.buildAuthenticator(amount, buyerEmail);
            const preloadedPaymentMethods = await authenticator.getPreloadedPaymentMethods();

            if (!preloadedPaymentMethods?.length) {
                throw new Error('EMPTY_PRELOADED_PAYMENT_METHODS');
            }

            this.storeAuthenticator(authenticator);

            return preloadedPaymentMethods;
        } catch (error) {
            this.mpSuperTokenMetrics.errorToGetPreloadedPaymentMethods(error);
        }
    }

    async showAuthenticatorWithPreloadedPaymentMethods() {
        try {
            const token = await this.getStoredAuthenticator().show({ confirmationLocation: 'app', skipAllUserConfirmation: true });

            await this.renderAccountPaymentMethods(token);
        } catch (error) {
            this.mpSuperTokenMetrics.errorToShowAuthenticator(error);
        }
    }
}
