const { SuperTokenDebounce } = require('@super-token/adapters/runtime/SuperTokenDebounce');

describe('SuperTokenDebounce', () => {
  beforeEach(() => jest.useFakeTimers());
  afterEach(() => jest.useRealTimers());

  it('Given a debounced callback, When invoked once, Then it fires only after 3000ms with the event', () => {
    const callback = jest.fn();
    const debounced = new SuperTokenDebounce().inputDebounce(callback);

    debounced('event');
    jest.advanceTimersByTime(2999);
    expect(callback).not.toHaveBeenCalled();

    jest.advanceTimersByTime(1);
    expect(callback).toHaveBeenCalledTimes(1);
    expect(callback).toHaveBeenCalledWith('event');
  });

  it('Given rapid invocations within the window, When called repeatedly, Then only the last event fires once', () => {
    const callback = jest.fn();
    const debounced = new SuperTokenDebounce().inputDebounce(callback);

    debounced('first');
    jest.advanceTimersByTime(1500);
    debounced('second');
    jest.advanceTimersByTime(2999);
    expect(callback).not.toHaveBeenCalled();

    jest.advanceTimersByTime(1);
    expect(callback).toHaveBeenCalledTimes(1);
    expect(callback).toHaveBeenCalledWith('second');
  });
});
