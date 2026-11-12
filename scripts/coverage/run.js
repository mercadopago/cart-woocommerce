#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const { spawnSync } = require('child_process');
const { run: runProcess, cancel } = require('./process');
const { randomUUID } = require('crypto');
const { DockerPhp } = require('./docker-php');
const { phpProfile } = require('./php-profile');
const { validatePolicy } = require('./policy');
let dockerPhp;
const { metric, relativeSource, normalizeJs, modulesFor, enforce, validateFiles, sourceInventory, validateInventory, hashFiles } = require('./report');
const HARNESS = path.resolve(__dirname, '../..');
let deadline;

function assertNodeRuntime(version = process.versions.node) {
  const match = /^22\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.exec(version);
  if (!match || Number(match[1]) < 23 || (Number(match[1]) === 23 && Number(match[2]) < 4)) {
    throw new Error('Coverage requires Node 22.23.4 or newer in the 22.x line');
  }
}

function options(argv) {
  const result = { root: process.cwd(), language: 'all', variant: 'all', 'collect-only': false, 'timeout-ms': 1800000, 'php-batch-size': 50, 'php-profile': process.env.COVERAGE_PHP_PROFILE || 'woo' };
  for (let index = 0; index < argv.length; index++) {
    const name = argv[index].replace(/^--/, '');
    if (name === 'collect-only') result[name] = true;
    else if (['root', 'output-dir', 'report-json', 'language', 'variant', 'timeout-ms', 'php-docker-image', 'php-batch-size', 'php-profile'].includes(name) && argv[index + 1]) result[name] = argv[++index];
    else throw new Error(`Unknown or missing coverage option: ${argv[index]}`);
  }
  if (!['js', 'php', 'all'].includes(result.language) || !['v2', 'v2.1', 'all'].includes(result.variant)) throw new Error('Invalid coverage language/variant');
  result['timeout-ms'] = Number(result['timeout-ms']);
  if (!Number.isInteger(result['timeout-ms']) || result['timeout-ms'] < 1) throw new Error('Invalid coverage timeout');
  result['php-batch-size'] = Number(result['php-batch-size']);
  if (!Number.isInteger(result['php-batch-size']) || result['php-batch-size'] < 1 || result['php-batch-size'] > 50) throw new Error('Invalid PHP batch size');
  result.root = fs.realpathSync(result.root);
  result['output-dir'] = path.resolve(result['output-dir'] || path.join(result.root, 'coverage', 'measurement'));
  result['report-json'] = path.resolve(result['report-json'] || path.join(result['output-dir'], 'report.json'));
  return result;
}

async function run(command, args, root, env = {}, log, cleanup = false) {
  if (command === '__docker_php__') return dockerPhp.execute(args, env, log);
  const invocation = `woo-coverage-${randomUUID()}`;
  try {
    const result = await runProcess(command, args, { cwd: root, env: { ...env, COVERAGE_INVOCATION_ID: invocation }, timeout: cleanup ? 10000 : Math.max(1, deadline - Date.now()) });
    if (log) fs.writeFileSync(log, result.stdout + result.stderr);
    return result.stdout.trim();
  } catch (error) {
    if (log) fs.writeFileSync(log, (error.stdout || '') + (error.stderr || '') + error.message);
    if (command === process.env.COVERAGE_PHP && process.env.COVERAGE_PHP_CLEANUP && ['TIMEOUT', 'CANCELLED'].includes(error.failure)) {
      await runProcess(process.env.COVERAGE_PHP_CLEANUP, [invocation], { cwd: root, timeout: 10000 });
    }
    if (error.failure === 'TIMEOUT') throw new Error(`Coverage deadline exceeded: ${command}; see ${log || 'runtime probe'}`);
    throw error;
  }
}

function git(root, args) {
  const result = spawnSync('git', ['--no-optional-locks', '-C', root, ...args], { encoding: 'utf8' });
  return result.status === 0 ? result.stdout.trim() : null;
}

function cleanArtifacts(directory) {
  fs.mkdirSync(directory, { recursive: true });
  fs.rmSync(path.join(directory, 'vm-metadata'), { recursive: true, force: true });
  fs.rmSync(path.join(directory, 'bootstrap-receipts'), { recursive: true, force: true });
  const names = ['coverage-final.json', 'coverage-summary.json', 'run.log', 'raw.json', 'tests.xml', 'executed-tests.json', 'partitions.json', 'clover.xml', 'jest.config.cjs', 'phpunit.xml', 'test-results.json', 'bootstrap-context.json', 'bootstrap.php', 'bootstrap-declarations.php', 'bootstrap.log', 'php-bootstrap-summary.json'];
  for (const filename of fs.readdirSync(directory)) {
    if (names.includes(filename) || /^partition-\d+\.(php|log|xml)$/.test(filename)) fs.rmSync(path.join(directory, filename), { force: true });
  }
}

