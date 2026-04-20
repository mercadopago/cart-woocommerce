const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');

const emailListenerPath = resolveAlias('assets/js/checkouts/super-token/entities/email-listener.js');

describe('WCEmailListener', () => {
  let WCEmailListener;
  let emailListener;
  let mockDebounce;
  let mockJQueryOn;
  let mockJQueryVal;

  // jQueryProxy is created once and passed to the VM context via loadFile.
  // Its implementation is swapped per test via mockImplementation in beforeEach.
  // This is necessary because vm.runInNewContext captures the function reference,
  // not the value of global.jQuery at call time.
  let jQueryProxy;

  beforeAll(() => {
    // Fake timers must be installed BEFORE loadFile so the VM context
    // captures the fake setTimeout (not the real one), enabling jest.advanceTimersByTime().
    jest.useFakeTimers();

    jQueryProxy = jest.fn();
    global.jQuery = jQueryProxy;

    WCEmailListener = loadFile(emailListenerPath, 'WCEmailListener', global);
  });

  afterAll(() => {
    jest.useRealTimers();
  });

  beforeEach(() => {
    mockJQueryOn = jest.fn();
    mockJQueryVal = jest.fn().mockReturnValue('');

    jQueryProxy.mockImplementation((target) => {
      if (target === document) {
        return { on: mockJQueryOn };
      }
      return { val: mockJQueryVal };
    });

    mockDebounce = {
      inputDebounce: jest.fn((fn) => fn),
    };

    emailListener = new WCEmailListener(mockDebounce);
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  describe('constructor', () => {
    test('should initialize _callbacks as an empty array', () => {
      expect(emailListener._callbacks).toEqual([]);
    });

    test('should store the mpDebounce dependency', () => {
      expect(emailListener.mpDebounce).toBe(mockDebounce);
    });
  });

  // ---------------------------------------------------------------------------
  describe('isValid(email)', () => {
    describe('valid emails', () => {
      test.each([
        ['simple email', 'user@example.com'],
        ['email with plus sign', 'user+tag@example.com'],
        ['email with dots in local part', 'first.last@example.com'],
        ['email from brazil', 'first.last@example.com.br'],
        ['email with subdomain', 'user@mail.example.com'],
        ['email with uppercase letters', 'User@Example.COM'],
        ['email with 2-char TLD', 'user@example.br'],
        ['email with long TLD', 'user@example.technology'],
        ['email with hyphen in domain', 'user@my-domain.com'],
      ])('Given %s "%s", When isValid() is called, Then should return true', (_, email) => {
        expect(emailListener.isValid(email)).toBe(true);
      });
    });

    describe('invalid emails', () => {
      test.each([
        ['empty string', ''],
        ['missing @', 'userexample.com'],
        ['missing domain', 'user@'],
        ['missing local part', '@example.com'],
        ['missing TLD', 'user@example'],
        ['TLD with 1 char', 'user@example.c'],
        ['only spaces', '   '],
      ])('Given %s "%s", When isValid() is called, Then should return false', (_, email) => {
        expect(emailListener.isValid(email)).toBe(false);
      });
    });

  });

  // ---------------------------------------------------------------------------
  describe('getEmail()', () => {
    test('Given email field in DOM with value, When getEmail() is called, Then should return the trimmed email', () => {
      mockJQueryVal.mockReturnValue('user@example.com');

      expect(emailListener.getEmail()).toBe('user@example.com');
    });

    test('Given email field with surrounding whitespace, When getEmail() is called, Then should return trimmed value', () => {
      mockJQueryVal.mockReturnValue('  user@example.com  ');

      expect(emailListener.getEmail()).toBe('user@example.com');
    });

    test('Given email field with empty value, When getEmail() is called, Then should return empty string', () => {
      mockJQueryVal.mockReturnValue('');

      expect(emailListener.getEmail()).toBe('');
    });

    test('Given no email field in DOM, When getEmail() is called, Then should return undefined', () => {
      mockJQueryVal.mockReturnValue(undefined);

      expect(emailListener.getEmail()).toBeUndefined();
    });
  });

  // ---------------------------------------------------------------------------
  describe('onEmailChange(callback)', () => {
    test('Given a callback function, When onEmailChange() is called, Then should add it to _callbacks', () => {
      const callback = jest.fn();

      emailListener.onEmailChange(callback);

      expect(emailListener._callbacks).toContain(callback);
      expect(emailListener._callbacks).toHaveLength(1);
    });

    test('Given a callback, When onEmailChange() is called, Then should return `this` for chaining', () => {
      const result = emailListener.onEmailChange(jest.fn());

      expect(result).toBe(emailListener);
    });

    test('Given two different callbacks, When onEmailChange() is called twice, Then should register both', () => {
      const callbackA = jest.fn();
      const callbackB = jest.fn();

      emailListener.onEmailChange(callbackA);
      emailListener.onEmailChange(callbackB);

      expect(emailListener._callbacks).toContain(callbackA);
      expect(emailListener._callbacks).toContain(callbackB);
      expect(emailListener._callbacks).toHaveLength(2);
    });

    test('Given the same callback registered twice, When onEmailChange() is called twice, Then accumulates duplicates (bug: no deduplication)', () => {
      // BUG: no deduplication — same callback added multiple times causes it to fire multiple times
      const callback = jest.fn();

      emailListener.onEmailChange(callback);
      emailListener.onEmailChange(callback);

      expect(emailListener._callbacks).toHaveLength(2);
      expect(emailListener._callbacks.filter((cb) => cb === callback)).toHaveLength(2);
    });

    test('Given chaining is used, When onEmailChange() is chained, Then all callbacks are registered', () => {
      const callbackA = jest.fn();
      const callbackB = jest.fn();

      emailListener.onEmailChange(callbackA).onEmailChange(callbackB);

      expect(emailListener._callbacks).toHaveLength(2);
    });
  });

  // ---------------------------------------------------------------------------
  describe('setupEmailChangeHandlers()', () => {
    test('Given mpDebounce is injected, When setupEmailChangeHandlers() is called, Then should use inputDebounce to wrap the handler', () => {
      emailListener.setupEmailChangeHandlers();

      expect(mockDebounce.inputDebounce).toHaveBeenCalledTimes(1);
    });

    test('Given setupEmailChangeHandlers() is called, When executed, Then should register a jQuery input listener on document', () => {
      const EMAIL_SELECTOR = emailListener.EMAIL_FIELD_SELECTOR;

      emailListener.setupEmailChangeHandlers();

      expect(mockJQueryOn).toHaveBeenCalledWith('input', EMAIL_SELECTOR, expect.any(Function));
    });

    test('Given a callback is registered, When input event fires with a valid email, Then callback receives (email, true)', () => {
      const callback = jest.fn();
      emailListener.onEmailChange(callback);

      mockJQueryVal.mockReturnValue('user@example.com');
      mockDebounce.inputDebounce.mockImplementation((fn) => fn);

      emailListener.setupEmailChangeHandlers();

      // Simulate the input event firing
      const inputHandler = mockJQueryOn.mock.calls[0][2];
      inputHandler();

      expect(callback).toHaveBeenCalledWith('user@example.com', true);
    });

    test('Given a callback is registered, When input event fires with an invalid email, Then callback receives (email, false)', () => {
      const callback = jest.fn();
      emailListener.onEmailChange(callback);

      mockJQueryVal.mockReturnValue('invalid-email');
      mockDebounce.inputDebounce.mockImplementation((fn) => fn);

      emailListener.setupEmailChangeHandlers();

      const inputHandler = mockJQueryOn.mock.calls[0][2];
      inputHandler();

      expect(callback).toHaveBeenCalledWith('invalid-email', false);
    });

    test('Given multiple callbacks registered, When input event fires, Then all callbacks are called', () => {
      const callbackA = jest.fn();
      const callbackB = jest.fn();
      emailListener.onEmailChange(callbackA).onEmailChange(callbackB);

      mockJQueryVal.mockReturnValue('user@example.com');
      mockDebounce.inputDebounce.mockImplementation((fn) => fn);

      emailListener.setupEmailChangeHandlers();

      const inputHandler = mockJQueryOn.mock.calls[0][2];
      inputHandler();

      expect(callbackA).toHaveBeenCalledTimes(1);
      expect(callbackB).toHaveBeenCalledTimes(1);
    });

    test('Given a callback is registered, When 1500ms timeout fires with a valid email in the field, Then callback is called with current email', () => {
      const callback = jest.fn();
      emailListener.onEmailChange(callback);
      mockJQueryVal.mockReturnValue('user@example.com');

      emailListener.setupEmailChangeHandlers();

      expect(callback).not.toHaveBeenCalled();

      jest.advanceTimersByTime(emailListener.INTERVAL_TIME);

      expect(callback).toHaveBeenCalledWith('user@example.com', true);
    });

    test('Given email field is empty, When input event fires with empty string, Then callbacks are NOT called (bug: falsy check skips empty email notification)', () => {
      // BUG: `if (email)` skips callbacks when email is empty string
      // This means if a user clears the email field, the Super Token flow is never reset
      const callback = jest.fn();
      emailListener.onEmailChange(callback);

      mockJQueryVal.mockReturnValue('');
      mockDebounce.inputDebounce.mockImplementation((fn) => fn);

      emailListener.setupEmailChangeHandlers();

      const inputHandler = mockJQueryOn.mock.calls[0][2];
      inputHandler();

      expect(callback).not.toHaveBeenCalled();
    });

    test('Given setupEmailChangeHandlers() is called twice, When input event fires, Then jQuery.on is registered twice (bug: no guard against duplicate registration)', () => {
      // BUG: no `isAlreadyListening` guard — multiple calls stack jQuery listeners
      emailListener.setupEmailChangeHandlers();
      emailListener.setupEmailChangeHandlers();

      expect(mockJQueryOn).toHaveBeenCalledTimes(2);
    });
  });
});
