const {
  LegacyErrorHandlerSession,
} = require('@super-token/adapters/session/LegacyErrorHandlerSession');

const buildMetrics = (overrides = {}) => ({
  errorOnSubmit: jest.fn(),
  ...overrides,
});

const buildController = (overrides = {}) => ({
  forceShowValidationErrors: jest.fn(),
  convertErrorCodeToErrorMessage: jest.fn(() => 'Translated message'),
  showSuperTokenError: jest.fn(),
  ...overrides,
});

describe('LegacyErrorHandlerSession', () => {
  it('Given an error code and message, When reporting the metric, Then it forwards to errorOnSubmit', () => {
    const metrics = buildMetrics();
    const session = new LegacyErrorHandlerSession(metrics, buildController());

    session.reportErrorMetric('SOME_CODE', 'some message');

    expect(metrics.errorOnSubmit).toHaveBeenCalledWith('SOME_CODE', 'some message');
  });

  it('Given a validation error, When forcing validation errors, Then it forwards to the controller', () => {
    const controller = buildController();
    const session = new LegacyErrorHandlerSession(buildMetrics(), controller);

    session.forceShowValidationErrors();

    expect(controller.forceShowValidationErrors).toHaveBeenCalledTimes(1);
  });

  it('Given an error code, When getting the error message, Then it returns the translated string from the controller', () => {
    const controller = buildController({
      convertErrorCodeToErrorMessage: jest.fn(() => 'Payment failed'),
    });
    const session = new LegacyErrorHandlerSession(buildMetrics(), controller);

    const message = session.getErrorMessage('AUTHORIZE_PAYMENT_METHOD_ERROR');

    expect(controller.convertErrorCodeToErrorMessage).toHaveBeenCalledWith(
      'AUTHORIZE_PAYMENT_METHOD_ERROR',
    );
    expect(message).toBe('Payment failed');
  });

  it('Given an error message, When showing the error, Then it forwards to showSuperTokenError', () => {
    const controller = buildController();
    const session = new LegacyErrorHandlerSession(buildMetrics(), controller);

    session.showError('Something went wrong');

    expect(controller.showSuperTokenError).toHaveBeenCalledWith('Something went wrong');
  });
});
