// Build fixture nodes without interpreting markup; strings are appended as text nodes.
function createElement(tag, attributes = {}, ...children) {
  const element = document.createElement(tag);
  Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));
  element.append(...children);
  return element;
}

// Capture external event boundaries; callbacks, validation and DOM effects stay in the source.
// Returning false here does not emulate jQuery's native-event cancellation or Woo submission.
function createJQueryHarness() {
  const handlers = new Map();
  const wrappers = new Map();
  const ready = [];
  const jQuery = (target) => {
    if (typeof target === 'function') {
      ready.push(target);
      return undefined;
    }
    if (!wrappers.has(target)) {
      const data = new Map();
      const wrapper = {
        on: jest.fn((name, callback) => {
          const events = handlers.get(target) || new Map();
          const callbacks = events.get(name) || [];
          callbacks.push(callback);
          events.set(name, callbacks);
          handlers.set(target, events);
          return wrapper;
        }),
        submit: jest.fn(callback => wrapper.on('submit', callback)),
        trigger: jest.fn(),
        data: jest.fn(function (name, value) {
          if (arguments.length === 1) return data.get(name);
          data.set(name, value);
          return wrapper;
        }),
      };
      wrappers.set(target, wrapper);
    }
    return wrappers.get(target);
  };
  return {
    jQuery,
    runReady: () => ready.splice(0).forEach(callback => callback()),
    callbacks: (target, name) => handlers.get(target)?.get(name) || [],
    emit: (target, name, ...args) => (handlers.get(target)?.get(name) || []).map(callback => callback(...args)),
  };
}

function createDomReadyHarness() {
  const ready = [];
  const proxy = new Proxy(document, {
    get(target, key) {
      if (key === 'addEventListener') {
        return (name, callback, options) => {
          if (name === 'DOMContentLoaded') ready.push(callback);
          else target.addEventListener(name, callback, options);
        };
      }
      const value = Reflect.get(target, key, target);
      return typeof value === 'function' ? value.bind(target) : value;
    },
  });
  return { document: proxy, runReady: () => ready.splice(0).forEach(callback => callback()) };
}

const timers = () => ({ setTimeout, clearTimeout, setInterval, clearInterval });
const flushPromises = async () => { for (let step = 0; step < 6; step++) await Promise.resolve(); };

module.exports = { createElement, createJQueryHarness, createDomReadyHarness, timers, flushPromises };
