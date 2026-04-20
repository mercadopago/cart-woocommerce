const fs = require('fs');
const vm = require('vm');
const { resolveAlias } = require('../helpers/path-resolver');

const HEALTH_MONITOR_PATH = resolveAlias('assets/js/health/mp-health-monitor.js');

/**
 * Execute the IIFE in a VM context sharing the jsdom globals.
 * This registers the DOMContentLoaded listener on the real document.
 */
function loadHealthMonitor() {
  const code = fs.readFileSync(HEALTH_MONITOR_PATH, 'utf8');
  const script = new vm.Script(code);
  script.runInNewContext({
    window: global.window,
    document: global.document,
    navigator: global.navigator,
    sessionStorage: global.sessionStorage,
    console: global.console,
    setTimeout: global.setTimeout,
    wc_mercadopago_health_monitor_params: global.wc_mercadopago_health_monitor_params,
  });
}

/**
 * Dispatch DOMContentLoaded and fast-forward the 3 s setTimeout used
 * to let MeliData load before running checks.
 */
function triggerChecks() {
  document.dispatchEvent(new Event('DOMContentLoaded'));
  jest.runAllTimers();
}

describe('mp-health-monitor', () => {
  beforeEach(() => {
    global.wc_mercadopago_health_monitor_params = {
      plugin_version: '7.0.0',
      theme: 'storefront',
      platform_version: '8.0.0',
      site_id: 'MLB',
      cust_id: 'cust-123',
      is_test: false,
      is_checkout: true,
    };

    if (!global.navigator.sendBeacon) {
      Object.defineProperty(global.navigator, 'sendBeacon', {
        value: jest.fn(),
        writable: true,
        configurable: true,
      });
    }

    jest.useFakeTimers();
    jest.clearAllMocks();
    global.navigator.sendBeacon = jest.fn();
    sessionStorage.clear();
    document.body.innerHTML = '';

    // Reset monitored globals on window (the context used by the script)
    delete window.wc_mercadopago_checkout_session_data_register_params;
    delete window.wc_mercadopago_supertoken_bundle_params;
    delete window.mercadopago_melidata_params;
    delete window.MercadoPago;
    delete window.MelidataClient;
    delete window.melidata;

    // Load the script fresh for each test so the listener binds to the current document
    loadHealthMonitor();
  });

  afterEach(() => {
    jest.useRealTimers();
    document.body.innerHTML = '';
  });

  // ---------------------------------------------------------------------------
  // Grupo A — checkCssConflicts
  // ---------------------------------------------------------------------------

  describe('checkCssConflicts()', () => {
    test('TC-CSS-01: sem elemento crítico no DOM, não envia métrica de CSS', () => {
      // DOM vazio — nenhum dos seletores existe; globals presentes para isolar checkCssConflicts
      window.wc_mercadopago_checkout_session_data_register_params = { loaded: true };
      window.wc_mercadopago_supertoken_bundle_params = { loaded: true };
      window.mercadopago_melidata_params = { loaded: true };
      window.MercadoPago = {};
      window.MelidataClient = {};
      window.melidata = {};

      triggerChecks();

      const cssCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_css_conflict_detected')
      );
      expect(cssCalls).toHaveLength(0);
    });

    test('TC-CSS-02: elemento presente sem customizações externas, não envia métrica', () => {
      document.body.innerHTML = `
        <div class="mp-wallet-button-container"></div>
      `;

      triggerChecks();

      const cssCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_css_conflict_detected')
      );
      expect(cssCalls).toHaveLength(0);
    });

    test('TC-CSS-03: checkCssConflicts roda sem lançar exceção quando elemento presente no DOM (jsdom)', () => {
      // jsdom não processa CSS real — getComputedStyle retorna valores vazios independente
      // de qualquer stylesheet, então elementsWithCustomizations nunca detecta anomalias
      // neste ambiente. O teste garante que o fluxo completo roda sem exceção e que
      // nenhuma métrica é enviada sem conflito real. O path de detecção real é coberto
      // pelos testes E2E (I-04) em browser Chromium.
      document.body.innerHTML = `
        <div class="mp-wallet-button-container" style="display:none"></div>
      `;

      window.wc_mercadopago_checkout_session_data_register_params = { loaded: true };
      window.mercadopago_melidata_params = { loaded: true };
      window.MelidataClient = {};
      window.melidata = {};

      expect(() => triggerChecks()).not.toThrow();

      const cssCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_css_conflict_detected')
      );
      // jsdom não detecta conflito — nenhuma métrica deve ser enviada
      expect(cssCalls).toHaveLength(0);
    });

    test('TC-CSS-05: sessionStorage previne segunda métrica de CSS na mesma sessão', () => {
      sessionStorage.setItem('mp_health_css_conflict_wallet_sent', '1');
      sessionStorage.setItem('mp_health_script_globals_sent', '1');
      document.body.innerHTML = `
        <div class="mp-wallet-button-container"></div>
      `;

      triggerChecks();

      expect(global.navigator.sendBeacon).not.toHaveBeenCalled();
    });

    test('TC-CSS-06: listener supertoken_loaded executa checkCssConflicts com seletor do Super Token', () => {
      // Arrange — element present so querySelector is exercised
      document.body.innerHTML = '<div class="mp-super-token-payment-methods-list"></div>';
      const querySpy = jest.spyOn(document, 'querySelector');

      // Act — dispatch the event that super-token-payment-methods.js fires after render
      document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId: 'test-sdk' } }));

      // Assert — checkCssConflicts must have queried the super token selector
      expect(querySpy).toHaveBeenCalledWith('.mp-super-token-payment-methods-list');
      querySpy.mockRestore();
    });

    test('TC-CSS-07: rate-limit sessionStorage é respeitado pelo listener supertoken_loaded', () => {
      // Arrange — rate-limit already active
      sessionStorage.setItem('mp_health_css_conflict_supertoken_sent', '1');
      const querySpy = jest.spyOn(document, 'querySelector');

      // Act
      document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId: 'test-sdk' } }));

      // Assert — early return before querySelector because sessionStorage flag is set
      expect(querySpy).not.toHaveBeenCalledWith('.mp-super-token-payment-methods-list');
      // Assert — no metric dispatched (rate-limit blocked the entire check)
      expect(global.navigator.sendBeacon).not.toHaveBeenCalled();
      querySpy.mockRestore();
    });

    test('TC-CSS-08: listener supertoken_loaded executa checkCssConflicts apenas uma vez mesmo com múltiplos disparos', () => {
      // Arrange — no session flag, no element in DOM so no anomalies and no flag set after first call
      sessionStorage.removeItem('mp_health_css_conflict_supertoken_sent');
      document.body.innerHTML = '';
      const querySpy = jest.spyOn(document, 'querySelector');

      // Act — fire the event twice; without {once: true} querySelector would be called twice
      document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId: 'test-sdk' } }));
      document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId: 'test-sdk' } }));

      // Assert — querySelector called at most once for the super token selector
      const calls = querySpy.mock.calls.filter(([arg]) => arg === '.mp-super-token-payment-methods-list');
      expect(calls).toHaveLength(1);
      querySpy.mockRestore();
    });
  });

  // ---------------------------------------------------------------------------
  // Grupo B — checkScriptGlobals
  // ---------------------------------------------------------------------------

  describe('checkScriptGlobals()', () => {
    test('TC-SG-01: is_checkout=false, check é ignorado', () => {
      // Temporarily override is_checkout to false
      const original = global.wc_mercadopago_health_monitor_params.is_checkout;
      global.wc_mercadopago_health_monitor_params.is_checkout = false;

      delete global.wc_mercadopago_checkout_session_data_register_params;

      triggerChecks();

      const scriptCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_script_missing_globals')
      );
      expect(scriptCalls).toHaveLength(0);

      global.wc_mercadopago_health_monitor_params.is_checkout = original;
    });

    test('TC-SG-02: is_checkout=true e todas as globals definidas, não envia métrica', () => {
      window.wc_mercadopago_checkout_session_data_register_params = { loaded: true };
      window.wc_mercadopago_supertoken_bundle_params = { loaded: true };
      window.mercadopago_melidata_params = { loaded: true };
      window.MercadoPago = {};
      window.MelidataClient = {};
      window.melidata = {};

      triggerChecks();

      const scriptCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_script_missing_globals')
      );
      expect(scriptCalls).toHaveLength(0);
    });

    test('TC-SG-03: global ausente com is_checkout=true envia mp_script_missing_globals', () => {
      delete global.wc_mercadopago_checkout_session_data_register_params;

      triggerChecks();

      const scriptCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_script_missing_globals')
      );
      expect(scriptCalls).toHaveLength(1);

      const [, body] = scriptCalls[0];
      const payload = JSON.parse(body);
      expect(payload.value).toBe('true');
      expect(payload.message).toContain('session_data_register');
    });

    test('TC-SG-04: sessionStorage previne segunda métrica de globals na mesma sessão', () => {
      sessionStorage.setItem('mp_health_script_globals_sent', '1');
      delete global.wc_mercadopago_checkout_session_data_register_params;

      triggerChecks();

      const scriptCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_script_missing_globals')
      );
      expect(scriptCalls).toHaveLength(0);
    });

    test('TC-SG-05a: supertoken global ausente envia mp_script_missing_globals quando payment_methods inclui custom', () => {
      global.wc_mercadopago_health_monitor_params.payment_methods = ['woo-mercado-pago-custom'];
      loadHealthMonitor();

      window.wc_mercadopago_checkout_session_data_register_params = { loaded: true };
      window.mercadopago_melidata_params = { loaded: true };
      window.MercadoPago = {};
      window.MelidataClient = {};
      window.melidata = {};
      delete window.wc_mercadopago_supertoken_bundle_params;

      triggerChecks();

      const scriptCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_script_missing_globals')
      );
      expect(scriptCalls).toHaveLength(1);

      const [, body] = scriptCalls[0];
      const payload = JSON.parse(body);
      expect(payload.message).toContain('supertoken_bundle');
    });

    test('TC-SG-05b: supertoken global ausente não envia métrica quando payment_methods não inclui custom', () => {
      window.wc_mercadopago_checkout_session_data_register_params = { loaded: true };
      window.mercadopago_melidata_params = { loaded: true };
      window.MercadoPago = {};
      window.MelidataClient = {};
      window.melidata = {};
      delete window.wc_mercadopago_supertoken_bundle_params;

      triggerChecks();

      const scriptCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_script_missing_globals')
      );
      expect(scriptCalls).toHaveLength(0);
    });

    test('TC-SG-06: sessionStorage é gravado após envio da métrica de globals', () => {
      delete global.wc_mercadopago_checkout_session_data_register_params;

      triggerChecks();

      expect(sessionStorage.getItem('mp_health_script_globals_sent')).toBe('1');
    });
  });

  // ---------------------------------------------------------------------------
  // Grupo C — Resiliência e payload
  // ---------------------------------------------------------------------------

  describe('Resiliência e payload', () => {
    test('TC-RES-01: erro no setup do listener não propaga — try/catch externo absorve e envia mp_health_monitor_error', () => {
      // Sobrescrever document.addEventListener para lançar durante o setup da IIFE.
      // O try/catch externo deve absorver o erro e emitir mp_health_monitor_error.
      const original = window.document.addEventListener.bind(window.document);
      window.document.addEventListener = () => { throw new Error('forced setup error'); };

      expect(() => loadHealthMonitor()).not.toThrow();

      const errorCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_health_monitor_error')
      );
      expect(errorCalls).toHaveLength(1);
      const payload = JSON.parse(errorCalls[0][1]);
      expect(payload.value).toBe('error');
      expect(payload.message).toBe('forced setup error');

      window.document.addEventListener = original;
    });

    test('TC-RES-02: readyState já complete quando script carrega — scripts() chamado imediatamente', () => {
      // Simula página já carregada (readyState=complete): scripts() deve ser chamado
      // diretamente sem aguardar DOMContentLoaded.
      Object.defineProperty(document, 'readyState', { value: 'complete', configurable: true });

      window.wc_mercadopago_checkout_session_data_register_params = { loaded: true };
      window.mercadopago_melidata_params = { loaded: true };
      window.MelidataClient = {};
      window.melidata = {};

      loadHealthMonitor();
      jest.runAllTimers();

      // O setTimeout interno de 3s deve ter rodado — nenhuma métrica de erro enviada
      const errorCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_health_monitor_error') || url.includes('mp_health_check_error')
      );
      expect(errorCalls).toHaveLength(0);

      Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });
    });

    test('TC-PAY-01: payload contém todos os campos obrigatórios', () => {
      delete global.wc_mercadopago_checkout_session_data_register_params;

      triggerChecks();

      const scriptCalls = global.navigator.sendBeacon.mock.calls.filter(([url]) =>
        url.includes('mp_script_missing_globals')
      );
      expect(scriptCalls.length).toBeGreaterThan(0);

      const [, body] = scriptCalls[0];
      const payload = JSON.parse(body);

      expect(payload.value).toBe('true');
      expect(payload.plugin_version).toBe('7.0.0');
      expect(payload.platform.name).toBe('woocommerce');
      expect(payload.platform.uri).toBe('storefront');
      expect(payload.platform.version).toBe('8.0.0');
      expect(typeof payload.platform.url).toBe('string');
      expect(payload.details.site_id).toBe('MLB');
      expect(payload.details.cust_id).toBe('cust-123');
      expect(payload.details.environment).toBe('prod');
      expect(typeof payload.details.sdk_instance_id).toBe('string');
    });
  });
});
