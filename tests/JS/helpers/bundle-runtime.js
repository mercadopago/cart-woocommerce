// Isolate browser resources registered by a real bundle without replacing its runtime.
function createBundleRuntime() {
  jest.useFakeTimers();
  const isBundleGlobal = (key) => key.startsWith('mpSuperToken') || [
    'MPSuperTokenErrorCodes', 'mpResolveCheckoutValidation',
    'hasWooCommerceValidationErrors', 'wc_mercadopago_supertoken_bundle_params',
  ].includes(key);
  const previousGlobals = Object.fromEntries(Object.keys(window)
    .filter(isBundleGlobal)
    .map((key) => [key, window[key]]));
  const listeners = [];
  const spies = [document, window].map((target) => {
    const nativeAdd = target.addEventListener.bind(target);
    return jest.spyOn(target, 'addEventListener').mockImplementation((...args) => {
      listeners.push([target, args]);
      nativeAdd(...args);
    });
  });
  const observers = [];
  const NativeObserver = window.MutationObserver;
  const observerSpy = jest.spyOn(window, 'MutationObserver').mockImplementation((...args) => {
    const observer = new NativeObserver(...args);
    observers.push(observer);
    return observer;
  });

  const waitForReady = async (elapsed = 0) => {
    if (typeof window.mpSuperTokenOrderAndDecorate === 'function') return undefined;
    if (elapsed >= 1000) {
      throw new Error('Fast Payment did not expose mpSuperTokenOrderAndDecorate within 1000 ms');
    }
    await jest.advanceTimersByTimeAsync(10);
    return waitForReady(elapsed + 10);
  };

  return {
    waitForReady,
    cleanup() {
      listeners.forEach(([target, args]) => target.removeEventListener(...args));
      observers.forEach((observer) => observer.disconnect());
      spies.forEach((spy) => spy.mockRestore());
      observerSpy.mockRestore();
      jest.clearAllTimers();
      expect(jest.getTimerCount()).toBe(0);
      jest.useRealTimers();
      Object.keys(window)
        .filter(isBundleGlobal)
        .forEach((key) => {
          if (Object.prototype.hasOwnProperty.call(previousGlobals, key)) {
            window[key] = previousGlobals[key];
          } else {
            delete window[key];
          }
        });
    },
  };
}

module.exports = { createBundleRuntime };
