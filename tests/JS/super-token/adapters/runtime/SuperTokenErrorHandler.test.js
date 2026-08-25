const { SuperTokenErrorHandler } = require('@super-token/adapters/runtime/SuperTokenErrorHandler');

const buildMetrics = (overrides = {}) => ({
  errorOnSubmit: jest.fn(),
  ...overrides,
});

const buildPaymentMethods = (overrides = {}) => ({
  forceShowValidationErrors: jest.fn(),
  convertErrorCodeToErrorMessage: jest.fn(() => 'Something went wrong'),
  showSuperTokenError: jest.fn(),
  ...overrides,
});

const build = (paymentMethods, metrics) => new SuperTokenErrorHandler(paymentMethods, metrics);

describe('SuperTokenErrorHandler', () => {
  it('Given an error string, When handled, Then it reports the metric and shows the generic error message', () => {
    const metrics = buildMetrics();
    const paymentMethods = buildPaymentMethods({
      convertErrorCodeToErrorMessage: jest.fn(() => 'Oops'),
    });

    const code = build(paymentMethods, metrics).handleError('SOME_ERROR_CODE');

    expect(metrics.errorOnSubmit).toHaveBeenCalledWith('SOME_ERROR_CODE', 'SOME_ERROR_CODE');
    expect(paymentMethods.convertErrorCodeToErrorMessage).toHaveBeenCalledWith('SOME_ERROR_CODE');
    expect(paymentMethods.showSuperTokenError).toHaveBeenCalledWith('Oops');
    expect(paymentMethods.forceShowValidationErrors).not.toHaveBeenCalled();
    expect(code).toBe('SOME_ERROR_CODE');
  });

  it('Given a SELECT_PAYMENT_METHOD_NOT_VALID code, When handled, Then it forces validation errors instead of a generic message', () => {
    const metrics = buildMetrics();
    const paymentMethods = buildPaymentMethods();

    const code = build(paymentMethods, metrics).handleError('Error: SELECT_PAYMENT_METHOD_NOT_VALID');

    expect(paymentMethods.forceShowValidationErrors).toHaveBeenCalledTimes(1);
    expect(paymentMethods.showSuperTokenError).not.toHaveBeenCalled();
    expect(paymentMethods.convertErrorCodeToErrorMessage).not.toHaveBeenCalled();
    expect(code).toBe('Error: SELECT_PAYMENT_METHOD_NOT_VALID');
  });

  it('Given an Error object, When handled, Then it normalises it to string and uses it as code and message', () => {
    const metrics = buildMetrics();
    const paymentMethods = buildPaymentMethods();

    const code = build(paymentMethods, metrics).handleError(new Error('AUTHORIZE_PAYMENT_METHOD_ERROR'));

    expect(metrics.errorOnSubmit).toHaveBeenCalledWith(
      'Error: AUTHORIZE_PAYMENT_METHOD_ERROR',
      'Error: AUTHORIZE_PAYMENT_METHOD_ERROR',
    );
    expect(code).toBe('Error: AUTHORIZE_PAYMENT_METHOD_ERROR');
  });

  it('Given an empty string, When handled, Then it falls back to UNKNOWN_ERROR', () => {
    const metrics = buildMetrics();
    const paymentMethods = buildPaymentMethods();

    const code = build(paymentMethods, metrics).handleError('');

    expect(metrics.errorOnSubmit).toHaveBeenCalledWith('UNKNOWN_ERROR', 'Unknown error');
    expect(code).toBe('UNKNOWN_ERROR');
  });

  // Parity guard: only the empty string falls back to UNKNOWN_ERROR. null/undefined are not
  // strings, so the legacy `${exception}` coercion stringifies them into truthy codes ("null" /
  // "undefined") — they are NOT treated as unknown. This locks that boundary against a future
  // "fix" of the normalize step.
  it.each([
    [null, 'null'],
    [undefined, 'undefined'],
  ])('Given %p, When handled, Then it stringifies to %p instead of UNKNOWN_ERROR', (exception, expected) => {
    const metrics = buildMetrics();
    const paymentMethods = buildPaymentMethods();

    const code = build(paymentMethods, metrics).handleError(exception);

    expect(metrics.errorOnSubmit).toHaveBeenCalledWith(expected, expected);
    expect(paymentMethods.convertErrorCodeToErrorMessage).toHaveBeenCalledWith(expected);
    expect(code).toBe(expected);
  });

  it('Given the error, When handled, Then it runs in order: metric then display', () => {
    const calls = [];
    const metrics = buildMetrics({ errorOnSubmit: jest.fn(() => calls.push('metric')) });
    const paymentMethods = buildPaymentMethods({ showSuperTokenError: jest.fn(() => calls.push('show')) });

    build(paymentMethods, metrics).handleError('SOME_ERROR');

    expect(calls).toEqual(['metric', 'show']);
  });
});