async function collectJs(opts, variant, policy) {
  const directory = path.join(opts['output-dir'], 'js', variant);
  cleanArtifacts(directory);
  const config = path.join(directory, 'jest.config.cjs');
  // Use the approved harness on both snapshots. Only each snapshot's sources/tests differ.
  fs.writeFileSync(config, `const base = require(${JSON.stringify(path.join(HARNESS, 'jest.config.js'))});\nmodule.exports = { ...base, rootDir: ${JSON.stringify(opts.root)}, coverageDirectory: ${JSON.stringify(directory)}, setupFilesAfterEnv: [${JSON.stringify(path.join(HARNESS, 'jest.setup.js'))}, ${JSON.stringify(path.join(HARNESS, 'scripts/coverage/jest-vm-metadata.js'))}], globalSetup: ${JSON.stringify(path.join(HARNESS, 'scripts/coverage/jest-global-setup.js'))}, coverageReporters: ['json','json-summary'], ${opts['collect-only'] ? 'coverageThreshold: undefined,' : ''} };\n`);
  try {
    await run(process.execPath, ['--no-compilation-cache', path.join(opts.root, 'node_modules/jest/bin/jest.js'), '--config', config, '--runInBand', '--coverage', '--json', '--outputFile', path.join(directory, 'test-results.json')], opts.root,
      { SUPER_TOKEN_VERSION: variant, COVERAGE_VM_RECEIPT_DIR: path.join(directory, 'vm-metadata') }, path.join(directory, 'run.log'));
  } catch (error) {
    for (const filename of ['coverage-final.json', 'coverage-summary.json']) fs.rmSync(path.join(directory, filename), { force: true });
    throw error;
  }
  const testResults = JSON.parse(fs.readFileSync(path.join(directory, 'test-results.json'), 'utf8'));
  if (!testResults.success || !testResults.numTotalTests) throw new Error('Jest did not execute a successful non-empty suite');
  const artifact = path.join(directory, 'coverage-final.json');
  const files = normalizeJs(JSON.parse(fs.readFileSync(artifact, 'utf8')), opts.root);
  validateFiles(files);
  validateInventory(files, sourceInventory(opts.root, 'js', policy.js));
  const result = { language: 'js', variant, provider: 'v8', files, modules: modulesFor(files, policy.js), artifacts: { coverage: artifact, tests: path.join(directory, 'test-results.json'), vmMetadata: path.join(directory, 'vm-metadata'), log: path.join(directory, 'run.log') } };
  if (!opts['collect-only']) enforce(result, policy.js, policy);
  return result;
}

// PHPUnit applies phpunit.xml <ini> values at runtime, after the -d flag, so the plain suite limit
// (512M) used to replace the collector limit. With path coverage, the parent of process-isolated tests
// unserializes large child results and ran out of memory. Only the generated config changes.
const PHP_COVERAGE_MEMORY_LIMIT = '2G';

