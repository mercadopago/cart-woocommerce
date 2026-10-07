#!/usr/bin/env node

/**
 * Gate público de validação de integridade dos assets (PPSP-1529).
 *
 * Compara cada asset listado no integrity-manifest.json contra o que está
 * efetivamente presente no pacote/working tree, falhando (exit != 0) quando
 * algum asset estiver AUSENTE ou com hash SHA-256 DIVERGENTE.
 *
 * Depende apenas da stdlib (fs, path, crypto) — roda no CI sem `npm ci`.
 * Espelha o algoritmo de hash de main.js:generateIntegrityManifest() e do
 * consumidor de runtime src/HealthMonitor/FileIntegrityChecker.php.
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { INTEGRITY_IGNORED_ASSETS } = require('./integrity-assets');

const DEFAULT_MANIFEST = 'integrity-manifest.json';

const MIN_ASSET_RE = /\.min\.(js|css)$/;
const ignoredAssets = new Set(INTEGRITY_IGNORED_ASSETS);

/**
 * Lista os assets .min.{js,css} presentes sob <root>/assets (recursivo).
 * Usa fs.readdirSync recursivo (Node 20+, stdlib). Se a pasta não existe,
 * retorna [] — a validação de órfãos simplesmente não se aplica.
 *
 * @param {string} root
 * @returns {string[]} caminhos relativos à raiz, no formato do manifest (assets/...)
 */
function collectMinAssets (root) {
  const assetsDir = path.resolve(root, 'assets');

  if (!fs.existsSync(assetsDir)) {
    return [];
  }

  const entries = fs.readdirSync(assetsDir, { recursive: true });

  if (!Array.isArray(entries)) {
    return [];
  }

  return entries
    .map((entry) => 'assets/' + String(entry).split(path.sep).join('/'))
    .filter((relativePath) => MIN_ASSET_RE.test(relativePath));
}

/**
 * Valida os assets de `root` contra o manifest.
 *
 * @param {object}  [options]
 * @param {string}  [options.root='.']                  Diretório-raiz onde vivem os assets/.
 * @param {string}  [options.manifest='integrity-manifest.json'] Caminho do manifest (fonte de verdade).
 * @returns {{ ok: boolean, checked: number, missing: string[], mismatched: string[], orphans: string[], manifestPath: string }}
 * @throws {Error} Se o manifest não existir, for ilegível, inválido ou vazio (pré-requisito de release).
 */
function verifyIntegrity (options = {}) {
  const root = options.root || '.';
  const manifestPath = path.resolve(options.manifest || DEFAULT_MANIFEST);

  if (!fs.existsSync(manifestPath)) {
    throw new Error(`Manifest de integridade não encontrado: ${manifestPath}`);
  }

  let manifest;
  try {
    manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  } catch (error) {
    throw new Error(`Manifest de integridade inválido (${manifestPath}): ${error.message}`);
  }

  if (!manifest || typeof manifest !== 'object' || Object.keys(manifest).length === 0) {
    throw new Error(`Manifest de integridade vazio: ${manifestPath}`);
  }

  const missing = [];
  const mismatched = [];

  Object.entries(manifest).forEach(([relativePath, expectedHash]) => {
    const absolutePath = path.resolve(root, relativePath);

    if (!fs.existsSync(absolutePath) || !fs.statSync(absolutePath).isFile()) {
      missing.push(relativePath);
      return;
    }

    const actualHash = crypto
      .createHash('sha256')
      .update(fs.readFileSync(absolutePath))
      .digest('hex');

    if (actualHash !== expectedHash) {
      mismatched.push(relativePath);
    }
  });

  // Assets .min presentes no pacote mas ausentes do manifest — mesma família
  // de falha (manifest desatualizado): um asset gerado sem recriar o
  // integrity-manifest.json não teria hash para ser validado nem em runtime.
  const manifestKeys = new Set(Object.keys(manifest));
  const orphans = collectMinAssets(root)
    .filter((relativePath) => !ignoredAssets.has(relativePath))
    .filter((relativePath) => !manifestKeys.has(relativePath));

  return {
    ok: missing.length === 0 && mismatched.length === 0 && orphans.length === 0,
    checked: Object.keys(manifest).length,
    missing,
    mismatched,
    orphans,
    manifestPath
  };
}

/**
 * Parse mínimo de argumentos de linha de comando: --root <dir> e --manifest <path>
 * (também aceita a forma --chave=valor).
 *
 * @param {string[]} argv
 * @returns {{ root?: string, manifest?: string }}
 */
function parseArgs (argv) {
  const options = {};

  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    const eq = arg.indexOf('=');
    const key = eq === -1 ? arg : arg.slice(0, eq);
    const inlineValue = eq === -1 ? undefined : arg.slice(eq + 1);

    if (key === '--root') {
      options.root = inlineValue !== undefined ? inlineValue : argv[++i];
    } else if (key === '--manifest') {
      options.manifest = inlineValue !== undefined ? inlineValue : argv[++i];
    }
  }

  return options;
}

function main () {
  const options = parseArgs(process.argv.slice(2));

  let result;
  try {
    result = verifyIntegrity(options);
  } catch (error) {
    console.error(`[integrity] ERRO: ${error.message}`);
    process.exit(1);
  }

  if (result.ok) {
    console.log(`[integrity] OK: ${result.checked} assets verificados com sucesso.`);
    process.exit(0);
  }

  console.error('[integrity] FALHA: pacote de release com assets ausentes ou divergentes.');
  console.error(`[integrity] Manifest: ${result.manifestPath}`);

  if (result.missing.length > 0) {
    console.error(`[integrity] Arquivos AUSENTES (${result.missing.length}):`);
    result.missing.forEach((file) => console.error(`  - ${file}`));
  }

  if (result.mismatched.length > 0) {
    console.error(`[integrity] Arquivos com hash DIVERGENTE (${result.mismatched.length}):`);
    result.mismatched.forEach((file) => console.error(`  - ${file}`));
  }

  if (result.orphans.length > 0) {
    console.error(`[integrity] Assets FORA do manifest (${result.orphans.length}) — manifest desatualizado:`);
    result.orphans.forEach((file) => console.error(`  - ${file}`));
  }

  console.error('[integrity] Rode `npm run build` e recommite os assets antes de publicar a release.');
  process.exit(1);
}

if (require.main === module) {
  main();
}

module.exports = { verifyIntegrity, parseArgs, main };
