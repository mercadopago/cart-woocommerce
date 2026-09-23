#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const { createHash, randomBytes } = require('crypto');
const { spawnSync } = require('child_process');
const {
  getEnvironmentTarget,
  getLaneContainers,
  loadEnvironmentConfig,
} = require('./helpers/environment-config');
const { runPublishLock } = require('./run-dual-report');
const { getSshOptions } = require('./helpers/ssh-options');

const REPOSITORY_ROOT = path.resolve(__dirname, '..');
const SAFE_OWNER = /^[a-zA-Z0-9][a-zA-Z0-9._-]{1,63}$/;
const SAFE_VERSION = /^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/;
const MAX_ARTIFACT_SIZE = 100 * 1024 * 1024;
const MAX_ARCHIVE_ENTRIES = 10000;
const MAX_UNCOMPRESSED_SIZE = 500 * 1024 * 1024;

// Exit codes the remote publisher uses, mapped to what an operator has to do about them.
const REMOTE_FAILURES = Object.freeze({
  64: 'argumentos invalidos no publisher remoto',
  65: 'o conteudo publicado nao corresponde ao artefato esperado',
  66: 'o artefato enviado nao foi encontrado no host',
  69: 'a lane nao usa o layout de bind-mount atual; recrie o par com o compose vigente',
  70: 'um container nao respondeu apos o reload do Apache',
  75: 'a lane esta ocupada por outra publicacao ou execucao',
});

// Publishing is an atomic repoint of the lane's `current` symlink on the host, never a write
// inside a container. The containers bind-mount the lane root read-only, so a write through the
// plugin path would either fail or -- without the read-only flag -- delete the release directory
// shared by all seven countries. The repoint is visible to a running container because the mount
// is the lane root and not the resolved release directory; the Apache reload is what makes it
// take effect, since the official WordPress image enables opcache and PHP caches resolved
// symlinks for realpath_cache_ttl (120s by default).
const REMOTE_PUBLISH_SCRIPT = `
set -eu
token="$1"
expected_sha="$2"
lane="$3"
owner="$4"
expected_version="$5"
shift 5
case "$lane" in staging|homol) ;; *) exit 64 ;; esac
case "$expected_version" in *[!0-9A-Za-z._+-]*|'') exit 64 ;; esac
case "$token" in *[!0-9a-f]*|'') exit 64 ;; esac
[ "$#" -ge 1 ] || exit 64
umask 022
incoming="$HOME/.woo-e2e-artifacts/incoming"
manifests="$HOME/.woo-e2e-artifacts/manifests"
artifact="$incoming/$token.zip"
mkdir -p "$incoming" "$manifests"
[ -f "$artifact" ] || exit 66
actual_sha=$(sha256sum "$artifact" | awk '{print $1}')
[ "$actual_sha" = "$expected_sha" ] || exit 65

# The lane root is read back from the running containers instead of a configured path, so the
# publisher can only ever write to the directory the containers actually read.
lane_root=''
for container in "$@"; do
  source_path=$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/e2e-artifacts"}}{{.Source}}{{end}}{{end}}' "$container")
  [ -n "$source_path" ] || { echo "container $container has no /e2e-artifacts mount; recreate the lane with the current compose" >&2; exit 69; }
  case "$source_path" in */artifacts/"$lane") ;; *) echo "container $container mounts $source_path, which is not the $lane lane" >&2; exit 69 ;; esac
  if [ -z "$lane_root" ]; then
    lane_root="$source_path"
  elif [ "$source_path" != "$lane_root" ]; then
    echo "lane containers disagree on the artifacts root ($lane_root vs $source_path)" >&2
    exit 69
  fi
done
[ -d "$lane_root/current" ] || { echo "$lane_root/current is missing; seed the lane first" >&2; exit 69; }

release_name="$expected_version-$(printf '%s' "$actual_sha" | cut -c1-16)"
release_dir="$lane_root/releases/$release_name"
cleanup() { rm -f "$artifact"; rm -rf "$lane_root/.publish-$token"; }
trap cleanup EXIT

if [ ! -d "$release_dir" ]; then
  work_dir="$lane_root/.publish-$token"
  rm -rf "$work_dir"
  mkdir -p "$work_dir"
  unzip -q "$artifact" -d "$work_dir"
  extracted=$(ls -A "$work_dir")
  [ "$extracted" = "woocommerce-mercadopago" ] || { echo "unexpected archive layout: $extracted" >&2; exit 65; }
  [ -f "$work_dir/woocommerce-mercadopago/woocommerce-mercadopago.php" ] || exit 65
  chmod -R a+rX "$work_dir/woocommerce-mercadopago"
  mkdir -p "$lane_root/releases"
  # Losing this race is success: a concurrent publish of the same version and sha produced the
  # same bytes, so the directory that won is kept and reused.
  mv -T "$work_dir/woocommerce-mercadopago" "$release_dir" 2>/dev/null || [ -d "$release_dir" ]
fi
[ -f "$release_dir/woocommerce-mercadopago.php" ] || { echo "release $release_name is incomplete" >&2; exit 65; }
chmod a+rX "$lane_root" "$lane_root/releases" "$lane_root/current"

# rename(2) over the old symlink: readers see either the old or the new release, never neither.
# A plain "ln -sfn" would unlink first and expose a window with no plugin at all.
temporary_link="$lane_root/current/.woocommerce-mercadopago-$token"
rm -f "$temporary_link"
ln -s "../releases/$release_name" "$temporary_link"
mv -T "$temporary_link" "$lane_root/current/woocommerce-mercadopago"

for container in "$@"; do
  # graceful lets in-flight requests finish on the old workers while new ones pick up the new
  # release; it is also what drops the stale opcache and realpath cache entries.
  docker exec "$container" apache2ctl -k graceful
done
for container in "$@"; do
  ready=0
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    if docker exec "$container" curl -fsS -o /dev/null http://localhost/ 2>/dev/null; then ready=1; break; fi
    sleep 2
  done
  [ "$ready" = "1" ] || { echo "$container did not answer after the graceful reload" >&2; exit 70; }
  # Best-effort: a swap keeps the plugin active, so this only covers a lane left deactivated by an
  # earlier failure. It must not abort the publish -- WP-CLI's exit code for an already-active
  # plugin is not worth betting the whole release on. The status check below is the real gate.
  docker exec --user www-data "$container" wp --path=/var/www/html plugin activate woocommerce-mercadopago >/dev/null 2>&1 || true
  version=$(docker exec --user www-data "$container" wp --path=/var/www/html plugin get woocommerce-mercadopago --field=version)
  case "$version" in *[!0-9A-Za-z._+-]*|'') exit 65 ;; esac
  [ "$version" = "$expected_version" ] || { echo "$container reports $version, expected $expected_version" >&2; exit 65; }
  status=$(docker exec --user www-data "$container" wp --path=/var/www/html plugin get woocommerce-mercadopago --field=status)
  [ "$status" = "active" ] || { echo "$container reports the plugin as $status" >&2; exit 65; }
done

published_at=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
printf '{"lane":"%s","artifact_sha256":"%s","plugin_version":"%s","release":"%s","published_by":"%s","published_at":"%s"}\\n' \
  "$lane" "$actual_sha" "$expected_version" "$release_name" "$owner" "$published_at" > "$manifests/$lane.json"
`;

