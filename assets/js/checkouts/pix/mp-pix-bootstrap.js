/* The head script owns only the presentation before the independent Smart Pix bundle starts. */
(() => {
  const enabledClass = 'mp-smart-pix-bootstrap-enabled';
  const attribute = 'data-mp-smart-pix-bootstrap';
  const selector = `#mp-checkout-pix-root[${attribute}="pending"]`;
  const pending = new Map();
  const maxWaitMs = 10000;

  if (document.documentElement.classList.contains(enabledClass)
    || typeof MutationObserver !== 'function') return;

  const restoreBusy = (host, previous) => {
    // Another consumer may have taken ownership while we waited.
    if (host.getAttribute('aria-busy') !== 'true') return;
    if (previous === null) host.removeAttribute('aria-busy');
    else host.setAttribute('aria-busy', previous);
  };

  const release = (host) => {
    const state = pending.get(host);
    if (!state) return;
    clearTimeout(state.timer);
    restoreBusy(host, state.busy);
    pending.delete(host);
  };

  const observeHost = (host) => {
    if (!host.isConnected || pending.has(host) || !host.matches(selector)) return;
    const busy = host.getAttribute('aria-busy');
    host.setAttribute('aria-busy', 'true');
    const timer = setTimeout(() => {
      if (host.getAttribute(attribute) === 'pending') host.setAttribute(attribute, 'fallback');
      release(host);
    }, maxWaitMs);
    pending.set(host, { busy, timer });
  };

  const discover = (node) => {
    if (node.nodeType !== 1) return;
    if (node.matches(selector)) observeHost(node);
    node.querySelectorAll(selector).forEach(observeHost);
  };

  const observer = new MutationObserver((records) => {
    pending.forEach((state, host) => {
      if (!host.isConnected || host.getAttribute(attribute) !== 'pending') release(host);
    });
    records.forEach((record) => record.addedNodes.forEach(discover));
  });

  const resume = () => {
    observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: [attribute],
    });
    document.querySelectorAll(selector).forEach(observeHost);
    // Activate CSS only after recovery is installed. Without JS, conventional Pix remains available.
    document.documentElement.classList.add(enabledClass);
  };

  const onPageShow = (event) => {
    if (event.persisted) resume();
  };
  const onPageHide = (event) => {
    observer.disconnect();
    pending.forEach((state, host) => {
      if (host.getAttribute(attribute) === 'pending') host.setAttribute(attribute, 'fallback');
      release(host);
    });
    document.documentElement.classList.remove(enabledClass);
    if (!event.persisted) {
      window.removeEventListener('pagehide', onPageHide);
      window.removeEventListener('pageshow', onPageShow);
    }
  };
  window.addEventListener('pagehide', onPageHide);
  window.addEventListener('pageshow', onPageShow);
  resume();
})();
