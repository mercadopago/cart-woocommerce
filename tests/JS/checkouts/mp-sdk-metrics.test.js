const { resolveAlias } = require('../helpers/path-resolver');
const { loadFile } = require('../helpers/load-file');

const MP_SDK_METRICS_PATH = resolveAlias('assets/js/checkouts/mp-sdk-metrics.js');

function loadCallSdkWithMetrics(sendMetricMock) {
  return loadFile(MP_SDK_METRICS_PATH, 'callSdkWithMetrics', {
    window: { sendMetric: sendMetricMock },
  });
}

function loadCallSdkWithMetricsWithoutSendMetric() {
  // Carrega o wrapper com window.sendMetric ausente — simula falha de carregamento
  // do mp-checkout-metrics.js (ad-blocker, ordering issue).
  return loadFile(MP_SDK_METRICS_PATH, 'callSdkWithMetrics', {
    window: {},
  });
}

describe('callSdkWithMetrics — wrapper para chamadas ao SDK JS do Mercado Pago', () => {
  let sendMetricMock;
  let callSdkWithMetrics;

  beforeEach(() => {
    sendMetricMock = jest.fn();
    callSdkWithMetrics = loadCallSdkWithMetrics(sendMetricMock);
  });

  // ---------------------------------------------------------------------------
  // Caso 1: sucesso — não envia métrica, retorna valor resolvido
  // ---------------------------------------------------------------------------
  describe('caminho feliz', () => {
    test('TC-CSM-01: não chama sendMetric e retorna valor resolvido', async () => {
      const sdkCall = jest.fn().mockResolvedValue({ token: 'abc123' });

      const result = await callSdkWithMetrics(sdkCall, 'createCardToken');

      expect(result).toEqual({ token: 'abc123' });
      expect(sendMetricMock).not.toHaveBeenCalled();
      expect(sdkCall).toHaveBeenCalledTimes(1);
    });
  });

  // ---------------------------------------------------------------------------
  // Caso 2-4: extração do `value` (HTTP status)
  // ---------------------------------------------------------------------------
  describe('extração do value', () => {
    test('TC-CSM-02: error.status presente → value = String(status)', async () => {
      const error = { status: 400, message: 'Bad Request' };
      const sdkCall = jest.fn().mockRejectedValue(error);

      await callSdkWithMetrics(sdkCall, 'createCardToken').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        '400',
        expect.any(String),
        'mp_api_error',
        expect.any(Object)
      );
    });

    test('TC-CSM-03: erro sem status → value = "0"', async () => {
      const error = { message: 'Some error' };
      const sdkCall = jest.fn().mockRejectedValue(error);

      await callSdkWithMetrics(sdkCall, 'createCardToken').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        '0',
        expect.any(String),
        'mp_api_error',
        expect.any(Object)
      );
    });

    test('TC-CSM-04: error.status === 0 explicitamente → value = "0"', async () => {
      const error = { status: 0, message: 'Network error' };
      const sdkCall = jest.fn().mockRejectedValue(error);

      await callSdkWithMetrics(sdkCall, 'createCardToken').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        '0',
        expect.any(String),
        'mp_api_error',
        expect.any(Object)
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Caso 5-9: cadeia de fallback do `message`
  // Ordem esperada: typeof string → error.message → cause[0].description → 'Unknown SDK error'
  // ---------------------------------------------------------------------------
  describe('cadeia de fallback do message', () => {
    test('TC-CSM-05: erro como string literal → usa a própria string (cenário offline T00)', async () => {
      const error = 'Failed to fetch';
      const sdkCall = jest.fn().mockRejectedValue(error);

      await callSdkWithMetrics(sdkCall, 'createCardToken').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        '0',
        'Failed to fetch',
        'mp_api_error',
        expect.any(Object)
      );
    });

    test('TC-CSM-06: erro com .message → usa .message', async () => {
      const error = { message: 'API error', status: 500 };
      const sdkCall = jest.fn().mockRejectedValue(error);

      await callSdkWithMetrics(sdkCall, 'authorizePayment').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        '500',
        'API error',
        'mp_api_error',
        expect.any(Object)
      );
    });

    test('TC-CSM-07: erro {cause: [{description}]} sem .message → usa cause[0].description', async () => {
      const error = {
        cause: [{ code: 'E501', description: 'not found public_key' }],
        status: 404,
      };
      const sdkCall = jest.fn().mockRejectedValue(error);

      await callSdkWithMetrics(sdkCall, 'createCardToken').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        '404',
        'not found public_key',
        'mp_api_error',
        expect.any(Object)
      );
    });

    test('TC-CSM-08: erro sem message nem cause → "Unknown SDK error"', async () => {
      const error = { status: 500 };
      const sdkCall = jest.fn().mockRejectedValue(error);

      await callSdkWithMetrics(sdkCall, 'createCardToken').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        '500',
        'Unknown SDK error',
        'mp_api_error',
        expect.any(Object)
      );
    });

    test('TC-CSM-09: erro null/undefined → "Unknown SDK error"', async () => {
      const sdkCall = jest.fn().mockRejectedValue(null);

      await callSdkWithMetrics(sdkCall, 'createCardToken').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        '0',
        'Unknown SDK error',
        'mp_api_error',
        expect.any(Object)
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Caso 10-11: priorização da cadeia de fallback
  // ---------------------------------------------------------------------------
  describe('priorização da cadeia de fallback', () => {
    test('TC-CSM-10: string tem prioridade sobre tudo (offline mode T00)', async () => {
      // Caso edge: string capturada não tem como ter .message ou .cause,
      // mas garantimos que o branch de string sempre é avaliado primeiro.
      const sdkCall = jest.fn().mockRejectedValue('Failed to fetch');

      await callSdkWithMetrics(sdkCall, 'method').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        expect.any(String),
        'Failed to fetch',
        expect.any(String),
        expect.any(Object)
      );
    });

    test('TC-CSM-11: error.message tem prioridade sobre cause[0].description', async () => {
      const error = {
        message: 'top-level message',
        cause: [{ description: 'cause description' }],
      };
      const sdkCall = jest.fn().mockRejectedValue(error);

      await callSdkWithMetrics(sdkCall, 'method').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        expect.any(String),
        'top-level message',
        expect.any(String),
        expect.any(Object)
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Caso 12-13: re-throw do erro original
  // ---------------------------------------------------------------------------
  describe('re-throw do erro original', () => {
    test('TC-CSM-12: erro objeto é propagado preservando referência', async () => {
      const error = { custom: 'data', message: 'msg', status: 418 };
      const sdkCall = jest.fn().mockRejectedValue(error);

      await expect(
        callSdkWithMetrics(sdkCall, 'createCardToken')
      ).rejects.toBe(error); // .toBe — referência exata, não cópia
    });

    test('TC-CSM-13: erro string é propagado preservando valor', async () => {
      const sdkCall = jest.fn().mockRejectedValue('Failed to fetch');

      await expect(
        callSdkWithMetrics(sdkCall, 'createCardToken')
      ).rejects.toBe('Failed to fetch');
    });
  });

  // ---------------------------------------------------------------------------
  // Caso 14-15: details (apenas api_route)
  // ---------------------------------------------------------------------------
  describe('payload de details', () => {
    test('TC-CSM-14: details contém apenas api_route', async () => {
      const sdkCall = jest.fn().mockRejectedValue({ message: 'err' });

      await callSdkWithMetrics(sdkCall, 'yape.create').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        expect.any(String),
        expect.any(String),
        'mp_api_error',
        { api_route: 'yape.create' }
      );
    });

    test('TC-CSM-15: api_route reflete o sdkMethod passado', async () => {
      const sdkCall = jest.fn().mockRejectedValue({ message: 'err' });

      await callSdkWithMetrics(sdkCall, 'authorizePayment').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        expect.any(String),
        expect.any(String),
        expect.any(String),
        expect.objectContaining({ api_route: 'authorizePayment' })
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Caso 16: target sempre é mp_api_error (alinhamento com backend)
  // ---------------------------------------------------------------------------
  describe('target', () => {
    test('TC-CSM-16: target sempre é "mp_api_error"', async () => {
      const sdkCall = jest.fn().mockRejectedValue({ message: 'err' });

      await callSdkWithMetrics(sdkCall, 'method').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        expect.any(String),
        expect.any(String),
        'mp_api_error',
        expect.any(Object)
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Caso 17: defensive guard quando window.sendMetric não está disponível
  // ---------------------------------------------------------------------------
  describe('defensive guard for window.sendMetric', () => {
    test('TC-CSM-17: window.sendMetric ausente → não lança ReferenceError, apenas re-throw do erro original', async () => {
      // Simula cenário onde mp-checkout-metrics.js falhou ao carregar
      const callSdkWithMetricsNoSendMetric = loadCallSdkWithMetricsWithoutSendMetric();
      const originalError = { message: 'real SDK error', status: 500 };
      const sdkCall = jest.fn().mockRejectedValue(originalError);

      await expect(
        callSdkWithMetricsNoSendMetric(sdkCall, 'createCardToken')
      ).rejects.toBe(originalError); // erro ORIGINAL é propagado, não ReferenceError
    });
  });
});
