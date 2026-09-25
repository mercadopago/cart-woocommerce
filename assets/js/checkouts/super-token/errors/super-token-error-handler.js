/* globals MPSuperTokenErrorCodes */
/* eslint-disable no-unused-vars */
class MPSuperTokenErrorHandler {
    /**
     * @param {MPSuperTokenPaymentMethods} mpSuperTokenPaymentMethods
     * @param {MPSuperTokenMetrics} mpSuperTokenMetrics
     */
    constructor(mpSuperTokenPaymentMethods, mpSuperTokenMetrics) {
        this.paymentMethods = mpSuperTokenPaymentMethods;
        this.metrics = mpSuperTokenMetrics;
    }

    /**
     * Parses exception object to extract error code and message
     *
     * @param {Error|string} exception
     * @returns {{code: string, message: string}}
     */
    parseError(exception) {
      const normalizedMessage = typeof exception !== 'string' ? `${exception}` : exception;
      return {
          code: normalizedMessage || MPSuperTokenErrorCodes.UNKNOWN_ERROR,
          message: normalizedMessage || 'Unknown error'
      };
    }

    /**
     * Reports error metric
     *
     * @param {string} errorCode
     * @param {string} errorMessage
     */
    reportErrorMetric(errorCode, errorMessage) {
        this.metrics.errorOnSubmit(errorCode, errorMessage);
    }

    /**
     * Displays error message to user in the checkout UI
     *
     * @param {string} errorCode
     */
    displayError(errorCode) {
        if (errorCode.includes(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID)) {
            this.paymentMethods.forceShowValidationErrors();
        } else {
            const errorMessage = this.paymentMethods.convertErrorCodeToErrorMessage(errorCode);
            this.paymentMethods.showSuperTokenError(errorMessage);
        }
    }

    /**
     * Main error handling method - processes error metrics and display
     *
     * @param {Error|string} exception - The exception to handle
     * @returns {string} The error code
     */
    handleError(exception) {
        const { code, message } = this.parseError(exception);
        this.reportErrorMetric(code, message);
        this.displayError(code);
        return code;
    }
}

