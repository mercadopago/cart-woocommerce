describe.each(['source', 'minified'])('Jaiminho client loader (%s)', assetVariant => {
  let listeners;
  let fetch;

  function load() {
    const suffix = assetVariant === 'minified' ? '.min' : '';
    jest.isolateModules(() => require(`../../../assets/js/notices/notices-client${suffix}.js`));
  }

  function appendSlot() {
    const region = document.createElement('div');
    region.id = 'mp-admin-notices-region';
    const remote = document.createElement('div');
    remote.id = 'mp-jaiminho-notices-slot';
    const native = document.createElement('section');
    native.id = 'mp-native-onboarding-notice';
    region.append(remote, native);
    document.body.append(region);
    return native;
  }

  beforeEach(() => {
    document.body.replaceChildren();
    appendSlot();
    fetch = jest.fn(() => Promise.resolve({ ok: true }));
    window.fetch = fetch;
    window.wc_mercadopago_notices_params = {
      container: '#mp-jaiminho-notices-slot',
      css_url: 'https://cdn.example.invalid/notices/woocommerce.css',
      js_url: 'https://cdn.example.invalid/notices/woocommerce.js',
      metrics_url: 'https://api.example.invalid/notices/metrics',
      plugin_version: '8.9.8',
      platform_id: 'SYNTHETIC_PLATFORM',
      platform_version: '10.2.0',
    };
    jest.spyOn(document, 'readyState', 'get').mockReturnValue('complete');
    listeners = [];
    const addEventListener = window.addEventListener.bind(window);
    jest.spyOn(window, 'addEventListener').mockImplementation((type, listener, options) => {
      listeners.push([type, listener, options]);
      addEventListener(type, listener, options);
    });
  });

  afterEach(() => {
    listeners.forEach(args => window.removeEventListener(...args));
    document.body.replaceChildren();
    delete window.wc_mercadopago_notices_params;
    delete window.fetch;
    jest.restoreAllMocks();
  });

  it('uses localized URLs and loads CSS before JS into an exclusively remote slot', () => {
    load();
    const stylesheet = document.getElementById('mpnotices_woocommerce_client_css');
    expect(stylesheet.href).toBe(window.wc_mercadopago_notices_params.css_url);
    expect(document.getElementById('mpnotices_woocommerce_client')).toBeNull();
    stylesheet.dispatchEvent(new Event('load'));
    const script = document.getElementById('mpnotices_woocommerce_client');
    expect(script.src).toBe(window.wc_mercadopago_notices_params.js_url);
    expect(script.async).toBe(true);
    expect(document.getElementById('mp-native-onboarding-notice').parentElement.id).toBe('mp-admin-notices-region');
  });

  it('does not inject duplicate resources when initialized repeatedly', () => {
    load();
    load();
    document.getElementById('mpnotices_woocommerce_client_css').dispatchEvent(new Event('load'));
    document.getElementById('mpnotices_woocommerce_client_css').dispatchEvent(new Event('load'));
    expect(document.querySelectorAll('#mpnotices_woocommerce_client_css')).toHaveLength(1);
    expect(document.querySelectorAll('#mpnotices_woocommerce_client')).toHaveLength(1);
  });

  it('starts after load when the page is still loading', () => {
    jest.spyOn(document, 'readyState', 'get').mockReturnValue('loading');
    load();
    expect(document.getElementById('mpnotices_woocommerce_client_css')).toBeNull();
    window.dispatchEvent(new Event('load'));
    expect(document.getElementById('mpnotices_woocommerce_client_css')).not.toBeNull();
  });

  it.each(['css', 'js'])('keeps the native notice after remote %s failure', async target => {
    const native = document.getElementById('mp-native-onboarding-notice');
    load();
    const stylesheet = document.getElementById('mpnotices_woocommerce_client_css');
    if (target === 'js') stylesheet.dispatchEvent(new Event('load'));
    const resource = target === 'css' ? stylesheet : document.getElementById('mpnotices_woocommerce_client');
    fetch.mockRejectedValue(new Error('controlled metric delivery failure'));
    resource.dispatchEvent(new Event('error'));
    await Promise.resolve();
    expect(native.isConnected).toBe(true);
    const [url, options] = fetch.mock.calls[0];
    expect(url).toBe(window.wc_mercadopago_notices_params.metrics_url);
    expect(JSON.parse(options.body)).toMatchObject({
      target: `notices_woocommerce_client_${target}`,
      platform: { version: '10.2.0' },
      plugin: { version: '8.9.8' },
    });
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('does not fall back to a container shared with the native notice', () => {
    document.getElementById('mp-jaiminho-notices-slot').remove();
    load();
    expect(document.getElementById('mpnotices_woocommerce_client_css')).toBeNull();
    expect(document.getElementById('mp-native-onboarding-notice')).not.toBeNull();
  });

  it('ignores missing runtime configuration without throwing', () => {
    delete window.wc_mercadopago_notices_params;
    expect(load).not.toThrow();
    expect(document.querySelector('link')).toBeNull();
  });
});
