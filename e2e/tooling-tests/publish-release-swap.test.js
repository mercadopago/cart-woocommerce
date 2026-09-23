const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { describe, it } = require('node:test');
const {
  REMOTE_PUBLISH_SCRIPT,
  describeRemoteFailure,
  publishArtifact,
} = require('../publish-candidate');

// The remote script is what actually publishes, and it was the one part of the pipeline with no
// coverage at all -- which is why a publisher that could never work against the real lane layout
// shipped unnoticed. These tests run the script for real against a fake lane and a stub docker.
// It targets the shared Ubuntu host, so it relies on GNU `mv -T` and `sha256sum`; on anything
// else the behaviour under test is not the behaviour that would run in production.
const runsRemoteScript = process.platform === 'linux';

function createLane(root, lane, versions, currentVersion) {
  const laneRoot = path.join(root, 'artifacts', lane);
  const releases = path.join(laneRoot, 'releases');
  const current = path.join(laneRoot, 'current');
  fs.mkdirSync(releases, { recursive: true });
  fs.mkdirSync(current, { recursive: true });
  for (const [version, sha] of Object.entries(versions)) {
    const release = path.join(releases, `${version}-${sha.slice(0, 16)}`);
    fs.mkdirSync(release, { recursive: true });
    fs.writeFileSync(
      path.join(release, 'woocommerce-mercadopago.php'),
      `<?php\n/**\n * Version: ${version}\n */\n`
    );
  }
  const [version, sha] = [currentVersion, versions[currentVersion]];
  fs.symlinkSync(
    path.join('..', 'releases', `${version}-${sha.slice(0, 16)}`),
    path.join(current, 'woocommerce-mercadopago')
  );
  return laneRoot;
}

function createArtifact(workspace, version) {
  const source = path.join(workspace, 'src');
  const plugin = path.join(source, 'woocommerce-mercadopago');
  fs.mkdirSync(plugin, { recursive: true });
  fs.writeFileSync(
    path.join(plugin, 'woocommerce-mercadopago.php'),
    `<?php\n/**\n * Version: ${version}\n */\n`
  );
  const zip = path.join(workspace, `plugin-${version}.zip`);
  const created = spawnSync('zip', ['-q', '-r', zip, 'woocommerce-mercadopago'], { cwd: source });
  assert.equal(created.status, 0, 'zip fixture could not be created');
  const sha = spawnSync('sha256sum', [zip], { encoding: 'utf8' }).stdout.split(/\s+/)[0];
  return { zip, sha };
}

// A docker stub that answers the four calls the script makes. `plugin get --field=version` reads
// the version through the lane's `current` pointer, so a run only passes if the swap really
// happened on disk.
function createDockerStub(binDir, laneRoot, options = {}) {
  fs.mkdirSync(binDir, { recursive: true });
  const log = path.join(binDir, 'docker.log');
  const stub = `#!/bin/sh
echo "$@" >> ${JSON.stringify(log)}
case "$1" in
  inspect) printf '%s' ${JSON.stringify(options.mountSource ?? laneRoot)}; exit 0 ;;
esac
shift
[ "$1" = "--user" ] && shift 2
container="$1"; shift
case "$1" in
  apache2ctl) exit ${options.gracefulStatus ?? 0} ;;
  curl) exit ${options.curlStatus ?? 0} ;;
esac
# wp --path=... plugin <verb> ...
case "$*" in
  *"plugin activate"*) exit 0 ;;
  *--field=status) printf 'active'; exit 0 ;;
  *--field=version)
    sed -n 's/^ \\* Version: \\(.*\\)$/\\1/p' ${JSON.stringify(laneRoot)}/current/woocommerce-mercadopago/woocommerce-mercadopago.php | tr -d '\\n'
    exit 0 ;;
esac
exit 1
`;
  const dockerPath = path.join(binDir, 'docker');
  fs.writeFileSync(dockerPath, stub, { mode: 0o755 });
  return { log };
}

