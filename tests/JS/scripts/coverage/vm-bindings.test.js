const fs = require('fs');
const os = require('os');
const path = require('path');
const vm = require('vm');
const { spawnSync } = require('child_process');
const {
  BINDINGS, CONTEXT_BINDINGS, validateVmBindings, installVmMetadata,
} = require('../../../../scripts/coverage/jest-vm-metadata');
const { normalizeJs } = require('../../../../scripts/coverage/report');

const ROOT = path.resolve(__dirname, '../../../..');
const SETUP = path.join(ROOT, 'scripts/coverage/jest-vm-metadata.js');
const GLOBAL_SETUP = path.join(ROOT, 'scripts/coverage/jest-global-setup.js');
const IDENTITY = 'mp-plugins/woocommerce-plugins-enablers';
let fixture;

function put(root, relative, content) {
  const filename = path.join(root, relative);
  fs.mkdirSync(path.dirname(filename), { recursive: true });
  fs.writeFileSync(filename, content);
  return filename;
}

function pluginSnapshot(root) {
  put(root, 'composer.json', JSON.stringify({ name: IDENTITY }));
  for (const definition of [...Object.values(BINDINGS), ...Object.values(CONTEXT_BINDINGS)]) {
    put(root, definition.source, '// physical source fixture\n');
    for (const testPath of definition.testPaths) put(root, testPath, '// physical suite fixture\n');
  }
}

beforeEach(() => {
  fixture = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'vm-bindings-')));
});
afterEach(() => fs.rmSync(fixture, { recursive: true, force: true }));

test('the actual plugin validates all six binding identities, including both InputDocument consumers', () => {
  const result = validateVmBindings({ root: ROOT });
  expect(result.profile).toBe('woo');
  expect(result.bindings).toHaveLength(6);
  const contexts = result.bindings.filter(entry => entry.isolateContext);
  expect(contexts.map(entry => entry.identity)).toEqual(['inputDocument', 'customElements']);
  expect(contexts[0].source).toBe(contexts[1].source);
  expect(contexts[0].testPaths).not.toEqual(contexts[1].testPaths);
});

test('renaming a mapped test without updating its aliases fails with an actionable diagnosis', () => {
  pluginSnapshot(fixture);
  const previous = BINDINGS.healthMonitor.testPaths[0];
  fs.renameSync(path.join(fixture, previous), path.join(path.dirname(path.join(fixture, previous)), 'renamed.test.js'));
  expect(() => validateVmBindings({ root: fixture })).toThrow(/healthMonitor.*no test.*Update its explicit testPaths aliases/s);
});

test('one explicit alias contract loads each revision own unchanged source through the real VM constructor', () => {
  const source = 'throw new Error("actual source execution");\n';
  const bindings = { stableIdentity: { source: 'src/actual.js', testPaths: ['tests/old.test.js', 'tests/new.test.js'] } };
  const original = vm.Script;
  for (const [revision, testPath] of [['base', 'tests/old.test.js'], ['candidate', 'tests/new.test.js']]) {
    const root = path.join(fixture, revision);
    put(root, 'composer.json', JSON.stringify({ name: IDENTITY }));
    put(root, 'src/actual.js', source);
    put(root, testPath, '// revision owns its test\n');
    const args = { root, bindings, contextBindings: {} };
    expect(validateVmBindings(args).bindings[0].testPaths).toEqual([testPath]);
    const installed = installVmMetadata({ ...args, testPath: path.join(root, testPath) });
    try {
      let failure;
      try { new vm.Script(source).runInNewContext({}); } catch (error) { failure = error; }
      expect(failure.message).toBe('actual source execution');
      expect(failure.stack).toContain(path.join(root, 'src/actual.js') + ':1');
      const receipt = installed.restore();
      expect(receipt.constructions).toHaveLength(1);
      expect(receipt.testUnchanged && receipt.sourceUnchanged).toBe(true);
    } finally { installed.restore(); }
    expect(vm.Script).toBe(original);
  }
  // Both explicitly declared suites may coexist during a migration.
  put(path.join(fixture, 'base'), 'tests/new.test.js', '// additional explicit alias\n');
  expect(validateVmBindings({ root: path.join(fixture, 'base'), bindings, contextBindings: {} }).bindings[0].testPaths).toEqual(bindings.stableIdentity.testPaths);
});

