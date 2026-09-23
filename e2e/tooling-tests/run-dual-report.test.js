const assert = require('node:assert/strict');
const { EventEmitter } = require('node:events');
const { describe, it } = require('node:test');
const {
  createTerminationController,
  executeDualComparison,
  getRemoteLockName,
  parseArgs,
  runRemoteLock,
} = require('../run-dual-report');

describe('dual runner arguments', () => {
  it('accepts the bounded POC interface', () => {
    assert.deepEqual(
      parseArgs([
        '--site', 'MLB', '--checkout', 'classic',
        '--candidate-version', '8.9.4', '--baseline-version', '8.9.3',
        '--with-retries', '--monitor', '--dry-run',
      ]),
      {
        site: 'MLB',
        checkout: 'classic',
        candidateVersion: '8.9.4',
        baselineVersion: '8.9.3',
        withRetries: true,
        monitor: true,
        parityValidation: false,
        progressOnly: false,
        timeoutProfile: 'standard',
        dryRun: true,
      }
    );
  });

  it('rejects unknown flags instead of forwarding shell arguments', () => {
    assert.throws(() => parseArgs(['--site', 'MLB', '--checkout', 'classic', '--grep', 'x']), /nao suportado/);
  });

  it('records the RC and production versions together', () => {
    assert.deepEqual(
      parseArgs([
        '--site', 'MLB',
        '--checkout', 'blocks',
        '--candidate-version', 'v8.9.4',
        '--baseline-version', '8.9.3',
      ]),
      {
        site: 'MLB',
        checkout: 'blocks',
        candidateVersion: '8.9.4',
        baselineVersion: '8.9.3',
        withRetries: false,
        monitor: false,
        parityValidation: false,
        progressOnly: false,
        timeoutProfile: 'standard',
        dryRun: false,
      }
    );
  });

  it('rejects partial or malformed version metadata', () => {
    assert.throws(
      () => parseArgs(['--site', 'MLB', '--checkout', 'classic', '--candidate-version', '8.9.4']),
      /obrigatorias/
    );
    assert.throws(
      () => parseArgs([
        '--site', 'MLB', '--checkout', 'classic',
        '--candidate-version', '8.9.4;id', '--baseline-version', '8.9.3',
      ]),
      /invalida/
    );
  });

  it('requires both site and checkout', () => {
    assert.throws(() => parseArgs(['--site', 'MLB']), /obrigatorios/);
    assert.throws(
      () => parseArgs(['--site', 'MLB', '--checkout', 'classic']),
      /versoes candidate e baseline sao obrigatorias/
    );
  });

  it('accepts matrix progress metadata and a fast timeout profile', () => {
    const runId = 'a'.repeat(24);
    assert.deepEqual(
      parseArgs([
        '--site', 'MLA', '--checkout', 'blocks', '--run-id', runId,
        '--candidate-version', '8.9.4', '--baseline-version', '8.9.3',
        '--progress-only', '--timeout-profile', 'fast',
      ]),
      {
        site: 'MLA',
        checkout: 'blocks',
        candidateVersion: '8.9.4',
        baselineVersion: '8.9.3',
        runId,
        withRetries: false,
        monitor: false,
        parityValidation: false,
        progressOnly: true,
        timeoutProfile: 'fast',
        dryRun: false,
      }
    );
    assert.throws(
      () => parseArgs([
        '--site', 'MLA', '--checkout', 'blocks',
        '--candidate-version', '8.9.4', '--baseline-version', '8.9.3',
        '--timeout-profile', 'turbo',
      ]),
      /standard ou fast/
    );
  });

  it('requires an explicit non-release mode for equal versions', () => {
    assert.throws(() => parseArgs([
      '--site', 'MLB', '--checkout', 'classic',
      '--candidate-version', '8.9.3', '--baseline-version', '8.9.3',
    ]), /--parity-validation/);
    assert.equal(parseArgs([
      '--site', 'MLB', '--checkout', 'classic',
      '--candidate-version', '8.9.3', '--baseline-version', '8.9.3',
      '--parity-validation',
    ]).parityValidation, true);
    assert.throws(() => parseArgs([
      '--site', 'MLB', '--checkout', 'classic',
      '--candidate-version', '8.9.4', '--baseline-version', '8.9.3',
      '--parity-validation',
    ]), /exige versoes iguais/);
  });

  it('verifies the exact installed version in both lanes before starting tests', async () => {
    const verified = [];
    await assert.rejects(() => executeDualComparison({
      site: 'MLB',
      checkout: 'classic',
      candidateVersion: '8.9.4',
      baselineVersion: '8.9.3',
      dryRun: false,
      monitor: false,
      parityValidation: false,
      progressOnly: false,
      timeoutProfile: 'standard',
      withRetries: false,
    }, {
      owner: 'developer',
      verifyInstalledVersion: (_config, target, expectedVersion) => {
        verified.push([target.environment, expectedVersion]);
        if (verified.length === 2) throw new Error('[E2E] stop after verification');
      },
      runEnvironment: () => {
        throw new Error('tests must not start before both version checks');
      },
    }), /stop after verification/);
    assert.deepEqual(verified, [
      ['staging', '8.9.4'],
      ['homol', '8.9.3'],
    ]);
  });

  it('isolates the remote lease by country', () => {
    const config = { lock: { name: 'woocommerce-e2e-shared-pair' } };
    assert.equal(getRemoteLockName(config, 'MLA'), 'woocommerce-e2e-shared-pair-mla');
    assert.equal(getRemoteLockName(config, 'mlb'), 'woocommerce-e2e-shared-pair-mlb');
    assert.throws(() => getRemoteLockName(config, 'invalid'), /Site invalido/);
  });

  it('retries transient renew failures but fails a lost ownership token immediately', () => {
    const config = {
      lock: {
        name: 'woocommerce-e2e-shared-pair',
        sshHost: 'shared.example.test',
        sshUser: 'developer',
        ttlSeconds: 14400,
      },
    };
    const token = 'a'.repeat(48);
    const correlationId = 'b'.repeat(24);
    let attempts = 0;

    runRemoteLock(config, 'renew', token, 'developer', correlationId, 'MLB', {
      sshOptions: ['-o', 'BatchMode=yes'],
      spawnSync: () => {
        attempts += 1;
        return { status: attempts < 3 ? 255 : 0 };
      },
    });
    assert.equal(attempts, 3);

    attempts = 0;
    assert.throws(
      () => runRemoteLock(
        config,
        'renew',
        token,
        'developer',
        correlationId,
        'MLB',
        {
          sshOptions: [],
          spawnSync: () => {
            attempts += 1;
            return { status: 77 };
          },
        }
      ),
      /Nao foi possivel renovar o lease/
    );
    assert.equal(attempts, 1);
  });

  it('keeps signal handlers installed through repeated termination requests', () => {
    const emitter = new EventEmitter();
    const controller = createTerminationController(emitter);

    controller.install();
    emitter.emit('SIGINT', 'SIGINT');
    emitter.emit('SIGTERM', 'SIGTERM');
    assert.equal(controller.getSignal(), 'SIGINT');
    assert.equal(emitter.listenerCount('SIGINT'), 1);
    assert.equal(emitter.listenerCount('SIGTERM'), 1);

    controller.uninstall();
    assert.equal(emitter.listenerCount('SIGINT'), 0);
    assert.equal(emitter.listenerCount('SIGTERM'), 0);
  });
});
