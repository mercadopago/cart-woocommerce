const { ensureSuperTokenCss } = require('@super-token/adapters/platform/ensureSuperTokenCss');
const { SUPER_TOKEN_JS_VERSION } = require('@super-token/adapters/platform/constants');

const CSS_ID = 'wc_mercadopago_supertoken_bundle_css';
const FIX_ID = `${CSS_ID}_fix`;

describe.each(['v1', 'v2.1'])('Super Token CSS cache safeguard in %s', (folder) => {
  let stamp;
  let metrics;
  beforeEach(() => {
    jest.useFakeTimers();
    document.head.replaceChildren();
    stamp = SUPER_TOKEN_JS_VERSION;
    metrics = { sendMetric: jest.fn() };
    jest.spyOn(window, 'getComputedStyle').mockImplementation(() => ({ getPropertyValue: () => stamp }));
  });
  afterEach(() => {
    expect(jest.getTimerCount()).toBe(0);
    jest.useRealTimers();
    jest.restoreAllMocks();
    document.head.replaceChildren();
  });
  function original(linkFolder = folder, ready = true) {
    const link = document.createElement('link');
    link.id = CSS_ID;
    link.rel = 'stylesheet';
    link.href = `https://http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts/${linkFolder}/super-token.bundle.min.css?old=1`;
    if (ready) Object.defineProperty(link, 'sheet', { value: {}, configurable: true });
    document.head.appendChild(link);
    return link;
  }
  const settle = () => jest.advanceTimersByTimeAsync(0);
  function completeFix(event = 'load', version = SUPER_TOKEN_JS_VERSION) {
    stamp = version;
    document.getElementById(FIX_ID).dispatchEvent(new Event(event));
  }

  test.each([
    SUPER_TOKEN_JS_VERSION,
    `"${SUPER_TOKEN_JS_VERSION}"`,
    `'${SUPER_TOKEN_JS_VERSION}'`,
    ` \n"${SUPER_TOKEN_JS_VERSION}"\t`,
    SUPER_TOKEN_JS_VERSION.replace(/\.(\d+)$/, '/**/.$1'),
  ])('matching loaded CSS %s does not create a request or send a metric', async (version) => {
    original();
    stamp = version;
    ensureSuperTokenCss(metrics);
    await settle();
    expect(document.getElementById(FIX_ID)).toBeNull();
    expect(metrics.sendMetric).not.toHaveBeenCalled();
  });

  test.each([
    ['1.2.11', '1.2.11'], ['"1.2.11"', '1.2.11'], ["'1.2.11'", '1.2.11'], ['1.2/**/.11', '1.2.11'],
    ['1.2.12', '1.2.12'], ['"1.2.12"', '1.2.12'], ["'1.2.12'", '1.2.12'], ['1.2/**/.12', '1.2.12'],
  ])(
    'repairs the distinct old version %s and recognizes the current quoted repair', async (version, found) => {
      original();
      stamp = version;
      ensureSuperTokenCss(metrics);
      await settle();
      expect(document.getElementById(FIX_ID)).not.toBeNull();
      completeFix('load', `"${SUPER_TOKEN_JS_VERSION}"`);
      await settle();
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'super_token_css_mismatch', found, `fixed folder:${folder}`,
      );
      expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
    },
  );

  test.each([
    `"${SUPER_TOKEN_JS_VERSION}'`,
    `"${SUPER_TOKEN_JS_VERSION}"extra`,
    `"${SUPER_TOKEN_JS_VERSION}0"`,
    `" ${SUPER_TOKEN_JS_VERSION}"`,
    `"${SUPER_TOKEN_JS_VERSION.replace(/\.(\d+)$/, '/**/.$1')}"`,
    SUPER_TOKEN_JS_VERSION.replace('.', '. '),
  ])('does not recognize the invalid or distinct CSS value %s', async (version) => {
    original();
    stamp = version;
    ensureSuperTokenCss(metrics);
    await settle();
    expect(document.getElementById(FIX_ID)).not.toBeNull();
    completeFix('load', version);
    await settle();
    expect(metrics.sendMetric).toHaveBeenCalledWith(
      'super_token_css_mismatch', expect.any(String), `fix_failed folder:${folder}`,
    );
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
  });

  test('an existing correction prevents another check or repair', async () => {
    original();
    const fix = document.createElement('link');
    fix.id = FIX_ID;
    document.head.appendChild(fix);
    stamp = '1.2.11';
    ensureSuperTokenCss(metrics);
    await settle();
    expect(document.querySelectorAll(`#${FIX_ID}`)).toHaveLength(1);
    expect(metrics.sendMetric).not.toHaveBeenCalled();
  });

  test('repairs old CSS once in the original folder, replacing the query', async () => {
    const old = original(folder);
    stamp = '1.2.11';
    ensureSuperTokenCss(metrics);
    ensureSuperTokenCss(metrics);
    await settle();
    const fix = document.getElementById(FIX_ID);
    expect(fix.href).toBe(old.href.replace('?old=1', `?v=${SUPER_TOKEN_JS_VERSION}`));
    expect(fix.rel).toBe('stylesheet');
    expect([...document.head.children]).toEqual([old, fix]);
    expect(document.querySelector('style')).toBeNull();
    expect(metrics.sendMetric).not.toHaveBeenCalled();
    completeFix();
    await settle();
    ensureSuperTokenCss(metrics);
    expect(document.querySelectorAll(`#${FIX_ID}`)).toHaveLength(1);
    expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_css_mismatch', '1.2.11', `fixed folder:${folder}`);
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
  });

  test.each(['load', 'error'])('empty stamp after original %s still attempts a repair', async (event) => {
    const old = original(folder, false);
    stamp = '';
    ensureSuperTokenCss(metrics);
    old.dispatchEvent(new Event(event));
    await settle();
    expect(document.getElementById(FIX_ID)).not.toBeNull();
    completeFix();
    await settle();
    expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_css_mismatch', 'empty', `fixed folder:${folder}`);
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
  });

  test('does not run in local mode without the loader CSS link', async () => {
    ensureSuperTokenCss(metrics);
    await settle();
    expect(document.getElementById(FIX_ID)).toBeNull();
    expect(metrics.sendMetric).not.toHaveBeenCalled();
  });

  test('waits for original load before inspecting its stamp', async () => {
    const old = original(folder, false);
    stamp = '1.2.11';
    ensureSuperTokenCss(metrics);
    await jest.advanceTimersByTimeAsync(4999);
    expect(document.getElementById(FIX_ID)).toBeNull();
    stamp = SUPER_TOKEN_JS_VERSION;
    old.dispatchEvent(new Event('load'));
    await settle();
    expect(document.getElementById(FIX_ID)).toBeNull();
    expect(metrics.sendMetric).not.toHaveBeenCalled();
  });

  test('returns before a pending original stylesheet load', async () => {
    const old = original(folder, false);
    expect(ensureSuperTokenCss(metrics)).toBeUndefined();
    expect(document.getElementById(FIX_ID)).toBeNull();
    old.dispatchEvent(new Event('load'));
    await settle();
    expect(metrics.sendMetric).not.toHaveBeenCalled();
  });

  test('original timeout triggers one correction, then fix timeout terminates without retries', async () => {
    original(folder, false);
    ensureSuperTokenCss(metrics);
    await jest.advanceTimersByTimeAsync(5000);
    const fix = document.getElementById(FIX_ID);
    expect(fix).not.toBeNull();
    await jest.advanceTimersByTimeAsync(5000);
    expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_css_mismatch', 'timeout', `fix_failed folder:${folder}`);
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
    ensureSuperTokenCss(metrics);
    await jest.advanceTimersByTimeAsync(15000);
    expect(document.querySelectorAll(`#${FIX_ID}`)).toHaveLength(1);
    completeFix();
    await settle();
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
  });

  test.each([['error', SUPER_TOKEN_JS_VERSION], ['load', '1.2.11']])('reports fix_failed on correction %s with stamp %s', async (event, version) => {
    original();
    stamp = '1.2.11';
    ensureSuperTokenCss(metrics);
    await settle();
    completeFix(event, version);
    await settle();
    expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_css_mismatch', '1.2.11', `fix_failed folder:${folder}`);
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
  });

  test('DOM insertion failure clears its deadline and does not block the caller', async () => {
    original();
    stamp = '1.2.11';
    jest.spyOn(document.head, 'appendChild').mockImplementation(() => { throw new Error('blocked'); });
    expect(() => ensureSuperTokenCss(metrics)).not.toThrow();
    await settle();
    expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_css_mismatch', '1.2.11', `fix_failed folder:${folder}`);
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
  });
  test('original timeout can finish as fixed, retaining timeout as its value', async () => {
    original(folder, false);
    ensureSuperTokenCss(metrics);
    await jest.advanceTimersByTimeAsync(5000);
    completeFix();
    await settle();
    expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_css_mismatch', 'timeout', `fixed folder:${folder}`);
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
  });

  test('correction timeout retains the old stamp and reports fix_failed with the original folder', async () => {
    original();
    stamp = '1.2.11';
    ensureSuperTokenCss(metrics);
    await settle();
    await jest.advanceTimersByTimeAsync(5000);
    expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_css_mismatch', '1.2.11', `fix_failed folder:${folder}`);
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
  });

  test('empty stamp followed by correction error reports fix_failed with the original folder', async () => {
    const old = original(folder, false);
    stamp = '';
    ensureSuperTokenCss(metrics);
    old.dispatchEvent(new Event('error'));
    await settle();
    completeFix('error', '');
    await settle();
    expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_css_mismatch', 'empty', `fix_failed folder:${folder}`);
    expect(metrics.sendMetric).toHaveBeenCalledTimes(1);
  });

});