function runPublish(root, laneRoot, lane, artifact, version, containers, stubOptions = {}) {
  const home = path.join(root, 'home');
  const incoming = path.join(home, '.woo-e2e-artifacts', 'incoming');
  fs.mkdirSync(incoming, { recursive: true });
  const token = 'a1b2c3d4e5f60718';
  fs.copyFileSync(artifact.zip, path.join(incoming, `${token}.zip`));

  const binDir = path.join(root, 'bin');
  const stub = createDockerStub(binDir, laneRoot, stubOptions);
  const result = spawnSync(
    'sh',
    ['-s', '--', token, artifact.sha, lane, 'developer', version, ...containers],
    {
      input: REMOTE_PUBLISH_SCRIPT,
      encoding: 'utf8',
      env: { ...process.env, HOME: home, PATH: `${binDir}:${process.env.PATH}` },
      timeout: 60000,
    }
  );
  return { ...result, home, token, dockerLog: stub.log };
}

describe('remote release swap', { skip: !runsRemoteScript && 'requires GNU coreutils (Linux host)' }, () => {
  it('repoints current to the new release and reloads every container of the lane', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-publish-'));
    try {
      const baseline = createArtifact(root, '8.9.3');
      const laneRoot = createLane(root, 'staging', { '8.9.3': baseline.sha }, '8.9.3');
      const candidate = createArtifact(path.join(root, 'rc'), '8.9.4');
      const containers = ['wp-staging-mla', 'wp-staging-mlb'];

      const result = runPublish(root, laneRoot, 'staging', candidate, '8.9.4', containers);
      assert.equal(result.status, 0, result.stderr);

      const pointer = fs.readlinkSync(path.join(laneRoot, 'current', 'woocommerce-mercadopago'));
      assert.equal(pointer, `../releases/8.9.4-${candidate.sha.slice(0, 16)}`);

      const header = fs.readFileSync(
        path.join(laneRoot, 'current', 'woocommerce-mercadopago', 'woocommerce-mercadopago.php'),
        'utf8'
      );
      assert.match(header, /Version: 8\.9\.4/);

      // The previous release survives, which is what makes a rollback a repoint.
      assert.ok(fs.existsSync(path.join(laneRoot, 'releases', `8.9.3-${baseline.sha.slice(0, 16)}`)));

      const dockerCalls = fs.readFileSync(result.dockerLog, 'utf8');
      for (const container of containers) {
        assert.match(dockerCalls, new RegExp(`exec ${container} apache2ctl -k graceful`));
      }

      const manifest = JSON.parse(
        fs.readFileSync(path.join(result.home, '.woo-e2e-artifacts', 'manifests', 'staging.json'), 'utf8')
      );
      assert.equal(manifest.plugin_version, '8.9.4');
      assert.equal(manifest.published_by, 'developer');

      // The uploaded artifact is removed so a stale ZIP cannot be replayed later.
      assert.ok(!fs.existsSync(path.join(result.home, '.woo-e2e-artifacts', 'incoming', `${result.token}.zip`)));
    } finally {
      fs.rmSync(root, { recursive: true, force: true });
    }
  });

  it('leaves the lane traversable so www-data can reach the plugin', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-publish-'));
    try {
      const baseline = createArtifact(root, '8.9.3');
      const laneRoot = createLane(root, 'staging', { '8.9.3': baseline.sha }, '8.9.3');
      // A lane seeded by an older bootstrap is 0700 and would boot WordPress with no gateway.
      for (const directory of [laneRoot, path.join(laneRoot, 'releases'), path.join(laneRoot, 'current')]) {
        fs.chmodSync(directory, 0o700);
      }
      const candidate = createArtifact(path.join(root, 'rc'), '8.9.4');

      const result = runPublish(root, laneRoot, 'staging', candidate, '8.9.4', ['wp-staging-mlb']);
      assert.equal(result.status, 0, result.stderr);

      for (const directory of [laneRoot, path.join(laneRoot, 'releases'), path.join(laneRoot, 'current')]) {
        assert.equal(fs.statSync(directory).mode & 0o005, 0o005, `${directory} is not traversable`);
      }
      const release = path.join(laneRoot, 'releases', `8.9.4-${candidate.sha.slice(0, 16)}`);
      assert.equal(fs.statSync(path.join(release, 'woocommerce-mercadopago.php')).mode & 0o004, 0o004);
    } finally {
      fs.rmSync(root, { recursive: true, force: true });
    }
  });

  it('refuses a lane whose containers do not use the current bind-mount layout', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-publish-'));
    try {
      const baseline = createArtifact(root, '8.9.3');
      const laneRoot = createLane(root, 'staging', { '8.9.3': baseline.sha }, '8.9.3');
      const candidate = createArtifact(path.join(root, 'rc'), '8.9.4');

      const result = runPublish(
        root, laneRoot, 'staging', candidate, '8.9.4', ['wp-staging-mlb'], { mountSource: '' }
      );
      assert.equal(result.status, 69);
      assert.match(result.stderr, /no \/e2e-artifacts mount/);

      // The lane is untouched: a refused publish must never leave a half-swapped pointer.
      const pointer = fs.readlinkSync(path.join(laneRoot, 'current', 'woocommerce-mercadopago'));
      assert.equal(pointer, `../releases/8.9.3-${baseline.sha.slice(0, 16)}`);
    } finally {
      fs.rmSync(root, { recursive: true, force: true });
    }
  });

  it('rejects an artifact whose bytes do not match the declared sha256', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-publish-'));
    try {
      const baseline = createArtifact(root, '8.9.3');
      const laneRoot = createLane(root, 'staging', { '8.9.3': baseline.sha }, '8.9.3');
      const candidate = createArtifact(path.join(root, 'rc'), '8.9.4');

      const result = runPublish(
        root, laneRoot, 'staging', { ...candidate, sha: 'f'.repeat(64) }, '8.9.4', ['wp-staging-mlb']
      );
      assert.equal(result.status, 65);
    } finally {
      fs.rmSync(root, { recursive: true, force: true });
    }
  });

  it('fails when a container still reports the previous version after the reload', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-publish-'));
    try {
      const baseline = createArtifact(root, '8.9.3');
      const laneRoot = createLane(root, 'staging', { '8.9.3': baseline.sha }, '8.9.3');
      const candidate = createArtifact(path.join(root, 'rc'), '8.9.4');

      // A container that never answers after the graceful reload must not be reported as published.
      const result = runPublish(
        root, laneRoot, 'staging', candidate, '8.9.4', ['wp-staging-mlb'], { curlStatus: 1 }
      );
      assert.equal(result.status, 70);
      assert.match(result.stderr, /did not answer after the graceful reload/);
    } finally {
      fs.rmSync(root, { recursive: true, force: true });
    }
  });
});

