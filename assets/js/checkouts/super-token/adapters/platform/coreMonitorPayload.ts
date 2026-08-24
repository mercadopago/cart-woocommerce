/**
 * Shared contract for the Core Monitor POST payload.
 *
 * Both `CoreMonitorMetricsAdapter` and `VariantConfigAdapter` send metrics to
 * the same endpoint (`CORE_MONITOR_URL/{metricName}`) with the same schema.
 * Sharing the interface and the send function ensures the two callers never
 * diverge on structure or transport.
 */

export const CORE_MONITOR_URL =
  'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';

export interface CoreMonitorDetails {
  site_id: string;
  environment: string;
  cust_id: string;
  sdk_instance_id?: string;
  js_version?: string | null;
  ab_variant?: string;
  event?: string;
}

export interface CoreMonitorPayload {
  value: string;
  message: string;
  plugin_version: string;
  platform: {
    name: string;
    uri: string;
    version: string;
    url: string;
  };
  details: CoreMonitorDetails;
}

/**
 * Send a metric to Core Monitor. Prefers `fetch` (used by the Super Token metrics
 * adapter which needs to wait for the response); falls back to `sendBeacon`
 * (fire-and-forget, used by the variant config adapter for A/B telemetry).
 */
export function sendToCoreMonitor(
  metricName: string,
  payload: CoreMonitorPayload,
  transport: 'fetch' | 'beacon' = 'fetch',
): void {
  const url = `${CORE_MONITOR_URL}/${metricName}`;
  const body = JSON.stringify(payload);

  try {
    if (transport === 'beacon') {
      if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
        // Wrap in a Blob so the request carries application/json; a raw string
        // makes sendBeacon send text/plain, which the Core Monitor endpoint rejects.
        navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
        return;
      }
    }

    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body,
      keepalive: transport === 'beacon',
    }).catch(() => {
      // best-effort telemetry — never throw
    });
  } catch {
    // Intentionally swallow telemetry errors to avoid breaking checkout flow.
  }
}
