const { resolveAlias } = require('../helpers/path-resolver');
const { loadFile } = require('../helpers/load-file');

const MP_CHECKOUT_METRICS_PATH = resolveAlias('assets/js/checkouts/mp-checkout-metrics.js');

const STORE_PARAMS = {
  plugin_version: '8.7.19',
  location: '/checkout',
  theme: 'storefront',
  site_id: 'MLB',
  currency: 'BRL',
  platform_version: '10.6.2',
  cust_id: 'cust-12345',
};

function loadSendMetric(beaconMock, sessionStorageReturn = 'flow-uuid-test', params = STORE_PARAMS) {
  return loadFile(MP_CHECKOUT_METRICS_PATH, 'sendMetric', {
    wc_mercadopago_checkout_metrics_params: params,
    navigator: { sendBeacon: beaconMock },
    window: {
      location: {
        origin: 'https://example.com',
        href: 'https://example.com/checkout',
      },
      sessionStorage: {
        getItem: jest.fn().mockReturnValue(sessionStorageReturn),
      },
    },
  });
}

describe('sendMetric — estendido com extraDetails (RFC v1.5.0)', () => {
  let beaconMock;
  let sendMetric;

  beforeEach(() => {
    beaconMock = jest.fn();
    sendMetric = loadSendMetric(beaconMock);
  });

  function getPayload() {
    expect(beaconMock).toHaveBeenCalledTimes(1);
    const [, body] = beaconMock.mock.calls[0];
    return JSON.parse(body);
  }

  function getUrl() {
    expect(beaconMock).toHaveBeenCalledTimes(1);
    return beaconMock.mock.calls[0][0];
  }

  // ---------------------------------------------------------------------------
  // Backward compatibility — chamadas com 3 args (call sites legados)
  // ---------------------------------------------------------------------------
  describe('backward compatibility (3 args)', () => {
    test('TC-SM-01: 3 args produzem payload com baseline details apenas', () => {
      sendMetric('MP_TEST', 'message', 'target_test');

      const payload = getPayload();
      expect(payload.details).toEqual({
        site_id: 'MLB',
        environment: 'prod',
        sdk_instance_id: 'flow-uuid-test',
        cust_id: 'cust-12345',
      });
    });

    test('TC-SM-02: URL contém o target correto no path', () => {
      sendMetric('TEST', 'message', 'mp_api_error');

      expect(getUrl()).toBe(
        'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/mp_api_error'
      );
    });

    test('TC-SM-03: value e message são coercidos para string', () => {
      sendMetric(404, 42, 'target');

      const payload = getPayload();
      expect(payload.value).toBe('404');
      expect(payload.message).toBe('42');
    });

    test('TC-SM-04: omitir extraDetails (3 args) é equivalente a passar {} (4 args)', () => {
      sendMetric('TEST', 'msg', 'target');
      const payloadThreeArgs = getPayload();

      beaconMock.mockClear();
      sendMetric('TEST', 'msg', 'target', {});
      const payloadFourArgsEmpty = getPayload();

      expect(payloadThreeArgs).toEqual(payloadFourArgsEmpty);
    });
  });

  // ---------------------------------------------------------------------------
  // 4 args — extraDetails injetam campos no details
  // ---------------------------------------------------------------------------
  describe('4 args com extraDetails (mp_api_error use case)', () => {
    test('TC-SM-05: extraDetails são adicionados ao details junto com baseline', () => {
      sendMetric('0', 'msg', 'mp_api_error', {
        api_route: 'createCardToken',
        payment_method: 'custom',
      });

      const payload = getPayload();
      expect(payload.details).toEqual({
        api_route: 'createCardToken',
        payment_method: 'custom',
        site_id: 'MLB',
        environment: 'prod',
        sdk_instance_id: 'flow-uuid-test',
        cust_id: 'cust-12345',
      });
    });

    test('TC-SM-06: extraDetails com múltiplos campos preservam todos', () => {
      sendMetric('400', 'err', 'mp_api_error', {
        api_route: 'authorizePayment',
        payment_method: 'supertoken',
        custom_field: 'extra-value',
      });

      const payload = getPayload();
      expect(payload.details).toMatchObject({
        api_route: 'authorizePayment',
        payment_method: 'supertoken',
        custom_field: 'extra-value',
      });
    });
  });

  // ---------------------------------------------------------------------------
  // Proteção do baseline — spread coloca baseline POR ÚLTIMO
  // RFC v1.5.0: extraDetails NUNCA pode corromper site_id/environment/sdk_instance_id/cust_id
  // ---------------------------------------------------------------------------
  describe('proteção do baseline contra colisão com extraDetails', () => {
    test('TC-SM-07: tentativa de sobrescrever site_id é ignorada', () => {
      sendMetric('TEST', 'msg', 'target', { site_id: 'INJECTED' });

      const payload = getPayload();
      expect(payload.details.site_id).toBe('MLB');
    });

    test('TC-SM-08: tentativa de sobrescrever cust_id é ignorada', () => {
      sendMetric('TEST', 'msg', 'target', { cust_id: 'INJECTED' });

      const payload = getPayload();
      expect(payload.details.cust_id).toBe('cust-12345');
    });

    test('TC-SM-09: tentativa de sobrescrever environment é ignorada', () => {
      sendMetric('TEST', 'msg', 'target', { environment: 'staging' });

      const payload = getPayload();
      expect(payload.details.environment).toBe('prod');
    });

    test('TC-SM-10: tentativa de sobrescrever sdk_instance_id é ignorada', () => {
      sendMetric('TEST', 'msg', 'target', { sdk_instance_id: 'fake-id' });

      const payload = getPayload();
      expect(payload.details.sdk_instance_id).toBe('flow-uuid-test');
    });

    test('TC-SM-11: campos legítimos passam, baseline prevalece em colisões', () => {
      sendMetric('TEST', 'msg', 'target', {
        api_route: 'method',
        site_id: 'INJECTED',
        custom: 'allowed',
      });

      const payload = getPayload();
      expect(payload.details).toEqual({
        api_route: 'method',
        custom: 'allowed',
        site_id: 'MLB',
        environment: 'prod',
        sdk_instance_id: 'flow-uuid-test',
        cust_id: 'cust-12345',
      });
    });
  });

  // ---------------------------------------------------------------------------
  // Estrutura geral do payload
  // ---------------------------------------------------------------------------
  describe('estrutura do payload', () => {
    test('TC-SM-12: payload contém todos os campos top-level esperados', () => {
      sendMetric('500', 'err', 'target');

      const payload = getPayload();
      expect(payload).toMatchObject({
        value: '500',
        message: 'err',
        plugin_version: '8.7.19',
        platform: {
          name: 'woocommerce',
          version: '10.6.2',
          url: 'https://example.com/checkout',
        },
      });
      expect(payload.details).toBeDefined();
    });

    test('TC-SM-13: platform.uri concatena origin + location_theme_site_currency', () => {
      sendMetric('TEST', 'msg', 'target');

      const payload = getPayload();
      expect(payload.platform.uri).toBe(
        'https://example.com/checkout_storefront_MLB_BRL'
      );
    });
  });

  // ---------------------------------------------------------------------------
  // sdk_instance_id — fallback quando sessionStorage não tem o valor
  // ---------------------------------------------------------------------------
  describe('sdk_instance_id', () => {
    test('TC-SM-14: sessionStorage retornando null → "not_available"', () => {
      sendMetric = loadSendMetric(beaconMock, null);

      sendMetric('TEST', 'msg', 'target');

      const payload = getPayload();
      expect(payload.details.sdk_instance_id).toBe('not_available');
    });

    test('TC-SM-15: sessionStorage retornando string vazia → "not_available"', () => {
      sendMetric = loadSendMetric(beaconMock, '');

      sendMetric('TEST', 'msg', 'target');

      const payload = getPayload();
      expect(payload.details.sdk_instance_id).toBe('not_available');
    });
  });
});
