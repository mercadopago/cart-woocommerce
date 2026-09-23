#!/usr/bin/env node
const fs = require('node:fs');
const https = require('node:https');
const path = require('node:path');
const { randomBytes } = require('node:crypto');
const { Transform } = require('node:stream');
const { pipeline } = require('node:stream/promises');
const { normalizeVersion, validateArtifact } = require('./publish-candidate');

const MAX_DOWNLOAD_SIZE = 100 * 1024 * 1024;
const ARTIFACTS_DIR = path.resolve(__dirname, 'results', 'artifacts');

function parseArgs(argv) {
  const options = { dryRun: false };
  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (argument === '--version') {
      const value = argv[index + 1];
      if (!value || value.startsWith('--')) throw new Error('[E2E] --version requer um valor.');
      options.version = normalizeVersion(value, 'Versao produtiva');
      index += 1;
    } else if (argument === '--dry-run') {
      options.dryRun = true;
    } else {
      throw new Error(`[E2E] Argumento nao suportado: ${argument}.`);
    }
  }
  if (!options.version) throw new Error('[E2E] --version e obrigatorio.');
  return options;
}

function productionArtifactUrl(version) {
  const safeVersion = normalizeVersion(version, 'Versao produtiva');
  return new URL(
    `https://downloads.wordpress.org/plugin/woocommerce-mercadopago.${safeVersion}.zip`
  );
}

function productionArtifactPath(version) {
  const safeVersion = normalizeVersion(version, 'Versao produtiva');
  return path.join(ARTIFACTS_DIR, `woocommerce-mercadopago.${safeVersion}.zip`);
}

function ensureArtifactsDirectory() {
  const resultsDir = path.dirname(ARTIFACTS_DIR);
  fs.mkdirSync(resultsDir, { recursive: true, mode: 0o700 });
  if (fs.realpathSync(resultsDir) !== resultsDir) {
    throw new Error('[E2E] O diretorio de resultados nao pode usar links simbolicos.');
  }

  try {
    fs.mkdirSync(ARTIFACTS_DIR, { mode: 0o700 });
  } catch (error) {
    if (error.code !== 'EEXIST') throw error;
  }
  const artifactsStats = fs.lstatSync(ARTIFACTS_DIR);
  if (!artifactsStats.isDirectory() || artifactsStats.isSymbolicLink()) {
    throw new Error('[E2E] O diretorio de artefatos e invalido.');
  }
  fs.chmodSync(ARTIFACTS_DIR, 0o700);
}

async function download(url, destination) {
  const response = await new Promise((resolve, reject) => {
    const request = https.get(url, {
      headers: { 'User-Agent': 'woocommerce-mercadopago-e2e-release' },
    }, resolve);
    request.setTimeout(30000, () => request.destroy(new Error('download timeout')));
    request.on('error', reject);
  });

  if (response.statusCode !== 200) {
    response.resume();
    throw new Error('[E2E] WordPress.org nao retornou o ZIP produtivo solicitado.');
  }
  const declaredSize = Number(response.headers['content-length'] || 0);
  if (!Number.isSafeInteger(declaredSize) || declaredSize > MAX_DOWNLOAD_SIZE) {
    response.resume();
    throw new Error('[E2E] ZIP produtivo excede o limite de 100 MiB.');
  }

  let received = 0;
  const limiter = new Transform({
    transform(chunk, _encoding, callback) {
      received += chunk.length;
      if (received > MAX_DOWNLOAD_SIZE) {
        callback(new Error('download size limit exceeded'));
        return;
      }
      callback(null, chunk);
    },
  });
  const output = fs.createWriteStream(destination, { flags: 'wx', mode: 0o600 });
  try {
    await pipeline(response, limiter, output);
  } catch {
    throw new Error('[E2E] Download seguro do ZIP produtivo falhou.');
  }
}

async function fetchProductionArtifact(version) {
  const safeVersion = normalizeVersion(version, 'Versao produtiva');
  const destination = productionArtifactPath(safeVersion);
  ensureArtifactsDirectory();

  if (fs.existsSync(destination)) {
    try {
      const cached = validateArtifact(destination);
      if (cached.version === safeVersion) return { ...cached, cached: true };
    } catch {
      // A cache e descartavel; um ZIP invalido nunca e publicado.
    }
    fs.rmSync(destination, { force: true });
  }

  const temporary = `${destination}.${randomBytes(8).toString('hex')}.tmp.zip`;
  try {
    await download(productionArtifactUrl(safeVersion), temporary);
    const artifact = validateArtifact(temporary);
    if (artifact.version !== safeVersion) {
      throw new Error('[E2E] O ZIP baixado nao corresponde a versao produtiva solicitada.');
    }
    fs.renameSync(temporary, destination);
    fs.chmodSync(destination, 0o600);
    return { ...artifact, artifactPath: destination, cached: false };
  } finally {
    fs.rmSync(temporary, { force: true });
  }
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  const url = productionArtifactUrl(options.version);
  const destination = productionArtifactPath(options.version);
  if (options.dryRun) {
    process.stdout.write(
      `[E2E] production download | version=${options.version} | source=${url.origin} | destination=${destination}\n`
    );
    return;
  }

  const artifact = await fetchProductionArtifact(options.version);
  process.stdout.write(
    `[E2E] production artifact | version=${artifact.version} | sha256=${artifact.sha256} | cached=${artifact.cached ? 'yes' : 'no'} | path=${artifact.artifactPath}\n`
  );
}

if (require.main === module) {
  main().catch((error) => {
    const message = error.message && error.message.startsWith('[E2E]')
      ? error.message
      : '[E2E] Falha inesperada ao obter o ZIP produtivo.';
    process.stderr.write(`${message}\n`);
    process.exitCode = 1;
  });
}

module.exports = {
  ensureArtifactsDirectory,
  fetchProductionArtifact,
  parseArgs,
  productionArtifactPath,
  productionArtifactUrl,
};
