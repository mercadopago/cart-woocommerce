const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { describe, it } = require('node:test');
const {
  COMPARISON_MODE,
  classifyPair,
  collectTests,
  compareLaneReports,
  compareReports,
  readLaneReport,
  renderMarkdown,
  writeComparison,
} = require('../helpers/dual-report');

function report(statusByTitle) {
  return {
    suites: [{
      file: 'mlb/example.spec.js',
      specs: Object.entries(statusByTitle).map(([title, status], index) => ({
        title,
        line: index + 1,
        tests: [{
          projectName: 'chromium',
          status: status === 'FLAKY' ? 'flaky' : status === 'FAIL' ? 'unexpected' : status === 'SKIP' ? 'skipped' : 'expected',
          expectedStatus: status === 'SKIP' ? 'skipped' : 'passed',
          results: status === 'FLAKY'
            ? [{ status: 'failed' }, { status: 'passed' }]
            : [{ status: status === 'PASS' ? 'passed' : status === 'SKIP' ? 'skipped' : 'failed' }],
        }],
      })),
    }],
  };
}

describe('dual report', () => {
  it('classifies the full comparison table', () => {
    assert.equal(classifyPair('PASS', 'PASS'), 'OK');
    assert.equal(classifyPair('FAIL', 'PASS'), 'PROBABLE_REGRESSION');
    assert.equal(classifyPair('FAIL', 'FAIL'), 'COMMON_FAILURE');
    assert.equal(classifyPair('PASS', 'FAIL'), 'CANDIDATE_ONLY_PASS');
    assert.equal(classifyPair('FLAKY', 'PASS'), 'UNSTABLE_CANDIDATE_ONLY');
    assert.equal(classifyPair('PASS', 'FLAKY'), 'UNSTABLE_BASELINE_ONLY');
    assert.equal(classifyPair('FLAKY', 'FLAKY'), 'UNSTABLE_BOTH');
    assert.equal(classifyPair('SKIP', 'SKIP'), 'EXPECTED_SKIP');
    assert.equal(classifyPair('SKIP', 'PASS'), 'SELECTION_DRIFT');
    assert.equal(classifyPair('MISSING', 'PASS'), 'INVALID');
  });

  it('pairs scenarios by file, line, title and project', () => {
    const comparison = compareReports(
      report({ checkout: 'FAIL', pix: 'PASS', flaky: 'FLAKY' }),
      report({ checkout: 'PASS', pix: 'PASS', flaky: 'PASS' })
    );

    assert.equal(comparison.valid, true);
    assert.equal(comparison.counts.PROBABLE_REGRESSION, 1);
    assert.equal(comparison.counts.OK, 1);
    assert.equal(comparison.counts.UNSTABLE_CANDIDATE_ONLY, 1);
  });

  it('uses non-causal classifications for same-version parity validation', () => {
    const comparison = compareReports(
      report({ checkout: 'FAIL', pix: 'PASS', flaky: 'FLAKY' }),
      report({ checkout: 'PASS', pix: 'PASS', flaky: 'PASS' }),
      COMPARISON_MODE.PARITY_VALIDATION
    );

    assert.equal(comparison.valid, true);
    assert.equal(comparison.releaseEligible, false);
    assert.equal(comparison.counts.PARITY_DIVERGENCE, 1);
    assert.equal(comparison.counts.PARITY_OK, 1);
    assert.equal(comparison.counts.PARITY_INSTABILITY, 1);
    assert.equal(comparison.counts.PROBABLE_REGRESSION, undefined);
  });

  it('invalidates a pair when one side has no matching scenario', () => {
    const staging = report({ checkout: 'PASS', pix: 'PASS' });
    const homol = report({ checkout: 'PASS' });
    const comparison = compareReports(staging, homol);

    assert.equal(comparison.valid, false);
    assert.equal(comparison.counts.INVALID, 1);
  });

  it('rejects duplicate stable identifiers', () => {
    const duplicated = report({ checkout: 'PASS' });
    duplicated.suites.push(JSON.parse(JSON.stringify(duplicated.suites[0])));

    assert.throws(() => collectTests(duplicated), /duplicado/);
  });

  it('invalidates an empty pair instead of reporting EMPTY as valid', () => {
    const comparison = compareReports({ suites: [] }, { suites: [] });
    assert.equal(comparison.valid, false);
    assert.deepEqual(comparison.counts, { INVALID: 1 });
  });

  it('always writes an invalid report naming an unavailable lane', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'dual-report-'));
    const homolPath = path.join(root, 'homol.json');
    fs.writeFileSync(homolPath, JSON.stringify(report({ checkout: 'PASS' })));
    const comparison = compareLaneReports(
      readLaneReport('staging', path.join(root, 'missing.json'), 1),
      readLaneReport('homol', homolPath, 0)
    );
    writeComparison(root, {
      runId: 'a'.repeat(24),
      site: 'MLB',
      checkout: 'classic',
      owner: 'owner',
    }, comparison);

    assert.equal(comparison.valid, false);
    assert.equal(comparison.lanes.staging.reason, 'MISSING_REPORT');
    assert.match(fs.readFileSync(path.join(root, 'report.md'), 'utf8'), /staging \(MISSING_REPORT\)/);
    assert.equal(JSON.parse(fs.readFileSync(path.join(root, 'comparison.json'), 'utf8')).valid, false);
  });

  it('marks a lane with zero scenarios as unavailable', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'dual-empty-'));
    const emptyPath = path.join(root, 'empty.json');
    fs.writeFileSync(emptyPath, JSON.stringify({ suites: [] }));

    assert.equal(readLaneReport('staging', emptyPath, 1).reason, 'NO_SCENARIOS');
  });

  it('invalidates both lanes when the remote lease is lost', () => {
    const unavailable = (label) => ({
      available: false,
      exitCode: 1,
      label,
      reason: 'LEASE_LOST',
      scenarioCount: 0,
    });
    const comparison = compareLaneReports(unavailable('staging'), unavailable('homol'));

    assert.equal(comparison.valid, false);
    assert.deepEqual(comparison.invalidReasons, [
      'staging:LEASE_LOST',
      'homol:LEASE_LOST',
    ]);
  });

  it('records which version ran in each lane', () => {
    const comparison = compareReports(report({ checkout: 'PASS' }), report({ checkout: 'PASS' }));
    const markdown = renderMarkdown({
      runId: 'a'.repeat(24),
      site: 'MLB',
      checkout: 'classic',
      owner: 'owner',
      candidateVersion: '8.9.4',
      baselineVersion: '8.9.3',
    }, comparison);

    assert.match(markdown, /Staging \(RC\): 8\.9\.4/);
    assert.match(markdown, /Homol \(production\): 8\.9\.3/);
  });

  it('marks parity reports as ineligible for a release verdict', () => {
    const comparison = compareReports(
      report({ checkout: 'PASS' }),
      report({ checkout: 'PASS' }),
      COMPARISON_MODE.PARITY_VALIDATION
    );
    const markdown = renderMarkdown({
      runId: 'a'.repeat(24),
      site: 'MLB',
      checkout: 'classic',
      owner: 'owner',
      candidateVersion: '8.9.3',
      baselineVersion: '8.9.3',
    }, comparison);

    assert.match(markdown, /Comparison mode: PARITY_VALIDATION/);
    assert.match(markdown, /Release verdict eligible: no/);
    assert.match(markdown, /cannot support a release verdict/);
  });

  it('lists expected skips explicitly as coverage limitations', () => {
    const comparison = compareReports(report({ pix: 'SKIP' }), report({ pix: 'SKIP' }));
    const markdown = renderMarkdown({
      runId: 'a'.repeat(24),
      site: 'MLB',
      checkout: 'classic',
      owner: 'owner',
      candidateVersion: '8.9.4',
      baselineVersion: '8.9.3',
    }, comparison);

    assert.match(markdown, /Expected skips \/ coverage limitations/);
    assert.match(markdown, /pix/);
  });
});
