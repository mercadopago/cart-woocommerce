/**
 * Integration tests for the super-token A/B bundle build (main.js).
 *
 * Unlike main.test.js (which mocks `fs` and asserts the build *logic*), these
 * exercise the REAL filesystem:
 * - bundle concatenation: real per-variant source reads (only `fs.writeFileSync`
 *   is stubbed to capture the produced content);
 * - copy to scripts repo: a real copy into a temp directory via
 *   SUPER_TOKEN_SCRIPTS_REPO_PATH (no stubs).
 */

const fs = require('fs');
const path = require('path');
const os = require('os');
const main = require('../../../main.js');

describe('integration: super-token bundle concatenation (real sources)', () => {
  let writeSpy;

  beforeEach(() => {
    writeSpy = jest.spyOn(fs, 'writeFileSync').mockImplementation(() => {});
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  function bundledContent(outputFn, version, outputSuffix) {
    writeSpy.mockClear();
    const outputPath = outputFn(version);
    expect(outputPath).not.toBeNull();
    const writeCall = writeSpy.mock.calls.find((call) => String(call[0]).endsWith(outputSuffix));
    return writeCall ? writeCall[1] : '';
  }

  const bundledJs = (version) => bundledContent(main.bundleSuperTokenJs, version, 'super-token.bundle.js');
  const bundledCss = (version) => bundledContent(main.bundleSuperTokenCss, version, 'super-token.bundle.css');

  test('Given the v2 source folder, When bundleSuperTokenJs runs, Then it concatenates the real control entities', () => {
    const content = bundledJs('v2');

    // a known trigger-handler symbol confirms the real source was concatenated
    expect(content).toContain('class MPSuperTokenTriggerHandler');
  });

  test('Given the v2.1 source folder, When bundleSuperTokenJs runs, Then it concatenates the real treatment entities', () => {
    const content = bundledJs('v2.1');

    expect(content).toContain('class MPSuperTokenTriggerHandler');
  });

  test('Given v2 and v2.1, When bundled, Then each reads its own folder (explicit treatment marker absent in control)', () => {
    const v2 = bundledJs('v2');
    const v21 = bundledJs('v2.1');

    // VARIANT_MARKER is an intentional, stable marker (not an implementation symbol that may change)
    expect(v21).toContain('VARIANT_MARKER: v2.1');
    expect(v2).not.toContain('VARIANT_MARKER: v2.1');
  });

  test('Given each variant, When bundleSuperTokenCss runs, Then it produces non-empty CSS from the real source folder', () => {
    expect(bundledCss('v2').length).toBeGreaterThan(0);
    expect(bundledCss('v2.1').length).toBeGreaterThan(0);
  });

  describe('build-time loader version injection (single source of truth)', () => {
    const expectedVersion = (variant) => main.SUPER_TOKEN_LOADER_VERSION[variant];

    test('Given the JS bundle, When built, Then SUPER_TOKEN_JS_VERSION is injected from the version map', () => {
      expect(bundledJs('v2')).toContain(`SUPER_TOKEN_JS_VERSION = '${expectedVersion('v2')}'`);
      expect(bundledJs('v2.1')).toContain(`SUPER_TOKEN_JS_VERSION = '${expectedVersion('v2.1')}'`);
    });

    test('Given the CSS bundle, When built, Then the loader version stamp is prepended from the version map', () => {
      const v2Css = bundledCss('v2');
      const v21Css = bundledCss('v2.1');

      expect(v2Css).toContain(`--mp-super-token-loader-version: ${expectedVersion('v2')};`);
      expect(v21Css).toContain(`--mp-super-token-loader-version: ${expectedVersion('v2.1')};`);
      // stamp is at the very top of the bundle
      expect(v2Css.trimStart().startsWith('.root {')).toBe(true);
    });

    test('Given the same variant, When built, Then CSS and JS carry the same version (no drift)', () => {
      const js = bundledJs('v2.1');
      const css = bundledCss('v2.1');
      const version = expectedVersion('v2.1');

      expect(js).toContain(`SUPER_TOKEN_JS_VERSION = '${version}'`);
      expect(css).toContain(`--mp-super-token-loader-version: ${version};`);
    });
  });
});

describe('integration: copy bundles to a parametrized scripts repo path (PSW-4076)', () => {
  // Real copy (no stubs): point the destination at a temp dir via the env override.
  let tmpRepo;
  let previousRepoPath;

  const INTERMEDIATE_BUNDLES = [
    './assets/js/checkouts/super-token/super-token.bundle.js',
    './assets/css/checkouts/super-token/super-token.bundle.css',
  ];

  beforeEach(() => {
    previousRepoPath = process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH;
    tmpRepo = fs.mkdtempSync(path.join(os.tmpdir(), 'st-scripts-repo-'));
    process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH = tmpRepo;
  });

  afterEach(() => {
    if (previousRepoPath === undefined) {
      delete process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH;
    } else {
      process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH = previousRepoPath;
    }
    fs.rmSync(tmpRepo, { recursive: true, force: true });
    // defensive: clear any intermediate bundle left if a copy failed mid-way
    INTERMEDIATE_BUNDLES.forEach((p) => fs.rmSync(path.resolve(p), { force: true }));
  });

  test('Given v2, When bundleAndCopySuperTokenAssets runs, Then bundles land in v1/ of the target repo', () => {
    main.bundleAndCopySuperTokenAssets('v2');

    const targetDir = path.join(tmpRepo, 'src/scripts/super-token/v1');
    expect(fs.existsSync(path.join(targetDir, 'super-token.bundle.js'))).toBe(true);
    expect(fs.existsSync(path.join(targetDir, 'super-token.bundle.css'))).toBe(true);
  });

  test('Given v2.1, When bundleAndCopySuperTokenAssets runs, Then bundles land in v2.1/ of the target repo', () => {
    main.bundleAndCopySuperTokenAssets('v2.1');

    const targetDir = path.join(tmpRepo, 'src/scripts/super-token/v2.1');
    expect(fs.existsSync(path.join(targetDir, 'super-token.bundle.js'))).toBe(true);
    expect(fs.existsSync(path.join(targetDir, 'super-token.bundle.css'))).toBe(true);
  });

  test('Given a non-existent SUPER_TOKEN_SCRIPTS_REPO_PATH, When copying, Then it throws (no silent stale folder)', () => {
    process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH = path.join(os.tmpdir(), `st-missing-${Date.now()}`);
    expect(() => main.copySuperTokenBundlesToScriptsProject('v2')).toThrow(/non-existent directory/);
  });
});

describe('integration: setupSuperToken configures the dev runtime constants (PSW-4076)', () => {
  // Real read of the actual PHP source (validates the regexes match the real file); only
  // readline (interactive prompts) and the write are stubbed. setup:st is a dev-config tool:
  // it writes only the PHP constants — the loader version is a release/bump concern.
  const readline = require('readline');
  let writeSpy;

  const writtenFor = (suffix) => {
    const call = writeSpy.mock.calls.find((c) => String(c[0]).endsWith(suffix));
    return call ? String(call[1]) : null;
  };

  function mockAnswers(answers) {
    let i = 0;
    jest.spyOn(readline, 'createInterface').mockReturnValue({
      question: (_question, cb) => cb(answers[i++]),
      close: () => {},
    });
  }

  beforeEach(() => {
    writeSpy = jest.spyOn(fs, 'writeFileSync').mockImplementation(() => {});
    jest.spyOn(process, 'exit').mockImplementation(() => { throw new Error('process.exit'); });
    jest.spyOn(console, 'log').mockImplementation(() => {});
    jest.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  test('Given dev mode for v2, When setupSuperToken runs, Then it writes only the PHP constants (variant/toggle/env) and touches nothing else', async () => {
    mockAnswers(['v2', 'n', 'beta']);

    await main.setupSuperToken();

    const php = writtenFor('WoocommerceMercadoPago.php');
    expect(php).toContain("PLUGIN_SUPER_TOKEN_VERSION = 'v2'");
    expect(php).toContain('PLUGIN_SUPER_TOKEN_USE_BUNDLE = false');
    expect(php).toContain("PLUGIN_SDK_ENV = 'beta'");

    // the PHP is the only file written — no map, no JS const, no CSS, no build
    expect(writeSpy).toHaveBeenCalledTimes(1);
    expect(String(writeSpy.mock.calls[0][0])).toMatch(/WoocommerceMercadoPago\.php$/);
  });

  test('Given bundle mode for v2.1, When setupSuperToken runs, Then USE_BUNDLE is true and still only the PHP is written', async () => {
    mockAnswers(['v2.1', 's', 'prod']);

    await main.setupSuperToken();

    const php = writtenFor('WoocommerceMercadoPago.php');
    expect(php).toContain("PLUGIN_SUPER_TOKEN_VERSION = 'v2.1'");
    expect(php).toContain('PLUGIN_SUPER_TOKEN_USE_BUNDLE = true');
    expect(php).toContain("PLUGIN_SDK_ENV = 'prod'");

    expect(writeSpy).toHaveBeenCalledTimes(1);
  });

  test.each([
    ['an invalid variant', ['v9', 'n', 'prod']],
    ['an invalid bundle answer', ['v2', 'maybe', 'prod']],
    ['an invalid SDK env', ['v2', 'n', 'staging']],
  ])('Given %s, When setupSuperToken runs, Then it exits without writing any file', async (_label, answers) => {
    mockAnswers(answers);

    await expect(main.setupSuperToken()).rejects.toThrow('process.exit');
    expect(writeSpy).not.toHaveBeenCalled();
  });
});
