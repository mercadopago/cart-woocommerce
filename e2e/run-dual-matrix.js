#!/usr/bin/env node
const { randomBytes } = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { spawn } = require('node:child_process');
const { VALID_SITES } = require('./helpers/environment-config');
const { readProgress } = require('./helpers/dual-progress');

const E2E_DIR = __dirname;
const SITE_ORDER = ['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE'];
// Longest-processing-time order measured from the complete PSW-4320 baseline.
// Starting the slowest countries first avoids leaving MPE or MLB alone at the end.
const EXECUTION_ORDER = ['MLB', 'MPE', 'MLA', 'MLU', 'MCO', 'MLC', 'MLM'];
const SAFE_VERSION = /^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/;
const activeChildren = new Set();
const activeJobs = new Map();

function usage() {
  process.stdout.write(
    'Usage: node e2e/run-dual-matrix.js --candidate-version X.Y.Z --baseline-version A.B.C [--parity-validation] [--sites MLA,MLB,...] [--checkout classic|blocks|both] [--concurrency 1..4] [--timeout-profile standard|fast] [--monitor] [--with-retries] [--dry-run]\n'
  );
}

function parseArgs(argv) {
  const options = {
    checkout: 'both',
    concurrency: 4,
    dryRun: false,
    monitor: false,
    parityValidation: false,
    sites: [...SITE_ORDER],
    timeoutProfile: 'standard',
    withRetries: false,
  };

  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (
      argument === '--sites'
      || argument === '--checkout'
      || argument === '--concurrency'
      || argument === '--timeout-profile'
      || argument === '--candidate-version'
      || argument === '--baseline-version'
    ) {
      const value = argv[index + 1];
      if (!value || value.startsWith('--')) throw new Error(`[E2E] ${argument} requer um valor.`);
      if (argument === '--sites') {
        const sites = value.split(',').map((site) => site.trim().toUpperCase());
        if (
          sites.length === 0
          || new Set(sites).size !== sites.length
          || sites.some((site) => !VALID_SITES.has(site))
        ) {
          throw new Error('[E2E] --sites contem um pais invalido ou duplicado.');
        }
        options.sites = SITE_ORDER.filter((site) => sites.includes(site));
      } else if (argument === '--concurrency') {
        const concurrency = Number(value);
        if (!Number.isInteger(concurrency) || concurrency < 1 || concurrency > 4) {
          throw new Error('[E2E] --concurrency deve estar entre 1 e 4.');
        }
        options.concurrency = concurrency;
      } else {
        const keys = {
          '--checkout': 'checkout',
          '--timeout-profile': 'timeoutProfile',
          '--candidate-version': 'candidateVersion',
          '--baseline-version': 'baselineVersion',
        };
        const key = keys[argument];
        options[key] = key.endsWith('Version') ? value.replace(/^v/, '') : value;
      }
      index += 1;
    } else if (argument === '--monitor') {
      options.monitor = true;
    } else if (argument === '--with-retries') {
      options.withRetries = true;
    } else if (argument === '--parity-validation') {
      options.parityValidation = true;
    } else if (argument === '--dry-run') {
      options.dryRun = true;
    } else if (argument === '--help' || argument === '-h') {
      usage();
      process.exit(0);
    } else {
      throw new Error(`[E2E] Argumento nao suportado: ${argument}.`);
    }
  }

  if (!['classic', 'blocks', 'both'].includes(options.checkout)) {
    throw new Error('[E2E] --checkout deve ser classic, blocks ou both.');
  }
  if (!['standard', 'fast'].includes(options.timeoutProfile)) {
    throw new Error('[E2E] --timeout-profile deve ser standard ou fast.');
  }
  if (!options.candidateVersion || !options.baselineVersion) {
    throw new Error('[E2E] As versoes candidate e baseline sao obrigatorias.');
  }
  if (
    !SAFE_VERSION.test(options.candidateVersion) || !SAFE_VERSION.test(options.baselineVersion)
  ) {
    throw new Error('[E2E] Versao candidate ou baseline invalida.');
  }
  const sameVersion = options.candidateVersion === options.baselineVersion;
  if (sameVersion && !options.parityValidation) {
    throw new Error('[E2E] Versoes iguais exigem --parity-validation e nao valem como comparacao de release.');
  }
  if (!sameVersion && options.parityValidation) {
    throw new Error('[E2E] --parity-validation exige versoes iguais nas duas lanes.');
  }

  return options;
}

function formatDuration(milliseconds) {
  const seconds = Math.max(0, Math.round(milliseconds / 1000));
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const remainder = seconds % 60;
  return [hours, minutes, remainder].map((value) => String(value).padStart(2, '0')).join(':');
}

