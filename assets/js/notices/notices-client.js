(function () {
  const params = window.wc_mercadopago_notices_params || {};
  const stylesheetId = 'mpnotices_woocommerce_client_css';
  const scriptId = 'mpnotices_woocommerce_client';

  function sendError(target) {
    if (!params.metrics_url || !params.platform_id || typeof window.fetch !== 'function') return;
    const { plugin_version, platform_id, platform_version } = params;

    const payload = {
      target,
      type: 'error',
      name: 'ERR_CONNECTION_REFUSED',
      message: 'ERR_CONNECTION_REFUSED',
      plugin: {
        version: plugin_version,
      },
      platform: {
        uri: `${window.location.pathname}${window.location.search}`,
        name: 'woocommerce',
        version: platform_version,
        location: 'woo_admin_mercadopago_settings',
      },
    };

    try {
      Promise.resolve(window.fetch(params.metrics_url, {
        method: 'POST',
        body: JSON.stringify(payload),
        headers: {
          'Content-Type': 'application/json',
          'X-Platform-Id': platform_id.toLowerCase(),
          'X-Plugin-Version': plugin_version,
        },
      })).catch(() => {});
    } catch {
      // Reporting a CDN failure must not interrupt the local administrative notice.
    }
  }


  function loadScript() {
    if (document.getElementById(scriptId) || !params.js_url) return;
    const scriptTag = document.createElement('script');
    scriptTag.id = scriptId;
    scriptTag.src = params.js_url;
    scriptTag.async = true;
    scriptTag.defer = true;
    scriptTag.onerror = () => sendError('notices_woocommerce_client_js');
    document.body.appendChild(scriptTag);
  }

  function initialize() {
    try {
      if (!params.container || !document.querySelector(params.container) || !params.css_url || document.getElementById(stylesheetId)) return;
      const link = document.createElement('link');
      link.id = stylesheetId;
      link.rel = 'stylesheet';
      link.type = 'text/css';
      link.href = params.css_url;
      link.onerror = () => sendError('notices_woocommerce_client_css');
      link.onload = loadScript;
      document.body.appendChild(link);
    } catch {
      sendError('notices_woocommerce_client_js');
    }
  }


  if (document.readyState === 'complete') initialize();
  else window.addEventListener('load', initialize, { once: true });
})();
