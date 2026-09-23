const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { runReleaseComparison } = require('../run-release-comparison');

function preparedRelease() {
  return {
    candidate: { version: '8.9.4', sha256: 'a'.repeat(64) },
    production: { version: '8.9.3', sha256: 'b'.repeat(64) },
    staging: { site: 'MLB' },
    homol: { site: 'MLB' },
    config: {},
  };
}

describe('canonical release comparison', () => {
  it('keeps one lease from publication through Classic and Blocks', async () => {
    const previousUser = process.env.SMOOTH_USER;
    process.env.SMOOTH_USER = 'developer';
    const events = [];
    try {
      const exitCode = await runReleaseComparison({
        site: 'MLB',
        candidateVersion: '8.9.4',
        productionVersion: '8.9.3',
        dryRun: false,
      }, {
        prepareReleaseArtifacts: () => preparedRelease(),
        publishReleaseUnderLease: () => events.push('publish'),
        executeDualComparison: async (options) => {
          events.push(options.checkout);
          return { exitCode: 0, interrupted: false };
        },
        runRemoteLock: (_config, action) => events.push(action),
        runPublishLock: (_config, action) => events.push(`publish-lock:${action}`),
        startLeaseHeartbeat: () => {
          events.push('heartbeat:start');
          return {
            assertHeld() {},
            isLost: () => false,
            renewNow: () => events.push('heartbeat:renew'),
            stop: () => events.push('heartbeat:stop'),
          };
        },
        tokenFactory: () => 'c'.repeat(48),
      });

      assert.equal(exitCode, 0);
      // The lane-wide writer is taken before the country lease and dropped as soon as both lanes
      // are published, so the long test run never blocks another country's publish.
      assert.deepEqual(events, [
        'publish-lock:acquire',
        'acquire',
        'heartbeat:start',
        'publish',
        'publish-lock:release',
        'heartbeat:renew',
        'classic',
        'blocks',
        'heartbeat:stop',
        'release',
      ]);
    } finally {
      if (previousUser === undefined) delete process.env.SMOOTH_USER;
      else process.env.SMOOTH_USER = previousUser;
    }
  });

  it('returns an interrupted status when a signal arrives during lease release', async () => {
    const previousUser = process.env.SMOOTH_USER;
    process.env.SMOOTH_USER = 'developer';
    let signal = null;
    try {
      const exitCode = await runReleaseComparison({
        site: 'MLB',
        candidateVersion: '8.9.4',
        productionVersion: '8.9.3',
        dryRun: false,
      }, {
        prepareReleaseArtifacts: () => preparedRelease(),
        publishReleaseUnderLease: () => {},
        executeDualComparison: async () => ({ exitCode: 0, interrupted: false }),
        runRemoteLock: (_config, action) => {
          if (action === 'release') signal = 'SIGTERM';
        },
        runPublishLock: () => {},
        startLeaseHeartbeat: () => ({
          assertHeld() {},
          isLost: () => false,
          renewNow() {},
          stop() {},
        }),
        signalController: {
          getSignal: () => signal,
          install() {},
          uninstall() {},
        },
        tokenFactory: () => 'c'.repeat(48),
      });

      assert.equal(exitCode, 130);
    } finally {
      if (previousUser === undefined) delete process.env.SMOOTH_USER;
      else process.env.SMOOTH_USER = previousUser;
    }
  });
});
