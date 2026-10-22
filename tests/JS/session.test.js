const { resolveAlias } = require('./helpers/path-resolver');
const { loadFile } = require('./helpers/load-file');

const sessionPath = resolveAlias('assets/js/session.js');

describe('security session script', () => {
  let loadEvents;

  beforeEach(() => {
    document.body.replaceChildren();
    jest.clearAllMocks();
    loadEvents = new EventTarget();
    loadFile(sessionPath, 'undefined', { window: loadEvents });
  });

  afterEach(() => {
    jest.restoreAllMocks();
    document.body.replaceChildren();
  });

  test('waits for load before inserting the external security script', () => {
    expect(document.querySelector('#mp_woocommerce_security_session')).toBeNull();

    loadEvents.dispatchEvent(new Event('load'));

    const script = document.querySelector('#mp_woocommerce_security_session');
    expect(script.tagName).toBe('SCRIPT');
    expect(script.src).toBe('https://www.mercadopago.com/v2/security.js');
    expect(script.async).toBe(true);
    expect(script.defer).toBe(true);
    expect(script.parentElement).toBe(document.body);
    expect(console.warn).not.toHaveBeenCalled();
  });

  test('warns when the external script emits an error', () => {
    loadEvents.dispatchEvent(new Event('load'));
    const script = document.querySelector('#mp_woocommerce_security_session');

    script.dispatchEvent(new Event('error'));

    expect(console.warn).toHaveBeenCalledWith('Error on loading mp security js script');
  });

  test.each(['createElement', 'appendChild'])('handles a synchronous %s failure', (operation) => {
    const error = new Error('synthetic DOM failure');
    const target = operation === 'createElement' ? document : document.body;
    jest.spyOn(target, operation).mockImplementation(() => { throw error; });

    expect(() => loadEvents.dispatchEvent(new Event('load'))).not.toThrow();

    expect(console.warn).toHaveBeenCalledWith(error);
    expect(document.querySelector('#mp_woocommerce_security_session')).toBeNull();
  });
});
