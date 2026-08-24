const { SuperTokenEmailListener } = require('@super-token/adapters/runtime/SuperTokenEmailListener');

const EMAIL_FIELD_SELECTOR =
  'form[name="checkout"] input[type="email"], #email, #billing_email';

const build = ({ emailValue, inputDebounce } = {}) => {
  const on = jest.fn();
  const val = jest.fn(() => emailValue);
  const jquery = jest.fn(() => ({ val, on }));
  const debounce = { inputDebounce: inputDebounce ?? jest.fn((callback) => callback) };
  const listener = new SuperTokenEmailListener(debounce, jquery);
  return { listener, jquery, on, val, debounce };
};

describe('SuperTokenEmailListener', () => {
  describe('isValid', () => {
    it.each([
      ['user@example.com', true],
      ['a@b.co', true],
      ['', false],
      ['not-an-email', false],
      [`${'a'.repeat(250)}@b.com`, false], // 256 chars > 254
      [`${'a'.repeat(65)}@b.com`, false], // local-part 65 > 64
    ])('Given %p, When validated, Then returns %p', (email, expected) => {
      const { listener } = build();
      expect(listener.isValid(email)).toBe(expected);
    });
  });

  describe('getEmail', () => {
    it('Given a value in the e-mail field, When read, Then it returns the trimmed value', () => {
      const { listener, jquery } = build({ emailValue: '  buyer@example.com  ' });

      expect(listener.getEmail()).toBe('buyer@example.com');
      expect(jquery).toHaveBeenCalledWith(EMAIL_FIELD_SELECTOR);
    });

    it('Given no value in the e-mail field, When read, Then it returns undefined', () => {
      const { listener } = build({ emailValue: undefined });

      expect(listener.getEmail()).toBeUndefined();
    });
  });

  describe('onEmailChange', () => {
    it('Given a callback, When registered, Then it returns the listener for chaining', () => {
      const { listener } = build();

      expect(listener.onEmailChange(jest.fn())).toBe(listener);
    });

    it('Given multiple callbacks, When the e-mail updates, Then all of them are notified', () => {
      jest.useFakeTimers();
      const { listener } = build({ emailValue: 'buyer@example.com' });
      const first = jest.fn();
      const second = jest.fn();

      listener.onEmailChange(first).onEmailChange(second);
      listener.setupEmailChangeHandlers();
      jest.advanceTimersByTime(1500);

      expect(first).toHaveBeenCalledWith('buyer@example.com', true);
      expect(second).toHaveBeenCalledWith('buyer@example.com', true);
      jest.useRealTimers();
    });
  });

  describe('setupEmailChangeHandlers', () => {
    it('Given the handlers are set up, When binding, Then it debounces a delegated input handler on the e-mail field', () => {
      const { listener, on, debounce } = build({ emailValue: 'buyer@example.com' });

      listener.setupEmailChangeHandlers();

      expect(debounce.inputDebounce).toHaveBeenCalledTimes(1);
      expect(on).toHaveBeenCalledWith('input', EMAIL_FIELD_SELECTOR, expect.any(Function));
    });

    it('Given a valid e-mail on input, When the debounced handler fires, Then callbacks receive the e-mail and isValid=true', () => {
      const { listener, on } = build({ emailValue: 'buyer@example.com' });
      const callback = jest.fn();
      listener.onEmailChange(callback);

      listener.setupEmailChangeHandlers();
      const boundHandler = on.mock.calls[0][2];
      boundHandler();

      expect(callback).toHaveBeenCalledWith('buyer@example.com', true);
    });

    it('Given an invalid e-mail on input, When the debounced handler fires, Then callbacks receive isValid=false', () => {
      const { listener, on } = build({ emailValue: 'not-an-email' });
      const callback = jest.fn();
      listener.onEmailChange(callback);

      listener.setupEmailChangeHandlers();
      on.mock.calls[0][2]();

      expect(callback).toHaveBeenCalledWith('not-an-email', false);
    });

    it('Given no e-mail present, When the handler fires, Then no callback is notified', () => {
      const { listener, on } = build({ emailValue: undefined });
      const callback = jest.fn();
      listener.onEmailChange(callback);

      listener.setupEmailChangeHandlers();
      on.mock.calls[0][2]();

      expect(callback).not.toHaveBeenCalled();
    });

    it('Given the handlers are set up, When the initial timer elapses, Then it fires one deferred update after INTERVAL_TIME', () => {
      jest.useFakeTimers();
      const { listener } = build({ emailValue: 'buyer@example.com' });
      const callback = jest.fn();
      listener.onEmailChange(callback);

      listener.setupEmailChangeHandlers();
      expect(callback).not.toHaveBeenCalled();

      jest.advanceTimersByTime(1500);
      expect(callback).toHaveBeenCalledTimes(1);
      jest.useRealTimers();
    });
  });
});