function usage(scriptName = 'publish-candidate.js') {
  process.stdout.write(
    `Usage: node e2e/${scriptName} --site MLB --artifact ./woocommerce-mercadopago.zip [--expected-version X.Y.Z] [--dry-run]\n`
  );
}

function normalizeVersion(value, label = 'versao') {
  const version = String(value || '').replace(/^v/, '');
  if (!SAFE_VERSION.test(version)) throw new Error(`[E2E] ${label} invalida.`);
  return version;
}

function parseArgs(argv, settings = {}) {
  const options = { dryRun: false };
  const valueArguments = {
    '--site': 'site',
    '--artifact': 'artifact',
    '--expected-version': 'expectedVersion',
  };
  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (valueArguments[argument]) {
      const value = argv[index + 1];
      if (!value || value.startsWith('--')) throw new Error(`[E2E] ${argument} requer um valor.`);
      const key = valueArguments[argument];
      options[key] = key === 'expectedVersion' ? normalizeVersion(value, 'Versao esperada') : value;
      index += 1;
    } else if (argument === '--dry-run') {
      options.dryRun = true;
    } else if (argument === '--help' || argument === '-h') {
      usage(settings.scriptName);
      process.exit(0);
    } else {
      throw new Error(`[E2E] Argumento nao suportado: ${argument}.`);
    }
  }
  if (!options.site || !options.artifact) {
    throw new Error('[E2E] --site e --artifact sao obrigatorios.');
  }
  if (settings.expectedVersionRequired && !options.expectedVersion) {
    throw new Error('[E2E] --expected-version e obrigatorio para publicar a baseline.');
  }
  return options;
}

