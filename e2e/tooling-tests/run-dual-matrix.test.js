const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const {
  createMatrixSignalHandler,
  formatDuration,
  parseArgs,
  prioritizeSites,
} = require('../run-dual-matrix');

describe('dual matrix runner', () => {
  it('defaults to the seven countries, both checkouts and four concurrent countries', () => {
    assert.deepEqual(parseArgs([
      '--candidate-version', '8.9.4', '--baseline-version', '8.9.3',
    ]), {
      baselineVersion: '8.9.3',
      candidateVersion: '8.9.4',
      checkout: 'both',
      concurrency: 4,
      dryRun: false,
      monitor: false,
      parityValidation: false,
      sites: ['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE'],
      timeoutProfile: 'standard',
      withRetries: false,
    });
  });

  it('accepts a bounded subset without changing canonical country order', () => {
    assert.deepEqual(
      parseArgs([
        '--sites', 'mpe,mla', '--checkout', 'classic', '--concurrency', '2',
        '--candidate-version', '8.9.4', '--baseline-version', '8.9.3',
        '--timeout-profile', 'standard', '--monitor', '--dry-run',
      ]),
      {
        baselineVersion: '8.9.3',
        candidateVersion: '8.9.4',
        checkout: 'classic',
        concurrency: 2,
        dryRun: true,
        monitor: true,
        parityValidation: false,
        sites: ['MLA', 'MPE'],
        timeoutProfile: 'standard',
        withRetries: false,
      }
    );
  });

  it('requires explicit parity validation for an equal-version matrix', () => {
    assert.throws(() => parseArgs([
      '--candidate-version', '8.9.3', '--baseline-version', '8.9.3',
    ]), /--parity-validation/);
    assert.equal(parseArgs([
      '--candidate-version', '8.9.3', '--baseline-version', '8.9.3',
      '--parity-validation',
    ]).parityValidation, true);
  });

  it('escalates a repeated interrupt from SIGTERM to SIGKILL', () => {
    const signals = [];
    let interrupted = 0;
    const handleSignal = createMatrixSignalHandler(new Set([{
      kill: (signal) => signals.push(signal),
    }]), () => { interrupted += 1; });

    handleSignal();
    handleSignal();

    assert.equal(interrupted, 1);
    assert.deepEqual(signals, ['SIGTERM', 'SIGKILL']);
  });

  it('rejects unsafe concurrency, countries and partial versions', () => {
    assert.throws(() => parseArgs([]), /obrigatorias/);
    assert.throws(
      () => parseArgs([
        '--candidate-version', '8.9.4', '--baseline-version', '8.9.3', '--concurrency', '5',
      ]),
      /entre 1 e 4/
    );
    assert.throws(
      () => parseArgs([
        '--candidate-version', '8.9.4', '--baseline-version', '8.9.3', '--sites', 'MLB,XXX',
      ]),
      /pais invalido/
    );
    assert.throws(() => parseArgs(['--candidate-version', '8.9.4']), /obrigatorias/);
  });

  it('formats elapsed time for the consolidated report', () => {
    assert.equal(formatDuration(4 * 3600000 + 34 * 60000 + 18000), '04:34:18');
  });

  it('starts historically slower countries first to reduce the matrix tail', () => {
    assert.deepEqual(
      prioritizeSites(['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE']),
      ['MLB', 'MPE', 'MLA', 'MLU', 'MCO', 'MLC', 'MLM']
    );
    assert.deepEqual(prioritizeSites(['MLM', 'MPE', 'MLC']), ['MPE', 'MLC', 'MLM']);
  });
});
