#!/usr/bin/env node
const { randomBytes } = require('node:crypto');
const {
  parseArgs,
  prepareReleaseArtifacts,
  publishReleaseUnderLease,
} = require('./prepare-release-comparison');
const {
  executeDualComparison,
  createTerminationController,
  runPublishLock,
  runRemoteLock,
  terminateActiveChildren,
} = require('./run-dual-report');
const { startLeaseHeartbeat } = require('./helpers/lease-heartbeat');

const SAFE_OWNER = /^[a-zA-Z0-9][a-zA-Z0-9._-]{1,63}$/;

async function runReleaseComparison(options, dependencies = {}) {
  const prepare = dependencies.prepareReleaseArtifacts || prepareReleaseArtifacts;
  const publish = dependencies.publishReleaseUnderLease || publishReleaseUnderLease;
  const execute = dependencies.executeDualComparison || executeDualComparison;
  const remoteLock = dependencies.runRemoteLock || runRemoteLock;
  const publishLock = dependencies.runPublishLock || runPublishLock;
  const heartbeatFactory = dependencies.startLeaseHeartbeat || startLeaseHeartbeat;
  const tokenFactory = dependencies.tokenFactory || (() => randomBytes(24).toString('hex'));
  const signalController = dependencies.signalController || createTerminationController();
  const prepared = prepare(options);
  const { candidate, config, production, staging } = prepared;
  const owner = options.dryRun ? 'dry-run' : process.env.SMOOTH_USER;
  if (!SAFE_OWNER.test(owner || '')) {
    throw new Error('[E2E] SMOOTH_USER ausente ou invalido; use sua identidade individual do smooth.');
  }

  process.stdout.write(
    `[E2E] release mapping | staging=${staging.site}@${candidate.version} (${candidate.sha256}) | homol=${production.version} (${production.sha256})\n`
  );
  if (options.dryRun) {
    process.stdout.write('[E2E] Dry-run concluido: nenhum artefato foi enviado e nenhum teste foi iniciado.\n');
    return 0;
  }

  const lockToken = tokenFactory();
  const correlationId = `${candidate.sha256.slice(0, 16)}${production.sha256.slice(0, 16)}`;
  const publishToken = tokenFactory();
  let lockAcquired = false;
  let publishLockAcquired = false;
  let leaseHeartbeat = null;
  let exitCode = 0;
  try {
    // Publishing swaps the release for all seven countries of the lane, so the lane-wide writer
    // lock comes first and is only released once both lanes are published. Taking it before the
    // country lease is what keeps a concurrent runner from slipping in: it can only observe the
    // writer as held. The country lease then covers both checkout modes, as before.
    publishLock(config, 'acquire', publishToken, owner, correlationId);
    publishLockAcquired = true;
    remoteLock(config, 'acquire', lockToken, owner, correlationId, staging.site);
    lockAcquired = true;
    signalController.install();
    leaseHeartbeat = heartbeatFactory({
      config,
      token: lockToken,
      owner,
      correlationId,
      site: staging.site,
      runRemoteLock: remoteLock,
      onLost: terminateActiveChildren,
    });
    publish(prepared, owner);
    // Both lanes are published, so the writer is dropped here: the remaining work is a test run
    // guarded by the country lease, and holding the writer for hours would block every other
    // country's publish for no reason.
    publishLock(config, 'release', publishToken, owner, correlationId);
    publishLockAcquired = false;
    leaseHeartbeat.renewNow();
    leaseHeartbeat.assertHeld();
    if (signalController.getSignal()) {
      exitCode = 130;
    } else {
      for (const checkout of ['classic', 'blocks']) {
        const result = await execute({
          baselineVersion: options.productionVersion,
          candidateVersion: options.candidateVersion,
          checkout,
          dryRun: false,
          monitor: true,
          progressOnly: false,
          site: options.site,
          timeoutProfile: 'standard',
          withRetries: true,
        }, { config, owner, leaseHeartbeat, signalController });
        if (result.exitCode !== 0) exitCode = result.exitCode;
        leaseHeartbeat.assertHeld();
        if (result.interrupted) break;
      }
    }
  } finally {
    try {
      if (leaseHeartbeat) leaseHeartbeat.stop();
      // Released before the country lease so a publish that failed midway cannot leave the lane
      // writer-locked for its whole TTL.
      if (publishLockAcquired) {
        publishLock(config, 'release', publishToken, owner, correlationId);
      }
      if (lockAcquired) {
        try {
          remoteLock(config, 'release', lockToken, owner, correlationId, staging.site);
        } catch (error) {
          if (!leaseHeartbeat?.isLost()) throw error;
        }
      }
    } finally {
      signalController.uninstall();
    }
  }
  return signalController.getSignal() ? 130 : exitCode;
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  process.exitCode = await runReleaseComparison(options);
}

if (require.main === module) {
  main().catch((error) => {
    const message = error.message && error.message.startsWith('[E2E]')
      ? error.message
      : '[E2E] Falha inesperada na comparacao canonica da release.';
    process.stderr.write(`${message}\n`);
    process.exitCode = 1;
  });
}

module.exports = { runReleaseComparison };