function validateZipSummary(summary) {
  const match = String(summary || '').match(/^(\d+) files?, (\d+) bytes uncompressed,/m);
  if (!match) throw new Error('[E2E] Nao foi possivel validar o tamanho descompactado do ZIP.');

  const entries = Number(match[1]);
  const uncompressedSize = Number(match[2]);
  if (
    !Number.isSafeInteger(entries)
    || !Number.isSafeInteger(uncompressedSize)
    || entries < 1
    || entries > MAX_ARCHIVE_ENTRIES
    || uncompressedSize > MAX_UNCOMPRESSED_SIZE
  ) {
    throw new Error('[E2E] ZIP excede o limite de entradas ou tamanho descompactado.');
  }
}

function validateArtifact(requestedPath) {
  const artifactPath = fs.realpathSync(path.resolve(process.cwd(), requestedPath));
  const relative = path.relative(REPOSITORY_ROOT, artifactPath);
  if (relative.startsWith('..') || path.isAbsolute(relative) || relative.startsWith(`.git${path.sep}`)) {
    throw new Error('[E2E] O artefato deve estar dentro do checkout do repositorio.');
  }
  if (path.extname(artifactPath).toLowerCase() !== '.zip') {
    throw new Error('[E2E] O artefato deve ser um arquivo ZIP.');
  }
  const stats = fs.statSync(artifactPath);
  if (!stats.isFile() || stats.size === 0 || stats.size > MAX_ARTIFACT_SIZE) {
    throw new Error('[E2E] Artefato vazio ou maior que 100 MiB.');
  }

  const listing = spawnSync('unzip', ['-Z1', artifactPath], { encoding: 'utf8', timeout: 15000 });
  if (listing.status !== 0) throw new Error('[E2E] ZIP invalido ou ilegivel.');
  const entries = listing.stdout.split('\n').filter(Boolean);
  const listingDetails = spawnSync('zipinfo', ['-l', artifactPath], { encoding: 'utf8', timeout: 15000 });
  if (listingDetails.status !== 0 || listingDetails.stdout.split('\n').some((line) => line.startsWith('l'))) {
    throw new Error('[E2E] ZIP com links simbolicos nao e permitido.');
  }
  const archiveSummary = spawnSync('zipinfo', ['-t', artifactPath], { encoding: 'utf8', timeout: 15000 });
  if (archiveSummary.status !== 0) throw new Error('[E2E] Nao foi possivel validar o conteudo do ZIP.');
  validateZipSummary(archiveSummary.stdout);
  if (
    !entries.includes('woocommerce-mercadopago/woocommerce-mercadopago.php')
    || entries.some((entry) => entry.startsWith('/') || entry.includes('..') || entry.includes('\\'))
    || entries.some((entry) => !entry.startsWith('woocommerce-mercadopago/'))
  ) {
    throw new Error('[E2E] Estrutura do ZIP do plugin invalida.');
  }

  const pluginHeader = spawnSync(
    'unzip',
    ['-p', artifactPath, 'woocommerce-mercadopago/woocommerce-mercadopago.php'],
    { encoding: 'utf8', timeout: 15000, maxBuffer: 1024 * 1024 }
  );
  const versionMatch = pluginHeader.status === 0
    ? pluginHeader.stdout.match(/^\s*\*\s*Version:\s*([^\s]+)\s*$/m)
    : null;
  if (!versionMatch) throw new Error('[E2E] ZIP sem versao valida no cabecalho do plugin.');
  const version = normalizeVersion(versionMatch[1], 'Versao do artefato');

  const sha256 = createHash('sha256').update(fs.readFileSync(artifactPath)).digest('hex');
  return { artifactPath, sha256, size: stats.size, version };
}

// The generic "a lane pode exigir recuperacao" message used to hide the actual cause: the only
// way to reach it was to replay the remote script by hand. The remote diagnostics are plugin
// paths, container names and versions -- never credentials -- so they are safe to surface.
function describeRemoteFailure(environment, result) {
  const reason = REMOTE_FAILURES[result.status];
  const details = String(result.stderr || '')
    // eslint-disable-next-line no-control-regex
    .replace(/[\x00-\x08\x0b-\x1f\x7f]/g, ' ')
    .trim()
    .slice(0, 2000);

  const parts = [`[E2E] Publicacao em ${environment} falhou`];
  if (reason) parts.push(`: ${reason}`);
  else if (result.error) parts.push(`: ${result.error.message}`);
  else parts.push(` (exit ${result.status})`);
  if (details) parts.push(`\n[E2E] Detalhe remoto: ${details}`);
  return parts.join('');
}

