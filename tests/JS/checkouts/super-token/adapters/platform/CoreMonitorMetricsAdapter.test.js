const { CoreMonitorMetricsAdapter } = require('@super-token/adapters/platform/CoreMonitorMetricsAdapter');

const CORE_MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';

function lastFetch() {
  const call = global.fetch.mock.calls[global.fetch.mock.calls.length - 1];
  return { url: call[0], body: JSON.parse(call[1].body), options: call[1] };
}

const TEST_PARAMS = {
  plugin_version: '8.9.0',
  platform_version: '9.0',
  site_id: 'MLB',
  cust_id: 'CUST_1',
  location: 'checkout',
  platform_id: 'BP1',
};

function buildAdapter() {
  const sdk = { getSDKInstanceId: jest.fn().mockReturnValue('SDK_1') };
  return new CoreMonitorMetricsAdapter(sdk, '2.0.0', TEST_PARAMS);
}

describe('CoreMonitorMetricsAdapter', () => {
  beforeEach(() => {
    window.melidata = {};
    global.fetch = jest.fn().mockResolvedValue({});
    jest.spyOn(document, 'dispatchEvent').mockImplementation(() => true);
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete window.melidata;
  });

  it('Given a success metric, When it is sent, Then it POSTs to the Core Monitor URL with the preserved payload shape', () => {
    const adapter = buildAdapter();

    adapter.canUseSuperToken(true);

    const { url, body, options } = lastFetch();
    expect(url).toBe(`${CORE_MONITOR_URL}/can_use_super_token`);
    expect(options.method).toBe('POST');
    expect(body.value).toBe('true');
    expect(body.plugin_version).toBe('8.9.0');
    expect(body.details).toMatchObject({ site_id: 'MLB', cust_id: 'CUST_1', environment: 'prod', sdk_instance_id: 'SDK_1', js_version: '2.0.0' });
  });

  it('Given a security-code update error, When the metric is sent, Then the non-sensitive payment-method id is used as value (not its token)', () => {
    const adapter = buildAdapter();

    // token is intentionally passed alongside id to assert id takes precedence as the metric value
    adapter.errorToUpdateSecurityCode(new Error('boom'), { id: 'PM_ID_1', token: 'PM_TOKEN_1' });

    const { url, body } = lastFetch();
    expect(url).toBe(`${CORE_MONITOR_URL}/error_to_update_security_code`);
    expect(body.value).toBe('PM_ID_1');
    expect(JSON.stringify(body)).not.toContain('PM_TOKEN_1');
    expect(document.dispatchEvent).toHaveBeenCalled();
  });

  it('Given an authorized pseudotoken, When the metric is sent, Then the sensitive pseudotoken is not transmitted and the value is a non-sensitive boolean', () => {
    const adapter = buildAdapter();

    adapter.registerAuthorizedPseudotoken(true);

    const { url, body } = lastFetch();
    expect(url).toBe(`${CORE_MONITOR_URL}/authorized_pseudotoken`);
    expect(body.value).toBe('true');
    expect(body.message).toBe('input_exists:true');
  });

  it('Given an error whose message mentions email, When normalized, Then the message is scrubbed to a fixed token', () => {
    const adapter = buildAdapter();

    adapter.canUseSuperToken(false, { message: 'the email is invalid' });

    expect(lastFetch().body.message).toBe('invalid_email_address_provided');
  });

  it('Given a failed credits contract render, When reported, Then it dispatches a MeliData error event and sends the metric', () => {
    const adapter = buildAdapter();

    adapter.renderCreditsContract(false, new Error('render failed'));

    expect(document.dispatchEvent).toHaveBeenCalled();
    expect(lastFetch().url).toBe(`${CORE_MONITOR_URL}/render_credits_contract`);
    expect(lastFetch().body.value).toBe('false');
  });

  // Initialization resilience metrics (TASK-010) — names preserved 1:1 from the
  // legacy mp-super-token.js; the four check signals moved onto Core Monitor.
  describe('initialization resilience metrics', () => {
    it('Given the SDK loaded, When reported, Then it sends super_token_sdk_loaded=true', () => {
      buildAdapter().superTokenSdkLoaded();

      const { url, body } = lastFetch();
      expect(url).toBe(`${CORE_MONITOR_URL}/super_token_sdk_loaded`);
      expect(body.value).toBe('true');
    });

    it('Given an init source and elapsed time, When reported, Then value is the source and message carries elapsed_ms', () => {
      buildAdapter().reportInitSource('fallback_poll', 1200);

      const { url, body } = lastFetch();
      expect(url).toBe(`${CORE_MONITOR_URL}/super_token_init_source`);
      expect(body.value).toBe('fallback_poll');
      expect(body.message).toBe('elapsed_ms:1200');
    });

    it('Given a successful init, When reported, Then it sends SUPER_TOKEN_INITIALIZATION_SUCCESS with the origin and success event', () => {
      buildAdapter().superTokenInitializationSuccess('mp_card_form_mounted');

      const { url, body } = lastFetch();
      expect(url).toBe(`${CORE_MONITOR_URL}/SUPER_TOKEN_INITIALIZATION_SUCCESS`);
      expect(body.message).toContain('Dispatched from: mp_card_form_mounted');
      expect(body.details.event).toBe('mp_super_token_init_success');
    });

    it('Given an init error, When reported, Then it sends SUPER_TOKEN_INITIALIZATION_ERROR with the error message and error event', () => {
      buildAdapter().superTokenInitializationError(new Error('boom'), 'mp_card_form_mounted');

      const { url, body } = lastFetch();
      expect(url).toBe(`${CORE_MONITOR_URL}/SUPER_TOKEN_INITIALIZATION_ERROR`);
      expect(body.message).toContain('boom');
      expect(body.message).toContain('Dispatched from: mp_card_form_mounted');
      expect(body.details.event).toBe('mp_super_token_init_error');
    });

    it('Given missing classes, When reported, Then it sends SUPER_TOKEN_CLASSES_NOT_EXISTS with the summary and origin', () => {
      buildAdapter().superTokenClassesNotExist('Authenticator class did not load.', 'mp_card_form_mounted');

      const { url, body } = lastFetch();
      expect(url).toBe(`${CORE_MONITOR_URL}/SUPER_TOKEN_CLASSES_NOT_EXISTS`);
      expect(body.message).toBe('Authenticator class did not load. Dispatched from: mp_card_form_mounted');
      expect(body.details.event).toBe('mp_super_token_init_error');
    });

    it('Given the trigger handler is not listening, When reported, Then it sends SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING', () => {
      buildAdapter().superTokenTriggerHandlerNotListening('mp_card_form_mounted');

      expect(lastFetch().url).toBe(`${CORE_MONITOR_URL}/SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING`);
    });

    it('Given the SDK instance is missing, When reported, Then it sends MP_SDK_INSTANCE_NOT_EXISTS', () => {
      buildAdapter().mpSdkInstanceNotExists('mp_card_form_mounted');

      expect(lastFetch().url).toBe(`${CORE_MONITOR_URL}/MP_SDK_INSTANCE_NOT_EXISTS`);
    });
  });

  // Ported 1:1 from the legacy MPSuperTokenMetrics suite
  // (tests/JS/checkouts/super-token/v2.1/entities/super-token-metrics.test.js) — the
  // adapter kept the behavior but had lost this coverage. sendMetric is spied so the
  // mocked fetch only serves the cache-age HEAD/GET probes, not the metric POST.
  describe('sendStaleCacheMetrics()', () => {
    const headers = (map) => ({ get: (key) => map[key] ?? null });
    let adapter;
    let sendMetricSpy;

    beforeEach(() => {
      localStorage.clear();
      adapter = buildAdapter();
      sendMetricSpy = jest.spyOn(adapter, 'sendMetric').mockImplementation(() => {});
    });

    it('Given already checked within 24h, When called, Then it does not send any metric', async () => {
      localStorage.setItem('mp_js_cache_age_checked', String(Date.now()));

      await adapter.sendStaleCacheMetrics();

      expect(sendMetricSpy).not.toHaveBeenCalled();
    });

    it('Given last check was over 24h ago, When called, Then it sends one metric per file', async () => {
      localStorage.setItem('mp_js_cache_age_checked', String(Date.now() - 86400001));
      global.fetch = jest.fn().mockResolvedValue({
        ok: true, status: 200, headers: headers({ 'last-modified': 'Mon, 10 Mar 2026 10:00:00 GMT' }),
      });

      await adapter.sendStaleCacheMetrics();

      expect(sendMetricSpy).toHaveBeenCalledTimes(4);
    });

    it('Given only the age header (no last-modified), When called, Then it uses age as the fallback', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: true, status: 200, headers: headers({ age: '1296000' }),
      });

      await adapter.sendStaleCacheMetrics();

      expect(sendMetricSpy).toHaveBeenCalledTimes(4);
      const firstCall = sendMetricSpy.mock.calls[0];
      expect(firstCall[0]).toBe('mp_js_cache_age');
      expect(firstCall[1]).toBe('15');
      expect(firstCall[2]).toContain('file : card-form');
      expect(firstCall[2]).toContain('age_days : 15');
    });

    it('Given HEAD returns 405, When called, Then it retries with GET Range and sends the metric', async () => {
      let callCount = 0;
      global.fetch = jest.fn().mockImplementation((url, options) => {
        callCount++;
        if (options.method === 'HEAD') {
          return Promise.resolve({ status: 405, headers: headers({}) });
        }
        return Promise.resolve({ ok: true, status: 206, headers: headers({ age: '864000' }) });
      });

      await adapter.sendStaleCacheMetrics();

      expect(callCount).toBe(8);
      expect(sendMetricSpy).toHaveBeenCalledTimes(4);
      expect(sendMetricSpy.mock.calls[0][1]).toBe('10');
    });

    it('Given only a last-modified header, When called, Then it computes age_days from last-modified', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: true, status: 200, headers: headers({ 'last-modified': new Date(Date.now() - 30 * 86400000).toUTCString() }),
      });

      await adapter.sendStaleCacheMetrics();

      expect(sendMetricSpy).toHaveBeenCalledTimes(4);
      const ageDays = parseInt(sendMetricSpy.mock.calls[0][1], 10);
      expect(ageDays).toBeGreaterThanOrEqual(29);
      expect(ageDays).toBeLessThanOrEqual(31);
    });

    it('Given plugin_js_base_url is absent, When called, Then it fetches from the hardcoded fallback path', async () => {
      const capturedUrls = [];
      global.fetch = jest.fn().mockImplementation((url) => {
        capturedUrls.push(url);
        return Promise.resolve({ ok: true, status: 200, headers: headers({ age: '86400' }) });
      });

      await adapter.sendStaleCacheMetrics();

      expect(capturedUrls[0]).toContain('/wp-content/plugins/woocommerce-mercadopago/assets/js/');
      expect(capturedUrls[0]).toContain('card-form.min.js');
    });

    it('Given response is not ok (404), When called, Then it does not send any metric', async () => {
      global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 404, headers: headers({}) });

      await adapter.sendStaleCacheMetrics();

      expect(sendMetricSpy).not.toHaveBeenCalled();
    });

    it('Given no cache headers, When called, Then it does not send any metric', async () => {
      global.fetch = jest.fn().mockResolvedValue({ ok: true, status: 200, headers: headers({}) });

      await adapter.sendStaleCacheMetrics();

      expect(sendMetricSpy).not.toHaveBeenCalled();
    });

    it('Given a malformed last-modified header, When called, Then it does not send a metric with NaN', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: true, status: 200, headers: headers({ 'last-modified': 'invalid-date' }),
      });

      await adapter.sendStaleCacheMetrics();

      expect(sendMetricSpy).not.toHaveBeenCalled();
    });

    it('Given fetch throws, When called, Then it silently skips without sending a metric', async () => {
      global.fetch = jest.fn().mockRejectedValue(new Error('CORS error'));

      await adapter.sendStaleCacheMetrics();

      expect(sendMetricSpy).not.toHaveBeenCalled();
    });

    it('Given both age and last-modified headers, When called, Then last-modified takes priority over age', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: true, status: 200, headers: headers({ age: '259200', 'last-modified': new Date(Date.now() - 20 * 86400000).toUTCString() }),
      });

      await adapter.sendStaleCacheMetrics();

      expect(sendMetricSpy).toHaveBeenCalledTimes(4);
      // age header would give 3 days; last-modified gives ~20 — priority is last-modified.
      const ageDays = parseInt(sendMetricSpy.mock.calls[0][1], 10);
      expect(ageDays).toBeGreaterThanOrEqual(19);
      expect(ageDays).toBeLessThanOrEqual(21);
    });
  });

  // Contract for the ad-hoc sendMetric call sites promoted to named semantic methods. Each must
  // preserve the legacy metric name/value/message 1:1 so the Datadog dashboards keep matching.
  describe('semantic orchestration metrics — contract', () => {
    it.each([
      ['registerWithdraw', 'super_token_withdraw', 'false', ''],
      ['authExpiredOnSubmit', 'super_token_auth_expired_on_submit', 'true', ''],
      ['skippedNoEmail', 'super_token_skipped_no_email', 'true', ''],
      ['skippedInvalidEmail', 'super_token_skipped_invalid_email', 'true', ''],
      ['emailCaptured', 'super_token_email_captured', 'true', ''],
      ['resetOnAmountChange', 'super_token_reset_on_amount_change', 'true', ''],
      ['resetOnEmailChange', 'super_token_reset_on_email_change', 'true', ''],
    ])('Given %s(), When called, Then it POSTs %s with value %p and message %p', (method, name, value, message) => {
      const adapter = buildAdapter();

      adapter[method]();

      const { url, body } = lastFetch();
      expect(url).toBe(`${CORE_MONITOR_URL}/${name}`);
      expect(body.value).toBe(value);
      expect(body.message).toBe(message);
    });

    it.each([
      'super_token_restore_active_method_not_set',
      'super_token_restore_element_not_found',
      'super_token_restore_installments_dropdown_not_found',
      'super_token_restore_installment_option_not_found',
    ])('Given reportRestoreError(%p), When called, Then it POSTs that reason with the restore error message', (reason) => {
      const adapter = buildAdapter();

      adapter.reportRestoreError(reason);

      const { url, body } = lastFetch();
      expect(url).toBe(`${CORE_MONITOR_URL}/${reason}`);
      expect(body.value).toBe('true');
      expect(body.message).toBe('mp_super_token_restore_error');
    });

    it('Given customCheckoutHandlerMissingOnInstallmentValidation(), When called, Then it POSTs the legacy name/value/message 1:1', () => {
      const adapter = buildAdapter();

      adapter.customCheckoutHandlerMissingOnInstallmentValidation();

      const { url, body } = lastFetch();
      expect(url).toBe(`${CORE_MONITOR_URL}/mp_custom_checkout_handler_missing`);
      expect(body.value).toBe('installment_validation_failed');
      expect(body.message).toBe('mpCustomCheckoutHandler was undefined during installment validation cleanup');
    });

    // sendMetric stays PUBLIC: older plugin installs load the same per-variant CDN bundle and call
    // window.mpSuperTokenMetrics.sendMetric(...) directly, so the published instance must keep it.
    it('Given sendMetric() called directly (legacy compatibility surface), When invoked, Then it POSTs the raw metric', () => {
      const adapter = buildAdapter();

      adapter.sendMetric('some_external_metric', 'a_value', 'a_message');

      const { url, body } = lastFetch();
      expect(url).toBe(`${CORE_MONITOR_URL}/some_external_metric`);
      expect(body.value).toBe('a_value');
      expect(body.message).toBe('a_message');
    });
  });

  // ab_variant is read from the mp_st_variant cookie the loader sets, so every metric can be
  // filtered by A/B experiment in Datadog (parity with the legacy MPSuperTokenMetrics payload).
  describe('ab_variant in the payload — contract', () => {
    const clearVariantCookie = () => {
      document.cookie = 'mp_st_variant=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    };

    beforeEach(clearVariantCookie);
    afterEach(clearVariantCookie);

    it.each([
      ['v2', 'v2'],
      ['v2.1', 'v2.1'],
    ])('Given the mp_st_variant cookie is %p, When a metric is sent, Then details.ab_variant is %p', (cookieValue, expected) => {
      document.cookie = `mp_st_variant=${cookieValue}; path=/`;
      const adapter = buildAdapter();

      adapter.canUseSuperToken(true);

      expect(lastFetch().body.details.ab_variant).toBe(expected);
    });

    it('Given no mp_st_variant cookie, When a metric is sent, Then details.ab_variant falls back to "unknown"', () => {
      const adapter = buildAdapter();

      adapter.canUseSuperToken(true);

      expect(lastFetch().body.details.ab_variant).toBe('unknown');
    });

    it('Given an unexpected mp_st_variant value, When a metric is sent, Then details.ab_variant is "unknown"', () => {
      document.cookie = 'mp_st_variant=v3; path=/';
      const adapter = buildAdapter();

      adapter.canUseSuperToken(true);

      expect(lastFetch().body.details.ab_variant).toBe('unknown');
    });
  });
});
