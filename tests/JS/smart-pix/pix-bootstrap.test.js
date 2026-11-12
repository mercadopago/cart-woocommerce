const source = '../../../assets/js/checkouts/pix/mp-pix-bootstrap.js';
const attribute = 'data-mp-smart-pix-bootstrap';
const enabledClass = 'mp-smart-pix-bootstrap-enabled';

function mount(busy = null, id = 'mp-checkout-pix-root') {
  const host = document.createElement('div');
  host.id = id;
  host.setAttribute(attribute, 'pending');
  host.className = 'merchant-layout';
  host.style.setProperty('padding', '7px', 'important');
  if (busy !== null) host.setAttribute('aria-busy', busy);
  host.innerHTML = '<pix-template></pix-template><div id="mp-smart-pix-root" hidden></div>';
  document.body.appendChild(host);
  return host;
}

function start() {
  jest.isolateModules(() => require(source));
}

beforeEach(() => {
  jest.useFakeTimers();
  document.body.innerHTML = '';
  document.documentElement.classList.remove(enabledClass);
});

afterEach(() => {
  window.dispatchEvent(new Event('pagehide'));
  jest.useRealTimers();
  document.body.innerHTML = '';
});

it('arms the recovery before activating first-paint presentation', () => {
  const host = mount();
  start();
  expect(document.documentElement.classList.contains(enabledClass)).toBe(true);
  expect(host.getAttribute('aria-busy')).toBe('true');
  expect(host.getAttribute(attribute)).toBe('pending');
  jest.advanceTimersByTime(9999);
  expect(host.getAttribute(attribute)).toBe('pending');
  jest.advanceTimersByTime(1);
  expect(host.getAttribute(attribute)).toBe('fallback');
  expect(host.hasAttribute('aria-busy')).toBe(false);
  expect(host.className).toBe('merchant-layout');
  expect(host.style.getPropertyValue('padding')).toBe('7px');
  expect(host.style.getPropertyPriority('padding')).toBe('important');
  expect(host.querySelector('pix-template').hasAttribute('hidden')).toBe(false);
});

it('discovers PHP fragments and later Blocks mounts before waiting for the bundle', async () => {
  start();
  const container = document.createElement('section');
  container.appendChild(mount());
  document.body.appendChild(container);
  await Promise.resolve();
  const host = container.firstElementChild;
  expect(host.getAttribute('aria-busy')).toBe('true');
  jest.advanceTimersByTime(10000);
  expect(host.getAttribute(attribute)).toBe('fallback');
});

it.each([null, 'false', 'true'])('hands off to the bundle and restores previous aria-busy=%s', async (busy) => {
  const host = mount(busy);
  start();
  host.setAttribute(attribute, 'complete');
  await Promise.resolve();
  expect(host.getAttribute('aria-busy')).toBe(busy);
  expect(jest.getTimerCount()).toBe(0);
  jest.advanceTimersByTime(10000);
  expect(host.getAttribute(attribute)).toBe('complete');
});

it('preserves an accessibility update made by another consumer', async () => {
  const host = mount();
  start();
  host.setAttribute('aria-busy', 'false');
  host.setAttribute(attribute, 'complete');
  await Promise.resolve();
  expect(host.getAttribute('aria-busy')).toBe('false');
});

it('cleans detached hosts and arms a new bounded wait if they are inserted again', async () => {
  const host = mount();
  start();
  host.remove();
  await Promise.resolve();
  expect(jest.getTimerCount()).toBe(0);
  expect(host.hasAttribute('aria-busy')).toBe(false);
  document.body.appendChild(host);
  await Promise.resolve();
  expect(host.getAttribute('aria-busy')).toBe('true');
  jest.advanceTimersByTime(10000);
  expect(host.getAttribute(attribute)).toBe('fallback');
});

it('leaves unrelated elements and completed presentations unchanged', async () => {
  const other = mount(null, 'another-gateway');
  const complete = mount();
  complete.setAttribute(attribute, 'complete');
  start();
  document.body.appendChild(document.createTextNode('theme content'));
  await Promise.resolve();
  expect(other.hasAttribute('aria-busy')).toBe(false);
  expect(complete.hasAttribute('aria-busy')).toBe(false);
  expect(jest.getTimerCount()).toBe(0);
});

it('does not duplicate ownership if the head script is included twice', () => {
  mount();
  start();
  start();
  expect(jest.getTimerCount()).toBe(1);
});

it('releases pending presentation when leaving', () => {
  const host = mount('false');
  start();
  window.dispatchEvent(new Event('pagehide'));
  expect(host.getAttribute(attribute)).toBe('fallback');
  expect(host.getAttribute('aria-busy')).toBe('false');
  expect(document.documentElement.classList.contains(enabledClass)).toBe(false);
  expect(jest.getTimerCount()).toBe(0);
});

it('recovers observation for new checkout fragments after history-cache restoration', async () => {
  const old = mount();
  start();
  window.dispatchEvent(new PageTransitionEvent('pagehide', { persisted: true }));
  expect(old.getAttribute(attribute)).toBe('fallback');
  window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
  old.remove();
  const fresh = mount();
  await Promise.resolve();
  expect(document.documentElement.classList.contains(enabledClass)).toBe(true);
  expect(fresh.getAttribute('aria-busy')).toBe('true');
  jest.advanceTimersByTime(10000);
  expect(fresh.getAttribute(attribute)).toBe('fallback');
});

it('keeps conventional Pix available when observation is unsupported', () => {
  const observer = global.MutationObserver;
  try {
    global.MutationObserver = undefined;
    const host = mount();
    start();
    expect(document.documentElement.classList.contains(enabledClass)).toBe(false);
    expect(host.hasAttribute('aria-busy')).toBe(false);
    expect(jest.getTimerCount()).toBe(0);
  } finally {
    global.MutationObserver = observer;
  }
});
