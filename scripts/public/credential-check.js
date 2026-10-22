#!/usr/bin/env node
'use strict';

// Blocks a package that carries credentials: sensitive file names anywhere in the tree, or file
// contents that look like private keys or provider tokens. Shared by the public packaging script
// and the private release tooling, so both apply the same patterns.
const fs = require('node:fs');
const path = require('node:path');

const SENSITIVE_FILE_BASENAMES = new Set([
  '.npmrc',
  '.pypirc',
  'auth.json',
  'credentials',
  'id_dsa',
  'id_ecdsa',
  'id_ed25519',
  'id_rsa',
]);

const SENSITIVE_FILE_EXTENSIONS = new Set([
  '.jks',
  '.key',
  '.keystore',
  '.p12',
  '.pem',
  '.pfx',
]);

const CREDENTIAL_PATTERNS = [
  /-----BEGIN (?:[A-Z\d ]+ )?PRIVATE KEY-----/u,
  /\bgh[pousr]_[A-Za-z\d]{20,}\b/u,
  /\bxox[baprs]-[A-Za-z\d-]{20,}\b/u,
  /\bAKIA[A-Z\d]{16}\b/u,
  // Mercado Pago access token (APP_USR-/TEST-<app>-<date>-<hash>-<user>) and GitHub fine-grained token.
  /\b(?:APP_USR|TEST)-\d{16}-\d{6}-[a-f\d]{32}-\d+\b/u,
  /\bgithub_pat_[A-Za-z\d_]{50,}\b/u,
];

function normalizeRepoPath (value) {
  return String(value).split(path.sep).join('/').replace(/^\.\//, '');
}

function isSensitiveFilePath (relativePath) {
  const normalized = normalizeRepoPath(relativePath);
  return normalized.split('/').some((component) => {
    const lower = component.toLowerCase();
    return lower === '.env'
      || lower.startsWith('.env.')
      || SENSITIVE_FILE_BASENAMES.has(lower)
      || SENSITIVE_FILE_EXTENSIONS.has(path.extname(lower));
  });
}

// Returns the relative paths that must not ship. Symbolic links are never followed.
function findCredentialPaths (root) {
  const unsafePaths = [];
  const pending = [root];

  while (pending.length > 0) {
    const current = pending.pop();
    for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
      if (current === root && entry.name === '.git') continue;

      const target = path.join(current, entry.name);
      const relative = normalizeRepoPath(path.relative(root, target));
      if (isSensitiveFilePath(relative)) {
        unsafePaths.push(relative);
        continue;
      }
      if (entry.isDirectory()) {
        pending.push(target);
        continue;
      }
      if (!entry.isFile()) continue;

      const content = fs.readFileSync(target, 'utf8');
      if (CREDENTIAL_PATTERNS.some((pattern) => pattern.test(content))) unsafePaths.push(relative);
    }
  }

  return unsafePaths.sort();
}

function main (argv = process.argv.slice(2)) {
  if (argv.length !== 1) {
    console.error('Usage: credential-check.js <directory>');
    process.exitCode = 2;
    return;
  }
  const unsafePaths = findCredentialPaths(path.resolve(argv[0]));
  if (unsafePaths.length > 0) {
    console.error(`Possible credentials found; the package was blocked: ${unsafePaths.join(', ')}`);
    process.exitCode = 1;
  }
}

if (require.main === module) main();

module.exports = {
  CREDENTIAL_PATTERNS,
  SENSITIVE_FILE_BASENAMES,
  SENSITIVE_FILE_EXTENSIONS,
  findCredentialPaths,
  isSensitiveFilePath,
  main,
  normalizeRepoPath,
};
