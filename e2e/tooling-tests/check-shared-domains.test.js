const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const config = require('../config/environments.json');
const {
  checkSharedDomains,
  diagnoseDomains,
  formatReport,
  getSharedHosts,
  getSmoothInstance,
  parseSmoothInstances,
} = require('../check-shared-domains');

const INSTANCE = Object.freeze({
  id: 'i-06d1545936c070469',
  status: 'running',
  ip: '44.210.146.181',
  name: 'big-shared-xlarge',
});

describe('shared domain check', () => {
  it('extracts the shared instance from the smooth table without using its domain metadata', () => {
    const stdout = [
      'ID                   Status   IP              Name               Owner  Pause Policy  Domain  Type',
      'i-0888cd93e9b553bba  running  34.201.129.111  another-instance   owner                another.ppolimpo.io  t2.medium',
      'i-06d1545936c070469  running  44.210.146.181  big-shared-xlarge  owner  default       big-shared-xlarge.ppolimpo.io  t4g.xlarge',
    ].join('\n');

    assert.deepEqual(parseSmoothInstances(stdout), [
      {
        id: 'i-0888cd93e9b553bba',
        status: 'running',
        ip: '34.201.129.111',
        name: 'another-instance',
      },
      INSTANCE,
    ]);
    assert.deepEqual(
      getSmoothInstance(config, { spawnSync: () => ({ status: 0, stdout }) }),
      INSTANCE
    );
  });

  it('derives exactly the 14 staging and homol hosts from the validated config', () => {
    const hosts = getSharedHosts(config);
    assert.equal(hosts.length, 14);
    assert.ok(hosts.includes('e2e-staging-mco.ppolimpo.io'));
    assert.ok(hosts.includes('e2e-homol-mpe.ppolimpo.io'));
    assert.ok(!hosts.includes('big-shared-xlarge.ppolimpo.io'));
  });

  it('passes only when every host has exactly the current smooth IPv4', () => {
    const results = getSharedHosts(config).map((host) => ({
      host,
      addresses: [INSTANCE.ip],
      errorCode: null,
    }));
    const diagnosis = diagnoseDomains(results, INSTANCE.ip);

    assert.equal(diagnosis.ready, true);
    assert.match(formatReport(INSTANCE, diagnosis, 'ppolimpo.io'), /14\/14 dominios/);
  });

  it('reports stale, extra and unresolved records with smooth-only remediation', () => {
    const results = [
      { host: 'e2e-staging-mco.ppolimpo.io', addresses: ['54.164.127.132'], errorCode: null },
      { host: 'e2e-homol-mlu.ppolimpo.io', addresses: [], errorCode: 'ENOTFOUND' },
      { host: 'e2e-homol-mpe.ppolimpo.io', addresses: [INSTANCE.ip, '54.164.127.132'], errorCode: null },
    ];
    const diagnosis = diagnoseDomains(results, INSTANCE.ip);
    const report = formatReport(INSTANCE, diagnosis, 'ppolimpo.io');

    assert.equal(diagnosis.ready, false);
    assert.equal(diagnosis.mismatches.length, 3);
    assert.match(report, /smooth add-domain i-06d1545936c070469 e2e-staging-mco/);
    assert.match(report, /smooth add-domain i-06d1545936c070469 e2e-homol-mlu/);
    assert.doesNotMatch(report, /aws|route53/i);
  });

  it('uses injected smooth and DNS dependencies in the complete read-only check', async () => {
    const hosts = getSharedHosts(config);
    const checked = await checkSharedDomains({
      config,
      instance: INSTANCE,
      resolve4: async (host) => {
        assert.ok(hosts.includes(host));
        return [INSTANCE.ip];
      },
    });

    assert.equal(checked.diagnosis.ready, true);
    assert.equal(checked.diagnosis.checked, 14);
  });
});