function phpunitCoverageConfig(xml, { root, bootstrap, directory }) {
  const escape = (value) => value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
  const escapedRoot = escape(root);
  return xml.replace('bootstrap="tests/bootstrap.php"', `bootstrap="${escape(bootstrap)}"`)
    .replace(/>src\//g, `>${escapedRoot}/src/`).replace('>tests</directory>', `>${escapedRoot}/tests</directory>`)
    .replace('cacheDirectory=".phpunit.cache/code-coverage"', `cacheDirectory="${directory}/cache"`)
    .replace(/<ini name="memory_limit" value="[^"]*"\s*\/>/, `<ini name="memory_limit" value="${PHP_COVERAGE_MEMORY_LIMIT}"/>`);
}

async function collectPhp(opts, policy, php) {
  const directory = path.join(opts['output-dir'], 'php');
  cleanArtifacts(directory);
  const recipePath = path.join(HARNESS, 'scripts/coverage/php-bootstrap.json');
  const recipe = JSON.parse(fs.readFileSync(recipePath));
  const selected = phpProfile(opts.root, opts['php-profile'], recipe, sourceInventory(opts.root, 'php', policy.php));
  const context = { ...selected, root: opts.root, directory, recipe, recipeSha256: require('crypto').createHash('sha256').update(fs.readFileSync(recipePath)).digest('hex') };
  const contextPath = path.join(directory, 'bootstrap-context.json');
  fs.writeFileSync(contextPath, JSON.stringify(context));
  const helper = path.join(HARNESS, 'scripts/coverage/php-bootstrap.php');
  const wrapper = path.join(directory, 'bootstrap.php');
  const phpString = value => "'" + value.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
  fs.writeFileSync(wrapper, `<?php require_once ${phpString(helper)}; coverageBootstrapLoad(json_decode(file_get_contents(${phpString(contextPath)}), true, 512, JSON_THROW_ON_ERROR), false);\n`);
  if (dockerPhp) await dockerPhp.stage([helper, recipePath, path.join(HARNESS, 'scripts/coverage/php-shapes.php'), contextPath, wrapper]);
  if (selected.profile === 'woo') {
    await run(php, ['-d', 'memory_limit=768M', helper, '--capture', contextPath], opts.root, { PHPUNIT_TEST: 'true' }, path.join(directory, 'bootstrap.log'));
    if (dockerPhp) await dockerPhp.retrieve(path.join(directory, 'bootstrap-declarations.php'));
  }
  const config = path.join(directory, 'phpunit.xml');
  const bootstrap = selected.profile === 'woo' ? wrapper : path.join(opts.root, 'tests/bootstrap.php');
  fs.writeFileSync(config, phpunitCoverageConfig(fs.readFileSync(path.join(HARNESS, 'phpunit.xml'), 'utf8'), { root: opts.root, bootstrap, directory }));
  const exported = path.join(directory, 'raw.json');
  const inventory = path.join(directory, 'tests.xml');
  await run(php, [path.join(opts.root, 'vendor/bin/phpunit'), '--configuration', config, '--list-tests-xml', inventory], opts.root);
  const testIds = JSON.parse(await run(php, [path.join(HARNESS, 'scripts/coverage/php-report.php'), opts.root, '--inventory', inventory], opts.root));
  if (!testIds.length) throw new Error('PHPUnit suite contains no partitions');
  const partitions = [];
  for (let index = 0; index < testIds.length; index += opts['php-batch-size']) partitions.push(testIds.slice(index, index + opts['php-batch-size']));
  const artifacts = selected.profile === 'woo' ? [path.join(directory, 'bootstrap-declarations.php')] : [];
  const junitFiles = [];
  for (const [index, testIds] of partitions.entries()) {
    const raw = path.join(directory, `partition-${index}.php`);
    const filter = '/^(?:' + testIds.map(id => id.replace(/[\\^$.*+?()[\]{}|/]/g, '\\$&')).join('|') + ')$/';
    const junit = path.join(directory, `partition-${index}.xml`);
    await run(php, ['-d', `memory_limit=${PHP_COVERAGE_MEMORY_LIMIT}`, path.join(opts.root, 'vendor/bin/phpunit'), '--configuration', config, '--filter', filter, '--fail-on-incomplete', '--fail-on-risky', '--path-coverage', '--coverage-php', raw, '--log-junit', junit], opts.root, { XDEBUG_MODE: 'coverage' }, path.join(directory, `partition-${index}.log`));
    artifacts.push(raw);
    junitFiles.push(junit);
  }
  fs.writeFileSync(path.join(directory, 'partitions.json'), JSON.stringify(partitions, null, 2));
  await run(php, ['-d', 'memory_limit=1G', path.join(HARNESS, 'scripts/coverage/php-report.php'), opts.root, artifacts[0], exported, ...artifacts.slice(1)], opts.root, { COVERAGE_TEST_INVENTORY: inventory, COVERAGE_JUNIT_FILES: JSON.stringify(junitFiles), COVERAGE_BOOTSTRAP_CONTEXT: contextPath });
  if (dockerPhp && selected.profile === 'woo') await dockerPhp.retrieve(path.join(directory, 'bootstrap-receipts'));
  const files = JSON.parse(fs.readFileSync(exported, 'utf8')).map(file => ({
    path: relativeSource(opts.root, file.path), lines: file.lines,
    metrics: Object.fromEntries(Object.entries(file.counts).map(([key, counts]) => [key, metric(...counts)])),
  })).sort((a, b) => a.path.localeCompare(b.path));
  validateFiles(files);
  validateInventory(files, sourceInventory(opts.root, 'php', policy.php));
  const phpBootstrap = JSON.parse(fs.readFileSync(path.join(directory, 'php-bootstrap-summary.json')));
  const result = { language: 'php', variant: null, provider: 'xdebug-path-coverage', phpBootstrap, files, modules: modulesFor(files, policy.php), artifacts: { clover: path.join(directory, 'clover.xml'), raw: artifacts, partitions: path.join(directory, 'partitions.json'), tests: path.join(directory, 'executed-tests.json'), bootstrap: path.join(directory, 'php-bootstrap-summary.json') } };
  if (!opts['collect-only']) enforce(result, policy.php, policy);
  return result;
}

async function main(argv) {
  require('./jest-global-setup').clearGitLocation(process.env);
  const opts = options(argv);
  deadline = Date.now() + opts['timeout-ms'];
  // Remove a previous success before any runtime/test validation. Never leave a stale green report.
  fs.rmSync(opts['report-json'], { force: true });
  assertNodeRuntime();
  fs.mkdirSync(opts['output-dir'], { recursive: true });
  const policy = validatePolicy(JSON.parse(fs.readFileSync(path.join(HARNESS, 'coverage-policy.json'))));
  const initialFingerprints = {
    measurement: hashFiles(HARNESS, ['scripts/coverage/run.js', 'scripts/coverage/report.js', 'scripts/coverage/policy.js', 'scripts/coverage/php-report.php', 'scripts/coverage/process.js', 'scripts/coverage/docker-php.js', 'scripts/coverage/php-filter.php', 'scripts/coverage/jest-vm-metadata.js', 'scripts/coverage/php-bootstrap.php', 'scripts/coverage/php-bootstrap.json', 'scripts/coverage/php-profile.js', 'scripts/coverage/php-shapes.php']),
    configuration: hashFiles(HARNESS, ['scripts/coverage/policy.js', 'jest.config.js', 'jest.setup.js', 'scripts/coverage/jest-global-setup.js', 'scripts/coverage/jest-vm-metadata.js', 'phpunit.xml', 'scripts/coverage/php-bootstrap.json', 'scripts/coverage/php-bootstrap.php', 'scripts/coverage/php-profile.js']),
    dependencies: hashFiles(opts.root, ['package-lock.json', 'composer.lock']),
  };
  const runtimes = { node: process.versions.node };
  if (opts.language !== 'php') runtimes.jest = require(path.join(opts.root, 'node_modules/jest/package.json')).version;
  const results = [];
  if (opts.language !== 'php') {
    for (const variant of opts.variant === 'all' ? ['v2', 'v2.1'] : [opts.variant]) results.push(await collectJs(opts, variant, policy));
  }
  if (opts.language !== 'js') {
    if (opts['php-docker-image']) {
      dockerPhp = new DockerPhp(opts['php-docker-image'], opts.root, opts['output-dir'], run);
      await dockerPhp.start();
    }
    const php = dockerPhp ? '__docker_php__' : process.env.COVERAGE_PHP || 'php';
    const runtime = JSON.parse(await run(php, ['-r', 'echo json_encode(["php"=>PHP_VERSION,"xdebug"=>phpversion("xdebug") ?: null,"mode"=>xdebug_info("mode"),"preload"=>ini_get("auto_prepend_file") ? hash_file("sha256",ini_get("auto_prepend_file")) : null]);'], opts.root, { XDEBUG_MODE: 'coverage' }));
    if (!runtime.php.startsWith('7.4.') || !runtime.xdebug) throw new Error('Coverage requires PHP 7.4 with Xdebug branch/path support');
    Object.assign(runtimes, runtime);
    if (dockerPhp) runtimes.phpImage = dockerPhp.imageId;
    runtimes.phpBatchSize = opts['php-batch-size'];
    runtimes.phpProfile = opts['php-profile'];
    runtimes.phpunit = await run(php, [path.join(opts.root, 'vendor/bin/phpunit'), '--version'], opts.root);
    results.push(await collectPhp(opts, policy, php));
  }
  const hashText = value => require('crypto').createHash('sha256').update(JSON.stringify(value)).digest('hex');
  const report = {
    schemaVersion: 1, valid: true, revision: { commit: git(opts.root, ['rev-parse', 'HEAD']), tree: git(opts.root, ['rev-parse', 'HEAD^{tree}']), dirty: Boolean(git(opts.root, ['status', '--porcelain'])) },
    fingerprints: {
      ...initialFingerprints, policy: hashText(policy), runtime: hashText(runtimes),
    }, runtimes, results,
  };
  fs.mkdirSync(path.dirname(opts['report-json']), { recursive: true });
  fs.writeFileSync(opts['report-json'], JSON.stringify(report, null, 2) + os.EOL);
  process.stdout.write(`Coverage report: ${opts['report-json']}\n`);
  return report;
}

if (require.main === module) {
  process.on('SIGTERM', cancel);
  process.on('SIGINT', cancel);
  main(process.argv.slice(2)).catch(error => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; }).finally(async () => {
    try { if (dockerPhp) await dockerPhp.close(); } catch (error) { process.stderr.write(`PHP runtime cleanup failed: ${error.message}\n`); process.exitCode = 1; }
  });
}
module.exports = { options, main, assertNodeRuntime, phpunitCoverageConfig, PHP_COVERAGE_MEMORY_LIMIT };