test('a missing source fails before running a suite', () => {
  pluginSnapshot(fixture);
  fs.unlinkSync(path.join(fixture, BINDINGS.healthMonitor.source));
  expect(() => validateVmBindings({ root: fixture })).toThrow(/healthMonitor source is missing/);
});

test('source and test symlinks cannot escape the physical snapshot', () => {
  pluginSnapshot(fixture);
  const external = fs.mkdtempSync(path.join(os.tmpdir(), 'vm-bindings-external-'));
  try {
    const outside = put(external, 'outside.js', '// outside snapshot\n');
    for (const relative of [BINDINGS.healthMonitor.source, BINDINGS.healthMonitor.testPaths[0]]) {
      const inside = path.join(fixture, relative);
      const previous = fs.readFileSync(inside);
      fs.unlinkSync(inside);
      fs.symlinkSync(outside, inside);
      expect(() => validateVmBindings({ root: fixture })).toThrow('VM metadata path outside snapshot');
      fs.unlinkSync(inside);
      fs.writeFileSync(inside, previous);
    }
  } finally { fs.rmSync(external, { recursive: true, force: true }); }
});

test('an alias cannot belong to two different bindings', () => {
  pluginSnapshot(fixture);
  const bindings = {
    first: { source: BINDINGS.healthMonitor.source, testPaths: BINDINGS.healthMonitor.testPaths },
    second: { source: BINDINGS.errorDispatcher.source, testPaths: BINDINGS.healthMonitor.testPaths },
  };
  expect(() => validateVmBindings({ root: fixture, bindings, contextBindings: {} })).toThrow('ambiguous VM metadata test alias');
});

test('generic snapshots do not inherit the enclosing harness WooCommerce contract', () => {
  expect(validateVmBindings({ root: fixture })).toEqual({ profile: 'generic', bindings: [] });
  put(fixture, 'composer.json', JSON.stringify({ name: 'coverage-fixture/generic' }));
  expect(validateVmBindings({ root: fixture })).toEqual({ profile: 'generic', bindings: [] });
});

test.each(['{', 'null', '[]'])('invalid Composer metadata %s fails instead of selecting a generic profile', json => {
  put(fixture, 'composer.json', json);
  expect(() => validateVmBindings({ root: fixture })).toThrow('invalid composer.json');
});

test('real Jest validates its snapshot before executing tests and keeps generic fixtures supported', () => {
  pluginSnapshot(fixture);
  put(fixture, 'package.json', '{}');
  fs.symlinkSync(path.join(ROOT, 'node_modules'), path.join(fixture, 'node_modules'), 'dir');
  const sourcePath = BINDINGS.healthMonitor.source;
  const testPath = BINDINGS.healthMonitor.testPaths[0];
  const marker = path.join(fixture, 'executed');
  put(fixture, sourcePath, 'class Sample { decide(value) { return value ? 1 : 0; } }\n');
  put(fixture, testPath, `const fs=require('fs'),vm=require('vm'),path=require('path');test('real mapped source',()=>{const file=path.resolve(${JSON.stringify(sourcePath)});const Sample=new vm.Script(fs.readFileSync(file,'utf8')+'\\nSample;').runInNewContext({});expect(new Sample().decide(true)).toBe(1);fs.writeFileSync(${JSON.stringify(marker)},'executed');});`);
  const config = { rootDir: fixture, testEnvironment: 'node', transform: {}, testMatch: ['**/tests/JS/health/*.test.js'],
    collectCoverage: true, coverageProvider: 'v8', collectCoverageFrom: [sourcePath], coverageDirectory: path.join(fixture, 'output'),
    coverageReporters: ['json'], globalSetup: GLOBAL_SETUP, setupFilesAfterEnv: [SETUP] };
  const configPath = put(fixture, 'jest.config.cjs', 'module.exports=' + JSON.stringify(config));
  const execute = () => spawnSync(process.execPath, ['--no-compilation-cache', path.join(ROOT, 'node_modules/jest/bin/jest.js'), '--config', configPath, '--runInBand'], { cwd: fixture, encoding: 'utf8', timeout: 30000 });
  let result = execute();
  expect(result.status).toBe(0);
  const files = normalizeJs(JSON.parse(fs.readFileSync(path.join(fixture, 'output/coverage-final.json'))), fixture);
  expect(files[0].metrics.lines.covered).toBeGreaterThan(0);
  expect(fs.existsSync(marker)).toBe(true);
  fs.unlinkSync(marker);
  fs.renameSync(path.join(fixture, testPath), path.join(path.dirname(path.join(fixture, testPath)), 'renamed.test.js'));
  result = execute();
  expect(result.status).toBe(1);
  expect(result.stderr).toContain('healthMonitor has no test');
  expect(fs.existsSync(marker)).toBe(false);
  put(fixture, 'composer.json', JSON.stringify({ name: 'coverage-fixture/generic' }));
  result = execute();
  expect(result.status).toBe(0);
  expect(fs.existsSync(marker)).toBe(true);
}, 120000);

