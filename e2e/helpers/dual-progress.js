const fs = require('node:fs');
const path = require('node:path');
const { COMPARISON_MODE, classifyPair } = require('./dual-report');

const MAX_PROGRESS_FILE_SIZE = 2 * 1024 * 1024;
const VALID_LANE_STATUSES = new Set([
  'WAITING',
  'RUNNING',
  'COMPLETED',
  'FAILED',
  'TIMED_OUT',
  'INTERRUPTED',
]);
const VALID_RESULTS = new Set(['PASS', 'FAIL', 'FLAKY', 'SKIP']);

function emptyLane() {
  return {
    status: 'WAITING',
    total: 0,
    completed: 0,
    counts: { pass: 0, fail: 0, flaky: 0, skip: 0 },
    current: null,
    scenarios: Object.create(null),
  };
}

function isSafeInteger(value) {
  return Number.isSafeInteger(value) && value >= 0;
}

function normalizeProgress(payload) {
  if (!payload || typeof payload !== 'object' || !VALID_LANE_STATUSES.has(payload.status)) {
    throw new Error('[E2E] Estado invalido no monitor.');
  }
  if (!isSafeInteger(payload.total) || !isSafeInteger(payload.completed)) {
    throw new Error('[E2E] Contadores invalidos no monitor.');
  }

  const lane = emptyLane();
  lane.status = payload.status;
  lane.total = payload.total;
  lane.completed = payload.completed;
  for (const name of Object.keys(lane.counts)) {
    if (!isSafeInteger(payload.counts?.[name])) {
      throw new Error('[E2E] Totais invalidos no monitor.');
    }
    lane.counts[name] = payload.counts[name];
  }

  if (payload.current && typeof payload.current === 'object') {
    lane.current = {
      file: String(payload.current.file || '').slice(0, 240),
      line: isSafeInteger(payload.current.line) ? payload.current.line : 0,
      title: String(payload.current.title || '').slice(0, 300),
    };
  }

  if (!payload.scenarios || typeof payload.scenarios !== 'object' || Array.isArray(payload.scenarios)) {
    throw new Error('[E2E] Cenarios invalidos no monitor.');
  }
  for (const [key, scenario] of Object.entries(payload.scenarios)) {
    if (!scenario || !VALID_RESULTS.has(scenario.status)) {
      throw new Error('[E2E] Resultado invalido no monitor.');
    }
    lane.scenarios[String(key).slice(0, 900)] = {
      file: String(scenario.file || '').slice(0, 240),
      line: isSafeInteger(scenario.line) ? scenario.line : 0,
      title: String(scenario.title || '').slice(0, 300),
      status: scenario.status,
    };
  }
  return lane;
}

function readProgress(filePath) {
  try {
    const stats = fs.statSync(filePath);
    if (!stats.isFile() || stats.size === 0 || stats.size > MAX_PROGRESS_FILE_SIZE) {
      return emptyLane();
    }
    return normalizeProgress(JSON.parse(fs.readFileSync(filePath, 'utf8')));
  } catch {
    return emptyLane();
  }
}

function compareLiveProgress(
  staging,
  homol,
  comparisonMode = COMPARISON_MODE.RELEASE_COMPARISON
) {
  const counts = {};
  const probableRegressions = [];
  const sharedKeys = Object.keys(staging.scenarios)
    .filter((key) => homol.scenarios[key])
    .sort();

  for (const key of sharedKeys) {
    const candidate = staging.scenarios[key];
    const baseline = homol.scenarios[key];
    const classification = classifyPair(candidate.status, baseline.status, comparisonMode);
    counts[classification] = (counts[classification] || 0) + 1;
    if (classification === 'PROBABLE_REGRESSION') {
      probableRegressions.push({
        file: candidate.file,
        line: candidate.line,
        title: candidate.title,
      });
    }
  }

  return { counts, matched: sharedKeys.length, probableRegressions };
}

function formatLane(name, lane) {
  const total = lane.total || '?';
  return `${name.padEnd(8)} ${String(lane.completed).padStart(3)}/${String(total).padEnd(3)}`
    + `  OK ${String(lane.counts.pass).padStart(3)}`
    + `  FAIL ${String(lane.counts.fail).padStart(3)}`
    + `  FLAKY ${String(lane.counts.flaky).padStart(3)}`
    + `  SKIP ${String(lane.counts.skip).padStart(3)}`
    + `  ${lane.status}`;
}

function currentLine(name, lane) {
  if (!lane.current) return `${name}: aguardando proximo cenario`;
  const location = lane.current.file
    ? `${lane.current.file}${lane.current.line ? `:${lane.current.line}` : ''}`
    : 'cenario';
  return `${name}: ${location} — ${lane.current.title}`.slice(0, 180);
}

function renderDashboard(metadata, staging, homol) {
  const comparisonMode = metadata.comparisonMode || COMPARISON_MODE.RELEASE_COMPARISON;
  const comparison = compareLiveProgress(staging, homol, comparisonMode);
  const classifications = Object.entries(comparison.counts)
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([name, count]) => `${name}=${count}`)
    .join(' | ');
  const regressions = comparison.probableRegressions.slice(0, 3).map((scenario) => (
    `  ! ${scenario.file}:${scenario.line} — ${scenario.title}`
  ));

  const versionLabel = metadata.candidateVersion && metadata.baselineVersion
    ? comparisonMode === COMPARISON_MODE.PARITY_VALIDATION
      ? ` | parity=${metadata.candidateVersion}`
      : ` | RC=${metadata.candidateVersion} prod=${metadata.baselineVersion}`
    : '';
  return [
    `E2E monitor | run=${metadata.runId} | ${metadata.site} ${metadata.checkout}${versionLabel}`,
    '--------------------------------------------------------------------------',
    formatLane('staging', staging),
    formatLane('homol', homol),
    '',
    currentLine('staging', staging),
    currentLine('homol', homol),
    '',
    `Comparados ao vivo: ${comparison.matched}`,
    classifications || 'Aguardando o primeiro par de resultados...',
    ...regressions,
    '',
  ].join('\n');
}

function createDualProgressMonitor(root, metadata, options = {}) {
  const output = options.output || process.stdout;
  const intervalMs = options.intervalMs || 1000;
  const progressFiles = {
    staging: path.join(root, 'staging', 'progress.json'),
    homol: path.join(root, 'homol', 'progress.json'),
  };
  let timer = null;
  let lastSnapshot = '';
  let lastPrintAt = 0;
  let renderedLines = 0;

  function render(force = false) {
    const staging = readProgress(progressFiles.staging);
    const homol = readProgress(progressFiles.homol);
    const dashboard = renderDashboard(metadata, staging, homol);
    if (!force && dashboard === lastSnapshot) return;

    const now = Date.now();
    const interactive = Boolean(output.isTTY && !process.env.CI);
    if (!interactive && !force && now - lastPrintAt < 5000) return;

    if (interactive && renderedLines > 0) {
      output.write(`\u001b[${renderedLines}F\u001b[J`);
    }
    output.write(dashboard);
    renderedLines = dashboard.split('\n').length - 1;
    lastSnapshot = dashboard;
    lastPrintAt = now;
  }

  return {
    progressFiles,
    start() {
      render(true);
      timer = setInterval(render, intervalMs);
      timer.unref();
    },
    stop() {
      if (timer) clearInterval(timer);
      timer = null;
      render(true);
    },
  };
}

module.exports = {
  compareLiveProgress,
  createDualProgressMonitor,
  emptyLane,
  normalizeProgress,
  readProgress,
  renderDashboard,
};
