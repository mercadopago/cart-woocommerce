const fs = require('node:fs');
const path = require('node:path');

const DUAL_RESULTS_ROOT = path.resolve(__dirname, '..', 'results', 'dual');
const CONTROL_CHARACTERS = /[\u0000-\u001f\u007f]/g;
const FINAL_STATUSES = new Set(['PASS', 'FAIL', 'FLAKY', 'SKIP']);

function sanitizeText(value, maxLength = 300) {
  return String(value || '')
    .replace(CONTROL_CHARACTERS, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, maxLength);
}

function resolveProgressPath(filePath, resultsRoot = DUAL_RESULTS_ROOT) {
  if (!filePath) return null;

  const root = path.resolve(resultsRoot);
  const resolved = path.resolve(filePath);
  const relative = path.relative(root, resolved).split(path.sep).join('/');
  if (!/^[0-9a-f]{24}\/(staging|homol)\/progress\.json$/.test(relative)) {
    throw new Error('[E2E] Caminho do monitor fora do diretorio permitido.');
  }
  return resolved;
}

function scenarioMetadata(test) {
  const testsRoot = path.resolve(__dirname, '..', 'tests');
  const relativeFile = path.relative(testsRoot, test.location.file).split(path.sep).join('/');
  const file = relativeFile.startsWith('../') ? path.basename(test.location.file) : relativeFile;
  const project = sanitizeText(test.parent?.project()?.name || 'default', 80);
  const title = sanitizeText(test.title);
  const line = Number.isInteger(test.location.line) ? test.location.line : 0;
  return {
    key: `${file}:${line}:${title}:${project}`,
    file: sanitizeText(file, 240),
    line,
    project,
    title,
  };
}

function finalStatus(test, result) {
  if (result.status === 'skipped' || test.outcome() === 'skipped') return 'SKIP';
  if (test.outcome() === 'flaky' || test.results.some((attempt) => attempt.status === 'failed')) {
    return result.status === 'passed' ? 'FLAKY' : 'FAIL';
  }
  if (result.status === 'passed') return 'PASS';
  return 'FAIL';
}

class ProgressReporter {
  constructor() {
    this.filePath = resolveProgressPath(process.env.E2E_PROGRESS_FILE);
    this.disabled = false;
    this.warned = false;
    this.writeSequence = 0;
    this.state = {
      schemaVersion: 1,
      status: 'WAITING',
      startedAt: null,
      updatedAt: null,
      finishedAt: null,
      total: 0,
      completed: 0,
      counts: { pass: 0, fail: 0, flaky: 0, skip: 0 },
      current: null,
      scenarios: {},
    };
  }

  writeState() {
    if (!this.filePath || this.disabled) return;

    const directory = path.dirname(this.filePath);
    let temporaryPath;
    try {
      fs.mkdirSync(directory, { recursive: true, mode: 0o700 });
      fs.chmodSync(directory, 0o700);
      this.state.updatedAt = new Date().toISOString();
      this.writeSequence += 1;
      temporaryPath = `${this.filePath}.${process.pid}.${this.writeSequence}.tmp`;
      fs.writeFileSync(temporaryPath, `${JSON.stringify(this.state, null, 2)}\n`, {
        encoding: 'utf8',
        flag: 'wx',
        mode: 0o600,
      });
      fs.renameSync(temporaryPath, this.filePath);
    } catch {
      // Progress is best-effort metadata. Disable it after the first filesystem failure so an
      // unavailable monitor cannot turn a valid Playwright result into a failed release lane.
      this.disabled = true;
      this.filePath = null;
      if (!this.warned) {
        this.warned = true;
        process.stderr.write('[E2E] Monitor de progresso desabilitado por falha local de escrita.\n');
      }
    } finally {
      if (temporaryPath) {
        try {
          fs.rmSync(temporaryPath, { force: true });
        } catch {
          // Best-effort cleanup must not affect the test result either.
        }
      }
    }
  }

  onBegin(_config, suite) {
    this.state.status = 'RUNNING';
    this.state.startedAt = new Date().toISOString();
    this.state.total = suite.allTests().length;
    this.writeState();
  }

  onTestBegin(test, result) {
    const metadata = scenarioMetadata(test);
    this.state.current = {
      ...metadata,
      attempt: result.retry + 1,
    };
    this.writeState();
  }

  onTestEnd(test, result) {
    const retries = Number.isInteger(test.retries) ? test.retries : 0;
    const retryableFailure = !['passed', 'skipped', 'interrupted'].includes(result.status);
    if (retryableFailure && result.retry < retries) {
      this.state.current = {
        ...scenarioMetadata(test),
        attempt: result.retry + 2,
      };
      this.writeState();
      return;
    }

    const metadata = scenarioMetadata(test);
    const status = finalStatus(test, result);
    if (!FINAL_STATUSES.has(status)) return;

    const previous = this.state.scenarios[metadata.key];
    if (!previous) {
      this.state.completed += 1;
      this.state.counts[status.toLowerCase()] += 1;
    }
    this.state.scenarios[metadata.key] = { ...metadata, status };
    this.state.current = null;
    this.writeState();
  }

  onEnd(result) {
    const statusMap = {
      passed: 'COMPLETED',
      failed: 'FAILED',
      timedout: 'TIMED_OUT',
      interrupted: 'INTERRUPTED',
    };
    this.state.status = statusMap[result.status] || 'FAILED';
    this.state.current = null;
    this.state.finishedAt = new Date().toISOString();
    this.writeState();
  }
}

module.exports = ProgressReporter;
module.exports.DUAL_RESULTS_ROOT = DUAL_RESULTS_ROOT;
module.exports.finalStatus = finalStatus;
module.exports.resolveProgressPath = resolveProgressPath;
module.exports.sanitizeText = sanitizeText;
module.exports.scenarioMetadata = scenarioMetadata;
