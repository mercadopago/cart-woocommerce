/**
 * Wrapper para chamadas ao SDK JS do Mercado Pago.
 * Captura falhas e envia métrica mp_api_error ao Datadog (via sendMetric),
 * mantendo o re-throw para que .catch() existentes continuem funcionando.
 *
 * Shape do erro varia conforme origem da falha:
 *   - Falhas de rede (offline/timeout): string literal (ex: "Failed to fetch")
 *   - Falhas de API (4xx/5xx): objeto { message, status, cause, error, ok }
 *
 * @param {Function} sdkCall - Função que invoca o método do SDK (retorna Promise).
 * @param {string} sdkMethod - Nome do método ("createCardToken", "yape.create", etc.) — usado como api_route.
 * @returns {Promise} Resultado da chamada ao SDK (re-throw em caso de erro).
 */
async function callSdkWithMetrics(sdkCall, sdkMethod) {
    try {
        return await sdkCall();
    } catch (error) {
        const status = String(error?.status ?? 0);
        const message =
            (typeof error === 'string' ? error : null) ||
            error?.message ||
            error?.cause?.[0]?.description ||
            'Unknown SDK error';

        // Defensive guard: if mp-checkout-metrics.js failed to load (ad-blocker,
        // enqueue order issue), accessing sendMetric directly would throw a
        // ReferenceError before the re-throw below, masking the original SDK
        // error and breaking checkout UI recovery (Error Cascade Prevention).
        if (typeof window.sendMetric === 'function') {
            window.sendMetric(status, message, 'mp_api_error', {
                api_route: sdkMethod,
            });
        }

        throw error;
    }
}

window.callSdkWithMetrics = callSdkWithMetrics;
