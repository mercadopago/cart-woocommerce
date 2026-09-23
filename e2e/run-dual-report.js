#!/usr/bin/env node
const { randomBytes } = require('crypto');
const fs = require('fs');
const path = require('path');
const { spawn, spawnSync } = require('child_process');
const {
  getEnvironmentTarget,
  loadEnvironmentConfig,
  VALID_SITES,
} = require('./helpers/environment-config');
const {
  COMPARISON_MODE,
  compareLaneReports,
  LANE_REASON,
  readLaneReport,
  writeComparison,
} = require('./helpers/dual-report');
const { createDualProgressMonitor } = require('./helpers/dual-progress');
const { startLeaseHeartbeat } = require('./helpers/lease-heartbeat');
const { verifyInstalledVersion } = require('./helpers/installed-version');
const { getSshOptions } = require('./helpers/ssh-options');

const E2E_DIR = __dirname;
const SAFE_OWNER = /^[a-zA-Z0-9][a-zA-Z0-9._-]{1,63}$/;
const SAFE_VERSION = /^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/;
const activeChildren = new Set();
const terminationTimers = new Map();
const USE_PROCESS_GROUPS = process.platform !== 'win32';
const TERMINATION_GRACE_MS = 10000;
const REMOTE_LOCK_SCRIPT = `
set -eu
action="$1"
lock_name="$2"
token="$3"
owner="$4"
ttl="$5"
correlation_id="$6"
root="$HOME/.woo-e2e-locks"
lock_dir="$root/$lock_name"
umask 077
mkdir -p "$root"
exec 9>"$root/.guard-$lock_name"
chmod 600 "$root/.guard-$lock_name"
flock -w 10 9 || exit 75
case "$action" in
  acquire)
    now=$(date +%s)
    if [ -d "$lock_dir" ]; then
      acquired=$(cat "$lock_dir/acquired_at" 2>/dev/null || printf '0')
      case "$acquired" in *[!0-9]*|'') acquired=0 ;; esac
      if [ "$acquired" -eq 0 ] || [ $((now - acquired)) -gt "$ttl" ]; then
        stale="$root/.stale-$lock_name-$token"
        if mv "$lock_dir" "$stale" 2>/dev/null; then
          rm -rf "$stale"
        else
          exit 75
        fi
      else
        exit 75
      fi
    fi
    pending="$root/.pending-$lock_name-$token"
    rm -rf "$pending"
    mkdir "$pending"
    printf '%s' "$token" > "$pending/token"
    printf '%s' "$owner" > "$pending/owner"
    printf '%s' "$now" > "$pending/acquired_at"
    if [ -e "$lock_dir" ] || ! mv "$pending" "$lock_dir"; then
      rm -rf "$pending"
      exit 75
    fi
    printf '%s\tacquire\t%s\t%s\n' "$now" "$owner" "$correlation_id" >> "$root/audit.log"
    chmod 600 "$root/audit.log"
    ;;
  renew)
    current=$(cat "$lock_dir/token" 2>/dev/null || printf '')
    [ "$current" = "$token" ] || exit 77
    now=$(date +%s)
    renewed="$lock_dir/.acquired_at-$token"
    printf '%s' "$now" > "$renewed"
    mv "$renewed" "$lock_dir/acquired_at"
    printf '%s\trenew\t%s\t%s\n' "$now" "$owner" "$correlation_id" >> "$root/audit.log"
    chmod 600 "$root/audit.log"
    ;;
  release)
    current=$(cat "$lock_dir/token" 2>/dev/null || printf '')
    [ "$current" = "$token" ] || exit 77
    released="$root/.released-$lock_name-$token"
    rm -rf "$released"
    mv "$lock_dir" "$released"
    rm -rf "$released"
    printf '%s\trelease\t%s\t%s\n' "$(date +%s)" "$owner" "$correlation_id" >> "$root/audit.log"
    ;;
  probe)
    # Reports whether a lease is held without taking it. Exit 75 means held, 0 means free.
    if [ -d "$lock_dir" ]; then
      acquired=$(cat "$lock_dir/acquired_at" 2>/dev/null || printf '0')
      case "$acquired" in *[!0-9]*|'') acquired=0 ;; esac
      if [ "$acquired" -ne 0 ] && [ $(($(date +%s) - acquired)) -le "$ttl" ]; then
        cat "$lock_dir/owner" 2>/dev/null >&2 || true
        exit 75
      fi
    fi
    ;;
  *) exit 64 ;;
esac
`;

