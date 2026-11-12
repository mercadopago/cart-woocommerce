/* eslint-disable no-unused-vars, @typescript-eslint/no-unused-vars */
const MPSmartPixStatusPoller = (function () {
  const TERMINAL_STATES = ['approved', 'rejected', 'cancelled'];
  const DEFAULT_INTERVAL_MS = 5000;
  const DEFAULT_MAX_DURATION_MS = 900000;
  const BACKOFF_STEPS = [
    { untilMs: 60000, delayMs: null },
    { untilMs: 300000, delayMs: 15000 },
  ];
  const LATE_INTERVAL_MS = 30000;
  const REQUEST_TIMEOUT_MS = 15000;
  const MESSAGE_STEPS = [10000, 20000, 30000, 45000];
  const LONG_WAIT_MS = 120000;
  // Match the height/card motion in assets/css/public/mp-smart-pix-status.css (150 ms + 100 ms).
  const TRANSITION_MS = 250;
  // Match the [data-progress-completion] width transition in that stylesheet.
  const PROGRESS_COMPLETION_MS = 150;
  const PROGRESS_HOLD_MS = 100;
  const REJECTION_CODES = ['smart_pix_rejected', 'smart_pix_insufficient_balance', 'smart_pix_limit_exceeded'];

  function showState(root, state, errorCode) {
    root.setAttribute('data-state', state);
    root.querySelectorAll('[data-mp-smart-pix-state]').forEach((card) => {
      card.hidden = card.getAttribute('data-mp-smart-pix-state') !== state;
    });
    if (state === 'rejected') {
      const code = REJECTION_CODES.includes(errorCode) ? errorCode : 'smart_pix_rejected';
      root.querySelectorAll('[data-mp-smart-pix-error]').forEach((message) => {
        message.hidden = message.getAttribute('data-mp-smart-pix-error') !== code;
      });
    }
  }

  function showProcessingStep(root, step) {
    root.setAttribute('data-stage', String(step));
    root.querySelectorAll('[data-mp-smart-pix-step]').forEach((message) => {
      message.hidden = message.getAttribute('data-mp-smart-pix-step') !== String(step);
    });
  }

  function position(root) {
    const document = root.ownerDocument;
    const scope = root.closest('.woocommerce-order, .woocommerce') || document;
    const anchor = scope.querySelector('.woocommerce-thankyou-order-received')
      || scope.querySelector('.wp-block-woocommerce-order-confirmation-status');
    // Older WooCommerce/theme templates may omit the native server placement hooks.
    if (anchor && !anchor.contains(root) && anchor.nextElementSibling !== root) anchor.insertAdjacentElement('afterend', root);
  }

  function buildRequestBody(params, pollingExpired = false) {
    const body = new FormData();
    body.append('order_id', params.orderId);
    body.append('order_key', params.orderKey);
    body.append('nonce', params.nonce);
    if (pollingExpired) body.append('polling_expired', '1');
    return body;
  }

  function nextDelay(params, elapsed) {
    const step = BACKOFF_STEPS.find((candidate) => elapsed < candidate.untilMs);
    return Math.max(params.intervalMs, step ? (step.delayMs ?? params.intervalMs) : LATE_INTERVAL_MS);
  }

  function start(params, root, runtime) {
    const progressFill = root.querySelector('.mp-smart-pix-status__progress-fill');
    // The inline presenter starts at first paint, before any external footer script can block it.
    const presentedAt = Number(root.getAttribute('data-mp-smart-pix-presented-at'));
    const presentationElapsed = root.hasAttribute('data-mp-smart-pix-presented-at') && Number.isFinite(presentedAt) && presentedAt >= 0
      ? Math.max(0, runtime.now() - presentedAt) : 0;
    const initialElapsed = Math.min((params.elapsedMs ?? 0) + presentationElapsed, params.maxDurationMs);
    // Consume only once; history restoration already carries the full elapsed attempt age.
    root.removeAttribute('data-mp-smart-pix-presented-at');
    root.setAttribute('data-initialized', 'true');
    const startedAt = runtime.now() - initialElapsed;
    const reducedMotion = runtime.prefersReducedMotion?.() === true;
    const loadingCard = root.querySelector('[data-mp-smart-pix-loading]');
    const waitingCard = root.querySelector('[data-mp-smart-pix-waiting]');
    const announcement = root.querySelector('[role="status"]');
    let currentCard = initialElapsed >= LONG_WAIT_MS ? waitingCard : loadingCard;
    let finished = false;
    let closed = false;
    let reported = false;
    let deadline = null;
    let nextPoll = null;
    let progressTimer = null;
    let completionTimer = null;
    let terminalResult = null;
    let cardTransition = null;
    let presentationTimers = [];
    let messageTimers = [];

    function announce(card) {
      if (!announcement || !card) return;
      announcement.textContent = [...card.querySelectorAll('p')]
        .filter((item) => !item.closest('[hidden]')).map((item) => item.textContent.trim()).join(' ');
    }

    function clearMessages() {
      messageTimers.forEach((timer) => runtime.clearTimeout(timer));
      messageTimers = [];
      root.removeAttribute('data-message-transition');
    }

    function settleCards() {
      if (!cardTransition) return;
      runtime.clearTimeout(cardTransition.timer);
      cardTransition.previous.hidden = true;
      cardTransition.previous.removeAttribute('data-mp-smart-pix-layer');
      cardTransition.previous.removeAttribute('aria-hidden');
      cardTransition.next.removeAttribute('data-mp-smart-pix-layer');
      root.style.height = '';
      root.removeAttribute('data-transition');
      currentCard = cardTransition.next;
      cardTransition = null;
    }

    function transitionTo(card, done = () => {}) {
      settleCards();
      if (!card) { done(); return; }
      const height = root.getBoundingClientRect().height;
      card.hidden = false;
      if (reducedMotion || !currentCard || currentCard === card) {
        if (currentCard && currentCard !== card) currentCard.hidden = true;
        currentCard = card;
        announce(card);
        done();
        return;
      }
      const previous = currentCard;
      card.setAttribute('data-mp-smart-pix-layer', 'entering');
      previous.setAttribute('data-mp-smart-pix-layer', 'leaving');
      previous.setAttribute('aria-hidden', 'true');
      root.style.height = `${height}px`;
      // Measure after exposing the incoming card at the same width, without putting it in flow.
      const nextHeight = card.getBoundingClientRect().height;
      root.getBoundingClientRect();
      root.setAttribute('data-transition', 'result');
      root.style.height = `${nextHeight}px`;
      cardTransition = { previous, next: card, timer: runtime.setTimeout(() => {
        settleCards();
        announce(card);
        if (!closed) done();
      }, TRANSITION_MS) };
    }

    function updateProgress() {
      if (!progressFill || finished || initialElapsed >= LONG_WAIT_MS) return;
      const elapsed = Math.max(0, runtime.now() - startedAt);
      progressFill.style.width = `${Math.min(90, elapsed * 90 / 30000)}%`;
      if (!reducedMotion && elapsed < 30000) progressTimer = runtime.setTimeout(updateProgress, 100);
    }

    function changeMessage(step) {
      if (finished) return;
      clearMessages();
      if (reducedMotion) {
        showProcessingStep(root, step);
        updateProgress();
        announce(loadingCard);
        return;
      }
      root.setAttribute('data-message-transition', 'leaving');
      messageTimers.push(runtime.setTimeout(() => {
        if (finished) return;
        showProcessingStep(root, step);
        root.setAttribute('data-message-transition', 'entering');
        announce(loadingCard);
        messageTimers.push(runtime.setTimeout(() => root.removeAttribute('data-message-transition'), 150));
      }, 100));
    }

    function showWaiting(stopped = false, immediate = false) {
      clearMessages();
      runtime.clearTimeout(progressTimer);
      if (stopped) root.setAttribute('data-observation-stopped', 'true');
      const timeout = root.querySelector('[data-mp-smart-pix-timeout]');
      const description = root.querySelector('[data-mp-smart-pix-long-wait]');
      if (timeout) timeout.hidden = !stopped;
      if (description) description.hidden = stopped;
      if (stopped) root.querySelectorAll('[data-mp-smart-pix-step]').forEach((item) => { item.hidden = true; });
      if (immediate) {
        settleCards();
        if (loadingCard) loadingCard.hidden = true;
        if (waitingCard) waitingCard.hidden = false;
        currentCard = waitingCard;
        announce(waitingCard);
      } else transitionTo(waitingCard);
    }

    function reportResult(state) {
      if (reported || closed || !['approved', 'rejected'].includes(state)) return;
      const milliseconds = runtime.navigationElapsedMs?.();
      if (!Number.isFinite(milliseconds) || milliseconds < 0) return;
      reported = true;
      const body = buildRequestBody(params);
      body.append('thankyou_result_time_ms', String(Math.round(milliseconds)));
      // Only the bounded duration and the existing POST credentials leave the page.
      // The endpoint verifies the persisted terminal state; telemetry cannot drive payment effects.
      const controller = runtime.createAbortController?.() ?? null;
      const timer = runtime.setTimeout(() => controller?.abort?.(), REQUEST_TIMEOUT_MS);
      try {
        Promise.resolve(runtime.fetch(params.ajaxUrl, {
          method: 'POST', body, credentials: 'same-origin', signal: controller?.signal,
        })).catch(() => undefined).finally(() => runtime.clearTimeout(timer));
      } catch (_) { runtime.clearTimeout(timer); }
    }

    function revealResult(state, errorCode) {
      settleCards();
      // Select the pre-rendered rejection without hiding the outgoing pending layer yet.
      if (state === 'rejected') {
        const code = REJECTION_CODES.includes(errorCode) ? errorCode : 'smart_pix_rejected';
        root.querySelectorAll('[data-mp-smart-pix-error]').forEach((message) => {
          message.hidden = message.getAttribute('data-mp-smart-pix-error') !== code;
        });
      }
      terminalResult = { state, errorCode };
      root.setAttribute('data-state', state);
      const result = root.querySelector(`[data-mp-smart-pix-state="${state}"]`);
      function beginResultTransition() {
        completionTimer = null;
        root.removeAttribute('data-progress-completion');
        if (closed) return;
        transitionTo(result, () => {
          showState(root, state, errorCode);
          reportResult(state);
        });
      }
      if (progressFill && loadingCard && currentCard === loadingCard && !loadingCard.hidden) {
        if (!reducedMotion) {
          root.setAttribute('data-progress-completion', 'true');
          // Commit the current width before completing, including an immediate first response.
          progressFill.getBoundingClientRect();
          progressFill.style.width = '100%';
          completionTimer = runtime.setTimeout(beginResultTransition, PROGRESS_COMPLETION_MS + PROGRESS_HOLD_MS);
          return;
        }
        progressFill.style.width = '100%';
      }
      beginResultTransition();
    }

    function reportFailure(reason, message) {
      const body = buildRequestBody(params);
      body.append('polling_failure', reason);
      body.append('polling_failure_message', String(message || reason).slice(0, 1024));
      const controller = runtime.createAbortController?.() ?? null;
      const reportTimer = runtime.setTimeout(() => controller?.abort?.(), REQUEST_TIMEOUT_MS);
      try {
        Promise.resolve(runtime.fetch(params.ajaxUrl, {
          method: 'POST', body, credentials: 'same-origin', signal: controller?.signal,
        })).catch(() => undefined).finally(() => runtime.clearTimeout(reportTimer));
      } catch (_) { runtime.clearTimeout(reportTimer); }
    }

    function reportExpiration() {
      try {
        Promise.resolve(runtime.fetch(params.ajaxUrl, {
          method: 'POST', body: buildRequestBody(params, true), credentials: 'same-origin',
        })).catch(() => undefined);
      } catch (_) { /* Reporting never changes the pending state. */ }
    }

    function finish() {
      finished = true;
      runtime.clearTimeout(deadline);
      runtime.clearTimeout(nextPoll);
      runtime.clearTimeout(progressTimer);
      runtime.clearTimeout(completionTimer);
      completionTimer = null;
      root.removeAttribute('data-progress-completion');
      presentationTimers.forEach((timer) => runtime.clearTimeout(timer));
      presentationTimers = [];
      clearMessages();
    }

    function expire() {
      if (finished) return;
      finish();
      showWaiting(true, true);
      reportExpiration();
    }

    function scheduleNext() {
      if (finished) return;
      const elapsed = runtime.now() - startedAt;
      if (elapsed >= params.maxDurationMs) { expire(); return; }
      // eslint-disable-next-line @typescript-eslint/no-use-before-define -- poll and scheduleNext recurse
      nextPoll = runtime.setTimeout(poll, nextDelay(params, elapsed));
    }

    function handleResponse(response) {
      if (finished) return undefined;
      if (response?.status === 403) {
        finish();
        showWaiting(true, true);
        return undefined;
      }
      if (!response?.ok) {
        reportFailure('http_error', `HTTP ${Number(response?.status) || 0}`);
        scheduleNext();
        return undefined;
      }
      return response.json().then((payload) => {
        if (finished) return;
        const state = payload?.data?.state;
        if (payload?.success !== true || (state !== 'pending' && !TERMINAL_STATES.includes(state))) {
          reportFailure('invalid_response', 'Invalid Smart Pix status response');
          scheduleNext();
        } else if (TERMINAL_STATES.includes(state)) {
          finish();
          revealResult(state, payload.data.smart_pix_error);
        } else scheduleNext();
      }).catch((error) => {
        if (!finished) {
          reportFailure('invalid_json', error?.message || 'Invalid JSON response');
          scheduleNext();
        }
      });
    }

    function poll() {
      if (finished) return Promise.resolve();
      const controller = runtime.createAbortController?.() ?? null;
      const requestTimer = runtime.setTimeout(() => controller?.abort?.(), REQUEST_TIMEOUT_MS);
      return runtime.fetch(params.ajaxUrl, {
        method: 'POST', body: buildRequestBody(params), credentials: 'same-origin', signal: controller?.signal,
      }).then(handleResponse).catch((error) => {
        if (!finished) {
          reportFailure(error?.name === 'AbortError' ? 'timeout' : 'network', error?.message);
          scheduleNext();
        }
      }).finally(() => runtime.clearTimeout(requestTimer));
    }

    runtime.onPageHide?.(() => {
      closed = true;
      finish();
      settleCards();
      if (terminalResult) {
        showState(root, terminalResult.state, terminalResult.errorCode);
        announce(root.querySelector(`[data-mp-smart-pix-state="${terminalResult.state}"]`));
      }
      // History may restore this same document from the back/forward cache rather than reload it.
      runtime.onPageRestore?.(() => {
        if (root.getAttribute('data-state') !== 'pending') return;
        const restoredAt = runtime.now();
        start({ ...params, elapsedMs: Math.max(0, restoredAt - startedAt) }, root, {
          ...runtime, navigationElapsedMs: () => runtime.now() - restoredAt,
        });
      });
    });
    if (initialElapsed >= params.maxDurationMs) { expire(); return Promise.resolve(); }

    const step = MESSAGE_STEPS.filter((at) => initialElapsed >= at).length;
    showProcessingStep(root, step);
    if (initialElapsed >= LONG_WAIT_MS) showWaiting(false, true);
    else {
      if (loadingCard) loadingCard.hidden = false;
      if (waitingCard) waitingCard.hidden = true;
      updateProgress();
      announce(loadingCard);
    }
    deadline = runtime.setTimeout(expire, params.maxDurationMs - initialElapsed);
    presentationTimers = MESSAGE_STEPS.flatMap((at, index) => (at > initialElapsed
      ? [runtime.setTimeout(() => changeMessage(index + 1), at - initialElapsed)] : []));
    if (initialElapsed < LONG_WAIT_MS) presentationTimers.push(runtime.setTimeout(() => {
      if (!finished) showWaiting();
    }, LONG_WAIT_MS - initialElapsed));
    return poll();
  }

  function readParams(raw) {
    if (!raw?.ajax_url || !raw?.nonce || !raw?.order_id || !raw?.order_key) return null;
    return {
      ajaxUrl: String(raw.ajax_url), nonce: String(raw.nonce), orderId: String(raw.order_id), orderKey: String(raw.order_key),
      intervalMs: Number(raw.interval_ms) > 0 ? Number(raw.interval_ms) : DEFAULT_INTERVAL_MS,
      maxDurationMs: Number(raw.max_duration_ms) > 0 ? Number(raw.max_duration_ms) : DEFAULT_MAX_DURATION_MS,
      elapsedMs: Number.isFinite(Number(raw.elapsed_ms)) && Number(raw.elapsed_ms) > 0 ? Number(raw.elapsed_ms) : 0,
    };
  }

  function init() {
    const root = window.document.querySelector('[data-mp-smart-pix-status]');
    const params = readParams(window.wc_mercadopago_smart_pix_status_params);
    if (!root || root.hasAttribute('data-initialized')) return;
    position(root);
    if (!params || TERMINAL_STATES.includes(root.getAttribute('data-state'))) return;
    start(params, root, {
      fetch: window.fetch.bind(window), setTimeout: window.setTimeout.bind(window), clearTimeout: window.clearTimeout.bind(window),
      createAbortController: () => (typeof window.AbortController === 'function' ? new window.AbortController() : null),
      now: () => Date.now(),
      navigationElapsedMs: () => window.performance?.now?.(),
      onPageHide: (callback) => window.addEventListener('pagehide', callback, { once: true }),
      onPageRestore: (callback) => window.addEventListener('pageshow', (event) => {
        if (event.persisted) callback();
      }, { once: true }),
      prefersReducedMotion: () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true,
    });
  }

  init();
  if (window.document.readyState === 'loading') window.document.addEventListener('DOMContentLoaded', init, { once: true });
  return { init, start, readParams, position };
})();
