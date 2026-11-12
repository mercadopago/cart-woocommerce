const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');
const ROOT = path.resolve(__dirname, '../../../..');

test('real JS CLI uses a symlink snapshot without vendor, writes outside it, and rejects test failures, no tests and deadlines without stale artifacts', () => {
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'coverage-cli-'));
  const output = fs.mkdtempSync(path.join(os.tmpdir(), 'coverage-output-'));
  const alias = fixture + '-alias';
  try {
    fs.symlinkSync(fixture, alias, 'dir');
    fs.symlinkSync(path.join(ROOT, 'node_modules'), path.join(fixture, 'node_modules'), 'dir');
    for (const directory of ['assets/js', 'packages/narciso', 'tests/JS']) fs.mkdirSync(path.join(fixture, directory), { recursive: true });
    for (const lock of ['composer.lock', 'package-lock.json']) fs.copyFileSync(path.join(ROOT, lock), path.join(fixture, lock));
    fs.writeFileSync(path.join(fixture, 'package.json'), '{}');
    fs.writeFileSync(path.join(fixture, 'jest.setup.js'), '');
    fs.writeFileSync(path.join(fixture, 'assets/js/manual.js'), 'module.exports = value => value + 1;\n');
    const testFile = path.join(fixture, 'tests/JS/source.test.js');
    const healthy = 'test("real source", () => expect(require("../../assets/js/manual")(1)).toBe(2));';
    fs.writeFileSync(testFile, healthy);
    const git = args => {
      const result = spawnSync('git', args, { cwd: fixture, encoding: 'utf8' });
      expect(result.status).toBe(0);
    };
    git(['init', '--quiet']);
    git(['config', '--local', 'commit.gpgsign', 'false']);
    git(['add', 'package.json']);
    git(['-c', 'core.hooksPath=/dev/null', '-c', 'user.name=Coverage fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '--quiet', '-m', 'fixture']);
    const originalIndex = fs.readFileSync(path.join(fixture, '.git/index'));
    const report = path.join(output, 'report.json');
    const execute = timeout => spawnSync(process.execPath, [path.join(ROOT, 'scripts/coverage/run.js'), '--root', alias, '--language', 'js', '--variant', 'v2', '--collect-only', '--output-dir', output, '--report-json', report, '--timeout-ms', String(timeout || 30000)], { cwd: fixture, env: { ...process.env, GIT_DIR: '/must-not-use-hook-repository', GIT_INDEX_FILE: path.join(output, 'caller-index') }, encoding: 'utf8', timeout: 35000 });
    let result = execute();
    expect(result.stderr).toBe('');
    expect(result.status).toBe(0);
    expect(fs.readFileSync(path.join(fixture, '.git/index')).equals(originalIndex)).toBe(true);
    const measured = JSON.parse(fs.readFileSync(report));
    expect(measured.runtimes.php).toBeUndefined();
    expect(measured.revision.commit).toMatch(/^[a-f0-9]{40}$/);
    expect(fs.existsSync(path.join(output, 'caller-index'))).toBe(false);
    expect(measured.results[0].files[0].path).toBe('assets/js/manual.js');
    expect(measured.results[0].modules.global.lines.covered).toBeGreaterThan(0);
    fs.writeFileSync(testFile, healthy.replace('toBe(2)', 'toBe(999)'));
    expect(execute().status).not.toBe(0);
    expect(fs.existsSync(report)).toBe(false);
    fs.unlinkSync(testFile);
    expect(execute().status).not.toBe(0);
    expect(fs.existsSync(report)).toBe(false);
    expect(fs.existsSync(path.join(output, 'js/v2/coverage-final.json'))).toBe(false);
    fs.writeFileSync(testFile, healthy);
    result = execute(1);
    expect(result.status).not.toBe(0);
    expect(result.stderr).toContain('deadline exceeded');
    expect(fs.existsSync(report)).toBe(false);
    expect(execute().status).toBe(0);
  } finally {
    fs.rmSync(alias, { force: true });
    fs.rmSync(fixture, { recursive: true, force: true });
    fs.rmSync(output, { recursive: true, force: true });
  }
}, 120000);

