(function () {
  window.addEventListener('load', function () {
    try {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.type = 'text/css';
      link.href = 'https://http2.mlstatic.com/storage/v1/plugins/caronte/woocommerce.css';

      link.onerror = function () {
        console.warn('Error on loading caronte css script');
      };

      link.onload = function () {
        const scriptTag = document.createElement('script');
        scriptTag.setAttribute('id', 'mpcaronte_woocommerce_client');
        scriptTag.src = 'https://http2.mlstatic.com/storage/v1/plugins/caronte/woocommerce.js';
        scriptTag.async = true;
        scriptTag.defer = true;

        scriptTag.onerror = function () {
          console.warn('Error on loading caronte js script');
        };

        document.body.appendChild(scriptTag);
      };

      document.body.appendChild(link);
    } catch (e) {
      console.warn(e);
    }
  });
})();
