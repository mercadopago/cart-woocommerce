const fs = require('fs');
const path = require('path');

const RESULT = Object.freeze({
  PASS: 'PASS',
  FAIL: 'FAIL',
  FLAKY: 'FLAKY',
  SKIP: 'SKIP',
  MISSING: 'MISSING',
});

const LANE_REASON = Object.freeze({
  AVAILABLE: 'AVAILABLE',
  CORRUPT_REPORT: 'CORRUPT_REPORT',
  INVALID_REPORT: 'INVALID_REPORT',
  INTERRUPTED: 'INTERRUPTED',
  LEASE_LOST: 'LEASE_LOST',
  MISSING_REPORT: 'MISSING_REPORT',
  NO_SCENARIOS: 'NO_SCENARIOS',
});

const COMPARISON_MODE = Object.freeze({
  PARITY_VALIDATION: 'PARITY_VALIDATION',
  RELEASE_COMPARISON: 'RELEASE_COMPARISON',
});

function finalTestStatus(test) {
  if (test.status === 'skipped' || test.expectedStatus === 'skipped') return RESULT.SKIP;
  if (test.status === 'flaky') return RESULT.FLAKY;
  if (test.status === 'unexpected') return RESULT.FAIL;

  const statuses = (test.results || []).map((result) => result.status);
  if (statuses.includes('failed') && statuses.includes('passed')) return RESULT.FLAKY;
  if (statuses.at(-1) === 'passed') return RESULT.PASS;
  if (statuses.at(-1) === 'skipped') return RESULT.SKIP;
  return RESULT.FAIL;
}

function collectTests(report) {
  if (!report || !Array.isArray(report.suites)) {
    throw new Error('[E2E] JSON Playwright sem suites validas.');
  }

  const tests = new Map();

  function visitSuite(suite, inheritedFile = '') {
    const file = suite.file || inheritedFile || suite.title || 'unknown';
    for (const spec of suite.specs || []) {
      for (const test of spec.tests || []) {
        const project = test.projectName || 'default';
        const line = Number.isInteger(spec.line) ? spec.line : 0;
        const key = `${file}:${line}:${spec.title}:${project}`;
        if (tests.has(key)) {
          throw new Error(`[E2E] Identificador de cenario duplicado: ${key}.`);
        }
        tests.set(key, {
          key,
          file,
          line,
          title: spec.title,
          project,
          status: finalTestStatus(test),
        });
      }
    }
    for (const child of suite.suites || []) visitSuite(child, file);
  }

  for (const suite of report.suites) visitSuite(suite);
  return tests;
}

function classifyPair(staging, homol, comparisonMode = COMPARISON_MODE.RELEASE_COMPARISON) {
  if (staging === RESULT.MISSING || homol === RESULT.MISSING) return 'INVALID';
  if (comparisonMode === COMPARISON_MODE.PARITY_VALIDATION) {
    if (staging === RESULT.FLAKY || homol === RESULT.FLAKY) return 'PARITY_INSTABILITY';
    if (staging === RESULT.FAIL && homol === RESULT.FAIL) return 'PARITY_COMMON_FAILURE';
    if (
      (staging === RESULT.FAIL && homol === RESULT.PASS)
      || (staging === RESULT.PASS && homol === RESULT.FAIL)
    ) return 'PARITY_DIVERGENCE';
    if (staging === RESULT.SKIP && homol === RESULT.SKIP) return 'EXPECTED_SKIP';
    if (staging === RESULT.SKIP || homol === RESULT.SKIP) return 'SELECTION_DRIFT';
    if (staging === RESULT.PASS && homol === RESULT.PASS) return 'PARITY_OK';
    return 'INVALID';
  }
  if (staging === RESULT.FLAKY && homol === RESULT.FLAKY) return 'UNSTABLE_BOTH';
  if (staging === RESULT.FLAKY) return 'UNSTABLE_CANDIDATE_ONLY';
  if (homol === RESULT.FLAKY) return 'UNSTABLE_BASELINE_ONLY';
  if (staging === RESULT.FAIL && homol === RESULT.PASS) return 'PROBABLE_REGRESSION';
  if (staging === RESULT.FAIL && homol === RESULT.FAIL) return 'COMMON_FAILURE';
  if (staging === RESULT.PASS && homol === RESULT.FAIL) return 'CANDIDATE_ONLY_PASS';
  if (staging === RESULT.SKIP && homol === RESULT.SKIP) return 'EXPECTED_SKIP';
  if (staging === RESULT.SKIP || homol === RESULT.SKIP) return 'SELECTION_DRIFT';
  if (staging === RESULT.PASS && homol === RESULT.PASS) return 'OK';
  return 'INVALID';
}

