const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const filePath = resolveAlias('assets/js/checkouts/super-token/errors/super-token-error-handler.js');

describe('MPSuperTokenErrorHandler', () => {
  let MPSuperTokenErrorHandler;
  let errorHandler;
  let mockMetrics;
  let mockPaymentMethods;

  beforeAll(() => {
    global.MPSuperTokenErrorCodes = {
      SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR',
      SELECT_PAYMENT_METHOD_NOT_VALID: 'SELECT_PAYMENT_METHOD_NOT_VALID',
      AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED: 'AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED',
      UNKNOWN_ERROR: 'UNKNOWN_ERROR',
    };

    MPSuperTokenErrorHandler = loadFile(filePath, 'MPSuperTokenErrorHandler', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();

    mockMetrics = {
      errorOnSubmit: jest.fn(),
    };

    mockPaymentMethods = {
      forceShowValidationErrors: jest.fn(),
      convertErrorCodeToErrorMessage: jest.fn().mockReturnValue('Generic error message'),
      showSuperTokenError: jest.fn(),
    };

    errorHandler = new MPSuperTokenErrorHandler(mockPaymentMethods, mockMetrics);
  });

  describe('reportErrorMetric()', () => {
    test('Given SELECT_PAYMENT_METHOD_ERROR, When reportErrorMetric() is called, Then should call errorOnSubmit with error code and message', () => {
      errorHandler.reportErrorMetric('SELECT_PAYMENT_METHOD_ERROR', 'validation failed');

      expect(mockMetrics.errorOnSubmit).toHaveBeenCalledWith(
        'SELECT_PAYMENT_METHOD_ERROR',
        'validation failed'
      );
    });

    test('Given SELECT_PAYMENT_METHOD_NOT_VALID, When reportErrorMetric() is called, Then should call errorOnSubmit with error code and message', () => {
      errorHandler.reportErrorMetric('SELECT_PAYMENT_METHOD_NOT_VALID', 'method not valid');

      expect(mockMetrics.errorOnSubmit).toHaveBeenCalledWith(
        'SELECT_PAYMENT_METHOD_NOT_VALID',
        'method not valid'
      );
    });

    test('Given AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED, When reportErrorMetric() is called, Then should pass error code as-is', () => {
      errorHandler.reportErrorMetric('AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED', 'user cancelled');

      expect(mockMetrics.errorOnSubmit).toHaveBeenCalledWith(
        'AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED',
        'user cancelled'
      );
    });

    test('Given unexpected error code, When reportErrorMetric() is called, Then should call errorOnSubmit with error code and message', () => {
      errorHandler.reportErrorMetric('AUTHENTICATOR_NOT_FOUND', 'authenticator missing');

      expect(mockMetrics.errorOnSubmit).toHaveBeenCalledWith(
        'AUTHENTICATOR_NOT_FOUND',
        'authenticator missing'
      );
    });

    test('Given UNKNOWN_ERROR, When reportErrorMetric() is called, Then should call errorOnSubmit with error code and message', () => {
      errorHandler.reportErrorMetric('UNKNOWN_ERROR', 'unknown error');

      expect(mockMetrics.errorOnSubmit).toHaveBeenCalledWith('UNKNOWN_ERROR', 'unknown error');
    });
  });

  describe('parseError()', () => {
    test('Given an Error object, When parseError() is called, Then should return its string representation as code and message', () => {
      const error = new Error('something went wrong');
      const result = errorHandler.parseError(error);

      expect(result.code).toContain('something went wrong');
      expect(result.message).toContain('something went wrong');
    });

    test('Given a string, When parseError() is called, Then should return the string as both code and message', () => {
      const result = errorHandler.parseError('string error');

      expect(result.code).toBe('string error');
      expect(result.message).toBe('string error');
    });
  });

  describe('handleError()', () => {
    test('Given an expected error exception, When handleError() is called, Then should report metric with error code and message', () => {
      const exception = new Error('SELECT_PAYMENT_METHOD_ERROR');

      errorHandler.handleError(exception);

      expect(mockMetrics.errorOnSubmit).toHaveBeenCalledWith(
        expect.stringContaining('SELECT_PAYMENT_METHOD_ERROR'),
        expect.any(String)
      );
    });

    test('Given an unexpected exception, When handleError() is called, Then should report metric with error code and message', () => {
      const exception = new Error('Network timeout');

      errorHandler.handleError(exception);

      expect(mockMetrics.errorOnSubmit).toHaveBeenCalledTimes(1);
      expect(mockMetrics.errorOnSubmit).toHaveBeenCalledWith(
        expect.stringContaining('Network timeout'),
        expect.any(String)
      );
    });
  });
});
