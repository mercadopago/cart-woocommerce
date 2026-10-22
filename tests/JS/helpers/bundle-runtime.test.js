const { createBundleRuntime } = require('./bundle-runtime');

test('bundle isolation removes document/window listeners, disconnects observers and clears timers', () => {
  const previousResolver = window.mpResolveCheckoutValidation;
  const runtime = createBundleRuntime();
  window.mpResolveCheckoutValidation = jest.fn();
  const handler = jest.fn();
  const observer = new MutationObserver(handler);
  const disconnect = jest.spyOn(observer, 'disconnect');
  observer.observe(document.body, { childList: true });
  document.addEventListener('bundle-isolation', handler);
  window.addEventListener('bundle-isolation', handler);
  setTimeout(handler, 100);
  const element = document.createElement('div');
  document.body.appendChild(element);

  runtime.cleanup();
  document.dispatchEvent(new Event('bundle-isolation'));
  window.dispatchEvent(new Event('bundle-isolation'));
  expect(window.mpResolveCheckoutValidation).toBe(previousResolver);
  expect(disconnect).toHaveBeenCalledTimes(1);
  expect(observer.takeRecords()).toEqual([]);
  expect(handler).not.toHaveBeenCalled();
  disconnect.mockRestore();
  element.remove();
});