function compareReports(
  stagingReport,
  homolReport,
  comparisonMode = COMPARISON_MODE.RELEASE_COMPARISON
) {
  const stagingTests = collectTests(stagingReport);
  const homolTests = collectTests(homolReport);
  const keys = [...new Set([...stagingTests.keys(), ...homolTests.keys()])].sort();
  if (keys.length === 0) {
    return {
      valid: false,
      comparisonMode,
      releaseEligible: false,
      counts: { INVALID: 1 },
      scenarios: [],
    };
  }
  const counts = {};

  const scenarios = keys.map((key) => {
    const staging = stagingTests.get(key)?.status || RESULT.MISSING;
    const homol = homolTests.get(key)?.status || RESULT.MISSING;
    const classification = classifyPair(staging, homol, comparisonMode);
    counts[classification] = (counts[classification] || 0) + 1;
    const metadata = stagingTests.get(key) || homolTests.get(key);
    return { ...metadata, staging, homol, classification };
  });

  const valid = !scenarios.some((scenario) => (
    scenario.classification === 'INVALID' || scenario.classification === 'SELECTION_DRIFT'
  ));
  return {
    valid,
    comparisonMode,
    releaseEligible: valid && comparisonMode === COMPARISON_MODE.RELEASE_COMPARISON,
    counts,
    scenarios,
  };
}

function laneErrorReason(error) {
  if (error && error.code === 'ENOENT') return LANE_REASON.MISSING_REPORT;
  if (error && error.reportReason) return error.reportReason;
  return LANE_REASON.INVALID_REPORT;
}

function readLaneReport(label, filePath, exitCode) {
  try {
    const report = readPlaywrightReport(filePath);
    const scenarioCount = collectTests(report).size;
    if (scenarioCount === 0) {
      return {
        available: false,
        exitCode,
        label,
        reason: LANE_REASON.NO_SCENARIOS,
        scenarioCount: 0,
      };
    }
    return {
      available: true,
      exitCode,
      label,
      reason: LANE_REASON.AVAILABLE,
      report,
      scenarioCount,
    };
  } catch (error) {
    return {
      available: false,
      exitCode,
      label,
      reason: laneErrorReason(error),
      scenarioCount: 0,
    };
  }
}

function sanitizeLane(lane) {
  return {
    available: lane.available,
    exitCode: lane.exitCode,
    reason: lane.reason,
    scenarioCount: lane.scenarioCount,
  };
}

function compareLaneReports(
  stagingLane,
  homolLane,
  comparisonMode = COMPARISON_MODE.RELEASE_COMPARISON
) {
  const lanes = {
    staging: sanitizeLane(stagingLane),
    homol: sanitizeLane(homolLane),
  };
  const unavailable = Object.entries(lanes)
    .filter(([, lane]) => !lane.available)
    .map(([name, lane]) => `${name}:${lane.reason}`);

  if (unavailable.length > 0) {
    return {
      valid: false,
      comparisonMode,
      releaseEligible: false,
      counts: { INVALID: 1 },
      scenarios: [],
      lanes,
      invalidReasons: unavailable,
    };
  }

  return {
    ...compareReports(stagingLane.report, homolLane.report, comparisonMode),
    lanes,
    invalidReasons: [],
  };
}

function readPlaywrightReport(filePath) {
  const stats = fs.statSync(filePath);
  if (!stats.isFile() || stats.size === 0 || stats.size > 50 * 1024 * 1024) {
    const error = new Error('[E2E] Relatorio Playwright ausente, vazio ou grande demais.');
    error.reportReason = LANE_REASON.INVALID_REPORT;
    throw error;
  }
  try {
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
  } catch {
    const error = new Error('[E2E] Relatorio Playwright corrompido.');
    error.reportReason = LANE_REASON.CORRUPT_REPORT;
    throw error;
  }
}

