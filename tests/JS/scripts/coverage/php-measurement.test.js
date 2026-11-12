const fs = require('fs');
const path = require('path');
const os = require('os');
const { spawnSync } = require('child_process');
const { createHash } = require('crypto');
const ROOT = path.resolve(__dirname, '../../../..');
const phpTest = process.env.COVERAGE_PHP || process.env.COVERAGE_PHP_DOCKER_IMAGE ? test : test.skip;

phpTest('real PHP collector works without node_modules and rejects failed/empty suites without stale success', () => {
  // Immutable fixtures avoid host/VM filesystem cache races when Docker is the PHP runtime.
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'php-tooling-'));
  const empty = fs.mkdtempSync(path.join(os.tmpdir(), 'php-empty-'));
  const policy = require('../../../../coverage-policy.json');
  try {
    for (const root of [fixture, empty]) {
      for (const group of Object.values(policy.php)) if (group.path !== './') fs.mkdirSync(path.join(root, group.path), { recursive: true });
      fs.mkdirSync(path.join(root, 'tests'));
      fs.symlinkSync(path.join(ROOT, 'vendor'), path.join(root, 'vendor'), 'dir');
      for (const lock of ['composer.lock', 'package-lock.json']) fs.copyFileSync(path.join(ROOT, lock), path.join(root, lock));
      fs.writeFileSync(path.join(root, 'tests/bootstrap.php'), '<?php');
      fs.writeFileSync(path.join(root, 'composer.json'), JSON.stringify({ name: 'coverage-fixture/generic', autoload: { 'psr-4': { 'CoverageFixture\\': 'src/' } } }));
    }
    fs.writeFileSync(path.join(fixture, 'src/Api/sample.php'), '<?php\nclass SampleDecision {\n public function decide($value) {\n if ($value) {\n return 2;\n }\n return 0;\n }\n}\n');
    fs.writeFileSync(path.join(fixture, 'tests/SampleDecisionTest.php'), '<?php namespace CoverageFixture; class SampleDecisionTest extends \\PHPUnit\\Framework\\TestCase { public function testRealDecision() { require_once __DIR__ . "/../src/Api/sample.php"; $this->assertSame((int) getenv("COVERAGE_EXPECTED"), (new \\SampleDecision())->decide(true)); } /** @dataProvider values */ public function testWithoutMeasuredCode($value) { $this->assertTrue($value); } public function values() { return [[true], "named case" => [true]]; } }');
    const report = path.join(fixture, 'out/report.json');
    const execute = (root = fixture, expected = '2') => spawnSync(process.execPath, [path.join(ROOT, 'scripts/coverage/run.js'), '--root', root, '--language', 'php', '--php-profile', 'generic', '--collect-only', '--output-dir', path.join(fixture, 'out'), '--report-json', report, ...(process.env.COVERAGE_PHP_DOCKER_IMAGE ? ['--php-docker-image', process.env.COVERAGE_PHP_DOCKER_IMAGE] : [])], { cwd: root, env: { ...process.env, COVERAGE_EXPECTED: expected }, encoding: 'utf8', timeout: 60000 });
    let result = execute();
    expect(result.stderr).toBe('');
    expect(result.status).toBe(0);
    const measured = JSON.parse(fs.readFileSync(report));
    expect(measured.runtimes.jest).toBeUndefined();
    expect(measured.runtimes.phpProfile).toBe('generic');
    expect(measured.results[0].phpBootstrap.profile).toBe('generic');
    expect(measured.results[0].phpBootstrap.allowlist).toEqual([]);
    expect(measured.results[0].files[0].path).toBe('src/Api/sample.php');
    expect(measured.results[0].modules.global.branches.total).toBeGreaterThan(0);
    expect(measured.results[0].modules.global.branches.covered).toBeLessThan(measured.results[0].modules.global.branches.total);
    result = execute(fixture, '999');
    expect(result.status).not.toBe(0);
    expect(fs.existsSync(report)).toBe(false);
    const restored = execute();
    expect(restored.stderr).toBe('');
    expect(restored.status).toBe(0);
    // Real PHPUnit's list-tests command exits zero with an empty test inventory.
    result = execute(empty);
    expect(result.status).not.toBe(0);
    expect(result.stderr).toContain('no partitions');
    expect(fs.existsSync(report)).toBe(false);
    expect(fs.existsSync(path.join(fixture, 'out/php/partition-0.php'))).toBe(false);
  } finally {
    fs.rmSync(fixture, { recursive: true, force: true });
    fs.rmSync(empty, { recursive: true, force: true });
  }
}, 120000);