test('one real common Jest harness attributes both old and new aliases in separate revision snapshots', () => {
  const oldPath = BINDINGS.healthMonitor.testPaths[0];
  const newPath = 'tests/JS/health/renamed-health.test.js';
  // Only fixture configuration changes: both snapshots execute these same real
  // harness files, with one explicitly declared alias added to the binding.
  const original = fs.readFileSync(SETUP, 'utf8');
  const declaration = "'" + oldPath + "'";
  const configured = original.replace(declaration, declaration + ", '" + newPath + "'");
  if (configured === original) throw new Error('Fixture cannot locate the explicit binding declaration');
  const commonSetup = put(fixture, 'common-harness/jest-vm-metadata.js', configured);
  const commonGlobalSetup = put(fixture, 'common-harness/jest-global-setup.js', fs.readFileSync(GLOBAL_SETUP));
  const sourcePath = BINDINGS.healthMonitor.source;
  const receipts = [];
  for (const [revision, testPath, input, expected] of [['base', oldPath, true, 1], ['candidate', newPath, false, 0]]) {
    const root = path.join(fixture, revision);
    pluginSnapshot(root);
    if (testPath !== oldPath) fs.unlinkSync(path.join(root, oldPath));
    put(root, 'package.json', '{}');
    fs.symlinkSync(path.join(ROOT, 'node_modules'), path.join(root, 'node_modules'), 'dir');
    const source = 'class Sample { decide(value) { return value ? 1 : 0; } }\n';
    put(root, sourcePath, source);
    const body = `const fs=require('fs'),vm=require('vm'),path=require('path');test('own ${revision} assertion',()=>{const file=path.resolve(${JSON.stringify(sourcePath)});const Sample=new vm.Script(fs.readFileSync(file,'utf8')+'\\nSample;').runInNewContext({});expect(new Sample().decide(${input})).toBe(${expected});});`;
    const physicalTest = put(root, testPath, body);
    const config = { rootDir: root, testEnvironment: 'node', transform: {}, testMatch: ['**/tests/JS/health/*.test.js'],
      collectCoverage: true, coverageProvider: 'v8', collectCoverageFrom: [sourcePath], coverageDirectory: path.join(root, 'output'),
      coverageReporters: ['json'], globalSetup: commonGlobalSetup, setupFilesAfterEnv: [commonSetup] };
    const configPath = put(root, 'jest.config.cjs', 'module.exports=' + JSON.stringify(config));
    const result = spawnSync(process.execPath, ['--no-compilation-cache', path.join(ROOT, 'node_modules/jest/bin/jest.js'), '--config', configPath, '--runInBand'], { cwd: root, encoding: 'utf8', timeout: 30000 });
    expect(result.status).toBe(0);
    expect(fs.readFileSync(physicalTest, 'utf8')).toBe(body);
    expect(fs.readFileSync(path.join(root, sourcePath), 'utf8')).toBe(source);
    const files = normalizeJs(JSON.parse(fs.readFileSync(path.join(root, 'output/coverage-final.json'))), root);
    expect(files[0].path).toBe(sourcePath);
    expect(files[0].metrics.lines.covered).toBeGreaterThan(0);
    const directory = path.join(root, 'coverage/vm-metadata');
    const paths = fs.readdirSync(directory);
    expect(paths).toHaveLength(1);
    const receipt = JSON.parse(fs.readFileSync(path.join(directory, paths[0])));
    expect(receipt.test).toBe(testPath);
    expect(receipt.constructions).toHaveLength(1);
    expect(receipt.constructions[0].filename).toBe(path.join(root, sourcePath));
    expect(receipt.testUnchanged && receipt.sourceUnchanged && receipt.restored).toBe(true);
    receipts.push(receipt);
  }
  expect(receipts[0].testSha256Before).not.toBe(receipts[1].testSha256Before);
}, 120000);
