/* The head script owns only the presentation before the independent Smart Pix bundle starts. */
(() => {
  const enabledClass = 'mp-smart-pix-bootstrap-enabled';
  const attribute = 'data-mp-smart-pix-bootstrap';
  const reasonAttribute = 'data-mp-smart-pix-fallback-reason';
  const assetLoadFailedReason = 'asset_load_failed';
  const selector = `#mp-checkout-pix-root[${attribute}="pending"]`;
  const pending = new Map();
  // Same cap as the eligibility load: a slow page must not hide the offer before the bundle starts.
  const maxWaitMs = 30000;
  const smartPixAssetIds = ['wc_mercadopago_smart_pix-js', 'wc_mercadopago_smart_pix-css'];
  let assetLoadFailed = false;

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

  const fallBack = (host, reason = null) => {
    if (host.getAttribute(attribute) === 'pending') {
      if (reason) host.setAttribute(reasonAttribute, reason);
      host.setAttribute(attribute, 'fallback');
    }
    release(host);
  };

  const fallBackAll = (reason = null) => pending.forEach((state, host) => fallBack(host, reason));

  const observeHost = (host) => {
    if (!host.isConnected || pending.has(host) || !host.matches(selector)) return;
    // The failed asset emits no second error: a host mounted or re-rendered later must not wait for it.
    if (assetLoadFailed) {
      fallBack(host, assetLoadFailedReason);
      return;
    }
    const busy = host.getAttribute('aria-busy');
    host.setAttribute('aria-busy', 'true');
    const timer = setTimeout(() => fallBack(host), maxWaitMs);
    pending.set(host, { busy, timer });
  };

  // A bundle that cannot load will never start: show conventional Pix now instead of at the cap.
  const onAssetError = (event) => {
    if (!smartPixAssetIds.includes(event.target?.id)) return;
    assetLoadFailed = true;
    fallBackAll(assetLoadFailedReason);
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
    fallBackAll();
    document.documentElement.classList.remove(enabledClass);
    if (!event.persisted) {
      window.removeEventListener('pagehide', onPageHide);
      window.removeEventListener('pageshow', onPageShow);
      document.removeEventListener('error', onAssetError, true);
    }
  };
  window.addEventListener('pagehide', onPageHide);
  window.addEventListener('pageshow', onPageShow);
  // Resource errors do not bubble; only the capture phase sees the failed script or stylesheet.
  document.addEventListener('error', onAssetError, true);
  resume();
})();
