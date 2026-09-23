const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { describe, it } = require('node:test');
const { getSshOptions } = require('../helpers/ssh-options');

describe('SSH options', () => {
  function createKnownHosts(directory, hostname = 'smooth.example') {
    const knownHosts = path.join(directory, 'known_hosts');
    fs.writeFileSync(
      knownHosts,
      `${hostname} ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA\n`
    );
    fs.chmodSync(knownHosts, 0o600);
    return knownHosts;
  }

  it('requires a protected known_hosts file with the expected host key', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-known-hosts-'));
    try {
      const knownHosts = createKnownHosts(directory);
      assert.deepEqual(
        getSshOptions({ SMOOTH_KNOWN_HOSTS_PATH: knownHosts }, 'smooth.example'),
        [
          '-o', 'BatchMode=yes',
          '-o', 'StrictHostKeyChecking=yes',
          '-o', `UserKnownHostsFile=${fs.realpathSync(knownHosts)}`,
        ]
      );
      assert.throws(
        () => getSshOptions({ SMOOTH_KNOWN_HOSTS_PATH: knownHosts }, 'unknown.example'),
        /nao contem uma chave/
      );
      fs.chmodSync(knownHosts, 0o666);
      assert.throws(
        () => getSshOptions({ SMOOTH_KNOWN_HOSTS_PATH: knownHosts }, 'smooth.example'),
        /protegido/
      );
      assert.throws(() => getSshOptions({}, 'smooth.example'), /obrigatorios/);
    } finally {
      fs.rmSync(directory, { recursive: true });
    }
  });

  it('adds a private key with restrictive permissions', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-key-'));
    const key = path.join(directory, 'id_test');
    try {
      const knownHosts = createKnownHosts(directory);
      fs.writeFileSync(key, 'test-only-key');
      fs.chmodSync(key, 0o600);
      assert.deepEqual(getSshOptions({
        SMOOTH_KNOWN_HOSTS_PATH: knownHosts,
        SMOOTH_PK_PATH: key,
      }, 'smooth.example'), [
        '-o', 'BatchMode=yes',
        '-o', 'StrictHostKeyChecking=yes',
        '-o', `UserKnownHostsFile=${fs.realpathSync(knownHosts)}`,
        '-o', 'IdentitiesOnly=yes',
        '-i', fs.realpathSync(key),
      ]);
    } finally {
      fs.rmSync(directory, { recursive: true });
    }
  });

  it('rejects a key readable by group or others', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-key-'));
    const key = path.join(directory, 'id_test');
    try {
      const knownHosts = createKnownHosts(directory);
      fs.writeFileSync(key, 'test-only-key');
      fs.chmodSync(key, 0o644);
      assert.throws(() => getSshOptions({
        SMOOTH_KNOWN_HOSTS_PATH: knownHosts,
        SMOOTH_PK_PATH: key,
      }, 'smooth.example'), /sem permissao/);
    } finally {
      fs.rmSync(directory, { recursive: true });
    }
  });
});
