const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { describe, it } = require('node:test');
const { REMOTE_LOCK_SCRIPT } = require('../run-dual-report');

function runLock(home, action, token = 'a'.repeat(48)) {
  const bin = path.join(home, 'test-bin');
  const flock = path.join(bin, 'flock');
  fs.mkdirSync(bin, { recursive: true });
  if (!fs.existsSync(flock)) {
    fs.writeFileSync(flock, '#!/bin/sh\nexit 0\n', { mode: 0o700 });
  }
  return spawnSync(
    'sh',
    ['-s', '--', action, 'shared-mlb', token, 'developer', '300', 'b'.repeat(24)],
    {
      encoding: 'utf8',
      env: { ...process.env, HOME: home, PATH: `${bin}:${process.env.PATH}` },
      input: REMOTE_LOCK_SCRIPT,
      timeout: 5000,
    }
  );
}

describe('remote lease script', () => {
  it('recovers an incomplete legacy lock and publishes complete metadata atomically', () => {
    const home = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-lock-'));
    const lock = path.join(home, '.woo-e2e-locks', 'shared-mlb');
    try {
      fs.mkdirSync(lock, { recursive: true });
      const acquired = runLock(home, 'acquire');

      assert.equal(acquired.status, 0, acquired.stderr);
      assert.equal(fs.readFileSync(path.join(lock, 'token'), 'utf8'), 'a'.repeat(48));
      assert.equal(fs.readFileSync(path.join(lock, 'owner'), 'utf8'), 'developer');
      assert.match(fs.readFileSync(path.join(lock, 'acquired_at'), 'utf8'), /^\d+$/);
    } finally {
      fs.rmSync(home, { recursive: true });
    }
  });

  it('renews metadata and releases the whole lock directory', () => {
    const home = fs.mkdtempSync(path.join(os.tmpdir(), 'woo-e2e-lock-'));
    const lock = path.join(home, '.woo-e2e-locks', 'shared-mlb');
    try {
      assert.equal(runLock(home, 'acquire').status, 0);
      assert.equal(runLock(home, 'renew').status, 0);
      assert.equal(runLock(home, 'release').status, 0);
      assert.equal(fs.existsSync(lock), false);
    } finally {
      fs.rmSync(home, { recursive: true });
    }
  });
});
