/* globals MelidataClient, mercadopago_melidata_params */
(function () {
  window.addEventListener('load', function () {
    window.melidata = null;

    try {
      const scriptTag = document.createElement('script');

      scriptTag.setAttribute('id', 'melidata_woocommerce_client');
      scriptTag.src = 'https://http2.mlstatic.com/storage/v1/plugins/melidata/woocommerce.min.js';
      scriptTag.async = true;
      scriptTag.defer = true;

      scriptTag.onerror = function () {
        const url = 'https://api.mercadopago.com/v1/plugins/melidata/errors';

        const payload = {
          name: 'ERR_CONNECTION_REFUSED',
          message: 'Unable to load melidata script on page',
          target: 'melidata_woocommerce_client',
          plugin: {
            version: mercadopago_melidata_params.plugin_version,
          },
          platform: {
            name: 'woocommerce',
            uri: `${window.location.pathname}${window.location.search}`,
            version: mercadopago_melidata_params.platform_version,
            location: mercadopago_melidata_params.location,
          },
        };

        navigator.sendBeacon(url, JSON.stringify(payload));
      };

      scriptTag.onload = function () {
        window.melidata = new MelidataClient({
          type: mercadopago_melidata_params.type,
          siteID: mercadopago_melidata_params.site_id,
          pluginVersion: mercadopago_melidata_params.plugin_version,
          platformVersion: mercadopago_melidata_params.platform_version,
          pageLocation: mercadopago_melidata_params.location,
          paymentMethod: mercadopago_melidata_params.payment_method,
        });
      };

      document.body.appendChild(scriptTag);
    } catch (e) {
      console.warn(e);
    }
  });
})();
