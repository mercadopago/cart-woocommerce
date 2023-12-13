(function () {
  window.addEventListener('load', function () {
    try {
      const scriptTag = document.createElement('script');
      scriptTag.setAttribute('id', 'mp_woocommerce_security_session');
      scriptTag.src = 'https://www.mercadopago.com/v2/security.js';
      scriptTag.async = true;
      scriptTag.defer = true;

      scriptTag.onerror = function () {
        console.warn('Error on loading mp security js script');
      };

      document.body.appendChild(scriptTag);
    } catch (e) {
      console.warn(e);
    }
  });
})();
