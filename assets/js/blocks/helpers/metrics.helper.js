const sendMetric = (name, message, target) => {
  const url = 'https://api.mercadopago.com/v1/plugins/melidata/errors';
  const payload = {
    name,
    message,
    target: target,
    plugin: {
      version: wc_mercadopago_custom_checkout_params.plugin_version,
    },
    platform: {
      name: 'woocommerce',
      uri: window.location.href,
      version: wc_mercadopago_custom_checkout_params.platform_version,
      location: `${wc_mercadopago_custom_checkout_params.location}_${wc_mercadopago_custom_checkout_params.theme}`,
    },
  };

  navigator.sendBeacon(url, JSON.stringify(payload));
}

export default sendMetric;