function prioritizeSites(sites) {
  return EXECUTION_ORDER.filter((site) => sites.includes(site));
}

function laneSummary(label, progress) {
  const total = progress.total || '?';
  return `${label} ${progress.completed}/${total} ${progress.status}`;
}

function renderActiveJobs() {
  if (activeJobs.size === 0) return;
  const summaries = [...activeJobs.values()].map((job) => {
    const root = path.join(E2E_DIR, 'results', 'dual', job.runId);
    const staging = readProgress(path.join(root, 'staging', 'progress.json'));
    const homol = readProgress(path.join(root, 'homol', 'progress.json'));
    return `${job.site}/${job.checkout}: ${laneSummary('S', staging)} | ${laneSummary('H', homol)}`;
  });
  process.stdout.write(`[E2E matrix] ${summaries.join(' || ')}\n`);
}

function runPair(site, checkout, options, matrixRoot) {
  const runId = randomBytes(12).toString('hex');
  const startedAt = Date.now();
  const args = [
    path.join(E2E_DIR, 'run-dual-report.js'),
    '--site', site,
    '--checkout', checkout,
    '--timeout-profile', options.timeoutProfile,
    '--run-id', runId,
    '--progress-only',
  ];
  args.push(
    '--candidate-version', options.candidateVersion,
    '--baseline-version', options.baselineVersion
  );
  if (options.parityValidation) args.push('--parity-validation');
  if (options.withRetries) args.push('--with-retries');
  if (options.dryRun) args.push('--dry-run');

  const logPath = path.join(matrixRoot, `${site.toLowerCase()}-${checkout}.log`);
  // Open synchronously before spawning a child. A path/permission failure therefore cannot orphan
  // a dual runner that would continue holding its remote lease without a parent log consumer.
  const logDescriptor = fs.openSync(logPath, 'wx', 0o600);
  const log = fs.createWriteStream(logPath, { fd: logDescriptor, autoClose: true });
  let logError = false;
  let child;
  log.on('error', () => {
    logError = true;
    if (child && !child.killed) child.kill('SIGTERM');
  });
  process.stdout.write(`[E2E matrix] INICIO ${site}/${checkout} | run_id=${runId}\n`);

  return new Promise((resolve) => {
    child = spawn(process.execPath, args, {
      cwd: E2E_DIR,
      env: process.env,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    const job = { checkout, runId, site };
    activeChildren.add(child);
    activeJobs.set(runId, job);
    child.stdout.pipe(log, { end: false });
    child.stderr.pipe(log, { end: false });

    let spawnError = false;
    child.on('error', () => {
      spawnError = true;
    });
    child.on('close', (code) => {
      activeChildren.delete(child);
      activeJobs.delete(runId);
      log.end();
      const exitCode = spawnError || logError ? 1 : (code === 0 ? 0 : 1);
      const durationMs = Date.now() - startedAt;
      process.stdout.write(
        `[E2E matrix] FIM ${site}/${checkout} | status=${exitCode === 0 ? 'OK' : 'FALHOU'} | duracao=${formatDuration(durationMs)} | run_id=${runId}\n`
      );
      resolve({ checkout, durationMs, exitCode, logPath, runId, site });
    });
  });
}

function writeMatrixReport(matrixRoot, metadata, results) {
  const ordered = [...results].sort((left, right) => (
    SITE_ORDER.indexOf(left.site) - SITE_ORDER.indexOf(right.site)
    || ['classic', 'blocks'].indexOf(left.checkout) - ['classic', 'blocks'].indexOf(right.checkout)
  ));
  const payload = { ...metadata, results: ordered };
  fs.writeFileSync(
    path.join(matrixRoot, 'matrix.json'),
    `${JSON.stringify(payload, null, 2)}\n`,
    { encoding: 'utf8', mode: 0o600 }
  );

  const rows = ordered.map((result) => (
    `| ${result.site} | ${result.checkout} | ${result.exitCode === 0 ? 'OK' : 'FALHOU'} | ${formatDuration(result.durationMs)} | ${result.runId} |`
  ));
  const report = [
    '# Matriz E2E compartilhada',
    '',
    `- Matrix ID: \`${metadata.matrixId}\``,
    `- Concorrencia por pais: \`${metadata.concurrency}\``,
    `- Ordem de execucao: \`${metadata.executionOrder.join(',')}\``,
    `- Perfil de timeout: \`${metadata.timeoutProfile}\``,
    `- Comparison mode: \`${metadata.comparisonMode}\``,
    `- Release verdict eligible: \`${metadata.releaseEligible ? 'yes' : 'no'}\``,
    ...(metadata.parityValidation
      ? [
        `- Staging (parity lane): \`${metadata.candidateVersion}\``,
        `- Homol (parity lane): \`${metadata.baselineVersion}\``,
        '- Warning: same-version parity validation; results cannot support a release verdict',
      ]
      : [
        `- Staging (RC): \`${metadata.candidateVersion}\``,
        `- Homol (production): \`${metadata.baselineVersion}\``,
      ]),
    `- Duracao: \`${formatDuration(metadata.durationMs)}\``,
    '',
    '| Site | Checkout | Status | Duracao | Run ID |',
    '|---|---|---|---:|---|',
    ...rows,
    '',
  ].join('\n');
  fs.writeFileSync(path.join(matrixRoot, 'report.md'), report, { encoding: 'utf8', mode: 0o600 });
}

function createMatrixSignalHandler(children, onFirstSignal) {
  let signalCount = 0;
  return () => {
    signalCount += 1;
    if (signalCount === 1) onFirstSignal();
    const childSignal = signalCount === 1 ? 'SIGTERM' : 'SIGKILL';
    for (const child of children) child.kill(childSignal);
  };
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  const matrixId = randomBytes(12).toString('hex');
  const matrixRoot = path.join(E2E_DIR, 'results', 'matrix', matrixId);
  fs.mkdirSync(matrixRoot, { recursive: true, mode: 0o700 });
  const startedAt = Date.now();
  const results = [];
  const checkouts = options.checkout === 'both' ? ['classic', 'blocks'] : [options.checkout];
  const executionSites = prioritizeSites(options.sites);
  let nextSite = 0;
  let interrupted = false;

  process.stdout.write(
    `[E2E matrix] matrix_id=${matrixId} | ordem=${executionSites.join(',')} | concorrencia=${options.concurrency} | checkout=${options.checkout} | timeout_profile=${options.timeoutProfile} | comparison_mode=${options.parityValidation ? 'PARITY_VALIDATION' : 'RELEASE_COMPARISON'}\n`
  );
  if (options.parityValidation) {
    process.stdout.write('[E2E matrix] Validacao de paridade: esta execucao nao produz veredito de release.\n');
  }

  const handleSignal = createMatrixSignalHandler(activeChildren, () => {
    interrupted = true;
  });
  process.on('SIGINT', handleSignal);
  process.on('SIGTERM', handleSignal);

  const monitor = options.monitor ? setInterval(renderActiveJobs, 15000) : null;
  if (monitor) monitor.unref();

  async function worker() {
    while (!interrupted) {
      const siteIndex = nextSite;
      nextSite += 1;
      if (siteIndex >= executionSites.length) return;
      const site = executionSites[siteIndex];
      for (const checkout of checkouts) {
        if (interrupted) return;
        results.push(await runPair(site, checkout, options, matrixRoot));
      }
    }
  }

  try {
    const workers = Array.from(
      { length: Math.min(options.concurrency, options.sites.length) },
      () => worker()
    );
    await Promise.all(workers);
  } finally {
    if (monitor) clearInterval(monitor);
    process.removeListener('SIGINT', handleSignal);
    process.removeListener('SIGTERM', handleSignal);
  }

  const durationMs = Date.now() - startedAt;
  writeMatrixReport(matrixRoot, {
    baselineVersion: options.baselineVersion,
    candidateVersion: options.candidateVersion,
    checkout: options.checkout,
    comparisonMode: options.parityValidation ? 'PARITY_VALIDATION' : 'RELEASE_COMPARISON',
    concurrency: options.concurrency,
    executionOrder: executionSites,
    durationMs,
    finishedAt: new Date().toISOString(),
    matrixId,
    parityValidation: options.parityValidation,
    releaseEligible: !options.parityValidation
      && results.length === executionSites.length * checkouts.length
      && results.every((result) => result.exitCode === 0),
    sites: options.sites,
    startedAt: new Date(startedAt).toISOString(),
    timeoutProfile: options.timeoutProfile,
  }, results);
  process.stdout.write(
    `[E2E matrix] Relatorio: ${path.join(matrixRoot, 'report.md')} | duracao=${formatDuration(durationMs)}\n`
  );
  if (interrupted) process.exitCode = 130;
  else if (results.some((result) => result.exitCode !== 0)) process.exitCode = 1;
}

if (require.main === module) {
  main().catch((error) => {
    const message = error.message && error.message.startsWith('[E2E]')
      ? error.message
      : '[E2E] Falha inesperada no runner da matriz.';
    process.stderr.write(`${message}\n`);
    process.exitCode = 1;
  });
}

module.exports = {
  createMatrixSignalHandler,
  formatDuration,
  parseArgs,
  prioritizeSites,
  writeMatrixReport,
};
