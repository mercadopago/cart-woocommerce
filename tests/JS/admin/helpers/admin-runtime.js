const path = require('path');
const fs = require('fs');
const vm = require('vm');
const { loadFile } = require('../../helpers/load-file');

// WordPress appends `jQuery.noConflict()` after the UMD module. Preserve it unchanged,
// exposing the genuine UMD export as the global that the WordPress trailer expects.
const jqueryPath = path.resolve(__dirname, '../fixtures/jquery-3.7.1.min.js');
const jqueryModule = { exports: {} };
const jqueryContext = { window: document.defaultView, module: jqueryModule };
Object.defineProperty(jqueryContext, 'jQuery', { get: () => jqueryModule.exports });
new vm.Script(fs.readFileSync(jqueryPath, 'utf8'), { filename: jqueryPath }).runInNewContext(jqueryContext);
const jquery = jqueryModule.exports;

const ADMIN_ROOT = process.env.PSW3844_ADMIN_SOURCE_ROOT || path.resolve(__dirname, '../../../../assets/js/admin');

// Only browser navigation and HTTP are replaced. jQuery, Deferred and the DOM are real.
function createAdminRuntime(markup, globals = {}, assetVariant = 'source') {
  document.body.replaceChildren(markup);
  const browser = document.defaultView;
  const listeners = [];
  const eventErrors = [];
  const captureEventError = event => {
    eventErrors.push(event.error || new Error(event.message));
    event.preventDefault();
  };
  browser.addEventListener('error', captureEventError);
  const documentEvents = jest.spyOn(document, 'addEventListener');
  const requests = [];
  const navigation = {
    href: 'https://shop.example.invalid/wp-admin/admin.php?page=mercadopago-settings',
    reload: jest.fn(),
  };
  const windowContext = {
    jQuery: jquery,
    location: navigation,
    history: { replaceState: jest.fn() },
    addEventListener(type, listener, options) {
      listeners.push([type, listener, options]);
      browser.addEventListener(type, listener, options);
    },
    ...globals.window,
  };
  const transport = jest.spyOn(jquery, 'post').mockImplementation((url, payload, callback) => {
    const deferred = jquery.Deferred();
    if (typeof callback === 'function') deferred.done(callback);
    requests.push({ url, payload, deferred });
    return deferred.promise();
  });
  const context = {
    window: windowContext,
    document,
    jQuery: jquery,
    ajaxurl: '/wp-admin/admin-ajax.php',
    location: navigation,
    DOMParser: browser.DOMParser,
    URL,
    Date,
    setTimeout,
    clearTimeout,
    setInterval,
    clearInterval,
    ...globals,
  };
  context.window = windowContext;

  return {
    $: jquery,
    eventErrors,
    requests,
    navigation,
    window: windowContext,
    load(relativePath, expression = 'undefined') {
      const assetPath = assetVariant === 'minified' ? relativePath.replace(/\.js$/, '.min.js') : relativePath;
      return loadFile(path.join(ADMIN_ROOT, assetPath), expression, context);
    },
    request(action, index = 0) {
      const matches = requests.filter(({ payload }) => payload.action === action);
      if (!matches[index]) throw new Error(`Missing HTTP request: ${action} #${index}`);
      return matches[index];
    },
    dispatchLoad() {
      browser.dispatchEvent(new browser.Event('load'));
    },
    dispatchReady() {
      document.dispatchEvent(new browser.Event('DOMContentLoaded'));
      // A completed jsdom document may schedule jQuery.ready before fake timers start.
      // Signal readiness through jQuery's genuine API, then drain its async callbacks.
      jquery.ready();
      jest.advanceTimersByTime(1);
    },
    cleanup() {
      browser.removeEventListener('error', captureEventError);
      listeners.forEach(args => browser.removeEventListener(...args));
      documentEvents.mock.calls.forEach(args => document.removeEventListener(...args));
      jquery(document).off();
      jquery(browser).off();
      jquery('body').off();
      transport.mockRestore();
      documentEvents.mockRestore();
      document.body.replaceChildren();
      jest.clearAllTimers();
      jest.useRealTimers();
      jest.restoreAllMocks();
      // dispatchEvent reports listener exceptions through window.error, not its return value.
      expect(eventErrors).toEqual([]);
    },
  };
}

module.exports = { createAdminRuntime };
