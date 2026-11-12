function element(tag, attributes = {}, ...children) {
  const node = document.createElement(tag);
  Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
  children.forEach(child => node.append(typeof child === 'string' ? document.createTextNode(child) : child));
  return node;
}

function markup({ card = true, link = true } = {}) {
  const remote = element('div', { id: 'mp-jaiminho-notices-slot' });
  const native = element('div', { id: 'mp-native-notices-slot' });
  if (card) {
    native.append(element('section', { id: 'mp-native-onboarding-notice' },
      element('button', { id: 'mp-native-onboarding-notice-close', type: 'button', 'aria-label': 'Close' }, '×'),
      element('h2', {}, 'Complete your Mercado Pago setup'),
      ...(link ? [element('a', { id: 'mp-native-onboarding-notice-link', href: '#onboarding' }, 'Link my account')] : [])));
  }
  return element('div', { id: 'mp-admin-notices-region' }, remote, native);
}

describe.each(['source', 'minified'])('Native notices layout (%s)', assetVariant => {
  let fetch;
  let intersectionCallback;
  let intersectionObserver;
  let rect;
  let originalIntersectionObserver;
  let originalWidth;
  let originalPushState;
  let originalReplaceState;
  let originalURL;
  let originalHistoryState;

  function load() {
    const suffix = assetVariant === 'minified' ? '.min' : '';
    jest.isolateModules(() => require(`../../../assets/js/admin/mp-admin-notices-layout${suffix}.js`));
  }

  function events() {
    return fetch.mock.calls.map(([, options]) => Object.fromEntries(new URLSearchParams(options.body)));
  }

  function observeNative(isIntersecting = true) {
    intersectionCallback([{ target: document.getElementById('mp-native-onboarding-notice'), isIntersecting }]);
  }

  function closeNative() {
    document.getElementById('mp-native-onboarding-notice-close').click();
  }

  function openWooAdmin() {
    window.history.replaceState({}, '', '/wp-admin/admin.php?page=wc-admin&path=/analytics/overview');
    window.wc_mercadopago_native_notices_params.screen = 'wc_home';
  }

  function mount(options) {
    document.body.replaceChildren(markup(options));
    const card = document.getElementById('mp-native-onboarding-notice');
    if (card) jest.spyOn(card, 'getBoundingClientRect').mockImplementation(() => rect);
    return card;
  }

  function mainContent(attributes = {}) {
    const content = element('div', { id: 'wpbody-content', ...attributes },
      element('h1', {}, 'Dashboard'), element('a', { href: '#content-action' }, 'Content action'));
    document.body.append(content);
    jest.spyOn(content, 'getBoundingClientRect').mockReturnValue({ ...rect, width: 800, height: 600, bottom: 712 });
    return content;
  }

  beforeEach(() => {
    originalPushState = window.history.pushState;
    originalReplaceState = window.history.replaceState;
    originalURL = window.location.href;
    originalHistoryState = window.history.state;
    window.history.replaceState(null, '', '/wp-admin/index.php');
    fetch = jest.fn(() => Promise.resolve({ ok: true }));
    window.fetch = fetch;
    window.wc_mercadopago_native_notices_params = {
      enabled: '1',
      ajax_url: '/wp-admin/admin-ajax.php',
      nonce: 'synthetic-notice-nonce',
      screen: 'dashboard',
    };
    rect = { width: 420, height: 180, top: 112, bottom: 292, left: 200, right: 620 };
    originalIntersectionObserver = window.IntersectionObserver;
    intersectionCallback = undefined;
    originalWidth = window.innerWidth;
    window.innerWidth = 1024;
    intersectionObserver = { observe: jest.fn(), disconnect: jest.fn() };
    window.IntersectionObserver = jest.fn(callback => {
      intersectionCallback = callback;
      return intersectionObserver;
    });
    jest.spyOn(document, 'readyState', 'get').mockReturnValue('complete');
    jest.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    delete document.documentElement.dataset.mpNativeNoticeErrors;
    mount();
  });

  afterEach(() => {
    window.dispatchEvent(new Event('pagehide'));
    window.history.pushState = originalPushState;
    window.history.replaceState = originalReplaceState;
    window.history.replaceState(originalHistoryState, '', originalURL);
    document.body.replaceChildren();
    delete window.wc_mercadopago_native_notices_params;
    delete window.fetch;
    window.IntersectionObserver = originalIntersectionObserver;
    window.innerWidth = originalWidth;
    delete document.documentElement.dataset.mpNativeNoticeErrors;
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  it('reports one actual visible view per document and ignores repeated bootstrap', () => {
    load();
    load();
    const card = document.getElementById('mp-native-onboarding-notice');
    intersectionCallback([{ target: card, isIntersecting: true }]);
    window.dispatchEvent(new Event('resize'));
    expect(events().map(event => event.event)).toEqual(['view']);
    expect(events()[0]).toEqual({
      action: 'mp_native_onboarding_notice_metric', nonce: 'synthetic-notice-nonce',
      event: 'view', screen: 'dashboard', viewport: 'desktop',
    });
    expect(intersectionObserver.disconnect).toHaveBeenCalled();
  });

  it('waits for the card to intersect the viewport and the document to become visible', () => {
    rect = { ...rect, top: 2000, bottom: 2180 };
    load();
    expect(fetch).not.toHaveBeenCalled();
    expect(document.getElementById('mp-admin-notices-region').classList.contains('mp-notices-region-empty')).toBe(false);
    rect = { ...rect, top: 112, bottom: 292 };
    const visibility = jest.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
    intersectionCallback([{ target: document.getElementById('mp-native-onboarding-notice'), isIntersecting: true }]);
    expect(fetch).not.toHaveBeenCalled();
    visibility.mockReturnValue('visible');
    document.dispatchEvent(new Event('visibilitychange'));
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('does not measure layout on scroll while IntersectionObserver reports no intersection', () => {
    load();
    observeNative(false);
    const card = document.getElementById('mp-native-onboarding-notice');
    const region = document.getElementById('mp-admin-notices-region');
    const regionBounds = jest.spyOn(region, 'getBoundingClientRect');
    const styles = jest.spyOn(window, 'getComputedStyle');
    card.getBoundingClientRect.mockClear();

    window.dispatchEvent(new Event('scroll'));
    region.dispatchEvent(new Event('scroll'));

    expect(styles).not.toHaveBeenCalled();
    expect(card.getBoundingClientRect).not.toHaveBeenCalled();
    expect(regionBounds).not.toHaveBeenCalled();
    expect(fetch).not.toHaveBeenCalled();
    expect(intersectionObserver.disconnect).not.toHaveBeenCalled();
  });

  it('removes its scroll retry after the first observed view and keeps it removed across native restoration', () => {
    openWooAdmin();
    const add = jest.spyOn(window, 'addEventListener');
    const remove = jest.spyOn(window, 'removeEventListener');
    load();
    const [, retry, options] = add.mock.calls.find(([type]) => type === 'scroll');
    expect(options).toEqual({ passive: true, capture: true });
    expect(remove).not.toHaveBeenCalledWith('scroll', retry, true);

    observeNative();

    expect(events().map(event => event.event)).toEqual(['view']);
    expect(remove).toHaveBeenCalledWith('scroll', retry, true);
    expect(intersectionObserver.disconnect).toHaveBeenCalledTimes(1);
    closeNative();
    window.history.pushState({}, '', '?page=wc-admin&path=/analytics/orders');
    window.dispatchEvent(new window.PageTransitionEvent('pagehide', { persisted: true }));
    window.dispatchEvent(new window.PageTransitionEvent('pageshow', { persisted: true }));
    window.dispatchEvent(new Event('scroll'));
    load();

    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(false);
    expect(add.mock.calls.filter(([type]) => type === 'scroll')).toHaveLength(1);
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('does not restore the scroll retry when a polyfill reports the initial view synchronously', () => {
    const add = jest.spyOn(window, 'addEventListener');
    intersectionObserver.observe.mockImplementation(card => {
      intersectionCallback([{ target: card, isIntersecting: true }]);
    });

    load();

    expect(events().map(event => event.event)).toEqual(['view']);
    expect(intersectionObserver.disconnect).toHaveBeenCalledTimes(1);
    expect(add.mock.calls.filter(([type]) => type === 'scroll')).toHaveLength(0);
  });

  it('retries CSS visibility on scroll without manufacturing another intersection callback', () => {
    const card = document.getElementById('mp-native-onboarding-notice');
    card.style.visibility = 'hidden';
    const add = jest.spyOn(window, 'addEventListener');
    const remove = jest.spyOn(window, 'removeEventListener');
    load();
    const [, retry] = add.mock.calls.find(([type]) => type === 'scroll');
    observeNative();
    expect(fetch).not.toHaveBeenCalled();
    expect(remove).not.toHaveBeenCalledWith('scroll', retry, true);

    card.style.visibility = 'visible';
    window.dispatchEvent(new Event('scroll'));
    window.dispatchEvent(new Event('scroll'));

    expect(events().map(event => event.event)).toEqual(['view']);
    expect(remove).toHaveBeenCalledWith('scroll', retry, true);
    expect(intersectionObserver.disconnect).toHaveBeenCalledTimes(1);
  });

  it('retries edge contact on scroll without requiring another threshold-zero observation', () => {
    const card = document.getElementById('mp-native-onboarding-notice');
    rect = { ...rect, top: window.innerHeight, bottom: window.innerHeight + 180 };
    load();
    // A threshold-zero entry may report edge contact before any positive visible area.
    intersectionCallback([{ target: card, isIntersecting: true, intersectionRatio: 0 }]);
    expect(fetch).not.toHaveBeenCalled();

    rect = { ...rect, top: window.innerHeight - 1, bottom: window.innerHeight + 179 };
    window.dispatchEvent(new Event('scroll'));
    window.dispatchEvent(new Event('scroll'));

    expect(events().map(event => event.event)).toEqual(['view']);
    expect(intersectionObserver.disconnect).toHaveBeenCalledTimes(1);
  });

  it('uses scroll/resize visibility detection when IntersectionObserver is unavailable', () => {
    delete window.IntersectionObserver;
    rect = { ...rect, top: 2000, bottom: 2180 };
    load();
    expect(fetch).not.toHaveBeenCalled();
    rect = { ...rect, top: 112, bottom: 292 };
    window.dispatchEvent(new Event('scroll'));
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('does not count a CSS-hidden native card as a view', () => {
    document.getElementById('mp-native-notices-slot').hidden = true;
    load();
    observeNative();
    expect(fetch).not.toHaveBeenCalled();
    document.getElementById('mp-native-notices-slot').hidden = false;
    observeNative();
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('waits until a viewport-visible card enters the scrollable region before counting its view', () => {
    delete window.IntersectionObserver;
    const region = document.getElementById('mp-admin-notices-region');
    region.style.overflowY = 'auto';
    jest.spyOn(region, 'getBoundingClientRect').mockReturnValue({ top: 150, bottom: 450 });
    rect = { ...rect, top: 50, bottom: 130 };

    load();
    expect(fetch).not.toHaveBeenCalled();

    rect = { ...rect, top: 500, bottom: 680 };
    region.dispatchEvent(new Event('scroll'));
    expect(fetch).not.toHaveBeenCalled();

    rect = { ...rect, top: 200, bottom: 380 };
    region.dispatchEvent(new Event('scroll'));
    region.dispatchEvent(new Event('scroll'));
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('observes scrolling inside the region in the fallback without IntersectionObserver', () => {
    delete window.IntersectionObserver;
    rect = { ...rect, top: 2000, bottom: 2180 };
    load();
    expect(fetch).not.toHaveBeenCalled();
    rect = { ...rect, top: 112, bottom: 292 };
    document.getElementById('mp-admin-notices-region').dispatchEvent(new Event('scroll'));
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('removes the fallback container scroll retry after its first view, including cached-page restoration', () => {
    delete window.IntersectionObserver;
    rect = { ...rect, top: 2000, bottom: 2180 };
    const add = jest.spyOn(window, 'addEventListener');
    const remove = jest.spyOn(window, 'removeEventListener');
    load();
    const [, retry] = add.mock.calls.find(([type]) => type === 'scroll');
    window.dispatchEvent(new window.PageTransitionEvent('pagehide', { persisted: true }));
    window.dispatchEvent(new window.PageTransitionEvent('pageshow', { persisted: true }));
    expect(remove).not.toHaveBeenCalledWith('scroll', retry, true);

    rect = { ...rect, top: 112, bottom: 292 };
    document.getElementById('mp-admin-notices-region').dispatchEvent(new Event('scroll'));

    expect(events().map(event => event.event)).toEqual(['view']);
    expect(remove).toHaveBeenCalledWith('scroll', retry, true);
    expect(window.IntersectionObserver).toBeUndefined();
  });

  it('does not keep a fallback scroll retry when the initial visibility check already counted the view', () => {
    delete window.IntersectionObserver;
    const add = jest.spyOn(window, 'addEventListener');
    const remove = jest.spyOn(window, 'removeEventListener');

    load();

    const [, retry] = add.mock.calls.find(([type]) => type === 'scroll');
    expect(events().map(event => event.event)).toEqual(['view']);
    expect(remove).toHaveBeenCalledWith('scroll', retry, true);
  });

  it('removes a still-pending fallback scroll retry on ordinary unload', () => {
    delete window.IntersectionObserver;
    rect = { ...rect, top: 2000, bottom: 2180 };
    load();
    window.dispatchEvent(new window.PageTransitionEvent('pagehide', { persisted: false }));
    rect = { ...rect, top: 112, bottom: 292 };
    window.dispatchEvent(new Event('scroll'));
    document.getElementById('mp-admin-notices-region').dispatchEvent(new Event('scroll'));

    expect(fetch).not.toHaveBeenCalled();
  });

  it('reports every real CTA activation without preventing navigation or waiting for transport', () => {
    load();
    observeNative();
    fetch.mockImplementation(() => new Promise(() => {}));
    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    const link = document.getElementById('mp-native-onboarding-notice-link');
    expect(link.dispatchEvent(click)).toBe(true);
    expect(click.defaultPrevented).toBe(false);
    link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(events().map(event => event.event)).toEqual(['view', 'click', 'click']);
    expect(fetch.mock.calls[1][1]).toMatchObject({ keepalive: true, credentials: 'same-origin', method: 'POST' });
  });

  it('preserves the native notice when telemetry rejects or throws', async () => {
    fetch.mockRejectedValue(new Error('controlled network failure'));
    load();
    observeNative();
    document.getElementById('mp-native-onboarding-notice-link').click();
    await Promise.resolve();
    fetch.mockImplementation(() => { throw new Error('controlled transport failure'); });
    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    expect(document.getElementById('mp-native-onboarding-notice-link').dispatchEvent(click)).toBe(true);
    expect(document.getElementById('mp-native-onboarding-notice')).not.toBeNull();
    expect(fetch).toHaveBeenCalledTimes(3);
  });

  it('never expires and closes only the native slot without persistent dismissal or CTA metrics', () => {
    jest.useFakeTimers();
    load();
    observeNative();
    jest.advanceTimersByTime(60000);
    const card = document.getElementById('mp-native-onboarding-notice');
    expect(card).not.toBeNull();
    expect(card.querySelector('.notice-dismiss')).toBeNull();
    const region = document.getElementById('mp-admin-notices-region');
    const remote = document.getElementById('mp-jaiminho-notices-slot');
    const localStorageBefore = { ...window.localStorage };
    const sessionStorageBefore = { ...window.sessionStorage };
    closeNative();
    load();
    window.dispatchEvent(new Event('resize'));
    document.dispatchEvent(new Event('visibilitychange'));
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(true);
    expect(document.getElementById('mp-native-onboarding-notice')).toBe(card);
    expect(document.getElementById('mp-admin-notices-region')).toBe(region);
    expect(document.getElementById('mp-jaiminho-notices-slot')).toBe(remote);
    expect(region.classList.contains('mp-notices-region-empty')).toBe(true);
    expect(events().map(event => event.event)).toEqual(['view']);
    expect(document.cookie).toBe('');
    expect({ ...window.localStorage }).toEqual(localStorageBefore);
    expect({ ...window.sessionStorage }).toEqual(sessionStorageBefore);
  });

  it('preserves existing and late Jaiminho campaigns after closing the native notice', async () => {
    load();
    observeNative();
    const remote = document.getElementById('mp-jaiminho-notices-slot');
    const campaign = element('div', { class: 'andes-message' }, element('button', { type: 'button' }, 'Remote action'));
    remote.append(campaign);
    await Promise.resolve();
    const originalCampaign = campaign.cloneNode(true);
    closeNative();
    expect(campaign.isEqualNode(originalCampaign)).toBe(true);
    expect(remote.classList.contains('mp-has-remote-notices')).toBe(true);
    campaign.remove();
    await Promise.resolve();
    const region = document.getElementById('mp-admin-notices-region');
    expect(region.classList.contains('mp-notices-region-empty')).toBe(true);
    remote.append(campaign);
    await Promise.resolve();
    expect(region.classList.contains('mp-notices-region-empty')).toBe(false);
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(true);
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('moves focus from the closed native slot to an existing visible campaign action without changing that campaign', () => {
    mainContent();
    const action = element('a', { href: '#campaign-action' }, 'Campaign action');
    const campaign = element('div', { class: 'andes-message' }, action, element('button', { type: 'button' }, 'Close campaign'));
    document.getElementById('mp-jaiminho-notices-slot').append(campaign);
    jest.spyOn(action, 'getBoundingClientRect').mockImplementation(() => rect);
    const focus = jest.spyOn(action, 'focus');
    const originalCampaign = campaign.cloneNode(true);
    load();
    document.getElementById('mp-native-onboarding-notice-close').focus();

    closeNative();

    expect(document.activeElement).toBe(action);
    expect(focus).toHaveBeenCalledWith({ preventScroll: true });
    expect(campaign.isEqualNode(originalCampaign)).toBe(true);
    expect(events().filter(event => event.event === 'click')).toHaveLength(0);
  });

  it.each(['none', 'hidden', 'disabled', 'inert', 'outside viewport'])('returns focus to visible main content when campaign actions are %s', state => {
    const content = mainContent();
    if (state !== 'none') {
      const button = element('button', { type: 'button' }, 'Campaign action');
      const campaign = element('div', { class: 'andes-message' }, button);
      if (state === 'hidden') campaign.hidden = true;
      if (state === 'disabled') button.disabled = true;
      if (state === 'inert') campaign.setAttribute('inert', '');
      jest.spyOn(button, 'getBoundingClientRect').mockImplementation(() => state === 'outside viewport'
        ? { ...rect, top: 2000, bottom: 2180 } : rect);
      document.getElementById('mp-jaiminho-notices-slot').append(campaign);
    }
    const focus = jest.spyOn(content, 'focus');
    load();
    document.getElementById('mp-native-onboarding-notice-close').focus();

    closeNative();

    expect(document.activeElement).toBe(content);
    expect(content.getAttribute('tabindex')).toBe('-1');
    expect(focus).toHaveBeenCalledWith({ preventScroll: true });
  });

  it.each(['0', '-1'])('preserves the existing main content tabindex %s when restoring focus', tabindex => {
    const content = mainContent({ tabindex });
    load();
    document.getElementById('mp-native-onboarding-notice-close').focus();

    closeNative();

    expect(document.activeElement).toBe(content);
    expect(content.getAttribute('tabindex')).toBe(tabindex);
  });

  it('leaves external focus in place when dismissal did not focus the native slot', () => {
    const content = mainContent();
    const external = content.querySelector('a');
    const contentFocus = jest.spyOn(content, 'focus');
    load();
    external.focus();

    closeNative();

    expect(document.activeElement).toBe(external);
    expect(contentFocus).not.toHaveBeenCalled();
    expect(content.hasAttribute('tabindex')).toBe(false);
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(true);
  });

  it('reopens for real WooCommerce push/replace route changes without recounting the document view', () => {
    openWooAdmin();
    load();
    observeNative();
    const card = document.getElementById('mp-native-onboarding-notice');
    const native = document.getElementById('mp-native-notices-slot');
    closeNative();
    window.history.pushState({ report: 'orders' }, '', '?page=wc-admin&path=/analytics/orders');
    expect(native.hidden).toBe(false);
    expect(document.getElementById('mp-native-onboarding-notice')).toBe(card);
    closeNative();
    window.history.replaceState({ report: 'revenue' }, '', '?page=wc-admin&path=/analytics/revenue');
    expect(native.hidden).toBe(false);
    observeNative();
    expect(events().map(event => event.event)).toEqual(['view']);
    document.getElementById('mp-native-onboarding-notice-link').click();
    expect(events().map(event => event.event)).toEqual(['view', 'click']);
  });

  it('keeps the native notice closed for history state, query filters and hash-only changes', () => {
    openWooAdmin();
    load();
    closeNative();
    window.history.replaceState({ filter: 'today' }, '');
    window.history.pushState({}, '', '?page=wc-admin&path=/analytics/overview&period=today#report');
    window.dispatchEvent(new PopStateEvent('popstate'));
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(true);
    expect(fetch).not.toHaveBeenCalled();
  });

  it('treats the implicit and explicit WooCommerce home route as the same page', () => {
    openWooAdmin();
    window.history.replaceState({}, '', '?page=wc-admin');
    load();
    closeNative();
    window.history.pushState({}, '', '?page=wc-admin&path=/');
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(true);
  });

  it('reopens after actual Back and Forward navigation within WooCommerce', async () => {
    openWooAdmin();
    load();
    observeNative();
    window.history.pushState({}, '', '?page=wc-admin&path=/analytics/orders');
    const native = document.getElementById('mp-native-notices-slot');
    closeNative();
    const back = new Promise(resolve => window.addEventListener('popstate', resolve, { once: true }));
    window.history.back();
    await back;
    expect(native.hidden).toBe(false);
    closeNative();
    const forward = new Promise(resolve => window.addEventListener('popstate', resolve, { once: true }));
    window.history.forward();
    await forward;
    expect(native.hidden).toBe(false);
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('preserves the History receiver, arguments, return value and original exception', () => {
    openWooAdmin();
    const result = {};
    const delegate = jest.spyOn(window.history, 'pushState').mockImplementation(function (...args) {
      originalPushState.apply(this, args);
      return result;
    });
    load();
    closeNative();
    const args = [{ report: 'orders' }, 'Orders', '?page=wc-admin&path=/analytics/orders'];
    expect(window.history.pushState(...args)).toBe(result);
    expect(delegate.mock.contexts[0]).toBe(window.history);
    expect(delegate.mock.calls[0]).toEqual(args);
    expect(window.history.state).toEqual(args[0]);
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(false);
    closeNative();
    const failure = new Error('External History failure');
    delegate.mockImplementationOnce(() => { throw failure; });
    expect(() => window.history.pushState({}, '', '?page=wc-admin&path=/analytics/revenue')).toThrow(failure);
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(true);
  });

  it('preserves real History navigation when the notice layout measurement fails', () => {
    openWooAdmin();
    load();
    observeNative();
    closeNative();
    const region = document.getElementById('mp-admin-notices-region');
    jest.spyOn(region, 'getBoundingClientRect').mockImplementationOnce(() => {
      throw new DOMException('Controlled layout boundary failure', 'InvalidStateError');
    });
    const state = { report: 'orders' };
    expect(window.history.pushState(state, '', '?page=wc-admin&path=/analytics/orders')).toBeUndefined();
    expect(new URL(window.location.href).searchParams.get('path')).toBe('/analytics/orders');
    expect(window.history.state).toEqual(state);
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(false);
    expect(events().map(event => event.event)).toEqual(['view']);

    closeNative();
    window.history.replaceState({}, '', '?page=wc-admin&path=/analytics/revenue');
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(false);
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('does not wrap History outside WooCommerce and restores its own wrappers on unload', () => {
    load();
    expect(window.history.pushState).toBe(originalPushState);
    expect(window.history.replaceState).toBe(originalReplaceState);
    window.dispatchEvent(new Event('pagehide'));
    mount();
    openWooAdmin();
    load();
    const wrapped = window.history.pushState;
    load();
    expect(window.history.pushState).toBe(wrapped);
    expect(wrapped).not.toBe(originalPushState);
    window.dispatchEvent(new Event('pagehide'));
    expect(window.history.pushState).toBe(originalPushState);
    expect(window.history.replaceState).toBe(originalReplaceState);
  });

  it('preserves a later History owner and disables callbacks retained by that owner on unload', () => {
    openWooAdmin();
    load();
    const wrapped = window.history.pushState;
    const laterOwner = function (...args) { return wrapped.apply(this, args); };
    window.history.pushState = laterOwner;
    closeNative();
    window.dispatchEvent(new Event('pagehide'));
    expect(window.history.pushState).toBe(laterOwner);
    window.history.pushState({}, '', '?page=wc-admin&path=/analytics/orders');
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(true);
  });

  it('reopens on BFCache restoration while preserving the one-view-per-document marker', () => {
    load();
    observeNative();
    const card = document.getElementById('mp-native-onboarding-notice');
    closeNative();
    window.dispatchEvent(new window.PageTransitionEvent('pagehide', { persisted: true }));
    window.dispatchEvent(new window.PageTransitionEvent('pageshow', { persisted: true }));
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(false);
    expect(card.dataset.mpNoticeViewed).toBe('1');
    expect(events().map(event => event.event)).toEqual(['view']);
    closeNative();
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(true);
  });

  it('adapts viewport metadata to mobile without submitting browser URLs or credentials', () => {
    window.innerWidth = 375;
    window.wc_mercadopago_native_notices_params.public_key = 'synthetic-unused-key';
    load();
    observeNative();
    expect(events()[0].viewport).toBe('mobile');
    expect(events()[0]).not.toHaveProperty('url');
    expect(events()[0]).not.toHaveProperty('public_key');
  });

  it('updates only the remote slot for late/closed campaigns and keeps the native sibling', async () => {
    load();
    observeNative();
    const remote = document.getElementById('mp-jaiminho-notices-slot');
    expect(remote.classList.contains('mp-has-remote-notices')).toBe(false);
    const message = element('div', { class: 'andes-message' }, 'Real renderer boundary content');
    const wrapper = element('div', { class: 'mp-woocommerce-notices-container' }, element('div', { class: 'mp-woocommerce-notices-content' }, message));
    const unchanged = wrapper.cloneNode(true);
    remote.append(wrapper);
    await Promise.resolve();
    expect(remote.classList.contains('mp-has-remote-notices')).toBe(true);
    expect(wrapper.isEqualNode(unchanged)).toBe(true);
    message.style.display = 'none';
    await Promise.resolve();
    expect(remote.classList.contains('mp-has-remote-notices')).toBe(false);
    expect(document.getElementById('mp-native-onboarding-notice').parentElement.id).toBe('mp-native-notices-slot');
    message.remove();
    await Promise.resolve();
    expect(remote.classList.contains('mp-has-remote-notices')).toBe(false);
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('collapses the empty region when the native notice is initially ineligible', () => {
    mount({ card: false });
    document.getElementById('mp-native-notices-slot').hidden = true;
    window.wc_mercadopago_native_notices_params.enabled = '';
    load();
    expect(document.getElementById('mp-admin-notices-region').classList.contains('mp-notices-region-empty')).toBe(true);
    expect(fetch).not.toHaveBeenCalled();
  });

  it('preserves late campaigns and collapses after the last campaign disappears while the native notice is ineligible', async () => {
    mount({ card: false });
    document.getElementById('mp-native-notices-slot').hidden = true;
    window.wc_mercadopago_native_notices_params.enabled = '';
    load();
    const region = document.getElementById('mp-admin-notices-region');
    const remote = document.getElementById('mp-jaiminho-notices-slot');
    const campaign = element('div', { class: 'andes-message' }, 'Campaign');
    remote.append(campaign);
    await Promise.resolve();
    expect(remote.classList.contains('mp-has-remote-notices')).toBe(true);
    expect(region.classList.contains('mp-notices-region-empty')).toBe(false);
    campaign.remove();
    await Promise.resolve();
    expect(remote.classList.contains('mp-has-remote-notices')).toBe(false);
    expect(region.classList.contains('mp-notices-region-empty')).toBe(true);
    remote.append(campaign);
    await Promise.resolve();
    expect(remote.classList.contains('mp-has-remote-notices')).toBe(true);
    expect(region.classList.contains('mp-notices-region-empty')).toBe(false);
    expect(document.getElementById('mp-native-notices-slot').hidden).toBe(true);
    expect(fetch).not.toHaveBeenCalled();
  });

  it('keeps native close, restoration and CTA available without a remote slot', () => {
    document.getElementById('mp-jaiminho-notices-slot').remove();
    load();
    observeNative();
    const native = document.getElementById('mp-native-notices-slot');
    const card = document.getElementById('mp-native-onboarding-notice');
    closeNative();
    expect(native.hidden).toBe(true);
    expect(document.getElementById('mp-admin-notices-region').classList.contains('mp-notices-region-empty')).toBe(true);

    window.dispatchEvent(new window.PageTransitionEvent('pageshow', { persisted: true }));
    expect(native.hidden).toBe(false);
    expect(document.getElementById('mp-native-onboarding-notice')).toBe(card);
    expect(document.getElementById('mp-jaiminho-notices-slot')).toBeNull();
    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    expect(document.getElementById('mp-native-onboarding-notice-link').dispatchEvent(click)).toBe(true);
    expect(click.defaultPrevented).toBe(false);
    expect(events().map(event => event.event)).toEqual(['view', 'click']);
  });

  it('moves only its owned region out of the hidden WooCommerce notice wrapper', () => {
    const region = document.getElementById('mp-admin-notices-region');
    const card = document.getElementById('mp-native-onboarding-notice');
    const otherNotice = element('div', { class: 'notice' }, 'Unrelated WooCommerce notice');
    const wrapper = element('div', { id: 'wp__notice-list', style: 'display:none' }, region, otherNotice);
    const content = element('div', { id: 'wpbody-content' }, wrapper);
    document.body.append(content);

    load();
    observeNative();

    expect(region.parentElement).toBe(content);
    expect(content.firstElementChild).toBe(region);
    expect(document.getElementById('mp-native-onboarding-notice')).toBe(card);
    expect(otherNotice.parentElement).toBe(wrapper);
    expect(wrapper.style.display).toBe('none');
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('prepends its region before existing admin content even when already a direct child', () => {
    const region = document.getElementById('mp-admin-notices-region');
    const heading = element('h1', {}, 'Existing settings heading');
    const content = element('div', { id: 'wpbody-content' }, heading, region);
    document.body.append(content);

    load();

    expect(content.firstElementChild).toBe(region);
    expect(region.nextElementSibling).toBe(heading);
    expect(region.querySelector('#mp-native-onboarding-notice')).not.toBeNull();
  });

  it('caps desktop height using the actual anchor and resets it across mobile/desktop resize', () => {
    const region = document.getElementById('mp-admin-notices-region');
    const anchor = { top: 92 };
    jest.spyOn(region, 'getBoundingClientRect').mockReturnValue(anchor);
    load();
    expect(region.style.getPropertyValue('--mp-admin-notices-top')).toBe('92px');

    anchor.top = 32;
    window.dispatchEvent(new Event('resize'));
    expect(region.style.getPropertyValue('--mp-admin-notices-top')).toBe('32px');

    window.innerWidth = 375;
    window.dispatchEvent(new Event('resize'));
    expect(region.style.getPropertyValue('--mp-admin-notices-top')).toBe('');

    window.innerWidth = 1024;
    anchor.top = 92;
    window.dispatchEvent(new Event('resize'));
    expect(region.style.getPropertyValue('--mp-admin-notices-top')).toBe('92px');
  });

  it('removes its height-resize listener on ordinary page unload', () => {
    const region = document.getElementById('mp-admin-notices-region');
    const anchor = { top: 92 };
    jest.spyOn(region, 'getBoundingClientRect').mockReturnValue(anchor);
    load();
    window.dispatchEvent(new window.PageTransitionEvent('pagehide', { persisted: false }));
    anchor.top = 32;
    window.dispatchEvent(new Event('resize'));
    expect(region.style.getPropertyValue('--mp-admin-notices-top')).toBe('92px');
    expect(fetch).not.toHaveBeenCalled();
  });

  it('keeps campaign reflow and pending visibility tracking after a cached Back navigation', async () => {
    const region = document.getElementById('mp-admin-notices-region');
    const anchor = { top: 92 };
    jest.spyOn(region, 'getBoundingClientRect').mockReturnValue(anchor);
    load();
    const remote = document.getElementById('mp-jaiminho-notices-slot');
    const message = element('div', { class: 'andes-message' }, 'Campaign');
    remote.append(message);
    await Promise.resolve();
    expect(remote.classList.contains('mp-has-remote-notices')).toBe(true);

    window.dispatchEvent(new window.PageTransitionEvent('pagehide', { persisted: true }));
    window.dispatchEvent(new window.PageTransitionEvent('pageshow', { persisted: true }));
    anchor.top = 32;
    window.dispatchEvent(new Event('resize'));
    expect(region.style.getPropertyValue('--mp-admin-notices-top')).toBe('32px');
    message.remove();
    await Promise.resolve();

    expect(remote.classList.contains('mp-has-remote-notices')).toBe(false);
    expect(intersectionObserver.disconnect).not.toHaveBeenCalled();
    observeNative();
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('reports missing native markup once without manufacturing a view', () => {
    mount({ card: false });
    load();
    load();
    expect(events().map(event => [event.event, event.reason])).toEqual([['error', 'native_slot_missing']]);
  });

  it('deduplicates the missing-region error when a repeated bootstrap finds no rendered region', () => {
    document.body.replaceChildren();
    load();
    load();
    expect(events().map(event => [event.event, event.reason])).toEqual([['error', 'native_slot_missing']]);
  });

  it('reports a missing CTA while preserving the visible native content', () => {
    mount({ link: false });
    load();
    expect(events()).toEqual(expect.arrayContaining([expect.objectContaining({ event: 'error', reason: 'cta_missing' })]));
    expect(document.getElementById('mp-native-onboarding-notice')).not.toBeNull();
  });

  it('reports a reachable bootstrap exception without exposing its text', () => {
    jest.spyOn(window, 'MutationObserver').mockImplementation(() => { throw new Error('controlled initialization failure'); });
    load();
    expect(events()).toEqual(expect.arrayContaining([expect.objectContaining({ event: 'error', reason: 'bootstrap_failed' })]));
    expect(fetch.mock.calls.map(([, request]) => request.body).join('')).not.toContain('controlled initialization failure');
  });

  it('handles absent DOM and disabled notices without an error', () => {
    document.body.replaceChildren();
    window.wc_mercadopago_native_notices_params.enabled = false;
    load();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('keeps the rendered notice available when runtime configuration is absent', () => {
    delete window.wc_mercadopago_native_notices_params;
    expect(load).not.toThrow();
    expect(document.getElementById('mp-native-onboarding-notice')).not.toBeNull();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('preserves the CTA default action when the browser has no fetch transport', () => {
    delete window.fetch;
    load();
    observeNative();
    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    const link = document.getElementById('mp-native-onboarding-notice-link');
    expect(link.dispatchEvent(click)).toBe(true);
    expect(click.defaultPrevented).toBe(false);
    expect(document.getElementById('mp-native-onboarding-notice')).not.toBeNull();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('initializes once after DOMContentLoaded if the script runs while parsing', () => {
    jest.spyOn(document, 'readyState', 'get').mockReturnValue('loading');
    load();
    load();
    expect(fetch).not.toHaveBeenCalled();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    observeNative();
    expect(events().map(event => event.event)).toEqual(['view']);
  });

  it('does not count a viewport-positioned card while the browser reports it is clipped', () => {
    load();
    observeNative(false);
    window.dispatchEvent(new Event('resize'));
    document.dispatchEvent(new Event('visibilitychange'));
    expect(fetch).not.toHaveBeenCalled();
    observeNative(true);
    expect(events().map(event => event.event)).toEqual(['view']);
  });
});