phpTest.each(['incomplete', 'risky'])('real PHP collector rejects %s partitions while allowing intentional skips', rejectedOutcome => {
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'php-outcomes-'));
  const policy = require('../../../../coverage-policy.json');
  const observations = [];
  const transcripts = [];
  try {
    for (const group of Object.values(policy.php)) if (group.path !== './') fs.mkdirSync(path.join(fixture, group.path), { recursive: true });
    fs.mkdirSync(path.join(fixture, 'tests'));
    fs.symlinkSync(path.join(ROOT, 'vendor'), path.join(fixture, 'vendor'), 'dir');
    for (const lock of ['composer.lock', 'package-lock.json']) fs.copyFileSync(path.join(ROOT, lock), path.join(fixture, lock));
    fs.writeFileSync(path.join(fixture, 'composer.json'), JSON.stringify({ name: 'coverage-fixture/outcomes', autoload: { 'psr-4': { 'CoverageFixture\\': 'src/' } } }));
    fs.writeFileSync(path.join(fixture, 'tests/bootstrap.php'), '<?php');
    fs.writeFileSync(path.join(fixture, 'src/Api/decision.php'), '<?php\nclass OutcomeDecision {\n public function decide($value) {\n if ($value) {\n return 2;\n }\n return 0;\n }\n}\n');
    const report = path.join(fixture, 'out/report.json');
    const outcomes = {
      skipped: '$this->markTestSkipped("intentional optional fixture");',
      [rejectedOutcome]: rejectedOutcome === 'incomplete' ? '$this->markTestIncomplete("incomplete outcome fixture");' : '/* deliberately has no assertions */',
    };
    let previousSuccess;
    for (const [outcome, body] of Object.entries(outcomes)) {
      fs.writeFileSync(path.join(fixture, 'tests/OutcomeDecisionTest.php'), `<?php namespace CoverageFixture;
class OutcomeDecisionTest extends \\PHPUnit\\Framework\\TestCase {
 public function testARealDecision() { require_once __DIR__ . "/../src/Api/decision.php"; $decision = new \\OutcomeDecision(); $this->assertSame(2, $decision->decide(true)); $this->assertSame(0, $decision->decide(false)); }
 public function testZOutcome() { ${body} }
}`);
      if (previousSuccess) fs.writeFileSync(report, previousSuccess);
      const args = [path.join(ROOT, 'scripts/coverage/run.js'), '--root', fixture, '--language', 'php', '--php-profile', 'generic', '--php-batch-size', '1', '--collect-only', '--output-dir', path.join(fixture, 'out'), '--report-json', report, ...(process.env.COVERAGE_PHP_DOCKER_IMAGE ? ['--php-docker-image', process.env.COVERAGE_PHP_DOCKER_IMAGE] : [])];
      const result = spawnSync(process.execPath, args, { cwd: fixture, env: { ...process.env }, encoding: 'utf8', timeout: 90000 });
      expect(result.error).toBeUndefined();
      const log = fs.readFileSync(path.join(fixture, 'out/php/partition-1.log'), 'utf8');
      transcripts.push({ outcome, command: [process.execPath, ...args], runnerSha256: createHash('sha256').update(fs.readFileSync(args[0])).digest('hex'), harnessSha256: createHash('sha256').update(fs.readFileSync(__filename)).digest('hex'), sourceSha256: createHash('sha256').update(fs.readFileSync(path.join(fixture, 'src/Api/decision.php'))).digest('hex'), testSha256: createHash('sha256').update(fs.readFileSync(path.join(fixture, 'tests/OutcomeDecisionTest.php'))).digest('hex'), seededReportSha256: previousSuccess ? createHash('sha256').update(previousSuccess).digest('hex') : null, stdout: result.stdout, stderr: result.stderr, partitionLog: log, junit: fs.existsSync(path.join(fixture, 'out/php/partition-1.xml')) ? fs.readFileSync(path.join(fixture, 'out/php/partition-1.xml'), 'utf8') : null });
      expect(log).toMatch(/PHPUnit 9\.6\./);
      expect(log).toContain(`${outcome === 'skipped' ? 'Skipped' : outcome === 'incomplete' ? 'Incomplete' : 'Risky'}: 1`);
      const measured = fs.existsSync(report) ? JSON.parse(fs.readFileSync(report, 'utf8')) : null;
      if (outcome === 'skipped') {
        expect(result.status).toBe(0);
        expect(measured.valid).toBe(true);
        expect(measured.results[0].files[0].path).toBe('src/Api/decision.php');
        expect(measured.results[0].modules.global.branches.covered).toBeGreaterThan(0);
        previousSuccess = fs.readFileSync(report);
      } else {
        observations.push({ outcome, status: result.status, reportValid: measured?.valid ?? null });
      }
      if (process.env.COVERAGE_PHP_DOCKER_IMAGE) {
        const runtime = JSON.parse(fs.readFileSync(path.join(fixture, 'out/php-runtime.json'), 'utf8'));
        transcripts[transcripts.length - 1].runtime = runtime;
        const inspected = spawnSync('docker', ['inspect', runtime.cid], { encoding: 'utf8' });
        transcripts[transcripts.length - 1].cleanupInspectStatus = inspected.status;
        transcripts[transcripts.length - 1].cleanupInspectStderr = inspected.stderr;
        expect(inspected.status).not.toBe(0);
        expect(inspected.stderr).toMatch(/No such (container|object)/i);
      }
    }
    expect(observations).toEqual([
      { outcome: rejectedOutcome, status: 1, reportValid: null },
    ]);
  } finally {
    if (process.env.COVERAGE_TEST_ARTIFACT_DIR) {
      fs.mkdirSync(process.env.COVERAGE_TEST_ARTIFACT_DIR, { recursive: true });
      fs.writeFileSync(path.join(process.env.COVERAGE_TEST_ARTIFACT_DIR, `php-outcomes-${rejectedOutcome}.json`), JSON.stringify({ observations, transcripts }, null, 2));
    }
    fs.rmSync(fixture, { recursive: true, force: true });
  }
}, 300000);

const dockerTest = process.env.COVERAGE_PHP_DOCKER_IMAGE ? test : test.skip;
dockerTest('managed PHP timeout removes only its own container after the exec client is killed', async () => {
  const { DockerPhp } = require('../../../../scripts/coverage/docker-php');
  const { run } = require('../../../../scripts/coverage/process');
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'php-deadline-'));
  fs.mkdirSync(path.join(fixture, 'vendor'));
  const execute = async (command, args, cwd, env = {}) => {
    const result = await run(command, args, { cwd, env, timeout: args.includes('php') ? 200 : 10000 });
    return result.stdout.trim();
  };
  const runtime = new DockerPhp(process.env.COVERAGE_PHP_DOCKER_IMAGE, fixture, path.join(fixture, 'out'), execute);
  try {
    await runtime.start();
    await expect(runtime.execute(['-r', 'sleep(30);'], {})).rejects.toThrow('TIMEOUT');
  } finally {
    await runtime.close();
    if (runtime.cid) {
      const inspected = spawnSync('docker', ['inspect', runtime.cid], { encoding: 'utf8' });
      expect(inspected.status).not.toBe(0);
    }
    fs.rmSync(fixture, { recursive: true, force: true });
  }
}, 60000);
