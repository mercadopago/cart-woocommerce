const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it } = require('node:test');
const ProgressReporter = require('../helpers/progress-reporter');

describe('Playwright progress reporter', () => {
  it('accepts only a progress file inside a run lane', () => {
    const root = path.resolve('/tmp/e2e-results');
    const valid = path.join(root, 'a'.repeat(24), 'staging', 'progress.json');

    assert.equal(ProgressReporter.resolveProgressPath(valid, root), valid);
    assert.throws(
      () => ProgressReporter.resolveProgressPath('/tmp/leak.json', root),
      /fora do diretorio permitido/
    );
    assert.throws(
      () => ProgressReporter.resolveProgressPath(path.join(root, 'latest', 'progress.json'), root),
      /fora do diretorio permitido/
    );
  });

  it('removes terminal control characters and bounds displayed text', () => {
    assert.equal(ProgressReporter.sanitizeText('token\n\u001b[31mvalue', 12), 'token [31mva');
  });

  it('maps final attempts without exposing the Playwright error', () => {
    const passed = { outcome: () => 'expected', results: [{ status: 'passed' }] };
    const flaky = {
      outcome: () => 'flaky',
      results: [{ status: 'failed' }, { status: 'passed' }],
    };
    const failed = { outcome: () => 'unexpected', results: [{ status: 'failed' }] };

    assert.equal(ProgressReporter.finalStatus(passed, { status: 'passed' }), 'PASS');
    assert.equal(ProgressReporter.finalStatus(flaky, { status: 'passed' }), 'FLAKY');
    assert.equal(ProgressReporter.finalStatus(failed, { status: 'failed' }), 'FAIL');
  });

  it('disables best-effort progress after a filesystem error without throwing', () => {
    const previousProgressFile = process.env.E2E_PROGRESS_FILE;
    const previousMkdir = fs.mkdirSync;
    const previousWrite = process.stderr.write;
    process.env.E2E_PROGRESS_FILE = path.join(
      ProgressReporter.DUAL_RESULTS_ROOT,
      'a'.repeat(24),
      'staging',
      'progress.json'
    );
    const reporter = new ProgressReporter();
    let warnings = 0;
    fs.mkdirSync = () => {
      const error = new Error('simulated filesystem failure');
      error.code = 'ENOSPC';
      throw error;
    };
    process.stderr.write = () => {
      warnings += 1;
      return true;
    };
    try {
      assert.doesNotThrow(() => reporter.writeState());
      assert.doesNotThrow(() => reporter.writeState());
      assert.equal(reporter.disabled, true);
      assert.equal(warnings, 1);
    } finally {
      fs.mkdirSync = previousMkdir;
      process.stderr.write = previousWrite;
      if (previousProgressFile === undefined) delete process.env.E2E_PROGRESS_FILE;
      else process.env.E2E_PROGRESS_FILE = previousProgressFile;
    }
  });
});