test('real CLI preserves native initializer hits across Inspector sessions', () => {
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'coverage-v8-cache-'));
  const report = path.join(fixture, 'output/report.json');
  let result;
  try {
    fs.symlinkSync(path.join(ROOT, 'node_modules'), path.join(fixture, 'node_modules'), 'dir');
    for (const directory of ['assets/js', 'packages/narciso', 'tests/JS', 'output']) fs.mkdirSync(path.join(fixture, directory), { recursive: true });
    for (const lock of ['composer.lock', 'package-lock.json']) fs.copyFileSync(path.join(ROOT, lock), path.join(fixture, lock));
    fs.writeFileSync(path.join(fixture, 'package.json'), '{}');
    fs.writeFileSync(path.join(fixture, 'assets/js/field.js'), 'class FieldOwner { delay = 3000; read() { return this.delay; } }\nmodule.exports = FieldOwner;\n');
    fs.writeFileSync(path.join(fixture, 'native-probe.cjs'), `
const fs = require('fs'), path = require('path'), vm = require('vm');
const { Session } = require('inspector');
const { pathToFileURL } = require('url');
const { mergeProcessCovs } = require('@bcoe/v8-coverage');
const filename = fs.realpathSync(path.join(__dirname, 'assets/js/field.js'));
const code = fs.readFileSync(filename, 'utf8');
async function observe(instantiate) {
  const session = new Session();
  session.connect();
  const post = (method, params = {}) => new Promise((resolve, reject) => session.post(method, params, (error, value) => error ? reject(error) : resolve(value)));
  try {
    await post('Profiler.enable');
    await post('Profiler.startPreciseCoverage', { callCount: true, detailed: true });
    const factory = vm.compileFunction(code, ['module'], { filename, parsingContext: vm.createContext({}) });
    const module = { exports: {} };
    factory(module);
    const value = instantiate ? new module.exports().read() : null;
    const coverage = await post('Profiler.takePreciseCoverage');
    await post('Profiler.stopPreciseCoverage');
    return { value, result: coverage.result.filter(item => item.url === pathToFileURL(filename).href) };
  } finally { session.disconnect(); }
}
(async () => {
  const observations = [await observe(false), await observe(true)];
  const merged = mergeProcessCovs(JSON.parse(JSON.stringify(observations.map(item => ({ result: item.result })))));
  const initializer = merged.result[0].functions.find(item => item.functionName === '<instance_members_initializer>');
  const result = { node: process.versions.node, execArgv: process.execArgv, observations, initializerHits: initializer.ranges[0].count };
  fs.writeFileSync(path.join(__dirname, 'native-result.json'), JSON.stringify(result));
  process.send(result, () => process.disconnect());
})().catch(error => { process.stderr.write(error.stack); process.exitCode = 1; });
`);
    fs.writeFileSync(path.join(fixture, 'tests/JS/field.test.js'), `
const path = require('path');
const { fork } = require('child_process');
test('real initialized value and native merged hits', async () => {
  const child = fork(path.resolve('native-probe.cjs'), { stdio: ['ignore', 'pipe', 'pipe', 'ipc'] });
  let stderr = '', value;
  child.stdout.resume();
  child.stderr.on('data', bytes => { stderr += bytes; });
  await new Promise((resolve, reject) => {
    const timer = setTimeout(() => { child.kill('SIGKILL'); reject(new Error('Native probe deadline exceeded')); }, 5000);
    child.once('message', result => { value = result; });
    child.once('error', error => { clearTimeout(timer); reject(error); });
    child.once('close', code => { clearTimeout(timer); code === 0 ? resolve() : reject(new Error(stderr || 'Native probe failed: ' + code)); });
  });
  expect(value.observations.map(item => item.value)).toEqual([null, 3000]);
  expect(value.initializerHits).toBe(1);
});
`);
    // A rejected collection must not preserve a previously successful report.
    fs.writeFileSync(report, '{"valid":true}\n');
    result = spawnSync(process.execPath, [path.join(ROOT, 'scripts/coverage/run.js'), '--root', fixture, '--language', 'js', '--variant', 'v2', '--collect-only', '--output-dir', path.join(fixture, 'output'), '--report-json', report], { cwd: fixture, encoding: 'utf8', timeout: 45000 });
    expect(result.error).toBeUndefined();
    expect(result.status).toBe(0);
    expect(result.stderr).toBe('');
    expect(JSON.parse(fs.readFileSync(report)).valid).toBe(true);
    const measured = JSON.parse(fs.readFileSync(path.join(fixture, 'native-result.json')));
    expect(measured.observations.map(item => item.value)).toEqual([null, 3000]);
    expect(measured.initializerHits).toBe(1);
  } finally {
    if (process.env.COVERAGE_V8_PROOF_DIR) {
      const output = process.env.COVERAGE_V8_PROOF_DIR;
      fs.mkdirSync(output, { recursive: true });
      fs.writeFileSync(path.join(output, 'cli-result.json'), JSON.stringify({ status: result?.status, stdout: result?.stdout, stderr: result?.stderr, reportExists: fs.existsSync(report) }, null, 2));
      for (const filename of ['native-result.json', 'native-probe.cjs', 'tests/JS/field.test.js', 'assets/js/field.js', 'output/js/v2/test-results.json', 'output/js/v2/run.log']) {
        if (fs.existsSync(path.join(fixture, filename))) fs.copyFileSync(path.join(fixture, filename), path.join(output, filename.replaceAll('/', '-')));
      }
    }
    fs.rmSync(fixture, { recursive: true, force: true });
  }
}, 60000);
