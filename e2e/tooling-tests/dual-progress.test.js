const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const {
  compareLiveProgress,
  normalizeProgress,
  renderDashboard,
} = require('../helpers/dual-progress');
const { COMPARISON_MODE } = require('../helpers/dual-report');

function lane(statuses, current = null) {
  const scenarios = {};
  const counts = { pass: 0, fail: 0, flaky: 0, skip: 0 };
  Object.entries(statuses).forEach(([key, status], index) => {
    scenarios[key] = {
      file: 'mlb/example.spec.js',
      line: index + 1,
      title: key,
      status,
    };
    counts[status.toLowerCase()] += 1;
  });
  return {
    status: 'RUNNING',
    total: 5,
    completed: Object.keys(statuses).length,
    counts,
    current,
    scenarios,
  };
}

describe('dual progress monitor', () => {
  it('classifies only scenarios completed by both lanes', () => {
    const staging = lane({ checkout: 'FAIL', pix: 'PASS', onlyCandidate: 'PASS' });
    const homol = lane({ checkout: 'PASS', pix: 'PASS', onlyBaseline: 'FAIL' });
    const comparison = compareLiveProgress(staging, homol);

    assert.equal(comparison.matched, 2);
    assert.deepEqual(comparison.counts, { PROBABLE_REGRESSION: 1, OK: 1 });
    assert.equal(comparison.probableRegressions[0].title, 'checkout');
  });

  it('renders both lanes without error details or environment variables', () => {
    const staging = lane(
      { checkout: 'FAIL' },
      { file: 'mlb/pix.spec.js', line: 8, title: 'pays with Pix' }
    );
    const homol = lane({ checkout: 'PASS' });
    const output = renderDashboard(
      {
        runId: 'a'.repeat(24),
        site: 'MLB',
        checkout: 'classic',
        candidateVersion: '8.9.4',
        baselineVersion: '8.9.3',
      },
      staging,
      homol
    );

    assert.match(output, /staging/);
    assert.match(output, /homol/);
    assert.match(output, /PROBABLE_REGRESSION=1/);
    assert.match(output, /mlb\/pix\.spec\.js:8/);
    assert.match(output, /RC=8\.9\.4 prod=8\.9\.3/);
    assert.doesNotMatch(output, /ACCESS_TOKEN|PUBLIC_KEY|password/i);
  });

  it('does not show causal release classifications during parity validation', () => {
    const staging = lane({ checkout: 'FAIL' });
    const homol = lane({ checkout: 'PASS' });
    const comparison = compareLiveProgress(
      staging,
      homol,
      COMPARISON_MODE.PARITY_VALIDATION
    );

    assert.deepEqual(comparison.counts, { PARITY_DIVERGENCE: 1 });
    assert.deepEqual(comparison.probableRegressions, []);
  });

  it('rejects malformed counters and statuses', () => {
    assert.throws(
      () => normalizeProgress({ status: 'RUNNING', total: -1, completed: 0 }),
      /Contadores invalidos/
    );
    assert.throws(
      () => normalizeProgress({ status: 'UNKNOWN', total: 0, completed: 0 }),
      /Estado invalido/
    );
  });

  it('keeps scenario keys in a dictionary without an object prototype', () => {
    const payload = JSON.parse(`{
      "status":"RUNNING",
      "total":1,
      "completed":1,
      "counts":{"pass":1,"fail":0,"flaky":0,"skip":0},
      "scenarios":{"__proto__":{"file":"safe.spec.js","line":1,"title":"safe","status":"PASS"}}
    }`);
    const normalized = normalizeProgress(payload);

    assert.equal(Object.getPrototypeOf(normalized.scenarios), null);
    assert.equal(normalized.scenarios.__proto__.status, 'PASS');
  });
});
