#!/usr/bin/env node
const { randomBytes } = require('node:crypto');
const { getEnvironmentTarget, loadEnvironmentConfig } = require('./helpers/environment-config');
const {
  normalizeVersion,
  publishArtifact,
  validateArtifact,
} = require('./publish-candidate');
const { validateBaselineArtifactLocation } = require('./publish-baseline');
const { runPublishLock } = require('./run-dual-report');

const SAFE_OWNER = /^[a-zA-Z0-9][a-zA-Z0-9._-]{1,63}$/;

function parseArgs(argv) {
  const options = { dryRun: false };
  const valueArguments = new Map([
    ['--site', 'site'],
    ['--candidate-artifact', 'candidateArtifact'],
    ['--candidate-version', 'candidateVersion'],
    ['--production-artifact', 'productionArtifact'],
    ['--production-version', 'productionVersion'],
  ]);
  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (valueArguments.has(argument)) {
      const value = argv[index + 1];
      if (!value || value.startsWith('--')) throw new Error(`[E2E] ${argument} requer um valor.`);
      const key = valueArguments.get(argument);
      options[key] = key.endsWith('Version')
        ? normalizeVersion(value, `Valor de ${argument}`)
        : value;
      index += 1;
    } else if (argument === '--dry-run') {
      options.dryRun = true;
    } else {
      throw new Error(`[E2E] Argumento nao suportado: ${argument}.`);
    }
  }
  const required = [...valueArguments.values()];
  if (required.some((key) => !options[key])) {
    throw new Error('[E2E] Site, artefatos e versoes de candidate/production sao obrigatorios.');
  }
  if (options.candidateVersion === options.productionVersion) {
    throw new Error('[E2E] RC e producao devem apontar para versoes diferentes.');
  }
  return options;
}

function validateExpectedVersion(artifact, expectedVersion, label) {
  if (artifact.version !== expectedVersion) {
    throw new Error(
      `[E2E] ${label} contem ${artifact.version}, mas ${expectedVersion} era esperada.`
    );
  }
}

function prepareReleaseArtifacts(options, config = loadEnvironmentConfig()) {
  const staging = getEnvironmentTarget(config, 'staging', options.site, 'classic');
  const homol = getEnvironmentTarget(config, 'homol', options.site, 'classic');
  const candidate = validateArtifact(options.candidateArtifact);
  const production = validateArtifact(options.productionArtifact);
  validateBaselineArtifactLocation(production, { expectedVersion: options.productionVersion });
  validateExpectedVersion(candidate, options.candidateVersion, 'O ZIP da RC');
  validateExpectedVersion(production, options.productionVersion, 'O ZIP produtivo');

  return { candidate, config, homol, production, staging };
}

function publishReleaseUnderLease(prepared, owner, dependencies = {}) {
  const { candidate, config, homol, production, staging } = prepared;
  const publish = dependencies.publishArtifact || publishArtifact;
  const tokenFactory = dependencies.tokenFactory || (() => randomBytes(16).toString('hex'));

  publish(config, homol, production, tokenFactory(), owner);
  try {
    publish(config, staging, candidate, tokenFactory(), owner);
  } catch {
    try {
      // A distributed WordPress install cannot be committed atomically across containers. Restore
      // staging to the already-verified production artifact so the pair converges to a safe state.
      publish(config, staging, production, tokenFactory(), owner);
    } catch {
      throw new Error(
        '[E2E] A publicacao da RC falhou e o rollback de staging para producao tambem falhou; '
        + 'o par permanece bloqueado para comparacao e exige recuperacao.'
      );
    }
    throw new Error(
      '[E2E] A publicacao da RC falhou; staging foi restaurada para a versao de producao '
      + 'e nenhuma comparacao foi iniciada.'
    );
  }
  process.stdout.write(
    `[E2E] Release preparada: staging=${candidate.version} e homol=${production.version}.\n`
  );
}

function main() {
  const options = parseArgs(process.argv.slice(2));
  const prepared = prepareReleaseArtifacts(options);
  const { candidate, config, homol, production, staging } = prepared;

  const owner = options.dryRun ? 'dry-run' : process.env.SMOOTH_USER;
  if (!SAFE_OWNER.test(owner || '')) {
    throw new Error('[E2E] SMOOTH_USER ausente ou invalido; use sua identidade individual do smooth.');
  }

  process.stdout.write(
    `[E2E] release mapping | staging=${staging.site}@${candidate.version} (${candidate.sha256}) | homol=${homol.site}@${production.version} (${production.sha256})\n`
  );
  if (options.dryRun) {
    process.stdout.write('[E2E] Dry-run concluido: nenhum artefato foi enviado.\n');
    return;
  }

  const lockToken = randomBytes(24).toString('hex');
  const correlationId = `${candidate.sha256.slice(0, 16)}${production.sha256.slice(0, 16)}`;
  let lockAcquired = false;
  try {
    // Standalone publication touches both lanes for all seven countries, so the lane-wide writer
    // lock is the right scope here; the per-country lease belongs to test runs.
    runPublishLock(config, 'acquire', lockToken, owner, correlationId);
    lockAcquired = true;
    publishReleaseUnderLease(prepared, owner);
  } finally {
    if (lockAcquired) runPublishLock(config, 'release', lockToken, owner, correlationId);
  }
}

if (require.main === module) {
  try {
    main();
  } catch (error) {
    const message = error.message && error.message.startsWith('[E2E]')
      ? error.message
      : '[E2E] Falha inesperada ao preparar a comparacao da release.';
    process.stderr.write(`${message}\n`);
    process.exitCode = 1;
  }
}

module.exports = {
  parseArgs,
  prepareReleaseArtifacts,
  publishReleaseUnderLease,
  validateExpectedVersion,
};
