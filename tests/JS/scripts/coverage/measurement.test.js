const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');
const { normalizeJs, modulesFor, enforce, validateFiles, metric, relativeSource, validateInventory } = require('../../../../scripts/coverage/report');
const { options, assertNodeRuntime } = require('../../../../scripts/coverage/run');

const ROOT = path.resolve(__dirname, '../../../..');
const jestBin = path.join(ROOT, 'node_modules/jest/bin/jest.js');
let fixture;

function execute(config, test) {
  fs.writeFileSync(path.join(fixture, 'test.test.js'), test);
  fs.writeFileSync(path.join(fixture, 'jest.config.cjs'), `module.exports=${JSON.stringify({
    rootDir: fixture, testEnvironment: 'node', testMatch: ['<rootDir>/test.test.js'], transform: {},
    collectCoverage: true, coverageProvider: 'v8', coverageReporters: ['json'], coverageDirectory: path.join(fixture, 'output'),
    collectCoverageFrom: ['src/**/*.js', '!src/**/*.bundle.js'], ...config,
  })}`);
  return spawnSync(process.execPath, [jestBin, '--config', path.join(fixture, 'jest.config.cjs'), '--runInBand'], {
    cwd: fixture, encoding: 'utf8', timeout: 30000,
  });
}

beforeEach(() => {
  fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'coverage-measurement-'));
  fs.mkdirSync(path.join(fixture, 'src'));
  fs.symlinkSync(path.join(ROOT, 'node_modules'), path.join(fixture, 'node_modules'), 'dir');
  fs.writeFileSync(path.join(fixture, 'package.json'), '{}');
  fs.writeFileSync(path.join(fixture, 'src/live.js'), 'module.exports = value => { if (value > 0) return value + 1; return 0; };\n');
  fs.writeFileSync(path.join(fixture, 'src/untested.js'), 'module.exports = value => value * 2;\n');
  fs.writeFileSync(path.join(fixture, 'src/generated.bundle.js'), 'throw new Error("generated output must not be measured");\n');
});
afterEach(() => fs.rmSync(fixture, { recursive: true, force: true }));

const TEST = 'test("real source", () => expect(require("./src/live")(1)).toBe(2));';

test.each(['22.23.4', '22.23.5', '22.24.0'])('coverage runtime accepts supported Node version %s', version => {
  expect(() => assertNodeRuntime(version)).not.toThrow();
});

test.each(['20.20.2', '22.22.99', '22.23.3', '23.0.0', '24.14.0', '22.23.4-rc.1', '22.23', '22.023.4'])('coverage runtime rejects incompatible Node version %s', version => {
  expect(() => assertNodeRuntime(version)).toThrow('Coverage requires Node 22.23.4 or newer in the 22.x line');
});

test('real Jest measures manual untested code at zero and excludes generated bundles', () => {
  const result = execute({}, TEST);
  expect(result.status).toBe(0);
  const raw = JSON.parse(fs.readFileSync(path.join(fixture, 'output/coverage-final.json')));
  const files = normalizeJs(raw, fixture);
  validateFiles(files);
  expect(files.map(file => file.path)).toEqual(['src/live.js', 'src/untested.js']);
  expect(files.find(file => file.path.endsWith('untested.js')).metrics.lines.covered).toBe(0);
  const modules = modulesFor(files, { global: { path: './' } });
  expect(modules.global.lines.covered).toBeLessThan(modules.global.lines.total);
  expect(() => enforce({ language: 'js', variant: 'v2', modules }, { global: { floors: { lines: 100 } } })).toThrow('Coverage thresholds failed');
});

test('real Jest root PATH enforces total coverage even when a module PATH matches', () => {
  const result = execute({ coverageThreshold: { './': { lines: 100 }, './src/live.js': { lines: 0 } } }, TEST);
  expect(result.status).toBe(1);
  expect(result.stderr).toContain('coverage threshold for lines (100%) not met');
  expect(result.stderr).toContain('"./"');
});

test('real Jest module PATH enforces branches independently of an accepted total floor', () => {
  const result = execute({ coverageThreshold: { './': { lines: 0 }, './src/live.js': { branches: 100 } } }, TEST);
  expect(result.status).toBe(1);
  expect(result.stderr).toContain('coverage threshold for branches (100%) not met');
});

