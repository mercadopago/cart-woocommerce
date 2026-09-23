const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { startLeaseHeartbeat } = require('../helpers/lease-heartbeat');

describe('remote lease heartbeat', () => {
  it('renews ownership and fails closed after the first renewal error', () => {
    const calls = [];
    let intervalCallback;
    let cleared = 0;
    let lost = 0;
    let failRenewal = false;
    const controller = startLeaseHeartbeat({
      config: { lock: { heartbeatSeconds: 60 } },
      token: 'a'.repeat(48),
      owner: 'developer',
      correlationId: 'b'.repeat(24),
      site: 'MLB',
      runRemoteLock: (...args) => {
        calls.push(args.slice(1));
        if (failRenewal) throw new Error('ssh unavailable');
      },
      onLost: () => { lost += 1; },
      setIntervalFn: (callback, milliseconds) => {
        assert.equal(milliseconds, 60000);
        intervalCallback = callback;
        return { unref() {} };
      },
      clearIntervalFn: () => { cleared += 1; },
    });

    intervalCallback();
    assert.deepEqual(calls[0], ['renew', 'a'.repeat(48), 'developer', 'b'.repeat(24), 'MLB']);
    controller.assertHeld();

    failRenewal = true;
    intervalCallback();
    intervalCallback();

    assert.equal(lost, 1);
    assert.equal(cleared, 1);
    assert.equal(controller.isLost(), true);
    assert.throws(() => controller.assertHeld(), /Lease remoto perdido/);
  });

  it('stops without attempting another renewal', () => {
    let intervalCallback;
    let renewals = 0;
    const controller = startLeaseHeartbeat({
      config: { lock: { heartbeatSeconds: 30 } },
      token: 'a'.repeat(48),
      owner: 'developer',
      correlationId: 'b'.repeat(24),
      site: 'MLA',
      runRemoteLock: () => { renewals += 1; },
      setIntervalFn: (callback) => {
        intervalCallback = callback;
        return { unref() {} };
      },
      clearIntervalFn: () => {},
    });

    controller.stop();
    intervalCallback();
    assert.equal(renewals, 0);
  });
});
