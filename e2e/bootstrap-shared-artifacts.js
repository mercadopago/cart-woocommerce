#!/usr/bin/env node
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { validateArtifact } = require('./publish-candidate');

const DEPLOY_ARTIFACTS_ROOT = path.resolve(
  __dirname,
  '..',
  'docker-flexible-environment',
  'deploy',
  'artifacts'
);
const LANES = Object.freeze(['staging', 'homol']);
// Containers bind-mount the lane root read-only and reach the plugin through `current`, so
// www-data (uid 33 inside the container) has to traverse the lane root, `current` and `releases`.
// These directories hold public plugin builds only — the country credentials live in
// deploy/secrets — so they are world-traversable on purpose. A 0700 lane root boots WordPress
// with no gateway at all.
const LANE_DIRECTORY_MODE = 0o755;

function usage() {
  process.stdout.write(
    'Usage: node e2e/bootstrap-shared-artifacts.js --check | --repair-permissions | --candidate-artifact <zip> --candidate-version X.Y.Z --baseline-artifact <zip> --baseline-version A.B.C\n'
  );
}

function parseArgs(argv) {
  const options = { check: false, repairPermissions: false };
  const valueArguments = {
    '--candidate-artifact': 'candidateArtifact',
    '--candidate-version': 'candidateVersion',
    '--baseline-artifact': 'baselineArtifact',
    '--baseline-version': 'baselineVersion',
  };
  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (valueArguments[argument]) {
      const value = argv[index + 1];
      if (!value || value.startsWith('--')) throw new Error(`[E2E] ${argument} requer um valor.`);
      const key = valueArguments[argument];
      options[key] = key.endsWith('Version') ? value.replace(/^v/, '') : value;
      index += 1;
    } else if (argument === '--check') {
      options.check = true;
    } else if (argument === '--repair-permissions') {
      options.repairPermissions = true;
    } else if (argument === '--help' || argument === '-h') {
      usage();
      process.exit(0);
    } else {
      throw new Error(`[E2E] Argumento nao suportado: ${argument}.`);
    }
  }

  if (options.check || options.repairPermissions) {
    if (options.check && options.repairPermissions) {
      throw new Error('[E2E] --check e --repair-permissions sao mutuamente exclusivos.');
    }
    if (Object.keys(options).length !== 2) {
      throw new Error('[E2E] --check nao aceita argumentos de publicacao.');
    }
    return options;
  }
  for (const field of ['candidateArtifact', 'candidateVersion', 'baselineArtifact', 'baselineVersion']) {
    if (!options[field]) throw new Error('[E2E] Os dois artefatos e suas versoes sao obrigatorios.');
  }
  return options;
}

function currentPluginPath(root, lane) {
  if (!LANES.includes(lane)) throw new Error('[E2E] Lane invalida para o bootstrap de artefatos.');
  return path.join(root, lane, 'current', 'woocommerce-mercadopago');
}

function assertSeededArtifacts(root = DEPLOY_ARTIFACTS_ROOT) {
  for (const lane of LANES) {
    const pluginPath = currentPluginPath(root, lane);
    const headerPath = path.join(pluginPath, 'woocommerce-mercadopago.php');
    let stats;
    try {
      stats = fs.statSync(pluginPath);
    } catch {
      throw new Error(
        `[E2E] Artefato inicial ausente em ${lane}; execute e2e-shared-infra-seed antes de shared-up.`
      );
    }
    let headerStats;
    try {
      headerStats = fs.statSync(headerPath);
    } catch {
      throw new Error(`[E2E] Artefato inicial invalido em ${lane}.`);
    }
    if (!stats.isDirectory() || !headerStats.isFile()) {
      throw new Error(`[E2E] Artefato inicial invalido em ${lane}.`);
    }
    assertLaneIsTraversable(root, lane);
  }
}

// Checked before `shared-up` so an unreadable lane fails here, with the directory named, instead
// of as a WordPress boot with a missing gateway.
function assertLaneIsTraversable(root, lane) {
  const laneRoot = path.join(root, lane);
  const releasesRoot = path.join(laneRoot, 'releases');
  const directories = [laneRoot, path.join(laneRoot, 'current')];
  // A seeded lane always has `releases`, but a lane whose `current` entry is a real directory
  // instead of a release pointer is still mountable, so its absence is not an error here.
  if (fs.existsSync(releasesRoot)) directories.push(releasesRoot);

  for (const directory of directories) {
    const mode = fs.statSync(directory).mode & 0o777;
    if ((mode & 0o055) !== 0o055) {
      throw new Error(
        `[E2E] ${directory} nao e atravessavel por www-data (modo ${mode.toString(8)}); `
        + 'os containers montam a raiz da lane e precisam de r-x para outros.'
      );
    }
  }
}

// One-shot migration for lanes seeded before the lane root was the bind-mount source: back then
// 0700 was harmless because Docker mounted the already-resolved release directory. It has to run
// before `shared-up`, since the container that would fix nothing cannot even read the lane.
function repairLanePermissions(root = DEPLOY_ARTIFACTS_ROOT) {
  for (const lane of LANES) {
    const laneRoot = path.join(root, lane);
    for (const directory of [laneRoot, path.join(laneRoot, 'releases'), path.join(laneRoot, 'current')]) {
      if (fs.existsSync(directory)) fs.chmodSync(directory, LANE_DIRECTORY_MODE);
    }
  }
}

