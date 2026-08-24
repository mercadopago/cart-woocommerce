/**
 * Unit tests for the super-token asset build pipeline (main.js): the single
 * compiled CSS (both variants scoped by data-variant) plus the single staged JS
 * bundle.
 */

jest.mock('fs');
jest.mock('sass', () => ({
  compile: jest.fn(() => ({ css: '.compiled{color:red}' })),
}));
jest.mock('minify', () => jest.fn(() => Promise.resolve('minified')));

const fs = require('fs');
const path = require('path');
const sass = require('sass');
const minify = require('minify');

const main = require('../../../main.js');

/** Paths written by fs.writeFileSync, as strings. */
const writtenPaths = () => fs.writeFileSync.mock.calls.map((call) => String(call[0]));

describe('main.js — super-token asset build', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    fs.existsSync.mockReturnValue(true);
    fs.writeFileSync.mockImplementation(() => {});
    fs.copyFileSync.mockImplementation(() => {});
    sass.compile.mockReturnValue({ css: '.compiled{color:red}' });
  });

  describe('compileSuperTokenCss', () => {
    it('Given the SCSS entries exist, When compiling, Then it writes the unified and both per-variant stylesheets', () => {
      main.compileSuperTokenCss();

      const entries = sass.compile.mock.calls.map(([entry]) => path.basename(String(entry)));
      expect(entries).toEqual(['super-token.scss', 'super-token-v2.scss', 'super-token-v2.1.scss']);
      sass.compile.mock.calls.forEach(([, options]) => expect(options).toEqual({ style: 'compressed' }));

      const outputs = fs.writeFileSync.mock.calls.map(([out]) => path.basename(String(out)));
      expect(outputs).toEqual([
        'super-token.bundle.min.css',
        'super-token-v2.bundle.min.css',
        'super-token-v2.1.bundle.min.css',
      ]);
      const contents = fs.writeFileSync.mock.calls.map(([, content]) => content);
      expect(contents).toEqual([
        '.compiled{color:red}',
        ':root{--mp-super-token-loader-version:1.2.5}.compiled{color:red}',
        ':root{--mp-super-token-loader-version:1.2.5}.compiled{color:red}',
      ]);
    });

    it('Given an SCSS entry is missing, When compiling, Then it throws (fail-loud, no silent unstyled checkout)', () => {
      fs.existsSync.mockReturnValue(false);
      expect(() => main.compileSuperTokenCss()).toThrow(/SCSS entry not found/);
    });
  });

  describe('stageSuperTokenJsBundle', () => {
    it('Given the webpack outputs exist, When staging, Then it copies each build to its unminified hand-off (runtime + per-variant)', () => {
      main.stageSuperTokenJsBundle();

      const copies = fs.copyFileSync.mock.calls.map(([source, target]) => ({
        source: String(source),
        target: path.basename(String(target)),
      }));
      expect(copies).toEqual([
        { source: expect.stringContaining(path.join('build', 'super-token', 'bootstrap.ts.js')), target: 'super-token.bundle.js' },
        { source: expect.stringContaining(path.join('build', 'super-token-v2', 'bootstrap.ts.js')), target: 'super-token-v2.bundle.js' },
        { source: expect.stringContaining(path.join('build', 'super-token-v2.1', 'bootstrap.ts.js')), target: 'super-token-v2.1.bundle.js' },
      ]);
      // Hand-off stays unminified (the scripts repo minifies).
      copies.forEach(({ target }) => expect(target.endsWith('.min.js')).toBe(false));
    });

    it('Given a webpack output is missing, When staging, Then it throws (build:super-token:webpack must run first)', () => {
      fs.existsSync.mockReturnValue(false);
      expect(() => main.stageSuperTokenJsBundle()).toThrow(/webpack output not found/);
    });
  });

  describe('buildSuperTokenAssets', () => {
    it('compiles the three stylesheets and stages the three JS bundles (runtime + per-variant)', () => {
      main.buildSuperTokenAssets();

      const paths = writtenPaths();
      expect(paths.some((p) => p.endsWith('super-token.bundle.min.css'))).toBe(true);
      expect(paths.some((p) => p.endsWith('super-token-v2.bundle.min.css'))).toBe(true);
      expect(paths.some((p) => p.endsWith('super-token-v2.1.bundle.min.css'))).toBe(true);
      expect(sass.compile).toHaveBeenCalledTimes(3);
      expect(fs.copyFileSync).toHaveBeenCalledTimes(3);
    });
  });

  describe('minifyFiles — leaves the Super Token assets to their dedicated build steps', () => {
    it('Given a Super Token hand-off .js and a regular asset, When minifyFiles runs, Then only the regular asset is minified', () => {
      fs.readdirSync.mockImplementation((dir) => {
        const normalized = String(dir);
        if (normalized.endsWith(path.normalize('assets/js'))) return ['checkouts', 'notices.js'];
        if (normalized.endsWith('checkouts')) return ['super-token'];
        if (normalized.endsWith('super-token')) return ['super-token.bundle.js'];
        return [];
      });
      fs.lstatSync.mockImplementation((entry) => ({
        isDirectory: () => String(entry).endsWith('checkouts') || String(entry).endsWith('super-token'),
      }));

      main.minifyFiles('js');

      const minifiedPaths = minify.mock.calls.map((call) => String(call[0]));
      expect(minifiedPaths.some((p) => p.endsWith('notices.js'))).toBe(true);
      expect(minifiedPaths.some((p) => p.includes(path.normalize('checkouts/super-token/')))).toBe(false);
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

  describe('SUPER_TOKEN_LOADER_VERSION (A/B variant list)', () => {
    it('declares a version for both A/B variants', () => {
      expect(main.SUPER_TOKEN_LOADER_VERSION).toHaveProperty(['v2']);
      expect(main.SUPER_TOKEN_LOADER_VERSION).toHaveProperty(['v2.1']);
    });
  });
});
