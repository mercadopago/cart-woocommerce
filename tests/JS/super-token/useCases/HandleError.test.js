const { HandleError } = require('@super-token/useCases/HandleError');

const buildSession = (overrides = {}) => ({
  reportErrorMetric: jest.fn(),
  forceShowValidationErrors: jest.fn(),
  getErrorMessage: jest.fn(() => 'Something went wrong'),
  showError: jest.fn(),
  ...overrides,
});

const run = (session, exception) => new HandleError().execute({ session, exception });

describe('HandleError', () => {
  it('Given an error string, When handled, Then it reports the metric and shows the generic error message', () => {
    const session = buildSession({ getErrorMessage: jest.fn(() => 'Oops') });

    const code = run(session, 'SOME_ERROR_CODE');

    expect(session.reportErrorMetric).toHaveBeenCalledWith('SOME_ERROR_CODE', 'SOME_ERROR_CODE');
    expect(session.getErrorMessage).toHaveBeenCalledWith('SOME_ERROR_CODE');
    expect(session.showError).toHaveBeenCalledWith('Oops');
    expect(session.forceShowValidationErrors).not.toHaveBeenCalled();
    expect(code).toBe('SOME_ERROR_CODE');
  });

  it('Given a SELECT_PAYMENT_METHOD_NOT_VALID code, When handled, Then it forces validation errors instead of a generic message', () => {
    const session = buildSession();
    const exception = 'Error: SELECT_PAYMENT_METHOD_NOT_VALID';

    run(session, exception);

    expect(session.forceShowValidationErrors).toHaveBeenCalledTimes(1);
    expect(session.showError).not.toHaveBeenCalled();
  });

  it('Given an Error object, When handled, Then it normalises it to string and uses it as code and message', () => {
    const session = buildSession();
    const err = new Error('AUTHORIZE_PAYMENT_METHOD_ERROR');

    const code = run(session, err);

    expect(session.reportErrorMetric).toHaveBeenCalledWith(
      'Error: AUTHORIZE_PAYMENT_METHOD_ERROR',
      'Error: AUTHORIZE_PAYMENT_METHOD_ERROR',
    );
    expect(code).toBe('Error: AUTHORIZE_PAYMENT_METHOD_ERROR');
  });

  it('Given an empty string, When handled, Then it falls back to UNKNOWN_ERROR', () => {
    const session = buildSession();

    const code = run(session, '');

    expect(session.reportErrorMetric).toHaveBeenCalledWith('UNKNOWN_ERROR', 'Unknown error');
    expect(code).toBe('UNKNOWN_ERROR');
  });

  it('Given the error, When handled, Then it runs in order: metric then display', () => {
    const calls = [];
    const session = buildSession({
      reportErrorMetric: jest.fn(() => calls.push('metric')),
      showError: jest.fn(() => calls.push('show')),
    });

    run(session, 'SOME_ERROR');

    expect(calls).toEqual(['metric', 'show']);
  });
});
