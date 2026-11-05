'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { createHash } = require('crypto');

// Stable binding identities keep explicit old/new test aliases together across
// snapshots. Source text remains a guard, never a path-discovery mechanism.
const binding = (source, ...testPaths) => Object.freeze({ source, testPaths: Object.freeze(testPaths) });
const BINDINGS = Object.freeze({
  healthMonitor: binding('assets/js/health/mp-health-monitor.js', 'tests/JS/health/mp-health-monitor.test.js'),
  errorDispatcher: binding('assets/js/checkouts/mp-checkout-error-dispatcher.js', 'tests/JS/checkouts/mp-checkout-error-dispatcher.test.js'),
  superTokenLoader: binding('assets/js/checkouts/super-token-loader.js', 'tests/JS/checkouts/super-token/super-token-loader.test.js'),
  cartUpdateHelper: binding('assets/js/blocks/helpers/cart-update.helper.js', 'tests/JS/blocks/helpers/cart-update.helper.test.js'),
});
const CONTEXT_BINDINGS = Object.freeze({
  inputDocument: binding('packages/narciso/components/input-document/InputDocument.js', 'tests/JS/narciso/input-document.test.js'),
  customElements: binding('packages/narciso/components/input-document/InputDocument.js', 'tests/JS/checkouts/custom/mp-custom-elements.test.js'),
});
const digest = bytes => createHash('sha256').update(bytes).digest('hex');

function inside(root, filename) {
  const physical = fs.realpathSync(filename);
  const relative = path.relative(root, physical);
  if (relative.startsWith('..' + path.sep) || relative === '..' || path.isAbsolute(relative)) throw new Error('VM metadata path outside snapshot');
  return physical;
}

function bindingEntries(bindings, contextBindings) {
  const entries = [];
  const aliases = new Set();
  const identities = new Set();
  const relativePath = value => typeof value === 'string' && value && !path.isAbsolute(value)
    && value !== '.' && path.posix.normalize(value) === value && !value.split('/').includes('..') && !value.includes('\\');
  for (const [map, isolateContext] of [[bindings, false], [contextBindings, true]]) {
    for (const [identity, definition] of Object.entries(map)) {
      const { source, testPaths } = definition || {};
      if (!relativePath(source) || identities.has(identity) || !Array.isArray(testPaths) || !testPaths.length) throw new Error('Invalid VM metadata binding: ' + identity);
      identities.add(identity);
      for (const testPath of testPaths) {
        if (!relativePath(testPath) || aliases.has(testPath)) {
          throw new Error('Invalid or ambiguous VM metadata test alias: ' + identity);
        }
        aliases.add(testPath);
      }
      entries.push({ identity, source, testPaths, isolateContext });
    }
  }
  return entries;
}

function validateVmBindings({ root, bindings = BINDINGS, contextBindings = CONTEXT_BINDINGS }) {
  root = fs.realpathSync(root);
  const manifest = path.join(root, 'composer.json');
  let composer = {};
  if (fs.existsSync(manifest)) {
    try { composer = JSON.parse(fs.readFileSync(manifest, 'utf8')); }
    catch (error) { throw new Error('Cannot identify VM metadata snapshot: invalid composer.json (' + error.message + ')'); }
    if (!composer || typeof composer !== 'object' || Array.isArray(composer)) throw new Error('Cannot identify VM metadata snapshot: invalid composer.json');
  }
  // A generic measurement fixture has no WooCommerce binding contract. The
  // snapshot's identity, not the enclosing harness directory, selects the profile.
  if (composer.name !== 'mp-plugins/woocommerce-plugins-enablers') return { profile: 'generic', bindings: [] };
  const validated = bindingEntries(bindings, contextBindings).map(entry => {
    const testPaths = entry.testPaths.filter(testPath => fs.existsSync(path.join(root, testPath)));
    if (!testPaths.length) throw new Error(`VM metadata binding ${entry.identity} has no test in this snapshot; expected one of ${entry.testPaths.join(', ')}. Update its explicit testPaths aliases when renaming or moving a suite.`);
    const source = path.join(root, entry.source);
    if (!fs.existsSync(source)) throw new Error(`VM metadata binding ${entry.identity} source is missing: ${entry.source}`);
    for (const filename of [source, ...testPaths.map(testPath => path.join(root, testPath))]) {
      if (!fs.statSync(inside(root, filename)).isFile()) throw new Error(`VM metadata binding ${entry.identity} must reference physical files`);
    }
    return { identity: entry.identity, source: entry.source, testPaths, isolateContext: entry.isolateContext };
  });
  return { profile: 'woo', bindings: validated };
}