function renderMarkdown(metadata, comparison) {
  const rows = Object.entries(comparison.counts)
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([classification, count]) => `| ${classification} | ${count} |`)
    .join('\n');
  const differences = comparison.scenarios
    .filter((scenario) => !['OK', 'EXPECTED_SKIP'].includes(scenario.classification))
    .map((scenario) => (
      `| \`${scenario.file}:${scenario.line}\` | ${scenario.title.replace(/\|/g, '\\|')} | ${scenario.staging} | ${scenario.homol} | ${scenario.classification} |`
    ))
    .join('\n');
  const expectedSkips = comparison.scenarios
    .filter((scenario) => scenario.classification === 'EXPECTED_SKIP')
    .map((scenario) => (
      `| \`${scenario.file}:${scenario.line}\` | ${scenario.title.replace(/\|/g, '\\|')} |`
    ))
    .join('\n');

  const comparisonMode = comparison.comparisonMode || COMPARISON_MODE.RELEASE_COMPARISON;
  const parityValidation = comparisonMode === COMPARISON_MODE.PARITY_VALIDATION;
  const versions = metadata.candidateVersion && metadata.baselineVersion
    ? parityValidation
      ? [
        `- Staging (parity lane): ${metadata.candidateVersion}`,
        `- Homol (parity lane): ${metadata.baselineVersion}`,
      ]
      : [
        `- Staging (RC): ${metadata.candidateVersion}`,
        `- Homol (production): ${metadata.baselineVersion}`,
      ]
    : [];
  const laneRows = comparison.lanes
    ? Object.entries(comparison.lanes).map(([name, lane]) => (
      `| ${name} | ${lane.available ? 'AVAILABLE' : 'UNAVAILABLE'} | ${lane.reason} | ${lane.exitCode} | ${lane.scenarioCount} |`
    ))
    : [];
  const unavailableLanes = comparison.lanes
    ? Object.entries(comparison.lanes)
      .filter(([, lane]) => !lane.available)
      .map(([name, lane]) => `${name} (${lane.reason})`)
      .join(', ')
    : '';

  return [
    `# E2E dual report — ${metadata.runId}`,
    '',
    `- Site: ${metadata.site}`,
    `- Checkout: ${metadata.checkout}`,
    ...versions,
    `- Started by: ${metadata.owner}`,
    `- Comparison mode: ${comparisonMode}`,
    `- Valid comparison: ${comparison.valid ? 'yes' : 'no'}`,
    `- Release verdict eligible: ${comparison.releaseEligible ? 'yes' : 'no'}`,
    ...(parityValidation
      ? ['- Warning: same-version parity validation; results cannot support a release verdict']
      : []),
    '- Store-mutating specs (`@serial-store`): excluded until snapshot restore is available',
    '',
    '## Lane availability',
    '',
    '| Lane | Status | Reason | Exit | Scenarios |',
    '| --- | --- | --- | ---: | ---: |',
    ...(laneRows.length > 0 ? laneRows : ['| staging/homol | UNKNOWN | NOT_RECORDED | — | — |']),
    '',
    '## Summary',
    '',
    '| Classification | Count |',
    '| --- | ---: |',
    rows || '| EMPTY | 0 |',
    '',
    '## Differences',
    '',
    '| File | Scenario | Staging | Homol | Classification |',
    '| --- | --- | --- | --- | --- |',
    differences || (unavailableLanes
      ? `| — | Unavailable lane(s): ${unavailableLanes} | — | — | INVALID |`
      : '| — | No differences | — | — | OK |'),
    '',
    '## Expected skips / coverage limitations',
    '',
    '| File | Scenario |',
    '| --- | --- |',
    expectedSkips || '| — | No expected skips |',
    '',
  ].join('\n');
}

function writeComparison(outputDir, metadata, comparison) {
  const resolvedOutput = path.resolve(outputDir);
  fs.mkdirSync(resolvedOutput, { recursive: true });
  const payload = { metadata, ...comparison };
  fs.writeFileSync(path.join(resolvedOutput, 'comparison.json'), `${JSON.stringify(payload, null, 2)}\n`, { mode: 0o600 });
  fs.writeFileSync(path.join(resolvedOutput, 'report.md'), renderMarkdown(metadata, comparison), { mode: 0o600 });
}

module.exports = {
  COMPARISON_MODE,
  LANE_REASON,
  RESULT,
  classifyPair,
  collectTests,
  compareReports,
  compareLaneReports,
  finalTestStatus,
  readPlaywrightReport,
  readLaneReport,
  renderMarkdown,
  writeComparison,
};
