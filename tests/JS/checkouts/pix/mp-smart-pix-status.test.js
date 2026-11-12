const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');

const POLLER_PATH = resolveAlias('assets/js/checkouts/pix/mp-smart-pix-status.js');

const PARAMS = {
  ajaxUrl: '/?wc-ajax=mp_smart_pix_order_status',
  nonce: 'nonce-123',
  orderId: '321',
  orderKey: 'wc_order_SYNTHETIC',
  intervalMs: 5000,
  maxDurationMs: 900000,
};

function renderStatusPage(initialState = 'pending') {
  document.body.innerHTML = `
    <div data-mp-smart-pix-status data-state="${initialState}">
      <div data-mp-smart-pix-state="pending"${initialState === 'pending' ? '' : ' hidden'}>
        <div class="mp-smart-pix-status__card" data-mp-smart-pix-loading>
        <p data-mp-smart-pix-step="0">processing</p>
        <p data-mp-smart-pix-step="1" hidden>seconds</p>
        <p data-mp-smart-pix-step="2" hidden>finalizing</p>
        <p data-mp-smart-pix-step="3" hidden>delayed</p>
        <p data-mp-smart-pix-step="4" hidden>stay here</p>
        <div class="mp-smart-pix-status__progress" aria-hidden="true"><span class="mp-smart-pix-status__progress-fill"></span></div>
        </div>
        <div class="mp-smart-pix-status__card" data-mp-smart-pix-waiting hidden>
          <p data-mp-smart-pix-long-wait>up to 15 minutes</p>
          <p data-mp-smart-pix-timeout hidden>still processing</p>
        </div>
      </div>
      <span role="status" aria-live="polite"></span>
      <div data-mp-smart-pix-state="approved"${initialState === 'approved' ? '' : ' hidden'}></div>
      <div data-mp-smart-pix-state="rejected"${initialState === 'rejected' ? '' : ' hidden'}>
        <div data-mp-smart-pix-error="smart_pix_rejected"><p>generic</p></div>
        <div data-mp-smart-pix-error="smart_pix_insufficient_balance" hidden><p>balance</p></div>
        <div data-mp-smart-pix-error="smart_pix_limit_exceeded" hidden><p>limit</p></div>
      </div>
      <div data-mp-smart-pix-state="cancelled"${initialState === 'cancelled' ? '' : ' hidden'}></div>
    </div>`;
  return document.querySelector('[data-mp-smart-pix-status]');
}

function visibleStates(root) {
  return [...root.querySelectorAll('[data-mp-smart-pix-state]')]
    .filter((card) => !card.hidden)
    .map((card) => card.getAttribute('data-mp-smart-pix-state'));
}

function jsonResponse(state, status = 200, errorCode) {
  return Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve({ success: status === 200, data: { state, smart_pix_error: errorCode } }),
  });
}

const REQUEST_TIMEOUT_MS = 15000;

function createRuntime(responses) {
  let clock = 0;
  let nextTimerId = 1;
  const timers = new Map();
  const runtime = {
    fetch: jest.fn(() => responses.shift()),
    setTimeout: jest.fn((callback, delay) => {
      const id = nextTimerId;
      nextTimerId += 1;
      timers.set(id, { callback, delay });
      return id;
    }),
    clearTimeout: jest.fn((id) => timers.delete(id)),
    createAbortController: jest.fn(() => new AbortController()),
    now: () => clock,
    prefersReducedMotion: jest.fn(() => false),
  };
  const timersWithDelay = (delay) => [...timers.entries()].filter(([, timer]) => timer.delay === delay);
  // Status polls and the deadline are identified by their named callbacks: backoff delays can
  // coincide with the request timeout or a presentation step.
  const timersNamed = (name) => [...timers.entries()].filter(([, timer]) => timer.callback.name === name);
  const runEntry = ([id, timer], elapsedMs) => {
    clock += elapsedMs;
    timers.delete(id);
    return timer.callback();
  };
  const runTimer = (delay, elapsedMs) => runEntry(timersWithDelay(delay)[0], elapsedMs);
  return {
    runtime,
    scheduledPolls: () => timersNamed('poll').map(([, timer]) => timer),
    hasDeadline: () => timersNamed('expire').length > 0,
    deadlineDelay: () => timersNamed('expire')[0]?.[1].delay,
    runNextScheduled: (elapsedMs) => runEntry(timersNamed('poll')[0], elapsedMs),
    fireRequestTimeout: () => runTimer(REQUEST_TIMEOUT_MS, REQUEST_TIMEOUT_MS),
    fireDeadline: () => { const entry = timersNamed('expire')[0]; return runEntry(entry, entry[1].delay); },
    advanceClock: (ms) => { clock += ms; },
    firePresentation: (step) => {
      const at = [10000, 20000, 30000, 45000][step - 1];
      runTimer(at, step === 4 ? 15000 : 10000);
      const leaving = timersWithDelay(100).find(([, timer]) => timer.callback.name !== 'updateProgress');
      if (leaving) runEntry(leaving, 0);
      timersWithDelay(150).forEach((entry) => runEntry(entry, 0));
    },
    fireLongWait: () => runTimer(120000, 120000),
    presentationTimers: () => [10000, 20000, 30000, 45000, 120000].flatMap(timersWithDelay),
    runImmediateTimers: () => timersWithDelay(0).forEach((entry) => runEntry(entry, 0)),
    advanceProgress: (elapsedMs) => runTimer(100, elapsedMs),
    progressTimers: () => timersNamed('updateProgress'),
    activeTimers: () => timers.size,
    completeProgress: () => {
      const entry = timersNamed('beginResultTransition')[0];
      return runEntry(entry, entry[1].delay);
    },
    completeFade: () => runTimer(250, 250),
    revealResult: () => {
      const completion = timersNamed('beginResultTransition')[0];
      if (completion) runEntry(completion, completion[1].delay);
      return runTimer(250, 250);
    },
  };
}

function abortableHang(options) {
  return new Promise((resolve, reject) => {
    options.signal.addEventListener('abort', () => reject(new Error('AbortError')));
  });
}

