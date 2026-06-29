/**
 * Unit tests for the super-token A/B bundle build pipeline (main.js).
 */

jest.mock('fs');

const fs = require('fs');
const path = require('path');

const main = require('../../../main.js');

const SCRIPTS_BASE = path.join('mp-op-pp-woocommerce-scripts', 'src', 'scripts', 'super-token');

/** True if any fs.readdirSync call read exactly the given source dir. */
const readSourceDir = (relDir) =>
  fs.readdirSync.mock.calls.some((call) => call[0] === relDir);

/** The folder name used as copy target (derived from fs.mkdirSync). */
// returns only the first mkdirSync target — use only in single-variant tests
const firstCopyTargetDir = () => fs.mkdirSync.mock.calls[0][0];

describe('main.js — super-token bundle build (A/B)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    fs.existsSync.mockReturnValue(true);
    fs.readdirSync.mockReturnValue(['mp-super-token.js', 'mp-super-token.css']);
    fs.lstatSync.mockReturnValue({ isDirectory: () => false });
    // PHP read → the active-variant source of truth. Variant files → bundle source with the
    // SUPER_TOKEN_JS_VERSION assignment (only one file actually carries it — the metrics file).
    // Shared files (super-token/shared/**) are version-agnostic and carry no such declaration,
    // so the concatenated bundle still has exactly one declaration for the injection to replace.
    fs.readFileSync.mockImplementation((filePath) => {
      const normalizedPath = String(filePath);
      if (normalizedPath.endsWith('.php')) {
        return "private const PLUGIN_SUPER_TOKEN_VERSION = 'v2.1';";
      }
      if (normalizedPath.includes('shared')) {
        return '// shared bundled content (version-agnostic, no version declaration)';
      }
      return "const SUPER_TOKEN_JS_VERSION = '0.0.0-source';\n// bundled content";
    });
    fs.writeFileSync.mockImplementation(() => {});
    fs.copyFileSync.mockImplementation(() => {});
    fs.mkdirSync.mockImplementation(() => {});
    fs.rmSync.mockImplementation(() => {});
  });

  describe('bundleAndCopySuperTokenAssets', () => {
    it('v2: reads from the v2 source folders and copies to v1/', () => {
      main.bundleAndCopySuperTokenAssets('v2');

      // Source: v2 folders (JS + CSS), never the v2.1 folders
      expect(readSourceDir('./assets/js/checkouts/super-token/v2')).toBe(true);
      expect(readSourceDir('./assets/css/checkouts/super-token/v2')).toBe(true);
      expect(readSourceDir('./assets/js/checkouts/super-token/v2.1')).toBe(false);
      expect(readSourceDir('./assets/css/checkouts/super-token/v2.1')).toBe(false);

      // Destination: legacy v1/ folder (control bundle preserved at v1/)
      expect(firstCopyTargetDir().endsWith(path.join(SCRIPTS_BASE, 'v1'))).toBe(true);
    });

    it('v2.1: reads from the v2.1 source folders and copies to v2.1/', () => {
      main.bundleAndCopySuperTokenAssets('v2.1');

      expect(readSourceDir('./assets/js/checkouts/super-token/v2.1')).toBe(true);
      expect(readSourceDir('./assets/css/checkouts/super-token/v2.1')).toBe(true);
      expect(readSourceDir('./assets/js/checkouts/super-token/v2')).toBe(false);
      expect(readSourceDir('./assets/css/checkouts/super-token/v2')).toBe(false);

      expect(firstCopyTargetDir().endsWith(path.join(SCRIPTS_BASE, 'v2.1'))).toBe(true);
    });

    it('without argument: falls back to the default active version (v2.1)', () => {
      main.bundleAndCopySuperTokenAssets();

      expect(readSourceDir('./assets/js/checkouts/super-token/v2.1')).toBe(true);
      expect(firstCopyTargetDir().endsWith(path.join(SCRIPTS_BASE, 'v2.1'))).toBe(true);
    });

    it('copies both the CSS and JS bundles to the target folder', () => {
      main.bundleAndCopySuperTokenAssets('v2');

      const destinations = fs.copyFileSync.mock.calls.map((call) => call[1]);
      expect(destinations).toHaveLength(2);
      expect(
        destinations.some((dest) => dest.endsWith(path.join('v1', 'super-token.bundle.css')))
      ).toBe(true);
      expect(
        destinations.some((dest) => dest.endsWith(path.join('v1', 'super-token.bundle.js')))
      ).toBe(true);
    });
  });

  describe('copySuperTokenBundlesToScriptsProject — folderMap', () => {
    it('maps v2 → v1/', () => {
      main.copySuperTokenBundlesToScriptsProject('v2');
      expect(firstCopyTargetDir().endsWith(path.join(SCRIPTS_BASE, 'v1'))).toBe(true);
    });

    it('maps v2.1 → v2.1/', () => {
      main.copySuperTokenBundlesToScriptsProject('v2.1');
      expect(firstCopyTargetDir().endsWith(path.join(SCRIPTS_BASE, 'v2.1'))).toBe(true);
    });

    it('falls back to the version itself for unmapped variants', () => {
      main.copySuperTokenBundlesToScriptsProject('v3');
      expect(firstCopyTargetDir().endsWith(path.join(SCRIPTS_BASE, 'v3'))).toBe(true);
    });

    it('uses the default active version when called without argument', () => {
      main.copySuperTokenBundlesToScriptsProject();
      // default SUPER_TOKEN_VERSION is 'v2.1' → mapped to the v2.1/ folder
      expect(firstCopyTargetDir().endsWith(path.join(SCRIPTS_BASE, 'v2.1'))).toBe(true);
    });

    it('removes the intermediate source bundles after copying', () => {
      main.copySuperTokenBundlesToScriptsProject('v2');
      expect(fs.rmSync).toHaveBeenCalledTimes(2);
      const removedPaths = fs.rmSync.mock.calls.map((call) => call[0]);
      expect(removedPaths.some((p) => p.endsWith('super-token.bundle.css'))).toBe(true);
      expect(removedPaths.some((p) => p.endsWith('super-token.bundle.js'))).toBe(true);
    });
  });

  describe('resolveSuperTokenScriptsRepoDir', () => {
    const savedEnv = process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH;

    afterEach(() => {
      if (savedEnv === undefined) {
        delete process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH;
      } else {
        process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH = savedEnv;
      }
    });

    it('uses SUPER_TOKEN_SCRIPTS_REPO_PATH when the override directory exists', () => {
      process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH = '/tmp/custom-scripts-repo';
      fs.existsSync.mockReturnValue(true);
      fs.statSync.mockReturnValue({ isDirectory: () => true });
      expect(main.resolveSuperTokenScriptsRepoDir()).toBe(path.resolve('/tmp/custom-scripts-repo'));
    });

    it('throws when the SUPER_TOKEN_SCRIPTS_REPO_PATH override does not exist', () => {
      process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH = '/tmp/missing-scripts-repo';
      fs.existsSync.mockReturnValue(false);
      expect(() => main.resolveSuperTokenScriptsRepoDir()).toThrow(/non-existent directory/);
    });

    it('throws when the SUPER_TOKEN_SCRIPTS_REPO_PATH override points to a file, not a directory', () => {
      process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH = '/tmp/some-file.txt';
      fs.existsSync.mockReturnValue(true);
      fs.statSync.mockReturnValue({ isDirectory: () => false });
      expect(() => main.resolveSuperTokenScriptsRepoDir()).toThrow(/non-existent directory/);
    });

    it('falls back to a sibling directory (fury_ preferred) when no override is set', () => {
      delete process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH;
      fs.existsSync.mockImplementation((p) => String(p).includes('fury_mp-op-pp-woocommerce-scripts'));
      expect(main.resolveSuperTokenScriptsRepoDir()).toContain('fury_mp-op-pp-woocommerce-scripts');
    });

    it('throws when no override is set and no sibling directory exists', () => {
      delete process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH;
      fs.existsSync.mockReturnValue(false);
      expect(() => main.resolveSuperTokenScriptsRepoDir()).toThrow(/Scripts repo not found/);
    });
  });

  describe('SUPER_TOKEN_FOLDER_MAP (single source of truth)', () => {
    it('maps v2 → v1 (legacy/control) and v2.1 → v2.1', () => {
      expect(main.SUPER_TOKEN_FOLDER_MAP).toEqual({ 'v2': 'v1', 'v2.1': 'v2.1' });
    });
  });

  describe('getActiveSuperTokenVersion (derived from the PHP source of truth)', () => {
    it('reads PLUGIN_SUPER_TOKEN_VERSION from the PHP', () => {
      fs.readFileSync.mockReturnValue("private const PLUGIN_SUPER_TOKEN_VERSION = 'v2.1';");
      expect(main.getActiveSuperTokenVersion()).toBe('v2.1');
    });

    it('throws when PLUGIN_SUPER_TOKEN_VERSION is not found in the PHP', () => {
      fs.readFileSync.mockReturnValue('<?php // no super token constant here');
      expect(() => main.getActiveSuperTokenVersion()).toThrow(/PLUGIN_SUPER_TOKEN_VERSION not found/);
    });
  });

  describe('SUPER_TOKEN_LOADER_VERSION (loader version per variant)', () => {
    it('declares a version for both A/B variants', () => {
      expect(main.SUPER_TOKEN_LOADER_VERSION).toHaveProperty(['v2']);
      expect(main.SUPER_TOKEN_LOADER_VERSION).toHaveProperty(['v2.1']);
    });
  });

  describe('resolveSuperTokenLoaderVersion', () => {
    it('returns the version of a known variant', () => {
      expect(main.resolveSuperTokenLoaderVersion('v2')).toBe(main.SUPER_TOKEN_LOADER_VERSION['v2']);
      expect(main.resolveSuperTokenLoaderVersion('v2.1')).toBe(main.SUPER_TOKEN_LOADER_VERSION['v2.1']);
    });

    it('throws for an unknown variant (fail-loud, consistent with the build guard)', () => {
      // a variant added to SUPER_TOKEN_FOLDER_MAP without a version entry must fail the build,
      // not silently stamp another variant's version
      expect(() => main.resolveSuperTokenLoaderVersion('does-not-exist')).toThrow(/No loader version mapped/);
    });
  });

  describe('bundleSuperTokenJs — loader version injection', () => {
    it('overrides the source SUPER_TOKEN_JS_VERSION with the version from the map', () => {
      // beforeEach mock returns SUPER_TOKEN_JS_VERSION = '0.0.0-source'
      main.bundleSuperTokenJs('v2');

      const writtenJs = fs.writeFileSync.mock.calls.find((call) => String(call[0]).endsWith('super-token.bundle.js'))[1];
      expect(writtenJs).toContain(`SUPER_TOKEN_JS_VERSION = '${main.SUPER_TOKEN_LOADER_VERSION['v2']}'`);
      expect(writtenJs).not.toContain("0.0.0-source"); // proves injection replaced the source default
    });

    it('throws when the SUPER_TOKEN_JS_VERSION assignment is missing (no silent unstamped bundle)', () => {
      fs.readFileSync.mockReturnValue('// no version assignment here');
      expect(() => main.bundleSuperTokenJs('v2')).toThrow(/const SUPER_TOKEN_JS_VERSION declaration not found/);
    });

    it('targets only the const declaration, not a bare assignment (e.g. a class field default)', () => {
      // concatenation order: a class field (no const) appears BEFORE the const declaration
      fs.readFileSync.mockReturnValue(
        "class X { SUPER_TOKEN_JS_VERSION = '0.0.0-field'; }\nconst SUPER_TOKEN_JS_VERSION = '0.0.0-source';"
      );
      main.bundleSuperTokenJs('v2');

      const writtenJs = fs.writeFileSync.mock.calls.find((call) => String(call[0]).endsWith('super-token.bundle.js'))[1];
      // the const declaration is stamped with the map version
      expect(writtenJs).toContain(`const SUPER_TOKEN_JS_VERSION = '${main.SUPER_TOKEN_LOADER_VERSION['v2']}'`);
      // the bare assignment (class field) is left untouched — anchoring prevents stamping the wrong target
      expect(writtenJs).toContain("SUPER_TOKEN_JS_VERSION = '0.0.0-field'");
    });
  });

  describe('bundleSuperTokenJs — version-agnostic shared folder', () => {
    it('Given a shared folder exists, When bundling a variant, Then should also bundle super-token/shared (single source across A/B variants)', () => {
      main.bundleSuperTokenJs('v2');

      expect(readSourceDir('./assets/js/checkouts/super-token/v2')).toBe(true);
      expect(readSourceDir('./assets/js/checkouts/super-token/shared')).toBe(true);
    });

    it('Given the shared folder does not exist, When bundling a variant, Then should skip it without failing the build', () => {
      fs.existsSync.mockImplementation((p) => !String(p).includes('shared'));

      expect(() => main.bundleSuperTokenJs('v2')).not.toThrow();
      expect(readSourceDir('./assets/js/checkouts/super-token/shared')).toBe(false);
    });
  });

  describe('bundleSuperTokenCss — loader version stamp', () => {
    it('prepends the .root version stamp from the map at the top of the bundle', () => {
      main.bundleSuperTokenCss('v2.1');

      const writtenCss = fs.writeFileSync.mock.calls.find((call) => String(call[0]).endsWith('super-token.bundle.css'))[1];
      expect(writtenCss.trimStart().startsWith('.root {')).toBe(true);
      expect(writtenCss).toContain(`--mp-super-token-loader-version: ${main.SUPER_TOKEN_LOADER_VERSION['v2.1']};`);
    });
  });

  describe('bundleAllSuperTokenVariants', () => {
    it('bundles every variant from its own source and copies to every CDN folder', () => {
      main.bundleAllSuperTokenVariants();

      // reads each variant source folder (JS + CSS)
      expect(readSourceDir('./assets/js/checkouts/super-token/v2')).toBe(true);
      expect(readSourceDir('./assets/js/checkouts/super-token/v2.1')).toBe(true);
      expect(readSourceDir('./assets/css/checkouts/super-token/v2')).toBe(true);
      expect(readSourceDir('./assets/css/checkouts/super-token/v2.1')).toBe(true);

      // copies to every target folder declared in the map
      const targets = fs.mkdirSync.mock.calls.map((call) => call[0]);
      expect(targets.some((dir) => dir.endsWith(path.join(SCRIPTS_BASE, 'v1')))).toBe(true);
      expect(targets.some((dir) => dir.endsWith(path.join(SCRIPTS_BASE, 'v2.1')))).toBe(true);
    });

    it('processes exactly the variants declared in SUPER_TOKEN_FOLDER_MAP', () => {
      main.bundleAllSuperTokenVariants();

      // one copy (mkdirSync of the target folder) per variant in the map
      expect(fs.mkdirSync).toHaveBeenCalledTimes(Object.keys(main.SUPER_TOKEN_FOLDER_MAP).length);
    });

    it('cleans up intermediate bundles between variants', () => {
      main.bundleAllSuperTokenVariants();

      // 2 intermediate files (js + css) removed per variant — documents the sequential
      // cleanup invariant: intermediaries must be cleaned before the next variant starts
      expect(fs.rmSync).toHaveBeenCalledTimes(2 * Object.keys(main.SUPER_TOKEN_FOLDER_MAP).length);
    });
  });
});
