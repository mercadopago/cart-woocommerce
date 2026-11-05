const fs = require('fs');
const os = require('os');
const path = require('path');
const vm = require('vm');
const { spawnSync } = require('child_process');
const { createHash } = require('crypto');
const { Session } = require('inspector');
const { pathToFileURL } = require('url');
const { installVmMetadata } = require('../../../../scripts/coverage/jest-vm-metadata');
const { normalizeJs } = require('../../../../scripts/coverage/report');
const ROOT = path.resolve(__dirname, '../../../..');
const SETUP = path.join(ROOT, 'scripts/coverage/jest-vm-metadata.js');
const OriginalScript = vm.Script;
let fixture;
let installed;

beforeEach(() => {
  fixture = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'vm-metadata-')));
  fs.mkdirSync(path.join(fixture, 'src'));
  fs.mkdirSync(path.join(fixture, 'tests'));
  fs.writeFileSync(path.join(fixture, 'tests/a.test.js'), '// original assertion bytes\n');
  fs.writeFileSync(path.join(fixture, 'tests/b.test.js'), '// different assertion bytes\n');
});
afterEach(() => {
  if (installed) installed.restore();
  installed = null;
  vm.Script = OriginalScript;
  fs.rmSync(fixture, { recursive: true, force: true });
});

function install(source, test = 'tests/a.test.js', sourcePath = 'src/a.js') {
  fs.writeFileSync(path.join(fixture, sourcePath), source);
  installed = installVmMetadata({ root: fixture, testPath: path.join(fixture, test), bindings: { fixture: { source: sourcePath, testPaths: [test] } } });
  return installed;
}

test('real constructor preserves code, context, options, cached data and errors; hashes remain identical', () => {
  const source = 'globalThis.calls++; if (value === "fail") throw new Error("real failure"); globalThis.answer = value + 1;\n';
  install(source);
  const options = Object.freeze({ filename: 'synthetic.js',
    get lineOffset() { expect(this).toBe(options); return 2; }, columnOffset: 3, produceCachedData: true,
    get unused() { throw new Error('unused getter must not run'); } });
  const descriptors = Object.getOwnPropertyDescriptors(options);
  const script = new vm.Script(source + '\nanswer;', options);
  expect(script).toBeInstanceOf(OriginalScript);
  expect(script.cachedDataProduced).toBe(true);
  const context = { calls: 0, value: 4 };
  expect(script.runInNewContext(context)).toBe(5);
  expect(context.calls).toBe(1);
  expect(context.answer).toBe(5);
  let error;
  try { script.runInNewContext({ calls: 0, value: 'fail' }); } catch (caught) { error = caught; }
  expect(error.message).toBe('real failure');
  expect(error.stack).toContain(path.join(fixture, 'src/a.js') + ':3');
  expect(Object.getOwnPropertyDescriptors(options)).toEqual(descriptors);
  const receipt = installed.restore();
  expect(receipt.testSha256After).toBe(receipt.testSha256Before);
  expect(receipt.sourceSha256After).toBe(receipt.sourceSha256Before);
  expect(receipt.testUnchanged && receipt.sourceUnchanged && receipt.restored).toBe(true);
  expect(vm.Script).toBe(OriginalScript);
});

test('physical identity follows the explicit map when two source files have identical contents', () => {
  const source = 'throw new Error("same physical source contents");\n';
  for (const [test, sourcePath] of [['tests/a.test.js', 'src/a.js'], ['tests/b.test.js', 'src/b.js']]) {
    install(source, test, sourcePath);
    let stack;
    try { new vm.Script(source).runInNewContext({}); } catch (error) { stack = error.stack; }
    expect(stack).toContain(path.join(fixture, sourcePath));
    const receipt = installed.restore();
    expect(receipt.source).toBe(sourcePath);
    expect(receipt.constructions).toHaveLength(1);
  }
});