function seedLane(root, lane, artifact) {
  const laneRoot = path.join(root, lane);
  const releasesRoot = path.join(laneRoot, 'releases');
  const currentRoot = path.join(laneRoot, 'current');
  const currentPlugin = currentPluginPath(root, lane);
  const releaseName = `${artifact.version}-${artifact.sha256.slice(0, 16)}`;
  const releasePath = path.join(releasesRoot, releaseName);

  fs.mkdirSync(releasesRoot, { recursive: true, mode: LANE_DIRECTORY_MODE });
  fs.mkdirSync(currentRoot, { recursive: true, mode: LANE_DIRECTORY_MODE });
  // `recursive: true` ignores `mode` for directories that already exist, and the process umask
  // masks it for the ones it creates. Both leave a lane the container cannot traverse, so the
  // traversal bits are reasserted explicitly.
  for (const directory of [laneRoot, releasesRoot, currentRoot]) {
    fs.chmodSync(directory, LANE_DIRECTORY_MODE);
  }
  const expectedTarget = path.relative(currentRoot, releasePath);
  const currentEntryExists = fs.readdirSync(currentRoot).includes('woocommerce-mercadopago');
  if (currentEntryExists) {
    const currentTarget = fs.lstatSync(currentPlugin).isSymbolicLink()
      ? fs.readlinkSync(currentPlugin)
      : null;
    if (currentTarget === expectedTarget) {
      const releaseHeader = path.join(releasePath, 'woocommerce-mercadopago.php');
      if (
        !fs.existsSync(releasePath)
        || !fs.statSync(releasePath).isDirectory()
        || !fs.existsSync(releaseHeader)
      ) {
        throw new Error(`[E2E] Release inicial existente e invalida em ${lane}.`);
      }
      return;
    }
    throw new Error(
      `[E2E] ${lane}/current ja existe; use o publisher normal para atualizar uma lane provisionada.`
    );
  }
  if (fs.existsSync(releasePath)) {
    const releaseHeader = path.join(releasePath, 'woocommerce-mercadopago.php');
    if (!fs.statSync(releasePath).isDirectory() || !fs.existsSync(releaseHeader)) {
      throw new Error(`[E2E] Release inicial existente e invalida em ${lane}.`);
    }
  } else {
    const temporaryRoot = fs.mkdtempSync(path.join(laneRoot, '.seed-'));
    try {
      const extraction = spawnSync(
        'unzip',
        ['-q', artifact.artifactPath, '-d', temporaryRoot],
        { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 30000 }
      );
      const extractedPlugin = path.join(temporaryRoot, 'woocommerce-mercadopago');
      if (
        extraction.status !== 0
        || !fs.existsSync(path.join(extractedPlugin, 'woocommerce-mercadopago.php'))
      ) {
        throw new Error(`[E2E] Falha ao extrair o artefato inicial de ${lane}.`);
      }
      fs.renameSync(extractedPlugin, releasePath);
    } finally {
      fs.rmSync(temporaryRoot, { recursive: true, force: true });
    }
  }

  const temporaryLink = path.join(currentRoot, `.woocommerce-mercadopago-${process.pid}`);
  fs.symlinkSync(expectedTarget, temporaryLink, 'dir');
  fs.renameSync(temporaryLink, currentPlugin);
}

function bootstrapSharedArtifacts(options, root = DEPLOY_ARTIFACTS_ROOT) {
  const candidate = validateArtifact(options.candidateArtifact);
  const baseline = validateArtifact(options.baselineArtifact);
  if (candidate.version !== options.candidateVersion || baseline.version !== options.baselineVersion) {
    throw new Error('[E2E] A versao declarada nao corresponde ao cabecalho de um artefato inicial.');
  }
  seedLane(root, 'staging', candidate);
  seedLane(root, 'homol', baseline);
  assertSeededArtifacts(root);
}

function main() {
  const options = parseArgs(process.argv.slice(2));
  if (options.repairPermissions) {
    repairLanePermissions();
    process.stdout.write('[E2E] Permissoes das lanes ajustadas para 0755 (travessia por www-data).\n');
    return;
  }
  if (options.check) {
    assertSeededArtifacts();
    process.stdout.write('[E2E] Artefatos iniciais de staging e homol estao prontos.\n');
    return;
  }
  bootstrapSharedArtifacts(options);
  process.stdout.write('[E2E] Bootstrap inicial concluido: RC em staging e producao em homol.\n');
}

if (require.main === module) {
  try {
    main();
  } catch (error) {
    process.stderr.write(`${error.message?.startsWith('[E2E]') ? error.message : '[E2E] Falha no bootstrap inicial.'}\n`);
    process.exitCode = 1;
  }
}

module.exports = {
  assertLaneIsTraversable,
  assertSeededArtifacts,
  repairLanePermissions,
  bootstrapSharedArtifacts,
  currentPluginPath,
  parseArgs,
  seedLane,
};