function installVmMetadata({ root, testPath, bindings = BINDINGS, contextBindings = CONTEXT_BINDINGS, receiptDirectory }) {
  root = fs.realpathSync(root);
  const physicalTest = inside(root, testPath);
  const relativeTest = path.relative(root, physicalTest).split(path.sep).join('/');
  const selected = bindingEntries(bindings, contextBindings).find(entry => entry.testPaths.includes(relativeTest));
  if (!selected) return null;
  const { isolateContext, source: sourcePath } = selected;
  const physicalSource = inside(root, path.join(root, sourcePath));
  const suiteIdentity = isolateContext ? digest(relativeTest) : null;
  const testBefore = digest(fs.readFileSync(physicalTest));
  const source = fs.readFileSync(physicalSource, 'utf8');
  const forms = [
    { kind: 'original', code: source },
    ...(!isolateContext ? [{ kind: 'export-removed', code: source.replace(/^export\s*\{[^}]*\};?\s*$/m, '') }] : []),
  ].filter((form, index, all) => form.code && all.findIndex(other => other.code === form.code) === index);
  const original = vm.Script;
  const calls = [];
  let unmatched = 0;
  let receipt;
  const bridge = new Proxy(original, {
    construct(target, args, newTarget) {
      const form = typeof args[0] === 'string' && forms.find(candidate => args[0].startsWith(candidate.code));
      const options = args[1];
      // Unknown scripts and invalid option types keep the original behavior/errors.
      if (!form || (options !== undefined && typeof options !== 'string' && (!options || typeof options !== 'object' || Array.isArray(options)))) {
        unmatched++;
        return Reflect.construct(target, args, newTarget);
      }
      // Preserve getter receivers and unused getters, even on frozen options.
      const metadata = new Proxy(Object.create(null), {
        get(_target, key) {
          if (key === 'filename') return physicalSource;
          return options && typeof options === 'object' ? Reflect.get(options, key, options) : undefined;
        },
      });
      // Distinct suite texts prevent V8 from reusing compilation feedback across
      // inspector sessions. Original offsets and the physical filename stay intact.
      const compiled = isolateContext ? args[0] + '\n// woo-coverage-suite:' + suiteIdentity + '\n' : args[0];
      calls.push({ form: form.kind, filename: physicalSource, codeSha256: digest(args[0]),
        ...(isolateContext ? { compiledCodeSha256: digest(compiled), suiteIdentity } : {}) });
      return Reflect.construct(target, [compiled, metadata, ...args.slice(2)], newTarget);
    },
  });
  vm.Script = bridge;
  return {
    restore() {
      if (receipt) return receipt;
      const restored = vm.Script === bridge;
      if (restored) vm.Script = original;
      const testAfter = digest(fs.readFileSync(physicalTest));
      const sourceAfter = digest(fs.readFileSync(physicalSource));
      receipt = {
        schemaVersion: 1, test: relativeTest, source: sourcePath,
        testSha256Before: testBefore, testSha256After: testAfter, testUnchanged: testBefore === testAfter,
        sourceSha256Before: digest(source), sourceSha256After: sourceAfter, sourceUnchanged: digest(source) === sourceAfter,
        constructions: calls, unmatchedConstructions: unmatched, restored,
        ...(restored ? {} : { restoreSkipped: 'constructor now owned by another installer' }),
      };
      const directory = receiptDirectory || path.join(root, 'coverage', 'vm-metadata');
      fs.mkdirSync(directory, { recursive: true });
      fs.writeFileSync(path.join(directory, digest(relativeTest) + '.json'), JSON.stringify(receipt, null, 2) + '\n');
      if (!receipt.testUnchanged || !receipt.sourceUnchanged) throw new Error('VM metadata input changed during execution');
      return receipt;
    },
  };
}

function setupJestMetadata() {
  const testPath = globalThis.expect?.getState?.().testPath;
  if (!testPath) return;
  const normalized = path.resolve(testPath).split(path.sep).join('/');
  const relative = bindingEntries(BINDINGS, CONTEXT_BINDINGS).flatMap(entry => entry.testPaths).find(filename => normalized.endsWith('/' + filename));
  if (!relative) return;
  const root = normalized.slice(0, -relative.length);
  const installed = installVmMetadata({ root, testPath, receiptDirectory: process.env.COVERAGE_VM_RECEIPT_DIR });
  if (!installed) throw new Error('VM metadata cannot resolve mapped test identity: ' + relative);
  globalThis.afterAll(() => installed.restore());
}

module.exports = { BINDINGS, CONTEXT_BINDINGS, installVmMetadata, validateVmBindings };
if (typeof globalThis.afterAll === 'function') setupJestMetadata();
