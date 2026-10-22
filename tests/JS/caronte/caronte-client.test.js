const CLIENT_PATH = '../../../assets/js/caronte/caronte-client.js';
const CLIENT_ID = 'mpcaronte_woocommerce_client';
const CSS_URL = 'https://http2.mlstatic.com/storage/v1/plugins/caronte/woocommerce.css';
const JS_URL = 'https://http2.mlstatic.com/storage/v1/plugins/caronte/woocommerce.js';

function loadClient() {
  // Execute the source IIFE itself; do not reproduce its CSS/JS chain in a mock.
  jest.isolateModules(() => require(CLIENT_PATH));
}

function startPageLoad() {
  window.dispatchEvent(new Event('load'));
}

describe('Caronte client loader', () => {
  let loadListeners;

  beforeEach(() => {
    document.body.replaceChildren();
    console.warn.mockClear();
    loadListeners = [];
    const addEventListener = window.addEventListener.bind(window);
    jest.spyOn(window, 'addEventListener').mockImplementation((type, listener, options) => {
      if (type === 'load') loadListeners.push(listener);
      return addEventListener(type, listener, options);
    });
  });

  afterEach(() => {
    loadListeners.forEach(listener => window.removeEventListener('load', listener));
    jest.restoreAllMocks();
    document.body.replaceChildren();
  });

  test('waits for page load and appends only the external stylesheet', () => {
    loadClient();
    expect(document.body.childNodes).toHaveLength(0);
    startPageLoad();

    const link = document.querySelector('link');
    expect(link).not.toBeNull();
    expect(link.rel).toBe('stylesheet');
    expect(link.type).toBe('text/css');
    expect(link.href).toBe(CSS_URL);
    expect(document.body.childNodes).toHaveLength(1);
    expect(document.getElementById(CLIENT_ID)).toBeNull();
  });

  test('initializes the external JS only after stylesheet success with async and defer enabled', () => {
    loadClient();
    startPageLoad();
    const link = document.querySelector('link');
    expect(document.getElementById(CLIENT_ID)).toBeNull();

    link.onload();

    const script = document.getElementById(CLIENT_ID);
    expect(script).not.toBeNull();
    expect(script.tagName).toBe('SCRIPT');
    expect(script.src).toBe(JS_URL);
    expect(script.async).toBe(true);
    expect(script.defer).toBe(true);
    expect([...document.body.children]).toEqual([link, script]);
    expect(console.warn).not.toHaveBeenCalled();
  });

  test('reports stylesheet failure without loading the external JS', () => {
    loadClient();
    startPageLoad();

    document.querySelector('link').onerror();

    expect(console.warn).toHaveBeenCalledTimes(1);
    expect(console.warn).toHaveBeenCalledWith('Error on loading caronte css script');
    expect(document.getElementById(CLIENT_ID)).toBeNull();
    expect(document.body.childNodes).toHaveLength(1);
  });

  test('reports external JS failure after the actual CSS success chain', () => {
    loadClient();
    startPageLoad();
    document.querySelector('link').onload();

    document.getElementById(CLIENT_ID).onerror();

    expect(console.warn).toHaveBeenCalledTimes(1);
    expect(console.warn).toHaveBeenCalledWith('Error on loading caronte js script');
  });

  test.each(['createElement', 'appendChild'])('absorbs a synchronous %s browser failure', operation => {
    loadClient();
    const failure = new Error('DOM unavailable');
    const target = operation === 'createElement' ? document : document.body;
    jest.spyOn(target, operation).mockImplementation(() => { throw failure; });

    expect(startPageLoad).not.toThrow();

    expect(console.warn).toHaveBeenCalledTimes(1);
    expect(console.warn).toHaveBeenCalledWith(failure);
    expect(document.body.childNodes).toHaveLength(0);
  });
});
