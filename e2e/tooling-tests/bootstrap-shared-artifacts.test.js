const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { describe, it } = require('node:test');
const {
  assertSeededArtifacts,
  bootstrapSharedArtifacts,
  parseArgs,
  repairLanePermissions,
  seedLane,
} = require('../bootstrap-shared-artifacts');

function createArtifact(workspace, version, name) {
  const source = path.join(workspace, `${name}-source`);
  const plugin = path.join(source, 'woocommerce-mercadopago');
  const artifact = path.join(workspace, `${name}.zip`);
  fs.mkdirSync(plugin, { recursive: true });
  fs.writeFileSync(
    path.join(plugin, 'woocommerce-mercadopago.php'),
    `<?php\n/**\n * Version: ${version}\n */\n`
  );
  const zipped = spawnSync('zip', ['-qr', artifact, 'woocommerce-mercadopago'], {
    cwd: source,
    encoding: 'utf8',
  });
  assert.equal(zipped.status, 0, zipped.stderr);
  return artifact;
}

describe('shared artifact bootstrap', () => {
  it('requires both lane artifacts and their declared versions', () => {
    assert.deepEqual(parseArgs(['--check']), { check: true, repairPermissions: false });
    assert.deepEqual(parseArgs(['--repair-permissions']), { check: false, repairPermissions: true });
    assert.throws(() => parseArgs(['--check', '--repair-permissions']), /mutuamente exclusivos/);
    assert.throws(() => parseArgs(['--candidate-artifact', 'candidate.zip']), /obrigatorios/);
    assert.throws(() => parseArgs(['--check', '--candidate-artifact', 'candidate.zip']), /nao aceita/);
  });

  it('fails before Compose when a clean checkout has no seed artifacts', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-seed-'));
    try {
      assert.throws(() => assertSeededArtifacts(root), /e2e-shared-infra-seed/);
      for (const lane of ['staging', 'homol']) {
        const plugin = path.join(root, lane, 'current', 'woocommerce-mercadopago');
        fs.mkdirSync(plugin, { recursive: true });
        fs.writeFileSync(path.join(plugin, 'woocommerce-mercadopago.php'), '<?php // test');
      }
      assert.doesNotThrow(() => assertSeededArtifacts(root));
    } finally {
      fs.rmSync(root, { recursive: true });
    }
  });

  it('repairs a 0700 lane so the container can traverse it', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-seed-'));
    try {
      for (const lane of ['staging', 'homol']) {
        const plugin = path.join(root, lane, 'current', 'woocommerce-mercadopago');
        fs.mkdirSync(plugin, { recursive: true });
        fs.writeFileSync(path.join(plugin, 'woocommerce-mercadopago.php'), '<?php // test');
        fs.mkdirSync(path.join(root, lane, 'releases'), { recursive: true });
        for (const directory of ['', 'current', 'releases']) {
          fs.chmodSync(path.join(root, lane, directory), 0o700);
        }
      }
      // Lanes seeded before the lane-root mount fail the check instead of booting a gateway-less
      // WordPress, and the repair is what unblocks `shared-up` without reseeding.
      assert.throws(() => assertSeededArtifacts(root), /nao e atravessavel por www-data/);
      repairLanePermissions(root);
      assert.doesNotThrow(() => assertSeededArtifacts(root));
    } finally {
      fs.rmSync(root, { recursive: true });
    }
  });

  it('seeds both validated lanes atomically in a clean checkout', () => {
    const results = path.resolve(__dirname, '..', 'results');
    fs.mkdirSync(results, { recursive: true });
    const workspace = fs.mkdtempSync(path.join(results, 'tooling-seed-'));
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-seed-'));
    try {
      const candidateArtifact = createArtifact(workspace, '8.9.4', 'candidate');
      const baselineArtifact = createArtifact(workspace, '8.9.3', 'baseline');
      bootstrapSharedArtifacts({
        candidateArtifact,
        candidateVersion: '8.9.4',
        baselineArtifact,
        baselineVersion: '8.9.3',
      }, root);

      assert.doesNotThrow(() => assertSeededArtifacts(root));
      assert.equal(
        fs.lstatSync(path.join(root, 'staging', 'current', 'woocommerce-mercadopago')).isSymbolicLink(),
        true
      );
      assert.equal(
        fs.lstatSync(path.join(root, 'homol', 'current', 'woocommerce-mercadopago')).isSymbolicLink(),
        true
      );
    } finally {
      fs.rmSync(workspace, { recursive: true });
      fs.rmSync(root, { recursive: true });
    }
  });

  it('does not extract an orphan release when current belongs to another artifact', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-seed-'));
    const currentRoot = path.join(root, 'staging', 'current');
    fs.mkdirSync(currentRoot, { recursive: true });
    fs.mkdirSync(path.join(root, 'staging', 'releases', 'existing'), { recursive: true });
    fs.symlinkSync('../releases/existing', path.join(currentRoot, 'woocommerce-mercadopago'), 'dir');
    try {
      assert.throws(() => seedLane(root, 'staging', {
        artifactPath: path.join(root, 'candidate.zip'),
        sha256: 'a'.repeat(64),
        version: '8.9.4',
      }), /current ja existe/);
      assert.equal(
        fs.existsSync(path.join(root, 'staging', 'releases', `8.9.4-${'a'.repeat(16)}`)),
        false
      );
    } finally {
      fs.rmSync(root, { recursive: true });
    }
  });
});