test('only known source bodies receive metadata; unrelated code and invalid options keep real behavior', () => {
  install('globalThis.answer = 1;\n');
  expect(new vm.Script('12 + 30').runInNewContext({})).toBe(42);
  expect(() => new vm.Script('globalThis.answer = 1;\n', null)).toThrow();
  expect(() => new vm.Script('not valid javascript !!!')).toThrow("Unexpected identifier 'valid'");
  const receipt = installed.restore();
  expect(receipt.constructions).toHaveLength(0);
  expect(receipt.unmatchedConstructions).toBe(3);
});

test('export removal and appended exposure execute intact; restore never clobbers another owner', () => {
  const source = 'function decide() { return 17; }\nexport { decide };\n';
  install(source);
  const body = source.replace(/^export\s*\{[^}]*\};?\s*$/m, '') + '\ndecide();';
  expect(new vm.Script(body).runInNewContext({})).toBe(17);
  const otherOwner = new Proxy(OriginalScript, {});
  vm.Script = otherOwner;
  const receipt = installed.restore();
  expect(receipt.constructions[0].form).toBe('export-removed');
  expect(receipt.restored).toBe(false);
  expect(vm.Script).toBe(otherOwner);
});

test('receipt detects mutation of disposable test input after restoring the original constructor', () => {
  install('globalThis.answer = 1;\n');
  fs.appendFileSync(path.join(fixture, 'tests/a.test.js'), '// changed\n');
  expect(() => installed.restore()).toThrow('input changed');
  expect(vm.Script).toBe(OriginalScript);
});

test('suite isolation appends only an identity comment to actual compiled code and leaves unknown scripts intact', async () => {
  const source = 'class Sample { state = null; decide(value) { if (value < 0) throw new Error("real failure"); return value + 1; } }\n';
  const filename = path.join(fixture, 'src/a.js');
  const unknown = path.join(fixture, 'unknown.js');
  fs.writeFileSync(filename, source);
  const code = source + '\nSample;';
  const digest = value => createHash('sha256').update(value).digest('hex');
  const session = new Session();
  const scripts = new Map();
  session.connect();
  session.on('Debugger.scriptParsed', event => scripts.set(event.params.url, event.params.scriptId));
  const post = (method, params = {}) => new Promise((resolve, reject) => {
    session.post(method, params, (error, result) => error ? reject(error) : resolve(result));
  });
  const compiled = [];
  try {
    await post('Debugger.enable');
    for (const testPath of ['tests/a.test.js', 'tests/b.test.js']) {
      installed = installVmMetadata({ root: fixture, testPath: path.join(fixture, testPath), bindings: {}, contextBindings: { fixture: { source: 'src/a.js', testPaths: ['tests/a.test.js', 'tests/b.test.js'] } } });
      const options = Object.freeze({ filename,
        get lineOffset() { expect(this).toBe(options); return 2; },
        get unused() { throw new Error('unused getter must not run'); } });
      const descriptors = Object.getOwnPropertyDescriptors(options);
      const Sample = new vm.Script(code, options).runInNewContext({});
      const instance = new Sample();
      expect(instance.state).toBeNull();
      expect(instance.decide(2)).toBe(3);
      expect(() => instance.decide(-1)).toThrow('real failure');
      expect(Object.getOwnPropertyDescriptors(options)).toEqual(descriptors);
      const scriptId = scripts.get(pathToFileURL(fs.realpathSync(filename)).href);
      expect(typeof scriptId).toBe('string');
      const native = await post('Debugger.getScriptSource', { scriptId });
      const expected = code + '\n// woo-coverage-suite:' + digest(testPath) + '\n';
      expect(native.scriptSource).toBe(expected);
      compiled.push(native.scriptSource);
      expect(new vm.Script('40 + 2', { filename: unknown }).runInNewContext({})).toBe(42);
      const unknownId = scripts.get(pathToFileURL(unknown).href);
      expect(typeof unknownId).toBe('string');
      expect((await post('Debugger.getScriptSource', { scriptId: unknownId })).scriptSource).toBe('40 + 2');
      const receipt = installed.restore();
      expect(receipt.constructions[0].codeSha256).toBe(digest(code));
      expect(receipt.constructions[0].compiledCodeSha256).toBe(digest(expected));
      expect(receipt.constructions[0].suiteIdentity).toBe(digest(testPath));
      expect(receipt.unmatchedConstructions).toBe(1);
      expect(receipt.sourceUnchanged && receipt.testUnchanged).toBe(true);
    }
    expect(compiled[0]).not.toBe(compiled[1]);
    expect(installVmMetadata({ root: fixture, testPath: path.join(fixture, 'tests/a.test.js'), bindings: {}, contextBindings: {} })).toBeNull();
    expect(vm.Script).toBe(OriginalScript);
  } finally {
    session.disconnect();
  }
});

