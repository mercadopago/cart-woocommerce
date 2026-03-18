function sendMetric(value, message, target) {
  const storeParams = wc_mercadopago_checkout_metrics_params;
  const url = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/' + target;
  const payload = {
    value: `${value}`,
    message: `${message}`,
    plugin_version: storeParams.plugin_version,
    platform: {
      name: 'woocommerce',
      uri: `${window.location.origin}${storeParams.location}_${storeParams.theme}_${storeParams.site_id}_${storeParams.currency}`,
      version: storeParams.platform_version,
      url: window.location.href,
    },
    "details": {
      site_id: storeParams.site_id,
      environment: 'prod',
      sdk_instance_id: window.sessionStorage.getItem('_mp_flow_id') || 'not_available',
      cust_id: storeParams.cust_id,
    }
  };

  navigator.sendBeacon(url, JSON.stringify(payload));
}
