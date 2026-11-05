const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');
const { createHash } = require('crypto');
const { DockerPhp } = require('../../../../scripts/coverage/docker-php');

const image = process.env.COVERAGE_PHP_DOCKER_IMAGE;
const dockerTest = image ? test : test.skip;

dockerTest.each(['configuration', 'helper'])('stages unchanged %s once per container and retransfers changed bytes', async kind => {
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'coverage-docker-stage-'));
  const commands = [];
  const containers = [];
  const cleanup = [];
  const invoke = (command, args) => {
    const result = spawnSync(command, args, { encoding: 'utf8', timeout: 15000 });
    commands.push({ command, args, status: result.status, stderr: result.stderr });
    if (result.status !== 0 || result.error) throw new Error(result.stderr || result.error?.message);
    return result.stdout.trim();
  };
  const runtime = new DockerPhp(image, fixture, fixture, async (command, args) => invoke(command, args));
  const file = path.join(fixture, kind === 'helper' ? 'scripts/coverage/php-report.php' : 'phpunit.xml');
  const content = value => kind === 'helper' ? `<?php echo '${value}';` : value;
  const args = kind === 'helper'
    ? [file, '--inventory']
    : ['-r', 'echo file_get_contents($argv[2]);', '--', '--configuration', file];
  const transfers = () => commands.filter(value => value.status === 0 && value.args[0] === 'cp' && value.args[1] === file).length;
  const start = () => {
    const cid = invoke('docker', ['create', '--network', 'none', '--entrypoint', 'sleep', image, 'infinity']);
    containers.push(cid);
    invoke('docker', ['start', cid]);
    runtime.cid = cid;
  };
  try {
    start();
    await expect(runtime.execute(args, {})).rejects.toThrow();
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, content('first'));

    expect(await runtime.execute(args, {})).toBe('first');
    expect(await runtime.execute(args, {})).toBe('first');
    expect(transfers()).toBe(1);

    fs.writeFileSync(file, content('changed'));
    expect(await runtime.execute(args, {})).toBe('changed');
    expect(transfers()).toBe(2);

    start();
    expect(await runtime.execute(args, {})).toBe('changed');
    expect(transfers()).toBe(3);
  } finally {
    for (const cid of containers) {
      invoke('docker', ['rm', '--force', cid]);
      const result = spawnSync('docker', ['inspect', cid], { encoding: 'utf8', timeout: 15000 });
      cleanup.push({ cid, status: result.status, stderr: result.stderr });
      expect(result.status).not.toBe(0);
      expect(result.stderr).toMatch(/No such (container|object)/i);
    }
    if (process.env.COVERAGE_TEST_ARTIFACT_DIR) {
      fs.mkdirSync(process.env.COVERAGE_TEST_ARTIFACT_DIR, { recursive: true });
      fs.writeFileSync(path.join(process.env.COVERAGE_TEST_ARTIFACT_DIR, `docker-stage-${kind}.json`), JSON.stringify({ kind, image, commands, cleanup }, null, 2));
    }
    fs.rmSync(fixture, { recursive: true, force: true });
  }
}, 90000);

dockerTest('includes and executes the real uninstall entrypoint in the isolated snapshot', async () => {
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'coverage-docker-uninstall-'));
  const directory = path.join(fixture, 'output');
  const entrypoint = path.join(fixture, 'uninstall.php');
  const original = fs.readFileSync(path.resolve(__dirname, '../../../../uninstall.php'));
  const expectedHash = createHash('sha256').update(original).digest('hex');
  const commands = [];
  const cleanup = [];
  const invoke = (command, args) => {
    const result = spawnSync(command, args, { encoding: 'utf8', timeout: 15000 });
    commands.push({ command, args, status: result.status, stderr: result.stderr });
    if (result.status !== 0 || result.error) throw new Error(result.stderr || result.error?.message);
    return result.stdout.trim();
  };
  const runtime = new DockerPhp(image, fixture, directory, async (command, args) => invoke(command, args));
  let observed;
  fs.writeFileSync(entrypoint, original);
  fs.mkdirSync(path.join(fixture, 'vendor'));
  try {
    await runtime.start();
    observed = JSON.parse(await runtime.execute(['-r', `
      define('WP_UNINSTALL_PLUGIN', true);
      $deleted = [];
      function delete_option($name) { global $deleted; $deleted[] = $name; }
      require $argv[1];
      echo json_encode(['sha256' => hash_file('sha256', $argv[1]), 'deleted' => $deleted]);
    `, entrypoint], {}));
    expect(observed).toEqual({
      sha256: expectedHash,
      deleted: ['_mp_access_token_prod', '_mp_public_key_prod', '_mp_public_key_test', '_mp_access_token_test'],
    });
    expect(fs.readFileSync(entrypoint)).toEqual(original);
  } finally {
    if (runtime.cid) {
      await runtime.close();
      const result = spawnSync('docker', ['inspect', runtime.cid], { encoding: 'utf8', timeout: 15000 });
      cleanup.push({ cid: runtime.cid, status: result.status, stderr: result.stderr });
      expect(result.status).not.toBe(0);
      expect(result.stderr).toMatch(/No such (container|object)/i);
    }
    if (process.env.COVERAGE_TEST_ARTIFACT_DIR) {
      fs.mkdirSync(process.env.COVERAGE_TEST_ARTIFACT_DIR, { recursive: true });
      fs.writeFileSync(path.join(process.env.COVERAGE_TEST_ARTIFACT_DIR, 'docker-uninstall.json'), JSON.stringify({
        image, expectedHash, observed, commands, cleanup,
      }, null, 2));
    }
    fs.rmSync(fixture, { recursive: true, force: true });
  }
}, 90000);
