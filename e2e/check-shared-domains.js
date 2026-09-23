#!/usr/bin/env node
const { spawnSync } = require('node:child_process');
const dns = require('node:dns').promises;
const net = require('node:net');
const { loadEnvironmentConfig } = require('./helpers/environment-config');

const INSTANCE_ID_PATTERN = /^i-[0-9a-f]{8,32}$/;

function parseSmoothInstances(stdout) {
  const instances = [];

  for (const line of String(stdout || '').split('\n')) {
    const fields = line.trim().split(/\s+/);
    if (!INSTANCE_ID_PATTERN.test(fields[0] || '')) continue;

    const [id, status, ip, name] = fields;
    if (!status || !ip || !name) continue;
    instances.push({ id, status, ip, name });
  }

  return instances;
}

function getSharedHosts(config) {
  const hosts = new Set();

  for (const environment of ['staging', 'homol']) {
    for (const siteConfig of Object.values(config.environments[environment].sites)) {
      hosts.add(new URL(siteConfig.shopUrl).hostname.toLowerCase());
    }
  }

  return [...hosts].sort();
}

function getSmoothInstance(config, dependencies = {}) {
  const spawnSyncFn = dependencies.spawnSync || spawnSync;
  const result = spawnSyncFn('smooth', ['get-instances'], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
    timeout: 30000,
    maxBuffer: 1024 * 1024,
  });

  if (result.error || result.status !== 0) {
    throw new Error('[E2E] Nao foi possivel consultar as instancias pelo smooth.');
  }

  const instanceName = config.lock.sshHost.split('.')[0];
  const instance = parseSmoothInstances(result.stdout)
    .find((candidate) => candidate.name === instanceName);

  if (!instance) {
    throw new Error(`[E2E] A instancia '${instanceName}' nao foi encontrada pelo smooth.`);
  }
  if (instance.status !== 'running' || net.isIP(instance.ip) !== 4) {
    throw new Error(`[E2E] A instancia '${instanceName}' nao esta rodando com um IPv4 valido.`);
  }

  return instance;
}

async function resolveSharedHosts(hosts, resolver = dns.resolve4) {
  return Promise.all(hosts.map(async (host) => {
    try {
      const resolved = await resolver(host);
      const addresses = [...new Set(resolved)]
        .filter((address) => net.isIP(address) === 4)
        .sort();
      return { host, addresses, errorCode: addresses.length ? null : 'NO_A_RECORD' };
    } catch (error) {
      const errorCode = typeof error?.code === 'string' && /^[A-Z0-9_]+$/.test(error.code)
        ? error.code
        : 'DNS_ERROR';
      return { host, addresses: [], errorCode };
    }
  }));
}

function diagnoseDomains(results, expectedIp) {
  if (net.isIP(expectedIp) !== 4) {
    throw new Error('[E2E] O smooth retornou um IPv4 invalido para a instancia compartilhada.');
  }

  const mismatches = results.filter(({ addresses, errorCode }) => (
    errorCode || addresses.length !== 1 || addresses[0] !== expectedIp
  ));

  return {
    ready: mismatches.length === 0,
    checked: results.length,
    mismatches,
  };
}

function getDomainPrefix(host, baseDomain) {
  const suffix = `.${baseDomain}`;
  if (!host.endsWith(suffix)) {
    throw new Error('[E2E] Dominio compartilhado fora da zona gerenciada pelo smooth.');
  }
  return host.slice(0, -suffix.length);
}

function formatReport(instance, diagnosis, baseDomain) {
  const lines = [
    `[E2E] DNS compartilhado: instancia ${instance.name} (${instance.id}) -> ${instance.ip}`,
  ];

  if (diagnosis.ready) {
    lines.push(`[E2E] ${diagnosis.checked}/${diagnosis.checked} dominios alinhados com o smooth.`);
    return lines.join('\n');
  }

  lines.push(`[E2E] ${diagnosis.mismatches.length}/${diagnosis.checked} dominios divergem do smooth:`);
  for (const mismatch of diagnosis.mismatches) {
    const actual = mismatch.errorCode || mismatch.addresses.join(', ');
    lines.push(`  - ${mismatch.host}: ${actual} (esperado ${instance.ip})`);
  }
  lines.push('');
  lines.push('[E2E] Correcao permitida via smooth:');
  for (const mismatch of diagnosis.mismatches) {
    lines.push(`  smooth add-domain ${instance.id} ${getDomainPrefix(mismatch.host, baseDomain)}`);
  }
  lines.push('  Depois, aguarde a propagacao DNS e reexecute make e2e-shared-domain-check.');

  return lines.join('\n');
}

async function checkSharedDomains(dependencies = {}) {
  const config = dependencies.config || loadEnvironmentConfig();
  const instance = dependencies.instance || getSmoothInstance(config, dependencies);
  const hosts = getSharedHosts(config);
  const results = await resolveSharedHosts(hosts, dependencies.resolve4 || dns.resolve4);
  const diagnosis = diagnoseDomains(results, instance.ip);
  const baseDomain = config.lock.sshHost.split('.').slice(1).join('.');

  return { instance, diagnosis, report: formatReport(instance, diagnosis, baseDomain) };
}

async function main() {
  const { diagnosis, report } = await checkSharedDomains();
  process.stdout.write(`${report}\n`);
  process.exitCode = diagnosis.ready ? 0 : 1;
}

if (require.main === module) {
  main().catch((error) => {
    const message = error.message && error.message.startsWith('[E2E]')
      ? error.message
      : '[E2E] Falha inesperada ao verificar o DNS compartilhado.';
    process.stderr.write(`${message}\n`);
    process.exitCode = 2;
  });
}

module.exports = {
  checkSharedDomains,
  diagnoseDomains,
  formatReport,
  getDomainPrefix,
  getSharedHosts,
  getSmoothInstance,
  parseSmoothInstances,
  resolveSharedHosts,
};