test('real VM execution is attributed to its physical source; omitting filename is a negative control', () => {
  fs.writeFileSync(path.join(fixture, 'src/vm.js'), 'class Sample { compute(value) { return value ? "yes" : "no"; } }\n');
  const source = filename => `const fs=require('fs'),vm=require('vm'),p=require('path');const file=p.resolve('src/vm.js');test('VM source',()=>{const Sample=new vm.Script(fs.readFileSync(file,'utf8')+'\\nSample;',${filename ? '{filename:file}' : '{}'}).runInNewContext({});expect(new Sample().compute(true)).toBe('yes')});`;
  expect(execute({}, source(true)).status).toBe(0);
  let raw = JSON.parse(fs.readFileSync(path.join(fixture, 'output/coverage-final.json')));
  expect(normalizeJs(raw, fixture).find(file => file.path === 'src/vm.js').metrics.lines.covered).toBeGreaterThan(0);
  expect(execute({}, source(false)).status).toBe(0);
  raw = JSON.parse(fs.readFileSync(path.join(fixture, 'output/coverage-final.json')));
  expect(normalizeJs(raw, fixture).find(file => file.path === 'src/vm.js').metrics.lines.covered).toBe(0);
});

test('invalid reports and unsupported CLI options fail instead of fabricating green coverage', () => {
  expect(() => metric(2, 1)).toThrow('Invalid coverage counts');
  expect(metric(0, 0).pct).toBeNull();
  expect(() => validateFiles([])).toThrow('no source files');
  expect(() => options(['--language', 'unknown'])).toThrow('Invalid coverage language');
  expect(() => options(['--pretend-success'])).toThrow('Unknown');
});


test('snapshot symlink canonicalizes to the same source and outside sources remain invalid', () => {
  const link = fixture + '-link';
  fs.symlinkSync(fixture, link, 'dir');
  try {
    expect(relativeSource(link, path.join(fixture, 'src/live.js'))).toBe('src/live.js');
    expect(() => relativeSource(fixture, __filename)).toThrow('outside snapshot');
    expect(() => validateInventory([], ['src/live.js'])).toThrow('inventory mismatch');
  } finally { fs.unlinkSync(link); }
});

test('deadline kills a real non-cooperative child and grandchild process group', async () => {
  const { run } = require('../../../../scripts/coverage/process');
  const late = path.join(fixture, 'late-write');
  const grandchild = `process.on('SIGTERM',()=>{});setTimeout(()=>require('fs').writeFileSync(${JSON.stringify(late)},'leaked'),800);setInterval(()=>{},1000);`;
  const child = `process.on('SIGTERM',()=>{});require('child_process').spawn(process.execPath,['-e',${JSON.stringify(grandchild)}],{stdio:'inherit'});setInterval(()=>{},1000);`;
  await expect(run(process.execPath, ['-e', child], { cwd: fixture, timeout: 150 })).rejects.toThrow('TIMEOUT');
  await new Promise(resolve => setTimeout(resolve, 1000));
  expect(fs.existsSync(late)).toBe(false);
});

test('real Jest and collector accept the tolerated floor and reject the next basis point', () => {
  const { jestThresholds } = require('../../../../scripts/coverage/policy');
  const approved = {
    tolerance: { floorBasisPoints: 10, baseComparisonBasisPoints: 10 },
    js: { global: { path: './', floors: { functions: 50.1 } } },
  };
  const accepted = execute({ coverageThreshold: jestThresholds(approved) }, TEST);
  expect(accepted.status).toBe(0);
  const files = normalizeJs(JSON.parse(fs.readFileSync(path.join(fixture, 'output/coverage-final.json'))), fixture);
  const result = { language: 'js', modules: modulesFor(files, approved.js) };
  expect(result.modules.global.functions).toMatchObject({ covered: 1, total: 2 });
  expect(() => enforce(result, approved.js, approved)).not.toThrow();

  approved.js.global.floors.functions = 50.11;
  const rejected = execute({ coverageThreshold: jestThresholds(approved) }, TEST);
  expect(rejected.status).toBe(1);
  expect(rejected.stderr).toContain('coverage threshold for functions (50.01%) not met');
  expect(() => enforce(result, approved.js, approved)).toThrow('Coverage thresholds failed');
});
