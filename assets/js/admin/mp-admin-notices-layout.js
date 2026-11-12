(function () {
  const params = window.wc_mercadopago_native_notices_params || {};
  const nativeEnabled = [true, 1, '1'].includes(params.enabled);

  function sendMetric(event, reason) {
    if (!params.ajax_url || !params.nonce || typeof window.fetch !== 'function') return;

    try {
      const body = new URLSearchParams({
        action: 'mp_native_onboarding_notice_metric',
        nonce: params.nonce,
        event,
        screen: params.screen,
        viewport: window.innerWidth <= 782 ? 'mobile' : 'desktop',
      });
      if (reason) body.set('reason', reason);

      Promise.resolve(window.fetch(params.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        keepalive: true,
      })).catch(() => {});
    } catch {
      // Telemetry is best-effort and must never interrupt the onboarding link.
    }
  }

  function reportError(reason) {
    const reported = (document.documentElement.dataset.mpNativeNoticeErrors || '').split(' ');
    if (reported.includes(reason)) return;
    document.documentElement.dataset.mpNativeNoticeErrors = [...reported, reason].filter(Boolean).join(' ');
    sendMetric('error', reason);
  }

  function hasVisibleRemoteCard(slot) {
    return Array.from(slot.querySelectorAll('.andes-message')).some(card => {
      let current = card;
      while (current && current !== slot) {
        const style = window.getComputedStyle(current);
        if (current.hidden || style.display === 'none' || style.visibility === 'hidden') return false;
        current = current.parentElement;
      }
      return true;
    });
  }

  function isVisible(card) {
    if (document.visibilityState === 'hidden') return false;
    const style = window.getComputedStyle(card);
    if (style.display === 'none' || style.visibility === 'hidden' || card.closest('[hidden]')) return false;
    const rect = card.getBoundingClientRect();
    const region = card.closest('#mp-admin-notices-region');
    if (region && /auto|scroll|hidden/.test(window.getComputedStyle(region).overflowY)) {
      const regionRect = region.getBoundingClientRect();
      if (rect.bottom <= regionRect.top || rect.top >= regionRect.bottom) return false;
    }
    return rect.width > 0 && rect.height > 0 && rect.bottom > 0 && rect.right > 0 &&
      rect.top < window.innerHeight && rect.left < window.innerWidth;
  }

  function initialize() {
    const region = document.getElementById('mp-admin-notices-region');
    if (!region) {
      if (nativeEnabled) reportError('native_slot_missing');
      return;
    }
    if (region.dataset.mpNoticesInitialized === '1') return;
    region.dataset.mpNoticesInitialized = '1';

    try {
      const adminContent = document.getElementById('wpbody-content');
      if (adminContent) {
        adminContent.prepend(region);
      }
      const remoteSlot = document.getElementById('mp-jaiminho-notices-slot');
      const nativeSlot = document.getElementById('mp-native-notices-slot');
      const card = document.getElementById('mp-native-onboarding-notice');
      const link = document.getElementById('mp-native-onboarding-notice-link');
      const closeButton = document.getElementById('mp-native-onboarding-notice-close');
      let remoteObserver;
      let visibilityObserver;
      let nativeClosed = false;
      let active = true;
      let previousRoute;
      const historyWrappers = [];
      let intersectionVisible = typeof window.IntersectionObserver !== 'function';

      function updateRegionHeight() {
        if (window.innerWidth <= 782) {
          region.style.removeProperty('--mp-admin-notices-top');
          return;
        }
        region.style.setProperty('--mp-admin-notices-top', `${Math.max(0, region.getBoundingClientRect().top)}px`);
      }

      updateRegionHeight();

      function reportView() {
        if (!nativeEnabled || !card || !intersectionVisible || card.dataset.mpNoticeViewed === '1' || !isVisible(card)) return;
        card.dataset.mpNoticeViewed = '1';
        window.removeEventListener('scroll', reportView, true);
        sendMetric('view');
        if (visibilityObserver) visibilityObserver.disconnect();
      }

      function handleResize() {
        updateRegionHeight();
        reportView();
      }

      function updateRemoteSlot() {
        const hasRemote = remoteSlot ? hasVisibleRemoteCard(remoteSlot) : false;
        const hasNative = Boolean(card && nativeSlot && !nativeSlot.hidden);
        if (remoteSlot) remoteSlot.classList.toggle('mp-has-remote-notices', hasRemote);
        region.classList.toggle('mp-notices-region-empty', !hasNative && !hasRemote);
      }

      function closeNativeNotice() {
        const restoreFocus = nativeSlot.contains(nativeSlot.ownerDocument.activeElement);
        nativeClosed = true;
        nativeSlot.hidden = true;
        updateRemoteSlot();

        if (restoreFocus) {
          const remoteControls = remoteSlot ? [
            ...remoteSlot.querySelectorAll('.andes-message a[href]'),
            ...remoteSlot.querySelectorAll('.andes-message button, .andes-message [tabindex]'),
          ] : [];
          const target = remoteControls.find(control => control.tabIndex >= 0 && !control.matches(':disabled') &&
            control.getAttribute('aria-disabled') !== 'true' && !control.closest('[inert]') && isVisible(control)) || adminContent;
          if (target && isVisible(target)) {
            if (target === adminContent && !target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
            target.focus({ preventScroll: true });
          }
        }
      }

      function reopenNativeNotice() {
        if (!nativeClosed) return;
        nativeClosed = false;
        nativeSlot.hidden = false;
        updateRemoteSlot();
        updateRegionHeight();
        reportView();
      }

      function currentRoute() {
        const url = new URL(window.location.href);
        return JSON.stringify([url.pathname, url.searchParams.get('page'), url.searchParams.get('path') || '/']);
      }

      function handleRouteChange() {
        if (!active) return;
        const route = currentRoute();
        if (route === previousRoute) return;
        previousRoute = route;
        reopenNativeNotice();
      }

      function handlePageShow(event) {
        if (event.persisted) reopenNativeNotice();
      }

      if (nativeSlot && card) {
        if (closeButton) closeButton.addEventListener('click', closeNativeNotice);
        window.addEventListener('pageshow', handlePageShow);

        const url = new URL(window.location.href);
        if (url.pathname.endsWith('/admin.php') && url.searchParams.get('page') === 'wc-admin') {
          previousRoute = currentRoute();
          for (const name of ['pushState', 'replaceState']) {
            const original = window.history[name];
            const wrapped = function (...args) {
              const result = original.apply(this, args);
              try {
                handleRouteChange();
              } catch {
                // Notice updates must not change the History API's result.
              }
              return result;
            };
            historyWrappers.push({ name, original, wrapped });
            window.history[name] = wrapped;
          }
          window.addEventListener('popstate', handleRouteChange);
        }
      }

      if (remoteSlot) {
        updateRemoteSlot();
        if (typeof window.MutationObserver === 'function') {
          remoteObserver = new window.MutationObserver(() => {
            updateRemoteSlot();
            reportView();
          });
          remoteObserver.observe(remoteSlot, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'hidden'] });
        }
      }

      if (nativeEnabled) {
        if (!nativeSlot || !card) {
          reportError('native_slot_missing');
        } else {
          if (link) link.addEventListener('click', () => sendMetric('click'));
          else reportError('cta_missing');

          if (typeof window.IntersectionObserver === 'function') {
            visibilityObserver = new window.IntersectionObserver(entries => {
              const observation = entries.find(entry => entry.target === card);
              if (!observation) return;
              intersectionVisible = observation.isIntersecting;
              reportView();
            });
            visibilityObserver.observe(card);
          }
          document.addEventListener('visibilitychange', reportView);
          // Retry CSS visibility and edge contact until the first actual view.
          if (card.dataset.mpNoticeViewed !== '1') {
            window.addEventListener('scroll', reportView, { passive: true, capture: true });
          }
          reportView();
        }
      }

      window.addEventListener('resize', handleResize);
      window.addEventListener('pagehide', function cleanup(event) {
        if (event.persisted) return;
        active = false;
        if (remoteObserver) remoteObserver.disconnect();
        if (visibilityObserver) visibilityObserver.disconnect();
        if (closeButton) closeButton.removeEventListener('click', closeNativeNotice);
        window.removeEventListener('pageshow', handlePageShow);
        window.removeEventListener('popstate', handleRouteChange);
        for (const { name, original, wrapped } of historyWrappers) {
          if (window.history[name] === wrapped) window.history[name] = original;
        }
        document.removeEventListener('visibilitychange', reportView);
        window.removeEventListener('resize', handleResize);
        window.removeEventListener('scroll', reportView, true);
        window.removeEventListener('pagehide', cleanup);
      });
    } catch {
      if (nativeEnabled) reportError('bootstrap_failed');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
