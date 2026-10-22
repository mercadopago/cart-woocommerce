#!/usr/bin/env node
'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const DEFAULT_MANIFEST = path.resolve(__dirname, '..', '..', 'release-artifacts.json');
const PROFILES = new Set(['public_git', 'package']);

function readManifest (manifestPath = DEFAULT_MANIFEST) {
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  if (manifest.schemaVersion !== 1 || !manifest.profiles || typeof manifest.profiles !== 'object') {
    throw new Error('Manifesto de release inválido.');
  }
  for (const profile of PROFILES) {
    const files = manifest.profiles[profile]?.requiredFiles;
    if (!Array.isArray(files) || files.length === 0 || new Set(files).size !== files.length ||
      files.some((file) => typeof file !== 'string' || !isSafeRelativePath(file))) {
      throw new Error(`Perfil ${profile} inválido no manifesto de release.`);
    }
  }
  const published = new Set(manifest.profiles.public_git.requiredFiles);
  if ([...published].some((file) => !manifest.profiles.package.requiredFiles.includes(file))) {
    throw new Error('O perfil package deve conter todos os outputs do public_git.');
  }
  return manifest;
}

function isSafeRelativePath (value) {
  return value.length > 0 && !path.isAbsolute(value) && !value.includes('\\') &&
    value.split('/').every((part) => part && part !== '.' && part !== '..' && !part.includes('\0'));
}

function inventoryTree (root) {
  const rootPath = fs.realpathSync(root);
  if (!fs.statSync(rootPath).isDirectory()) {
    throw new Error('A raiz do artefato não é um diretório.');
  }
  const inventory = {};
  const pending = [{ absolute: rootPath, relative: '' }];
  while (pending.length > 0) {
    const current = pending.pop();
    for (const entry of fs.readdirSync(current.absolute, { withFileTypes: true })) {
      if (current.relative === '' && (entry.name === '.git' || entry.name === '.svn')) {
        continue;
      }
      if (entry.name === '.git' || entry.name === '.svn') {
        throw new Error('O artefato contém metadados de controle de versão aninhados.');
      }
      const relative = current.relative ? `${current.relative}/${entry.name}` : entry.name;
      if (!isSafeRelativePath(relative)) {
        throw new Error('O artefato contém um caminho inválido.');
      }
      const absolute = path.join(current.absolute, entry.name);
      const stat = fs.lstatSync(absolute);
      if (stat.isSymbolicLink()) {
        throw new Error(`O artefato contém um link simbólico: ${relative}`);
      }
      if (stat.isDirectory()) {
        pending.push({ absolute, relative });
      } else if (stat.isFile()) {
        inventory[relative] = crypto.createHash('sha256').update(fs.readFileSync(absolute)).digest('hex');
      } else {
        throw new Error(`O artefato contém um arquivo especial: ${relative}`);
      }
    }
  }
  return Object.fromEntries(Object.entries(inventory).sort(([left], [right]) => left.localeCompare(right)));
}

function validateArtifact ({ root, profile, manifestPath = DEFAULT_MANIFEST }) {
  if (!PROFILES.has(profile)) {
    throw new Error('Perfil de artefato inválido.');
  }
  const manifest = readManifest(manifestPath);
  const inventory = inventoryTree(root);
  const missing = manifest.profiles[profile].requiredFiles.filter((file) => !inventory[file]);
  const empty = manifest.profiles[profile].requiredFiles.filter((file) => {
    if (!inventory[file]) return false;
    return fs.statSync(path.join(root, file)).size === 0;
  });
  if (missing.length || empty.length) {
    throw new Error(`Artefato ${profile} inválido; ausentes: ${missing.join(', ') || '-'}; vazios: ${empty.join(', ') || '-'}.`);
  }
  return { profile, files: Object.keys(inventory).length, inventory };
}

function compareInventories (expected, actual) {
  return {
    missing: Object.keys(expected).filter((file) => !(file in actual)),
    extra: Object.keys(actual).filter((file) => !(file in expected)),
    changed: Object.keys(expected).filter((file) => file in actual && expected[file] !== actual[file]),
  };
}

const VALUE_OPTIONS = ['--root', '--profile', '--manifest', '--compare'];

function parseArgs (argv) {
  const options = {};
  const args = [...argv];
  while (args.length) {
    const key = args.shift();
    if (key === '--json') {
      options.json = true;
      continue;
    }
    if (!VALUE_OPTIONS.includes(key)) throw new Error('Opção inválida para validação de artefato.');
    const value = args.shift();
    if (!value) throw new Error('Valor de opção ausente.');
    options[key.slice(2)] = value;
  }
  if (!options.root || !options.profile) throw new Error('Informe --root e --profile.');
  return options;
}

function main () {
  try {
    const options = parseArgs(process.argv.slice(2));
    const result = validateArtifact({ root: options.root, profile: options.profile, manifestPath: options.manifest });
    const differences = options.compare
      ? compareInventories(result.inventory, inventoryTree(options.compare))
      : null;
    const ok = !differences || Object.values(differences).every((files) => files.length === 0);
    const output = { ok, profile: result.profile, files: result.files, differences };
    console.log(options.json ? JSON.stringify(output) : `Artefato ${result.profile}: ${result.files} arquivos; comparação ${ok ? 'OK' : 'DIVERGENTE'}.`);
    if (!ok) process.exitCode = 1;
  } catch (error) {
    console.error(`Validação de artefato falhou: ${error.message}`);
    process.exitCode = 1;
  }
}

if (require.main === module) main();

module.exports = { compareInventories, inventoryTree, isSafeRelativePath, main, parseArgs, readManifest, validateArtifact };
