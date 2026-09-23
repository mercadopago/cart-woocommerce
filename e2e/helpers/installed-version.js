const { spawnSync } = require('node:child_process');
const { getSshOptions } = require('./ssh-options');

const SAFE_CONTAINER = /^[a-zA-Z0-9][a-zA-Z0-9_.-]{1,127}$/;
const SAFE_VERSION = /^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/;

const REMOTE_VERSION_SCRIPT = `
set -eu
container="$1"
expected_version="$2"
case "$container" in *[!0-9A-Za-z_.-]*|'') exit 64 ;; esac
case "$expected_version" in *[!0-9A-Za-z.+-]*|'') exit 64 ;; esac
docker inspect "$container" >/dev/null 2>&1
installed_version=$(docker exec --user www-data "$container" \
  wp --path=/var/www/html plugin get woocommerce-mercadopago --field=version)
[ "$installed_version" = "$expected_version" ]
`;

function verifyInstalledVersion(config, target, expectedVersion, dependencies = {}) {
  if (
    !config?.lock?.sshHost
    || !config?.lock?.sshUser
    || !SAFE_CONTAINER.test(target?.containerName || '')
    || !['staging', 'homol'].includes(target?.environment)
    || !SAFE_VERSION.test(expectedVersion || '')
  ) {
    throw new Error('[E2E] Parametros invalidos para verificar a versao instalada.');
  }

  const spawnSyncFn = dependencies.spawnSync || spawnSync;
  const sshOptions = dependencies.sshOptions
    || getSshOptions(process.env, config.lock.sshHost);
  const sshTarget = `${config.lock.sshUser}@${config.lock.sshHost}`;
  const result = spawnSyncFn(
    'ssh',
    [
      ...sshOptions,
      sshTarget,
      'sh', '-s', '--', target.containerName, expectedVersion,
    ],
    {
      input: REMOTE_VERSION_SCRIPT,
      encoding: 'utf8',
      stdio: ['pipe', 'pipe', 'pipe'],
      timeout: 30000,
    }
  );

  if (result.status !== 0) {
    throw new Error(
      `[E2E] ${target.environment}/${target.site} nao possui a versao esperada; publique novamente antes de testar.`
    );
  }
}

module.exports = { REMOTE_VERSION_SCRIPT, verifyInstalledVersion };
