'use strict';

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { createRequire } = require('module');
const { validatePolicy, resolveTolerance, meetsFloor, effectiveFloorPercent } = require('./policy');

const METRICS = ['lines', 'branches', 'functions', 'statements'];

function metric(covered, total) {
  if (!Number.isInteger(total) || !Number.isInteger(covered) || covered < 0 || total < covered) {
    throw new Error('Invalid coverage counts');
  }
  return { covered, total, pct: total === 0 ? null : Math.floor(10000 * covered / total) / 100 };
}

function relativeSource(root, filename) {
  const relative = path.relative(fs.realpathSync(root), fs.realpathSync(filename)).split(path.sep).join('/');
  if (!relative || relative.startsWith('../') || path.isAbsolute(relative)) {
    throw new Error(`Coverage source outside snapshot: ${filename}`);
  }
  return relative;
}

function normalizeJs(raw, root) {
  const { createFileCoverage } = createRequire(path.join(root, 'package.json'))('istanbul-lib-coverage');
  return Object.entries(raw).map(([filename, data]) => {
    const coverage = createFileCoverage(data);
    const summary = coverage.toSummary().toJSON();
    const lines = coverage.getLineCoverage();
    return {
      path: relativeSource(root, filename),
      lines,
      metrics: Object.fromEntries(METRICS.map(key => [key, metric(summary[key].covered, summary[key].total)])),
    };
  }).sort((a, b) => a.path.localeCompare(b.path));
}

function summarize(files) {
  return Object.fromEntries(METRICS.map(key => [key, metric(
    files.reduce((sum, file) => sum + file.metrics[key].covered, 0),
    files.reduce((sum, file) => sum + file.metrics[key].total, 0),
  )]));
}

function modulesFor(files, groups) {
  return Object.fromEntries(Object.entries(groups).map(([name, group]) => [
    name, summarize(files.filter(file => group.path === './' || file.path.startsWith(group.path))),
  ]));
}

function enforce(result, groups, policy = { [result.language]: groups }) {
  validatePolicy(policy);
  const errors = [];
  for (const [name, group] of Object.entries(groups)) {
    for (const [key, floor] of Object.entries(group.floors)) {
      const { floorBasisPoints } = resolveTolerance(policy, result.language, name, key);
      const actual = result.modules[name][key];
      if (!meetsFloor(actual, floor, floorBasisPoints)) {
        errors.push(`${result.language}/${result.variant || 'default'} ${name}.${key}: ${actual.pct ?? 'n/a'} < ${effectiveFloorPercent(floor, floorBasisPoints) ?? 'empty'} (floor margin ${floorBasisPoints / 100} pp)`);
      }
    }
  }
  if (errors.length) throw new Error(`Coverage thresholds failed:\n${errors.join('\n')}`);
}

function validateFiles(files) {
  if (!Array.isArray(files) || files.length === 0) throw new Error('Coverage contains no source files');
  const seen = new Set();
  for (const file of files) {
    if (seen.has(file.path) || !file.path || file.path.startsWith('../') || path.isAbsolute(file.path)) {
      throw new Error(`Invalid or duplicate coverage source: ${file.path}`);
    }
    seen.add(file.path);
    for (const key of METRICS) metric(file.metrics[key].covered, file.metrics[key].total);
    for (const [line, hits] of Object.entries(file.lines)) {
      if (!/^\d+$/.test(line) || Number(line) < 1 || !Number.isFinite(hits) || hits < 0) {
        throw new Error(`Invalid coverage line: ${file.path}:${line}`);
      }
    }
  }
}

function sourceInventory(root, language, groups) {
  const files = [];
  const visit = directory => {
    for (const entry of fs.readdirSync(path.join(root, directory), { withFileTypes: true })) {
      const relative = path.posix.join(directory, entry.name);
      if (entry.isDirectory()) visit(relative);
      else if (language === 'js' ? /\.(js|ts)$/.test(relative) && !/\.(min\.js|bundle\.js|d\.ts)$/.test(relative) : relative.endsWith('.php') && entry.name !== 'index.php') files.push(relative);
    }
  };
  const roots = language === 'js' ? ['assets/js', 'packages/narciso'] : Object.values(groups).filter(group => group.path !== './').map(group => group.path);
  for (const directory of roots) visit(directory);
  return [...new Set(files)].sort();
}

function validateInventory(files, expected) {
  const measured = new Set(files.map(file => file.path));
  const missing = expected.filter(file => !measured.has(file));
  const extra = files.filter(file => !expected.includes(file.path));
  if (missing.length || extra.length) throw new Error(`Coverage inventory mismatch; missing: ${missing.join(', ')}; unexpected: ${extra.map(file => file.path).join(', ')}`);
}

function hashFiles(root, filenames) {
  const hash = crypto.createHash('sha256');
  for (const filename of filenames.slice().sort()) {
    hash.update(filename).update('\0').update(fs.readFileSync(path.join(root, filename))).update('\0');
  }
  return hash.digest('hex');
}

module.exports = { METRICS, metric, relativeSource, normalizeJs, summarize, modulesFor, enforce, validateFiles, sourceInventory, validateInventory, hashFiles };
