function sendMetric(name, message, target) {
    const storeParams = wc_mercadopago_checkout_metrics_params;
    const url = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/' + target;
    const payload = {
        value: name,
        message,
        target: target,
        plugin_version: storeParams.plugin_version,
        platform: {
            name: 'woocommerce',
            uri: window.location.href,
            version: storeParams.platform_version,
            location: `${storeParams.location}_${storeParams.theme}_${storeParams.site_id}_${storeParams.currency}`,
        },
    };

    navigator.sendBeacon(url, JSON.stringify(payload));
}