function usage() {
  process.stdout.write(
    'Usage: node e2e/run-dual-report.js --site MLB --checkout classic|blocks --candidate-version X.Y.Z --baseline-version A.B.C [--parity-validation] [--timeout-profile standard|fast] [--monitor|--progress-only] [--run-id 24hex] [--with-retries] [--dry-run]\n'
  );
}

function parseArgs(argv) {
  const options = {
    dryRun: false,
    monitor: false,
    parityValidation: false,
    progressOnly: false,
    timeoutProfile: 'standard',
    withRetries: false,
  };
  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (
      argument === '--site'
      || argument === '--checkout'
      || argument === '--candidate-version'
      || argument === '--baseline-version'
      || argument === '--run-id'
      || argument === '--timeout-profile'
    ) {
      const value = argv[index + 1];
      if (!value || value.startsWith('--')) throw new Error(`[E2E] ${argument} requer um valor.`);
      const keys = {
        '--site': 'site',
        '--checkout': 'checkout',
        '--candidate-version': 'candidateVersion',
        '--baseline-version': 'baselineVersion',
        '--run-id': 'runId',
        '--timeout-profile': 'timeoutProfile',
      };
      const key = keys[argument];
      options[key] = key.endsWith('Version') ? value.replace(/^v/, '') : value;
      index += 1;
    } else if (argument === '--with-retries') {
      options.withRetries = true;
    } else if (argument === '--monitor') {
      options.monitor = true;
    } else if (argument === '--progress-only') {
      options.progressOnly = true;
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
  if (!options.site || !options.checkout) {
    throw new Error('[E2E] --site e --checkout sao obrigatorios.');
  }
  if (!options.candidateVersion || !options.baselineVersion) {
    throw new Error('[E2E] As versoes candidate e baseline sao obrigatorias.');
  }
  if (
    !SAFE_VERSION.test(options.candidateVersion) || !SAFE_VERSION.test(options.baselineVersion)
  ) {
    throw new Error('[E2E] Versao candidate ou baseline invalida.');
  }
  if (!['standard', 'fast'].includes(options.timeoutProfile)) {
    throw new Error('[E2E] --timeout-profile deve ser standard ou fast.');
  }
  if (options.runId && !/^[0-9a-f]{24}$/.test(options.runId)) {
    throw new Error('[E2E] --run-id invalido.');
  }
  if (options.monitor && options.progressOnly) {
    throw new Error('[E2E] --monitor e --progress-only nao podem ser usados juntos.');
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

function getRemoteLockName(config, site) {
  const normalizedSite = String(site || '').toUpperCase();
  if (!VALID_SITES.has(normalizedSite)) {
    throw new Error('[E2E] Site invalido para o lease remoto.');
  }
  return `${config.lock.name}-${normalizedSite.toLowerCase()}`;
}

// The seven countries of an environment share one artifacts lane, so publishing is a lane-wide
// writer while a test run is a per-country reader. They are kept mutually exclusive by ordering,
// not by a single global lock, which would serialise the parallel matrix: the publisher takes the
// writer lock and only then probes the country leases, while a runner takes its country lease and
// only then probes the writer. For both to proceed the publisher would have to observe a country
// free after the runner took it while the runner observed the writer free before the publisher
// took it, which the ordering forbids. Both can back off; neither can overlap.
function getPublishLockName(config) {
  return `${config.lock.name}-publish`;
}

function probeRemoteLock(config, lockName, dependencies = {}) {
  const target = `${config.lock.sshUser}@${config.lock.sshHost}`;
  const spawnSyncFn = dependencies.spawnSync || spawnSync;
  const sshOptions = dependencies.sshOptions || getSshOptions(process.env, config.lock.sshHost);
  const result = spawnSyncFn(
    'ssh',
    [
      ...sshOptions,
      target,
      'sh', '-s', '--', 'probe', lockName, 'probe', 'probe', String(config.lock.ttlSeconds), 'probe',
    ],
    { input: REMOTE_LOCK_SCRIPT, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'], timeout: 30000 }
  );
  if (result.status === 0) return { held: false, owner: '' };
  if (result.status === 75) {
    return { held: true, owner: String(result.stderr || '').trim().slice(0, 64) };
  }
  throw new Error('[E2E] Nao foi possivel consultar o estado do lease remoto.');
}

// Called by a runner once it already holds its country lease.
function assertNoPublishInFlight(config, dependencies = {}) {
  const publishState = probeRemoteLock(config, getPublishLockName(config), dependencies);
  if (publishState.held) {
    throw new Error(
      '[E2E] Uma publicacao esta em andamento na lane compartilhada'
      + `${publishState.owner ? ` (${publishState.owner})` : ''}; aguarde antes de executar os testes.`
    );
  }
}

// Writer side: take the lane-wide lock, then require every country lease to be free.
function runPublishLock(config, action, token, owner, correlationId, dependencies = {}) {
  runLockAction(config, action, token, owner, correlationId, getPublishLockName(config), dependencies);
  if (action !== 'acquire') return;

  try {
    for (const site of [...VALID_SITES].sort()) {
      const siteState = probeRemoteLock(config, getRemoteLockName(config, site), dependencies);
      if (siteState.held) {
        throw new Error(
          `[E2E] ${site} tem uma execucao ativa na lane compartilhada`
          + `${siteState.owner ? ` (${siteState.owner})` : ''}; publicar agora trocaria o plugin embaixo dela.`
        );
      }
    }
  } catch (error) {
    runLockAction(config, 'release', token, owner, correlationId, getPublishLockName(config), dependencies);
    throw error;
  }
}

function runRemoteLock(config, action, token, owner, correlationId, site, dependencies = {}) {
  runLockAction(config, action, token, owner, correlationId, getRemoteLockName(config, site), dependencies);
}

function runLockAction(config, action, token, owner, correlationId, lockName, dependencies = {}) {
  if (
    !['acquire', 'renew', 'release'].includes(action)
    || !/^[0-9a-f]{48}$/.test(token)
    || !SAFE_OWNER.test(owner || '')
    || !/^[0-9a-f]{24,64}$/.test(correlationId || '')
  ) {
    throw new Error('[E2E] Parametros do lease remoto invalidos.');
  }
  const target = `${config.lock.sshUser}@${config.lock.sshHost}`;
  const spawnSyncFn = dependencies.spawnSync || spawnSync;
  const sshOptions = dependencies.sshOptions || getSshOptions(process.env, config.lock.sshHost);
  const attempts = action === 'renew' ? 3 : 1;
  let result;
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    result = spawnSyncFn(
      'ssh',
      [
        ...sshOptions,
        target,
        'sh', '-s', '--', action, lockName, token, owner, String(config.lock.ttlSeconds), correlationId,
      ],
      { input: REMOTE_LOCK_SCRIPT, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'], timeout: 30000 }
    );
    if (result.status === 0) return;
    // Exit 77 proves that this process no longer owns the lock; retrying could
    // only delay the mandatory fail-closed shutdown.
    if (result.status === 77) break;
  }

  const reason = result?.status === 75
    ? 'o par ja possui uma execucao ativa'
    : 'falha no controle do lease remoto';
  const actionLabel = { acquire: 'adquirir', renew: 'renovar', release: 'liberar' }[action];
  throw new Error(`[E2E] Nao foi possivel ${actionLabel} o lease: ${reason}.`);
}

function runEnvironment(environment, target, options, runId, progressFile = null) {
  const args = [
    path.join(E2E_DIR, 'run-all-report.sh'),
    '--site', options.site.toUpperCase(),
    '--checkout', options.checkout.toLowerCase(),
    '--no-report',
  ];
  if (options.withRetries) args.push('--with-retries');

  const outputDir = path.join(E2E_DIR, 'results', 'dual', runId, environment);
  return new Promise((resolve) => {
    let settled = false;
    const child = spawn('bash', args, {
      cwd: E2E_DIR,
      detached: USE_PROCESS_GROUPS,
      env: {
        ...process.env,
        CHECKOUT_URL: target.checkoutUrl,
        E2E_ENVIRONMENT: environment,
        E2E_REMOTE_MAIN_ONLY: '1',
        E2E_RESULTS_SUBDIR: `dual/${runId}/${environment}`,
        E2E_TIMEOUT_PROFILE: options.timeoutProfile,
        ...(progressFile ? { E2E_PROGRESS_FILE: progressFile } : {}),
        PLAYWRIGHT_OUTPUT_DIR: path.join(outputDir, 'test-results'),
        SHOP_URL: target.shopUrl,
        WP_EXTERNAL_STORE: '1',
      },
      stdio: progressFile ? ['ignore', 'pipe', 'pipe'] : 'inherit',
    });
    if (progressFile) {
      child.stdout.resume();
      child.stderr.resume();
    }
    activeChildren.add(child);
    const finish = (code) => {
      if (settled) return;
      settled = true;
      const timer = terminationTimers.get(child);
      if (timer) clearTimeout(timer);
      terminationTimers.delete(child);
      activeChildren.delete(child);
      resolve(code === 0 ? 0 : 1);
    };
    child.on('error', () => finish(1));
    // `close` is emitted only after stdio is closed and the process has exited. Waiting for it
    // keeps the remote lease held until Playwright and its process-group output are fully drained.
    child.on('close', (code) => finish(code));
  });
}

function signalChild(child, signal) {
  if (!child.pid) return;
  try {
    if (USE_PROCESS_GROUPS) process.kill(-child.pid, signal);
    else child.kill(signal);
  } catch {
    // The child may already have exited between signal receipt and cleanup.
  }
}

function terminateChild(child) {
  if (terminationTimers.has(child)) return;
  signalChild(child, 'SIGTERM');
  const timer = setTimeout(() => signalChild(child, 'SIGKILL'), TERMINATION_GRACE_MS);
  timer.unref();
  terminationTimers.set(child, timer);
}

function terminateActiveChildren() {
  for (const child of activeChildren) terminateChild(child);
}

function createTerminationController(signalEmitter = process) {
  let installed = false;
  let signal = null;
  const handleSignal = (nextSignal) => {
    if (!signal) signal = nextSignal;
    terminateActiveChildren();
  };

  return {
    getSignal: () => signal,
    install() {
      if (installed) return;
      installed = true;
      signalEmitter.on('SIGINT', handleSignal);
      signalEmitter.on('SIGTERM', handleSignal);
    },
    uninstall() {
      if (!installed) return;
      installed = false;
      signalEmitter.removeListener('SIGINT', handleSignal);
      signalEmitter.removeListener('SIGTERM', handleSignal);
    },
  };
}

async function executeDualComparison(options, context = {}) {
  const config = context.config || loadEnvironmentConfig();
  const staging = getEnvironmentTarget(config, 'staging', options.site, options.checkout);
  const homol = getEnvironmentTarget(config, 'homol', options.site, options.checkout);
  const runId = options.runId || randomBytes(12).toString('hex');
  const owner = options.dryRun ? 'dry-run' : (context.owner || process.env.SMOOTH_USER);
  const comparisonMode = options.parityValidation
    ? COMPARISON_MODE.PARITY_VALIDATION
    : COMPARISON_MODE.RELEASE_COMPARISON;
  if (!SAFE_OWNER.test(owner || '')) {
    throw new Error('[E2E] SMOOTH_USER ausente ou invalido; use sua identidade individual do smooth.');
  }

  process.stdout.write(`[E2E] run_id=${runId} | site=${staging.site} | checkout=${staging.checkout}\n`);
  process.stdout.write(`[E2E] staging=${staging.shopUrl} | homol=${homol.shopUrl}\n`);
  process.stdout.write(`[E2E] timeout_profile=${options.timeoutProfile}\n`);
  process.stdout.write(`[E2E] comparison_mode=${comparisonMode}\n`);
  process.stdout.write(options.parityValidation
    ? `[E2E] versions | staging(parity)=${options.candidateVersion} | homol(parity)=${options.baselineVersion}\n`
    : `[E2E] versions | staging(RC)=${options.candidateVersion} | homol(production)=${options.baselineVersion}\n`);
  if (options.parityValidation) {
    process.stdout.write('[E2E] Validacao de paridade: esta execucao nao produz veredito de release.\n');
  }
  process.stdout.write('[E2E] @serial-store sera excluido ate a automacao de snapshot estar disponivel.\n');
  if (options.dryRun) {
    process.stdout.write('[E2E] Dry-run concluido: configuracao, alvos e argumentos sao validos.\n');
    return { exitCode: 0, interrupted: false, runId };
  }

  const verifyVersion = context.verifyInstalledVersion || verifyInstalledVersion;
  verifyVersion(config, staging, options.candidateVersion);
  verifyVersion(config, homol, options.baselineVersion);
  context.leaseHeartbeat?.renewNow();
  context.leaseHeartbeat?.assertHeld();
  process.stdout.write('[E2E] Versoes instaladas confirmadas nas duas lanes.\n');

  const signalController = context.signalController || createTerminationController();
  const ownsSignalController = !context.signalController;
  let monitor = null;
  try {
    if (ownsSignalController) signalController.install();
    const root = path.join(E2E_DIR, 'results', 'dual', runId);
    fs.mkdirSync(root, { recursive: true, mode: 0o700 });
    let progressFiles = null;
    if (options.monitor) {
      monitor = createDualProgressMonitor(root, {
        runId,
        site: staging.site,
        checkout: staging.checkout,
        candidateVersion: options.candidateVersion,
        baselineVersion: options.baselineVersion,
        comparisonMode,
      });
      monitor.start();
      progressFiles = monitor.progressFiles;
    } else if (options.progressOnly) {
      progressFiles = {
        staging: path.join(root, 'staging', 'progress.json'),
        homol: path.join(root, 'homol', 'progress.json'),
      };
    }
    const runLane = context.runEnvironment || runEnvironment;
    const [stagingResult, homolResult] = await Promise.allSettled([
      runLane('staging', staging, options, runId, progressFiles?.staging),
      runLane('homol', homol, options, runId, progressFiles?.homol),
    ]);
    const stagingExit = stagingResult.status === 'fulfilled' ? stagingResult.value : 1;
    const homolExit = homolResult.status === 'fulfilled' ? homolResult.value : 1;
    if (monitor) {
      monitor.stop();
      monitor = null;
    }
    context.leaseHeartbeat?.renewNow();
    const stagingJson = path.join(root, 'staging', `${staging.site}-${staging.checkout}.json`);
    const homolJson = path.join(root, 'homol', `${homol.site}-${homol.checkout}.json`);
    const interruptedLane = (label, exitCode) => ({
      available: false,
      exitCode,
      label,
      reason: LANE_REASON.INTERRUPTED,
      scenarioCount: 0,
    });
    const leaseLost = context.leaseHeartbeat?.isLost() || false;
    const interruptedSignal = signalController.getSignal();
    let comparison;
    if (leaseLost) {
      const lostLane = (label, exitCode) => ({
        available: false,
        exitCode,
        label,
        reason: LANE_REASON.LEASE_LOST,
        scenarioCount: 0,
      });
      comparison = compareLaneReports(
        lostLane('staging', stagingExit),
        lostLane('homol', homolExit),
        comparisonMode
      );
    } else if (interruptedSignal) {
      comparison = compareLaneReports(
        interruptedLane('staging', stagingExit),
        interruptedLane('homol', homolExit),
        comparisonMode
      );
    } else {
      comparison = compareLaneReports(
        readLaneReport('staging', stagingJson, stagingExit),
        readLaneReport('homol', homolJson, homolExit),
        comparisonMode
      );
    }
    writeComparison(root, {
      runId,
      owner,
      site: staging.site,
      checkout: staging.checkout,
      candidateVersion: options.candidateVersion,
      baselineVersion: options.baselineVersion,
      stagingExit,
      homolExit,
    }, comparison);

    process.stdout.write(`[E2E] Relatorio dual: ${path.join(root, 'report.md')}\n`);
    if (leaseLost) {
      process.stderr.write('[E2E] Lease remoto perdido; as duas lanes foram invalidadas.\n');
      return { comparison, exitCode: 1, interrupted: false, leaseLost: true, runId };
    }
    if (interruptedSignal) {
      process.stderr.write(`[E2E] Execucao interrompida por ${interruptedSignal}; processos encerrados.\n`);
      return { comparison, exitCode: 130, interrupted: true, runId };
    }
    return {
      comparison,
      exitCode: stagingExit !== 0 || homolExit !== 0 || !comparison.valid ? 1 : 0,
      interrupted: false,
      runId,
    };
  } finally {
    if (monitor) monitor.stop();
    if (ownsSignalController) signalController.uninstall();
  }
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  const config = loadEnvironmentConfig();
  const owner = options.dryRun ? 'dry-run' : process.env.SMOOTH_USER;
  if (!SAFE_OWNER.test(owner || '')) {
    throw new Error('[E2E] SMOOTH_USER ausente ou invalido; use sua identidade individual do smooth.');
  }
  if (options.dryRun) {
    const result = await executeDualComparison(options, { config, owner });
    process.exitCode = result.exitCode;
    return;
  }

  const runId = options.runId || randomBytes(12).toString('hex');
  const lockToken = randomBytes(24).toString('hex');
  const signalController = createTerminationController();
  let lockAcquired = false;
  let leaseHeartbeat = null;
  try {
    runRemoteLock(config, 'acquire', lockToken, owner, runId, options.site);
    lockAcquired = true;
    // Ordering matters: the country lease is taken first and only then the writer is probed, so a
    // publish that started earlier is always observed here instead of swapping the plugin
    // underneath a run in progress.
    assertNoPublishInFlight(config);
    signalController.install();
    leaseHeartbeat = startLeaseHeartbeat({
      config,
      token: lockToken,
      owner,
      correlationId: runId,
      site: options.site,
      runRemoteLock,
      onLost: terminateActiveChildren,
    });
    const result = await executeDualComparison(
      { ...options, runId },
      { config, owner, leaseHeartbeat, signalController }
    );
    process.exitCode = result.exitCode;
  } finally {
    try {
      // executeDualComparison waits for every child `close` (and bounded SIGKILL fallback) before
      // returning, so a new lease owner can never overlap browsers from this execution.
      if (leaseHeartbeat) leaseHeartbeat.stop();
      if (lockAcquired) {
        try {
          runRemoteLock(config, 'release', lockToken, owner, runId, options.site);
        } catch (error) {
          if (!leaseHeartbeat?.isLost()) throw error;
        }
      }
    } finally {
      if (signalController.getSignal()) process.exitCode = 130;
      signalController.uninstall();
    }
  }
}

if (require.main === module) {
  main().catch((error) => {
    process.stderr.write(`${error.message && error.message.startsWith('[E2E]') ? error.message : '[E2E] Falha inesperada no runner dual.'}\n`);
    process.exitCode = 1;
  });
}

module.exports = {
  assertNoPublishInFlight,
  createTerminationController,
  executeDualComparison,
  getPublishLockName,
  getRemoteLockName,
  parseArgs,
  probeRemoteLock,
  REMOTE_LOCK_SCRIPT,
  runPublishLock,
  runRemoteLock,
  terminateActiveChildren,
  terminateChild,
};