test('real Jest attributes identical coverage with or without filename, while unknown/outside-map scripts stay untouched', () => {
  const sourcePath = 'assets/js/health/mp-health-monitor.js';
  const testPath = 'tests/JS/health/mp-health-monitor.test.js';
  fs.mkdirSync(path.dirname(path.join(fixture, sourcePath)), { recursive: true });
  fs.mkdirSync(path.dirname(path.join(fixture, testPath)), { recursive: true });
  fs.rmSync(path.join(fixture, 'tests/a.test.js'));
  fs.rmSync(path.join(fixture, 'tests/b.test.js'));
  fs.symlinkSync(path.join(ROOT, 'node_modules'), path.join(fixture, 'node_modules'), 'dir');
  fs.writeFileSync(path.join(fixture, 'package.json'), '{}');
  fs.writeFileSync(path.join(fixture, sourcePath), 'class Sample { decide(value) { return value ? 1 : 0; } }\n');
  const config = { rootDir: fixture, testEnvironment: 'node', transform: {}, testMatch: ['**/*.test.js'],
    collectCoverage: true, coverageProvider: 'v8', collectCoverageFrom: ['assets/js/**/*.js'],
    coverageReporters: ['json'], coverageDirectory: path.join(fixture, 'output'), setupFilesAfterEnv: [SETUP] };
  fs.writeFileSync(path.join(fixture, 'jest.config.cjs'), 'module.exports=' + JSON.stringify(config));
  const execute = (filename, body = null, target = testPath) => {
    fs.rmSync(path.join(fixture, 'coverage'), { recursive: true, force: true });
    const test = `const fs=require('fs'),vm=require('vm'),path=require('path'); const file=path.resolve(${JSON.stringify(sourcePath)});test('real source',()=>{const code=${body ? JSON.stringify(body) : "fs.readFileSync(file,'utf8')+'\\nSample;'"}; const result=new vm.Script(code,${filename ? '{filename:file}' : '{}'}).runInNewContext({}); ${body ? 'expect(result).toBe(42)' : 'expect(new result().decide(true)).toBe(1)'};});`;
    fs.writeFileSync(path.join(fixture, target), test);
    const before = fs.readFileSync(path.join(fixture, target));
    const result = spawnSync(process.execPath, [path.join(ROOT, 'node_modules/jest/bin/jest.js'), '--config', path.join(fixture, 'jest.config.cjs'), '--runInBand'], { cwd: fixture, encoding: 'utf8', timeout: 30000 });
    expect(result.stderr.includes('FAIL')).toBe(false);
    expect(result.status).toBe(0);
    expect(fs.readFileSync(path.join(fixture, target)).equals(before)).toBe(true);
    return normalizeJs(JSON.parse(fs.readFileSync(path.join(fixture, 'output/coverage-final.json'))), fixture)[0];
  };
  const implicit = execute(false);
  const explicit = execute(true);
  expect(implicit.metrics).toEqual(explicit.metrics);
  expect(implicit.lines).toEqual(explicit.lines);
  expect(implicit.metrics.lines.covered).toBeGreaterThan(0);
  expect(execute(false, '12+30').metrics.lines.covered).toBe(0);
  fs.rmSync(path.join(fixture, testPath));
  expect(execute(false, null, 'tests/outside.test.js').metrics.lines.covered).toBe(0);
}, 120000);
