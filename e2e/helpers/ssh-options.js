const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

function resolveKnownHosts(environment, hostname) {
  const requestedPath = environment.SMOOTH_KNOWN_HOSTS_PATH;
  if (!requestedPath || !hostname) {
    throw new Error(
      '[E2E] SMOOTH_KNOWN_HOSTS_PATH e o host SSH sao obrigatorios para validar a identidade remota.'
    );
  }

  let knownHostsPath;
  let stats;
  try {
    knownHostsPath = fs.realpathSync(path.resolve(requestedPath));
    stats = fs.statSync(knownHostsPath);
  } catch {
    throw new Error('[E2E] SMOOTH_KNOWN_HOSTS_PATH nao aponta para um arquivo legivel.');
  }
  if (!stats.isFile() || stats.size === 0 || stats.size > 1024 * 1024 || (stats.mode & 0o022) !== 0) {
    throw new Error(
      '[E2E] SMOOTH_KNOWN_HOSTS_PATH deve ser um arquivo regular protegido contra escrita de grupo/outros.'
    );
  }

  const lookup = spawnSync(
    'ssh-keygen',
    ['-F', hostname, '-f', knownHostsPath],
    { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 5000 }
  );
  if (lookup.status !== 0 || !lookup.stdout.trim()) {
    throw new Error(`[E2E] O known_hosts informado nao contem uma chave para ${hostname}.`);
  }

  return knownHostsPath;
}

function getSshOptions(environment = process.env, hostname) {
  const knownHostsPath = resolveKnownHosts(environment, hostname);
  const options = [
    '-o', 'BatchMode=yes',
    '-o', 'StrictHostKeyChecking=yes',
    '-o', `UserKnownHostsFile=${knownHostsPath}`,
  ];
  const requestedKey = environment.SMOOTH_PK_PATH;
  if (!requestedKey) return options;

  let keyPath;
  let stats;
  try {
    keyPath = fs.realpathSync(path.resolve(requestedKey));
    stats = fs.statSync(keyPath);
  } catch {
    throw new Error('[E2E] SMOOTH_PK_PATH nao aponta para uma chave legivel.');
  }
  if (!stats.isFile() || (stats.mode & 0o077) !== 0) {
    throw new Error('[E2E] SMOOTH_PK_PATH deve ser um arquivo regular sem permissao para grupo/outros.');
  }

  return [...options, '-o', 'IdentitiesOnly=yes', '-i', keyPath];
}

module.exports = { getSshOptions, resolveKnownHosts };