function loadPoller() {
  return loadFile(POLLER_PATH, 'MPSmartPixStatusPoller', {
    FormData: global.FormData,
    Promise: global.Promise,
    Date: global.Date,
    String: global.String,
    Number: global.Number,
  });
}

describe('MPSmartPixStatusPoller', () => {
  let poller;

  beforeEach(() => {
    delete window.wc_mercadopago_smart_pix_status_params;
    document.body.innerHTML = '';
    poller = loadPoller();
  });

  afterEach(() => {
    delete window.wc_mercadopago_smart_pix_status_params;
    delete window.fetch;
    jest.restoreAllMocks();
  });

  it.each([
    ['network', () => Promise.reject(new TypeError('Failed to fetch'))],
    ['http_error', () => Promise.resolve({ ok: false, status: 503 })],
    ['invalid_json', () => Promise.resolve({ ok: true, json: () => Promise.reject(new SyntaxError('Invalid JSON')) })],
    ['invalid_response', () => Promise.resolve({ ok: true, json: async () => ({ success: false, data: {} }) })],
    ['invalid_response', () => Promise.resolve({ ok: true, json: async () => ({ success: false, data: { state: 'approved' } }) })],
    ['timeout', () => Promise.reject(Object.assign(new Error('Request aborted'), { name: 'AbortError' }))],
  ])('reports a %s polling failure while preserving pending state and retry', async (reason, response) => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls } = createRuntime([]);
    runtime.fetch.mockImplementationOnce(response).mockResolvedValue({ ok: true });
    await poller.start(PARAMS, root, runtime);
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
    const [, report] = runtime.fetch.mock.calls[1];
    expect(report.body.get('polling_failure')).toBe(reason);
    expect(report.body.get('polling_failure_message')).toBeTruthy();
    expect(report.body.get('order_id')).toBe(PARAMS.orderId);
    expect(visibleStates(root)).toEqual(['pending']);
    expect(scheduledPolls()).toHaveLength(1);
  });

  it('does not recurse when reporting the polling failure also fails', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls } = createRuntime([]);
    runtime.fetch.mockRejectedValue(new TypeError('Failed to fetch'));
    await poller.start(PARAMS, root, runtime);
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
    expect(scheduledPolls()).toHaveLength(1);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  it('given a pending page when started then it queries immediately with the order credentials', async () => {
    const root = renderStatusPage();
    const { runtime } = createRuntime([jsonResponse('pending')]);

    await poller.start(PARAMS, root, runtime);

    expect(runtime.fetch).toHaveBeenCalledTimes(1);
    const [url, options] = runtime.fetch.mock.calls[0];
    expect(url).toBe(PARAMS.ajaxUrl);
    expect(options.method).toBe('POST');
    expect(options.credentials).toBe('same-origin');
    expect(options.body.get('order_id')).toBe('321');
    expect(options.body.get('order_key')).toBe('wc_order_SYNTHETIC');
    expect(options.body.get('nonce')).toBe('nonce-123');
  });

  it('given a pending response when it arrives then the next query waits for the interval', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls } = createRuntime([jsonResponse('pending')]);

    await poller.start(PARAMS, root, runtime);

    expect(scheduledPolls()).toHaveLength(1);
    expect(scheduledPolls()[0].delay).toBe(5000);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  // Expected delay before the next status query for a given time since the attempt started.
  const expectedDelay = (elapsed) => {
    if (elapsed < 60000) return 5000;
    return elapsed < 300000 ? 15000 : 30000;
  };

  it('given a pending order then polling backs off to 15 s after a minute and 30 s after five minutes until the deadline', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, hasDeadline, runNextScheduled } = createRuntime([]);
    runtime.fetch.mockImplementation(() => jsonResponse('pending'));

    await poller.start(PARAMS, root, runtime);
    let elapsed = 0;
    const delays = [];
    while (scheduledPolls().length > 0) {
      const [scheduled] = scheduledPolls();
      expect(scheduled.delay).toBe(expectedDelay(elapsed));
      expect(hasDeadline()).toBe(true);
      expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(true);
      delays.push(scheduled.delay);
      elapsed += scheduled.delay;
      await runNextScheduled(scheduled.delay);
    }

    expect(elapsed).toBe(900000);
    expect(delays.filter((delay) => delay === 5000)).toHaveLength(12);
    expect(delays.filter((delay) => delay === 15000)).toHaveLength(16);
    expect(delays.filter((delay) => delay === 30000)).toHaveLength(20);
    expect(hasDeadline()).toBe(false);
    expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(false);
    const statusQueries = runtime.fetch.mock.calls.filter(([, options]) => !options.body.get('polling_expired'));
    // The immediate first query plus one per scheduled delay.
    expect(statusQueries).toHaveLength(49);
    expect(runtime.fetch.mock.calls.filter(([, options]) => options.body.get('polling_expired'))).toHaveLength(1);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  it('given a reload ten minutes into the attempt then the window resumes with the 30 s cadence', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, deadlineDelay, runImmediateTimers } = createRuntime([jsonResponse('pending')]);

    await poller.start({ ...PARAMS, elapsedMs: 600000 }, root, runtime);

    expect(runtime.fetch).toHaveBeenCalledTimes(1);
    expect(scheduledPolls()).toHaveLength(1);
    expect(scheduledPolls()[0].delay).toBe(30000);
    expect(deadlineDelay()).toBe(300000);
    // Presentation steps already reached are shown without waiting another 10-30 seconds.
    runImmediateTimers();
    expect(root.getAttribute('data-stage')).toBe('4');
    expect(root.querySelector('[data-mp-smart-pix-waiting]').hidden).toBe(false);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  it('given a reload after the window then it shows the still-processing message without querying', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, hasDeadline } = createRuntime([jsonResponse('pending')]);

    await poller.start({ ...PARAMS, elapsedMs: PARAMS.maxDurationMs + 60000 }, root, runtime);

    expect(runtime.fetch).toHaveBeenCalledTimes(1);
    expect(runtime.fetch.mock.calls[0][1].body.get('polling_expired')).toBe('1');
    expect(scheduledPolls()).toHaveLength(0);
    expect(hasDeadline()).toBe(false);
    expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(false);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  it.each(['approved', 'rejected', 'cancelled'])(
    'given a %s response after five minutes then that state is shown and polling stops',
    async (state) => {
      const root = renderStatusPage();
      const { runtime, scheduledPolls, hasDeadline, runNextScheduled, revealResult } = createRuntime([
        jsonResponse('pending'), jsonResponse('pending'), jsonResponse(state),
      ]);

      await poller.start(PARAMS, root, runtime);
      await runNextScheduled(300000);
      expect(scheduledPolls()).toHaveLength(1);
      await runNextScheduled(5000);
      revealResult();

      expect(visibleStates(root)).toEqual([state]);
      expect(scheduledPolls()).toHaveLength(0);
      expect(hasDeadline()).toBe(false);
      expect(runtime.fetch).toHaveBeenCalledTimes(3);
    },
  );

  it.each(['approved', 'rejected', 'cancelled'])(
    'given a %s response when it arrives then only that state is shown and polling stops',
    async (state) => {
      const root = renderStatusPage();
      const { runtime, scheduledPolls, runNextScheduled, revealResult } = createRuntime([jsonResponse('pending'), jsonResponse(state)]);

      await poller.start(PARAMS, root, runtime);
      await runNextScheduled(5000);
      revealResult();

      expect(visibleStates(root)).toEqual([state]);
      expect(root.getAttribute('data-state')).toBe(state);
      expect(scheduledPolls()).toHaveLength(0);
    },
  );

  it('given a forbidden response when it arrives then polling stops and the still-processing message is shown', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls } = createRuntime([jsonResponse(null, 403)]);

    await poller.start(PARAMS, root, runtime);

    expect(scheduledPolls()).toHaveLength(0);
    expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(false);
    expect([...root.querySelectorAll('[data-mp-smart-pix-step]')].every((item) => item.hidden)).toBe(true);
    expect(root.getAttribute('data-observation-stopped')).toBe('true');
    expect(runtime.fetch).toHaveBeenCalledTimes(1);
  });

  it('given a server error when it arrives then polling continues', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls } = createRuntime([jsonResponse(null, 500)]);

    await poller.start(PARAMS, root, runtime);

    expect(scheduledPolls()).toHaveLength(1);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  it('given a network failure when querying then polling continues without breaking the page', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls } = createRuntime([Promise.reject(new TypeError('Failed to fetch'))]);

    await poller.start(PARAMS, root, runtime);

    expect(scheduledPolls()).toHaveLength(1);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  it('given an unparseable body when it arrives then polling continues', async () => {
    const root = renderStatusPage();
    const invalidJson = Promise.resolve({ ok: true, status: 200, json: () => Promise.reject(new SyntaxError('bad json')) });
    const { runtime, scheduledPolls } = createRuntime([invalidJson]);

    await poller.start(PARAMS, root, runtime);

    expect(scheduledPolls()).toHaveLength(1);
  });

  it('given the maximum duration elapsed when a pending response arrives then polling stops with the still-processing message', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, advanceClock } = createRuntime([jsonResponse('pending')]);
    runtime.fetch.mockImplementationOnce(() => {
      advanceClock(PARAMS.maxDurationMs);
      return jsonResponse('pending');
    });

    await poller.start(PARAMS, root, runtime);

    expect(scheduledPolls()).toHaveLength(0);
    expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(false);
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
    const expirationBody = runtime.fetch.mock.calls[1][1].body;
    expect(expirationBody.get('polling_expired')).toBe('1');
    expect(expirationBody.get('order_key')).toBe('wc_order_SYNTHETIC');
    expect(runtime.fetch.mock.calls[0][1].body.has('polling_expired')).toBe(false);
  });

  it('given the expiration report fails, when the maximum duration elapses, then the page keeps the still-processing message', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, advanceClock } = createRuntime([]);
    runtime.fetch
      .mockImplementationOnce(() => {
        advanceClock(PARAMS.maxDurationMs);
        return jsonResponse('pending');
      })
      .mockImplementationOnce(() => Promise.reject(new TypeError('offline')));

    await poller.start(PARAMS, root, runtime);
    await Promise.resolve();

    expect(scheduledPolls()).toHaveLength(0);
    expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(false);
  });

  it('given a request that never answers when the maximum duration elapses then the still-processing message is shown and expiration is reported', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, fireDeadline } = createRuntime([new Promise(() => {}), jsonResponse('pending')]);

    poller.start(PARAMS, root, runtime);
    fireDeadline();

    expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(false);
    expect(root.getAttribute('data-observation-stopped')).toBe('true');
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
    expect(runtime.fetch.mock.calls[1][1].body.get('polling_expired')).toBe('1');
    expect(scheduledPolls()).toHaveLength(0);
  });

  it('given a request that exceeds its timeout when it is aborted then the next query is scheduled', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, fireRequestTimeout } = createRuntime([]);
    runtime.fetch.mockImplementationOnce((url, options) => abortableHang(options));

    const polling = poller.start(PARAMS, root, runtime);
    fireRequestTimeout();
    await polling;

    expect(runtime.fetch.mock.calls[0][1].signal.aborted).toBe(true);
    expect(scheduledPolls()).toHaveLength(1);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  it('given a terminal response when it arrives then the maximum-duration deadline is cancelled', async () => {
    const root = renderStatusPage();
    const { runtime, hasDeadline } = createRuntime([jsonResponse('approved')]);

    await poller.start(PARAMS, root, runtime);

    expect(hasDeadline()).toBe(false);
    expect(runtime.fetch).toHaveBeenCalledTimes(1);
  });

  it('given a terminal response arriving after the deadline when it lands then the still-processing message stays', async () => {
    const root = renderStatusPage();
    let answer;
    const late = new Promise((resolve) => { answer = resolve; });
    const { runtime, fireDeadline } = createRuntime([late, jsonResponse('pending')]);

    const polling = poller.start(PARAMS, root, runtime);
    fireDeadline();
    answer(await jsonResponse('approved'));
    await polling;

    expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(false);
    expect(visibleStates(root)).not.toContain('approved');
  });

  it('given a response arriving after the deadline when it is still pending then no further query is scheduled', async () => {
    const root = renderStatusPage();
    let answer;
    const late = new Promise((resolve) => { answer = resolve; });
    const { runtime, scheduledPolls, fireDeadline } = createRuntime([late, jsonResponse('pending')]);

    const polling = poller.start(PARAMS, root, runtime);
    fireDeadline();
    answer(await jsonResponse('pending'));
    await polling;

    expect(scheduledPolls()).toHaveLength(0);
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
  });

  it('given a query scheduled just before the deadline, when the deadline fires, then the scheduled query is cancelled', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, advanceClock, fireDeadline } = createRuntime([jsonResponse('pending'), jsonResponse('pending')]);

    advanceClock(PARAMS.maxDurationMs - 1000);
    await poller.start(PARAMS, root, runtime);
    expect(scheduledPolls()).toHaveLength(1);
    fireDeadline();

    expect(scheduledPolls()).toHaveLength(0);
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
    expect(runtime.fetch.mock.calls[1][1].body.get('polling_expired')).toBe('1');
  });

  it('given a scheduled query whose timer still runs after the deadline, when it fires, then no status query is sent', async () => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, fireDeadline } = createRuntime([jsonResponse('pending'), jsonResponse('pending')]);

    await poller.start(PARAMS, root, runtime);
    const [scheduledPoll] = scheduledPolls();
    fireDeadline();
    await scheduledPoll.callback();

    expect(runtime.fetch).toHaveBeenCalledTimes(2);
    expect(runtime.fetch.mock.calls.filter(([, options]) => !options.body.get('polling_expired'))).toHaveLength(1);
  });

  it('advances presentation at 10, 20, 30 and 45 seconds without stopping status polling', async () => {
    const root = renderStatusPage();
    const { runtime, firePresentation, scheduledPolls } = createRuntime([jsonResponse('pending')]);
    await poller.start(PARAMS, root, runtime);
    for (let step = 1; step <= 4; step += 1) {
      firePresentation(step);
      expect(root.getAttribute('data-stage')).toBe(String(step));
      expect([...root.querySelectorAll('[data-mp-smart-pix-step]')].filter((item) => !item.hidden))
        .toEqual([root.querySelector(`[data-mp-smart-pix-step="${step}"]`)]);
      expect(scheduledPolls()).toHaveLength(1);
      expect(visibleStates(root)).toEqual(['pending']);
      expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(true);
    }
    expect(runtime.fetch).toHaveBeenCalledTimes(1);
  });

  it('updates the presentation even while the first request is hanging', () => {
    const root = renderStatusPage();
    const { runtime, firePresentation } = createRuntime([new Promise(() => {})]);
    poller.start(PARAMS, root, runtime);
    firePresentation(1);
    firePresentation(2);
    firePresentation(3);
    expect(root.getAttribute('data-stage')).toBe('3');
    expect(root.querySelector('[data-mp-smart-pix-step="3"]').hidden).toBe(false);
    expect(runtime.fetch).toHaveBeenCalledTimes(1);
  });

  it.each(['approved', 'rejected', 'cancelled'])('cancels presentation timers when %s arrives', async (state) => {
    const root = renderStatusPage();
    const { runtime, firePresentation, runNextScheduled, presentationTimers, revealResult } = createRuntime([
      jsonResponse('pending'), jsonResponse(state),
    ]);
    await poller.start(PARAMS, root, runtime);
    firePresentation(1);
    await runNextScheduled(5000);
    revealResult();
    expect(visibleStates(root)).toEqual([state]);
    expect(presentationTimers()).toHaveLength(0);
  });

  it.each([
    ['smart_pix_rejected', 'smart_pix_rejected'],
    ['smart_pix_insufficient_balance', 'smart_pix_insufficient_balance'],
    ['smart_pix_limit_exceeded', 'smart_pix_limit_exceeded'],
    [undefined, 'smart_pix_rejected'],
    ['insufficient_amount', 'smart_pix_rejected'],
    ['<img src=x onerror=alert(1)>', 'smart_pix_rejected'],
    [{ code: 'smart_pix_limit_exceeded' }, 'smart_pix_rejected'],
  ])('shows only the allowlisted rejection variant for %p', async (code, expected) => {
    const root = renderStatusPage();
    const { runtime, revealResult } = createRuntime([jsonResponse('rejected', 200, code)]);
    await poller.start(PARAMS, root, runtime);
    revealResult();
    const visible = [...root.querySelectorAll('[data-mp-smart-pix-error]')].filter((item) => !item.hidden);
    expect(visible).toHaveLength(1);
    expect(visible[0].getAttribute('data-mp-smart-pix-error')).toBe(expected);
    expect(root.querySelector('[role="status"]').textContent).toBe(visible[0].textContent);
    expect(root.querySelector('img')).toBeNull();
  });

  it.each(['deadline', 'forbidden'])('stops the presentation on %s without inventing a terminal result', async (ending) => {
    const root = renderStatusPage();
    const { runtime, fireDeadline, presentationTimers } = createRuntime([jsonResponse('pending'), jsonResponse('pending')]);
    if (ending === 'forbidden') runtime.fetch.mockImplementation(() => jsonResponse(null, 403));
    await poller.start(PARAMS, root, runtime);
    if (ending === 'deadline') fireDeadline();
    expect(presentationTimers()).toHaveLength(0);
    expect(root.getAttribute('data-observation-stopped')).toBe('true');
    expect([...root.querySelectorAll('[data-mp-smart-pix-step]')].every((item) => item.hidden)).toBe(true);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  it('advances smoothly between messages, reaching 30% after ten seconds', async () => {
    const root = renderStatusPage();
    const { runtime, advanceProgress } = createRuntime([jsonResponse('pending')]);
    await poller.start(PARAMS, root, runtime);
    const fill = root.querySelector('.mp-smart-pix-status__progress-fill');
    let previous = parseFloat(fill.style.width);
    expect(previous).toBe(0);
    for (let tick = 0; tick < 100; tick += 1) {
      advanceProgress(100);
      const current = parseFloat(fill.style.width);
      expect(current).toBeGreaterThan(previous);
      expect(current - previous).toBeLessThan(1);
      previous = current;
    }
    expect(previous).toBeCloseTo(30);
    expect(visibleStates(root)).toEqual(['pending']);
    expect(runtime.fetch).toHaveBeenCalledTimes(1);
  });

  it('reaches 90% in 30 seconds and holds without continuing progress timers', async () => {
    const root = renderStatusPage();
    const { runtime, advanceProgress, progressTimers, advanceClock } = createRuntime([jsonResponse('pending')]);
    await poller.start(PARAMS, root, runtime);
    advanceProgress(30000);
    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).toBe('90%');
    expect(progressTimers()).toHaveLength(0);
    advanceClock(60000);
    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).toBe('90%');
    expect(root.getAttribute('data-state')).toBe('pending');
  });

  it.each(['approved', 'rejected', 'cancelled'])('keeps the complete bar visible before the %s result enters', async (state) => {
    const root = renderStatusPage();
    const { runtime, scheduledPolls, hasDeadline } = createRuntime([jsonResponse(state)]);
    await poller.start(PARAMS, root, runtime);

    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).toBe('100%');
    expect(root.querySelector('[data-mp-smart-pix-loading]').hidden).toBe(false);
    expect(visibleStates(root)).toEqual(['pending']);
    expect(root.hasAttribute('data-transition')).toBe(false);
    expect(root.querySelector('[data-mp-smart-pix-layer]')).toBeNull();
    expect(scheduledPolls()).toHaveLength(0);
    expect(hasDeadline()).toBe(false);
  });

  it.each(['approved', 'rejected', 'cancelled'])('crossfades the complete loading and %s layers in 250 ms', async (state) => {
    const root = renderStatusPage();
    const { runtime, completeProgress, completeFade, scheduledPolls, hasDeadline, progressTimers } = createRuntime([jsonResponse(state)]);
    await poller.start(PARAMS, root, runtime);
    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).toBe('100%');
    expect(root.hasAttribute('data-transition')).toBe(false);
    completeProgress();
    expect(root.getAttribute('data-transition')).toBe('result');
    expect(root.querySelector('[data-mp-smart-pix-loading]').getAttribute('data-mp-smart-pix-layer')).toBe('leaving');
    expect(root.querySelector(`[data-mp-smart-pix-state="${state}"]`).getAttribute('data-mp-smart-pix-layer')).toBe('entering');
    expect(scheduledPolls()).toHaveLength(0);
    expect(hasDeadline()).toBe(false);
    expect(progressTimers()).toHaveLength(0);
    // Already-queued pending callbacks cannot overwrite the confirmed result.
    runtime.setTimeout.mock.calls.find(([, delay]) => delay === 100)[0]();
    runtime.setTimeout.mock.calls.find(([, delay]) => delay === PARAMS.maxDurationMs)[0]();
    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).toBe('100%');
    completeFade();
    expect(root.hasAttribute('data-transition')).toBe(false);
    expect(root.style.height).toBe('');
    expect(visibleStates(root)).toEqual([state]);
    expect(runtime.fetch).toHaveBeenCalledTimes(1);
  });

  it.each([10000, 30000])('completes the bar before changing cards after %i ms of loading', async (elapsed) => {
    const root = renderStatusPage();
    const { runtime, advanceProgress, runNextScheduled, completeProgress, completeFade } = createRuntime([
      jsonResponse('pending'), jsonResponse('approved'),
    ]);
    await poller.start(PARAMS, root, runtime);
    advanceProgress(elapsed);
    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).toBe(`${elapsed * 90 / 30000}%`);
    await runNextScheduled(0);
    expect(visibleStates(root)).toEqual(['pending']);
    completeProgress();
    expect(root.getAttribute('data-transition')).toBe('result');
    completeFade();
    expect(visibleStates(root)).toEqual(['approved']);
  });

  it.each(['approved', 'rejected'])('reports %s only after both bar completion and card transition', async (state) => {
    const root = renderStatusPage();
    const { runtime, runNextScheduled, completeProgress, completeFade } = createRuntime([
      jsonResponse('pending'), jsonResponse(state),
    ]);
    runtime.navigationElapsedMs = () => runtime.now();
    await poller.start(PARAMS, root, runtime);
    await runNextScheduled(5000);
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
    completeProgress();
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
    completeFade();
    const receipt = runtime.fetch.mock.calls[2][1].body;
    expect(receipt.get('thankyou_result_time_ms')).toBe('5500');
    expect(visibleStates(root)).toEqual([state]);
  });

  it.each(['approved', 'rejected', 'cancelled'])('preserves %s when leaving and restoring during completion', async (state) => {
    const root = renderStatusPage();
    const { runtime, activeTimers } = createRuntime([jsonResponse(state)]);
    runtime.navigationElapsedMs = () => runtime.now();
    runtime.onPageHide = jest.fn();
    runtime.onPageRestore = jest.fn();
    await poller.start(PARAMS, root, runtime);
    const completion = runtime.setTimeout.mock.calls.find(([callback]) => callback.name === 'beginResultTransition')[0];
    runtime.onPageHide.mock.calls[0][0]();
    expect(activeTimers()).toBe(0);
    expect(visibleStates(root)).toEqual([state]);
    expect(root.hasAttribute('data-progress-completion')).toBe(false);
    runtime.onPageRestore.mock.calls[0][0]();
    completion();
    expect(visibleStates(root)).toEqual([state]);
    expect(root.hasAttribute('data-transition')).toBe(false);
    expect(runtime.fetch).toHaveBeenCalledTimes(1);
    expect(activeTimers()).toBe(0);
    if (state === 'rejected') expect(root.querySelector('[role="status"]').textContent).toBe('generic');
  });

  it('does not complete a hidden bar when a result interrupts the long-wait transition', async () => {
    const root = renderStatusPage();
    const { runtime, fireLongWait, runNextScheduled, completeFade } = createRuntime([
      jsonResponse('pending'), jsonResponse('approved'),
    ]);
    await poller.start(PARAMS, root, runtime);
    fireLongWait();
    await runNextScheduled(0);
    expect(root.querySelector('[data-mp-smart-pix-loading]').hidden).toBe(true);
    expect(root.hasAttribute('data-progress-completion')).toBe(false);
    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).not.toBe('100%');
    completeFade();
    expect(visibleStates(root)).toEqual(['approved']);
  });

  it.each(['deadline', 'forbidden'])('freezes the loader on %s without completing it', async (ending) => {
    const root = renderStatusPage();
    const { runtime, advanceProgress, fireDeadline, runNextScheduled, progressTimers } = createRuntime([
      jsonResponse('pending'), jsonResponse(null, 403),
    ]);
    await poller.start(PARAMS, root, runtime);
    advanceProgress(10000);
    const fill = root.querySelector('.mp-smart-pix-status__progress-fill');
    const previousWidth = fill.style.width;
    if (ending === 'deadline') fireDeadline();
    else await runNextScheduled(5000);
    expect(fill.style.width).toBe(previousWidth);
    expect(progressTimers()).toHaveLength(0);
    expect(root.hasAttribute('data-transition')).toBe(false);
    expect(visibleStates(root)).toEqual(['pending']);
  });

  it.each(['approved', 'rejected', 'cancelled'])('shows %s immediately for reduced motion', async (state) => {
    const root = renderStatusPage();
    const { runtime, progressTimers, runNextScheduled } = createRuntime([jsonResponse('pending'), jsonResponse(state)]);
    runtime.prefersReducedMotion.mockReturnValue(true);
    await poller.start(PARAMS, root, runtime);
    expect(progressTimers()).toHaveLength(0);
    await runNextScheduled(5000);
    expect(visibleStates(root)).toEqual([state]);
    expect(root.hasAttribute('data-transition')).toBe(false);
  });

  it('still reveals a confirmed result when the theme removes the decorative progress bar', async () => {
    const root = renderStatusPage();
    root.querySelector('.mp-smart-pix-status__progress').remove();
    const { runtime, progressTimers, revealResult } = createRuntime([jsonResponse('approved')]);
    await poller.start(PARAMS, root, runtime);
    revealResult();
    expect(visibleStates(root)).toEqual(['approved']);
    expect(progressTimers()).toHaveLength(0);
    expect(root.hasAttribute('data-transition')).toBe(false);
  });

  it('crossfades to the informative notice at two minutes and continues polling', async () => {
    const root = renderStatusPage();
    const { runtime, fireLongWait, revealResult, scheduledPolls } = createRuntime([jsonResponse('pending')]);
    await poller.start(PARAMS, root, runtime);
    fireLongWait();
    expect(root.getAttribute('data-state')).toBe('pending');
    expect(root.hasAttribute('data-observation-stopped')).toBe(false);
    revealResult();
    expect(root.querySelector('[data-mp-smart-pix-loading]').hidden).toBe(true);
    expect(root.querySelector('[data-mp-smart-pix-waiting]').hidden).toBe(false);
    expect(root.querySelector('[data-mp-smart-pix-long-wait]').hidden).toBe(false);
    expect(root.querySelector('[data-mp-smart-pix-timeout]').hidden).toBe(true);
    expect(scheduledPolls()).toHaveLength(1);
    expect(runtime.fetch).toHaveBeenCalledTimes(1);
  });

  it('resumes the stage and linear progress without replaying elapsed messages', async () => {
    const root = renderStatusPage();
    const { runtime, presentationTimers } = createRuntime([jsonResponse('pending')]);
    await poller.start({ ...PARAMS, elapsedMs: 25000 }, root, runtime);
    expect(root.getAttribute('data-stage')).toBe('2');
    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).toBe('75%');
    expect(runtime.setTimeout.mock.calls.some(([, delay]) => delay === 0)).toBe(false);
    expect(root.querySelector('[data-mp-smart-pix-step="2"]').hidden).toBe(false);
  });

  it('keeps reduced-motion progress discrete and enters the long wait without an animation', async () => {
    const root = renderStatusPage();
    const { runtime, firePresentation, fireLongWait, progressTimers } = createRuntime([jsonResponse('pending')]);
    runtime.prefersReducedMotion.mockReturnValue(true);
    await poller.start(PARAMS, root, runtime);
    firePresentation(1);
    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).toBe('30%');
    expect(progressTimers()).toHaveLength(0);
    fireLongWait();
    expect(root.querySelector('[data-mp-smart-pix-waiting]').hidden).toBe(false);
    expect(root.hasAttribute('data-transition')).toBe(false);
  });

  it.each(['approved', 'rejected'])('reports navigation-to-%s time once, only after the complete result is displayed', async (state) => {
    const root = renderStatusPage();
    const { runtime, runNextScheduled, revealResult } = createRuntime([jsonResponse('pending'), jsonResponse(state)]);
    runtime.navigationElapsedMs = jest.fn(() => runtime.now() + 2000);
    await poller.start({ ...PARAMS, elapsedMs: 600000 }, root, runtime);
    await runNextScheduled(5000);
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
    const transition = runtime.setTimeout.mock.calls.find(([, delay]) => delay === 250)[0];
    revealResult();
    expect(runtime.fetch).toHaveBeenCalledTimes(3);
    const receipt = runtime.fetch.mock.calls[2][1];
    expect(receipt.body.get('thankyou_result_time_ms')).toBe('7250');
    expect(receipt.body.get('nonce')).toBe(PARAMS.nonce);
    expect(receipt.body.get('order_key')).toBe(PARAMS.orderKey);
    expect(receipt.body.has('polling_expired')).toBe(false);
    transition();
    expect(runtime.fetch).toHaveBeenCalledTimes(3);
    expect(visibleStates(root)).toEqual([state]);
  });

  it('includes the informative notice and hidden-tab elapsed time in the result measurement', async () => {
    const root = renderStatusPage();
    const { runtime, fireLongWait, revealResult, runNextScheduled } = createRuntime([jsonResponse('pending'), jsonResponse('approved')]);
    jest.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
    runtime.navigationElapsedMs = () => runtime.now();
    await poller.start(PARAMS, root, runtime);
    fireLongWait();
    revealResult();
    await runNextScheduled(5000);
    revealResult();
    const receipt = runtime.fetch.mock.calls.find(([, options]) => options.body.has('thankyou_result_time_ms'));
    expect(receipt[1].body.get('thankyou_result_time_ms')).toBe('125500');
  });

  it.each(['cancelled', 'deadline', 'forbidden'])('does not report a displayed approval/rejection for %s', async (ending) => {
    const root = renderStatusPage();
    const response = ending === 'forbidden' ? jsonResponse(null, 403) : jsonResponse(ending === 'cancelled' ? 'cancelled' : 'pending');
    const { runtime, revealResult, fireDeadline } = createRuntime([response]);
    runtime.navigationElapsedMs = () => 5000;
    await poller.start(PARAMS, root, runtime);
    if (ending === 'cancelled') revealResult();
    if (ending === 'deadline') fireDeadline();
    expect(runtime.fetch.mock.calls.some(([, options]) => options.body.has('thankyou_result_time_ms'))).toBe(false);
  });

  it('does not send a result receipt after the page is closed during the transition', async () => {
    const root = renderStatusPage();
    const { runtime, completeProgress } = createRuntime([jsonResponse('approved')]);
    runtime.navigationElapsedMs = () => 5000;
    runtime.onPageHide = jest.fn();
    await poller.start(PARAMS, root, runtime);
    completeProgress();
    const transition = runtime.setTimeout.mock.calls.find(([callback, delay]) => delay === 250 && callback.name !== 'beginResultTransition')[0];
    runtime.onPageHide.mock.calls[0][0]();
    transition();
    expect(visibleStates(root)).toEqual(['approved']);
    expect(runtime.fetch).toHaveBeenCalledTimes(1);
  });

  it('does not let a lost receipt restart polling or replace the result', async () => {
    const root = renderStatusPage();
    const { runtime, revealResult, scheduledPolls } = createRuntime([jsonResponse('approved')]);
    runtime.navigationElapsedMs = () => 5000;
    await poller.start(PARAMS, root, runtime);
    runtime.fetch.mockRejectedValueOnce(new Error('synthetic telemetry failure'));
    revealResult();
    await Promise.resolve();
    expect(visibleStates(root)).toEqual(['approved']);
    expect(scheduledPolls()).toHaveLength(0);
    expect(runtime.fetch).toHaveBeenCalledTimes(2);
  });

  it('resumes pending observation after a history restore with a fresh visit measurement', async () => {
    const root = renderStatusPage();
    const { runtime, advanceClock, runNextScheduled, revealResult } = createRuntime([
      jsonResponse('pending'), jsonResponse('pending'), jsonResponse('approved'),
    ]);
    runtime.onPageHide = jest.fn();
    runtime.onPageRestore = jest.fn();
    runtime.navigationElapsedMs = () => runtime.now();
    await poller.start(PARAMS, root, runtime);
    advanceClock(20000);
    runtime.onPageHide.mock.calls[0][0]();
    advanceClock(10000);
    runtime.onPageRestore.mock.calls[0][0]();
    await Promise.resolve();
    await Promise.resolve();
    expect(root.getAttribute('data-stage')).toBe('3');
    expect(root.querySelector('.mp-smart-pix-status__progress-fill').style.width).toBe('90%');
    await runNextScheduled(5000);
    revealResult();
    const receipt = runtime.fetch.mock.calls.find(([, options]) => options.body.has('thankyou_result_time_ms'));
    expect(receipt[1].body.get('thankyou_result_time_ms')).toBe('5500');
  });

  it.each([[0, 5000, '15%', '0'], [5000, 6000, '33%', '1'], [20000, 15000, '90%', '3']])(
    'continues first-paint progress without resetting after a delayed controller (%i + %i ms)',
    async (age, visibleMs, width, step) => {
      const root = renderStatusPage();
      const fill = root.querySelector('.mp-smart-pix-status__progress-fill');
      root.setAttribute('data-mp-smart-pix-presented-at', '0');
      const { runtime, deadlineDelay, advanceClock } = createRuntime([jsonResponse('pending')]);
      advanceClock(visibleMs);
      await poller.start({ ...PARAMS, elapsedMs: age }, root, runtime);
      expect(root.getAttribute('data-initialized')).toBe('true');
      expect(root.hasAttribute('data-mp-smart-pix-presented-at')).toBe(false);
      expect(fill.style.width).toBe(width);
      expect(root.getAttribute('data-stage')).toBe(step);
      expect(deadlineDelay()).toBe(PARAMS.maxDurationMs - age - visibleMs);
    },
  );

  it.each(['invalid', 'NaN', 'Infinity', '-1000', '1000'])('ignores an invalid/future first-paint timestamp (%p)', async (time) => {
    const root = renderStatusPage();
    root.setAttribute('data-mp-smart-pix-presented-at', time);
    const { runtime, deadlineDelay } = createRuntime([jsonResponse('pending')]);
    await poller.start({ ...PARAMS, elapsedMs: 5000 }, root, runtime);
    expect(deadlineDelay()).toBe(895000);
  });

  it('enters the informative notice when first-paint time crosses two minutes', async () => {
    const root = renderStatusPage();
    root.setAttribute('data-mp-smart-pix-presented-at', '0');
    const { runtime, advanceClock } = createRuntime([jsonResponse('pending')]);
    advanceClock(6000);
    await poller.start({ ...PARAMS, elapsedMs: 115000 }, root, runtime);
    expect(root.querySelector('[data-mp-smart-pix-loading]').hidden).toBe(true);
    expect(root.querySelector('[data-mp-smart-pix-waiting]').hidden).toBe(false);
  });

  it('counts first-paint time after the bar has stopped at 90%, including a hidden tab', async () => {
    const root = renderStatusPage();
    root.setAttribute('data-mp-smart-pix-presented-at', '0');
    const { runtime, deadlineDelay, advanceClock } = createRuntime([jsonResponse('pending')]);
    advanceClock(125000);
    await poller.start(PARAMS, root, runtime);
    expect(root.querySelector('[data-mp-smart-pix-waiting]').hidden).toBe(false);
    expect(deadlineDelay()).toBe(775000);
  });

  it('initializes an already rendered card before DOMContentLoaded and does not initialize twice', () => {
    jest.useFakeTimers();
    try {
      const root = renderStatusPage();
      jest.spyOn(document, 'readyState', 'get').mockReturnValue('loading');
      window.wc_mercadopago_smart_pix_status_params = { ajax_url: '/ajax', nonce: 'n', order_id: 321, order_key: 'k' };
      window.fetch = jest.fn(() => new Promise(() => {}));
      loadPoller();
      expect(root.getAttribute('data-initialized')).toBe('true');
      expect(window.fetch).toHaveBeenCalledTimes(1);
      document.dispatchEvent(new Event('DOMContentLoaded'));
      expect(window.fetch).toHaveBeenCalledTimes(1);
    } finally {
      jest.clearAllTimers();
      jest.useRealTimers();
    }
  });

  it.each(['classic', 'blocks'])('preserves native placement and still positions a legacy template in %s', (theme) => {
    const root = renderStatusPage('approved');
    const container = document.createElement('main');
    container.className = 'woocommerce';
    const anchor = document.createElement(theme === 'classic' ? 'p' : 'section');
    anchor.className = theme === 'classic' ? 'woocommerce-thankyou-order-received' : 'wp-block-woocommerce-order-confirmation-status';
    anchor.textContent = 'Thank you. Your order has been received.';
    const summary = document.createElement('ul');
    summary.className = 'woocommerce-order-overview';
    document.body.append(container);
    container.append(anchor, root, summary);
    const move = jest.spyOn(anchor, 'insertAdjacentElement');
    poller.position(root);
    expect(move).not.toHaveBeenCalled();
    container.append(root);
    poller.position(root);
    poller.position(root);
    expect(anchor.nextElementSibling).toBe(root);
    expect(root.nextElementSibling).toBe(summary);
    expect(document.querySelectorAll('[data-mp-smart-pix-status]')).toHaveLength(1);
    expect(anchor.textContent).toBe('Thank you. Your order has been received.');
  });

  it('keeps a rejected card inside the native status block before its failure notice and actions', () => {
    const root = renderStatusPage('rejected');
    const native = document.createElement('section');
    native.className = 'wp-block-woocommerce-order-confirmation-status';
    native.innerHTML = '<h1>Order failed</h1><p>Native failure</p><p><a href="/native-pay">Try again</a></p>';
    document.body.append(native);
    native.querySelector('h1').insertAdjacentElement('afterend', root);
    const original = native.innerHTML;
    poller.position(root);
    expect(native.innerHTML).toBe(original);
    expect(root.nextElementSibling.textContent).toBe('Native failure');
  });

  describe('readParams', () => {
    it('given localized string params when reading then numbers are restored', () => {
      expect(poller.readParams({
        ajax_url: '/ajax', nonce: 'n', order_id: 321, order_key: 'k', interval_ms: '5000', max_duration_ms: '900000',
      })).toEqual({
        ajaxUrl: '/ajax', nonce: 'n', orderId: '321', orderKey: 'k', intervalMs: 5000, maxDurationMs: 900000, elapsedMs: 0,
      });
    });

    it('given invalid timings when reading then safe defaults are used', () => {
      const params = poller.readParams({ ajax_url: '/ajax', nonce: 'n', order_id: 1, order_key: 'k', interval_ms: 'abc' });

      expect(params.intervalMs).toBe(5000);
      expect(params.maxDurationMs).toBe(900000);
      expect(params.elapsedMs).toBe(0);
    });

    it.each([['120000', 120000], ['abc', 0], ['-5', 0], ['Infinity', 0], [undefined, 0]])(
      'given the localized elapsed time %p when reading then the attempt window resumes at %p ms',
      (elapsed, expected) => {
        const params = poller.readParams({ ajax_url: '/ajax', nonce: 'n', order_id: 1, order_key: 'k', elapsed_ms: elapsed });

        expect(params.elapsedMs).toBe(expected);
      },
    );

    it.each(['ajax_url', 'nonce', 'order_id', 'order_key'])('given a missing %s when reading then it returns null', (field) => {
      const raw = { ajax_url: '/ajax', nonce: 'n', order_id: 1, order_key: 'k' };
      delete raw[field];

      expect(poller.readParams(raw)).toBeNull();
    });
  });

  describe('init', () => {
    const LOCALIZED = { ajax_url: '/ajax', nonce: 'n', order_id: '321', order_key: 'k', interval_ms: '5000', max_duration_ms: '900000' };

    it('given a pending page with params when initializing then it starts polling', () => {
      renderStatusPage('pending');
      window.wc_mercadopago_smart_pix_status_params = LOCALIZED;
      window.fetch = jest.fn(() => new Promise(() => {}));

      poller.init();

      expect(window.fetch).toHaveBeenCalledTimes(1);
    });

    it.each(['approved', 'rejected', 'cancelled'])('given a page rendered as %s then it does not poll', (state) => {
      renderStatusPage(state);
      window.wc_mercadopago_smart_pix_status_params = LOCALIZED;
      window.fetch = jest.fn();

      poller.init();

      expect(window.fetch).not.toHaveBeenCalled();
    });

    it('given no status container when initializing then it does nothing', () => {
      window.wc_mercadopago_smart_pix_status_params = LOCALIZED;
      window.fetch = jest.fn();

      poller.init();

      expect(window.fetch).not.toHaveBeenCalled();
    });

    it('given missing params when initializing then it does nothing', () => {
      renderStatusPage('pending');
      window.fetch = jest.fn();

      poller.init();

      expect(window.fetch).not.toHaveBeenCalled();
    });
  });
});