function publishArtifact(config, target, artifact, token, owner, dependencies = {}) {
  const sshTarget = `${config.lock.sshUser}@${config.lock.sshHost}`;
  const spawnSyncFn = dependencies.spawnSync || spawnSync;
  const sshOptions = dependencies.sshOptions || getSshOptions(process.env, config.lock.sshHost);
  const containers = getLaneContainers(config, target.environment);
  const remoteDir = `${sshTarget}:~/.woo-e2e-artifacts/incoming/${token}.zip`;
  const prepare = spawnSyncFn(
    'ssh',
    [...sshOptions, sshTarget, 'install', '-d', '-m', '700', '~/.woo-e2e-artifacts/incoming'],
    { encoding: 'utf8', timeout: 30000, stdio: ['ignore', 'pipe', 'pipe'] }
  );
  if (prepare.status !== 0) throw new Error('[E2E] Nao foi possivel preparar o destino do artefato.');

  const upload = spawnSyncFn(
    'scp',
    [...sshOptions, artifact.artifactPath, remoteDir],
    { encoding: 'utf8', timeout: 120000, stdio: ['ignore', 'pipe', 'pipe'] }
  );
  if (upload.status !== 0) throw new Error('[E2E] Upload do artefato falhou.');

  const publish = spawnSyncFn(
    'ssh',
    [
      ...sshOptions,
      sshTarget,
      'sh', '-s', '--', token, artifact.sha256, target.environment, owner,
      artifact.version, ...containers,
    ],
    { input: REMOTE_PUBLISH_SCRIPT, encoding: 'utf8', timeout: 300000, stdio: ['pipe', 'pipe', 'pipe'] }
  );
  if (publish.status !== 0) {
    throw new Error(describeRemoteFailure(target.environment, publish));
  }
  process.stdout.write(
    `[E2E] ${target.environment}: ${containers.length} lojas recarregadas em ${artifact.version}.\n`
  );
}

function runPublisher(environment, argv, settings = {}) {
  const options = parseArgs(argv, settings);
  const config = loadEnvironmentConfig();
  const target = getEnvironmentTarget(config, environment, options.site, 'classic');
  const artifact = validateArtifact(options.artifact);
  if (settings.validateArtifactLocation) settings.validateArtifactLocation(artifact, options);
  if (options.expectedVersion && artifact.version !== options.expectedVersion) {
    throw new Error(
      `[E2E] O ZIP contem a versao ${artifact.version}, mas ${options.expectedVersion} era esperada.`
    );
  }
  const owner = options.dryRun ? 'dry-run' : process.env.SMOOTH_USER;
  if (!SAFE_OWNER.test(owner || '')) {
    throw new Error('[E2E] SMOOTH_USER ausente ou invalido; use sua identidade individual do smooth.');
  }

  process.stdout.write(
    `[E2E] ${target.role} | lane=${target.environment} (7 lojas) | version=${artifact.version} | sha256=${artifact.sha256} | size=${artifact.size} bytes\n`
  );
  if (options.dryRun) {
    process.stdout.write('[E2E] Dry-run concluido: ZIP, destino e identidade sao validos; nenhum upload foi feito.\n');
    return;
  }

  const lockToken = randomBytes(24).toString('hex');
  const artifactToken = randomBytes(16).toString('hex');
  let lockAcquired = false;
  try {
    // A lane is shared by the seven countries, so the writer lock is lane-wide and not per site.
    runPublishLock(config, 'acquire', lockToken, owner, artifact.sha256);
    lockAcquired = true;
    publishArtifact(config, target, artifact, artifactToken, owner);
    process.stdout.write(
      `[E2E] ${target.role} publicado na lane ${target.environment} com versao e SHA-256 verificados.\n`
    );
  } finally {
    if (lockAcquired) runPublishLock(config, 'release', lockToken, owner, artifact.sha256);
  }
}

if (require.main === module) {
  try {
    runPublisher('staging', process.argv.slice(2), { scriptName: 'publish-candidate.js' });
  } catch (error) {
    process.stderr.write(`${error.message && error.message.startsWith('[E2E]') ? error.message : '[E2E] Falha inesperada na publicacao.'}\n`);
    process.exitCode = 1;
  }
}

module.exports = {
  describeRemoteFailure,
  REMOTE_PUBLISH_SCRIPT,
  normalizeVersion,
  parseArgs,
  publishArtifact,
  runPublisher,
  validateArtifact,
  validateZipSummary,
};