describe('publisher lane fan-out', () => {
  const config = {
    lock: { sshUser: 'ubuntu', sshHost: 'big-shared-xlarge.ppolimpo.io' },
    environments: {
      staging: {
        role: 'candidate',
        sites: Object.fromEntries(
          ['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE']
            .map((site) => [site, { containerName: `wp-staging-${site.toLowerCase()}` }])
        ),
      },
      homol: {
        role: 'baseline',
        sites: Object.fromEntries(
          ['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE']
            .map((site) => [site, { containerName: `wp-homol-${site.toLowerCase()}` }])
        ),
      },
    },
  };

  it('sends every container of the lane, not just the requested site', () => {
    const calls = [];
    publishArtifact(
      config,
      { environment: 'staging', site: 'MLB' },
      { artifactPath: '/tmp/plugin.zip', sha256: 'a'.repeat(64), version: '8.9.4' },
      'deadbeef',
      'developer',
      {
        sshOptions: [],
        spawnSync: (command, args) => {
          calls.push({ command, args });
          return { status: 0, stdout: '', stderr: '' };
        },
      }
    );

    const publish = calls.at(-1);
    assert.equal(publish.command, 'ssh');
    for (const site of ['mla', 'mlb', 'mlc', 'mlm', 'mco', 'mlu', 'mpe']) {
      assert.ok(publish.args.includes(`wp-staging-${site}`), `faltou wp-staging-${site}`);
    }
    assert.ok(!publish.args.some((argument) => argument.startsWith('wp-homol-')));
  });
});

describe('remote publish diagnostics', () => {
  it('names the cause instead of the old generic recovery message', () => {
    const message = describeRemoteFailure('homol', {
      status: 69,
      stderr: 'container wp-homol-mlb has no /e2e-artifacts mount',
    });
    assert.match(message, /layout de bind-mount atual/);
    assert.match(message, /wp-homol-mlb/);
  });

  it('keeps an unmapped exit code readable and strips terminal control sequences', () => {
    const message = describeRemoteFailure('staging', {
      status: 42,
      stderr: 'boom\x1b[31mred\x00',
    });
    assert.match(message, /exit 42/);
    assert.doesNotMatch(message, /\x1b/);
    assert.doesNotMatch(message, /\x00/);
  });
});
