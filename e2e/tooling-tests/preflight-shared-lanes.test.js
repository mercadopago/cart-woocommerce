const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const {
  diagnose,
  formatReport,
  parseRemoteState,
} = require('../preflight-shared-lanes');

const config = { lock: { name: 'woocommerce-e2e-shared-pair' } };
const TTL = 14400;

function line(...fields) {
  return fields.join('\t');
}

function healthyState(overrides = []) {
  const containers = [];
  for (const lane of ['staging', 'homol']) {
    for (const site of ['mla', 'mlb', 'mlc', 'mlm', 'mco', 'mlu', 'mpe']) {
      const version = lane === 'staging' ? '8.9.4' : '8.9.3';
      containers.push(line('container', `wp-${lane}-${site}`, 'running', `/srv/artifacts/${lane}`, '', version));
    }
  }
  return parseRemoteState([
    ...containers,
    line('lane', 'staging', '/srv/artifacts/staging', '755,755,755,', '../releases/8.9.4-abc', '2'),
    line('lane', 'homol', '/srv/artifacts/homol', '755,755,755,', '../releases/8.9.3-def', '1'),
    ...overrides,
  ].join('\n'));
}

describe('shared lane preflight', () => {
  it('clears a fully migrated pair and points at the release command', () => {
    const diagnosis = diagnose(healthyState(), config, TTL);
    assert.equal(diagnosis.ready, true);
    assert.deepEqual(diagnosis.blockers, []);
    assert.match(formatReport(healthyState(), diagnosis), /e2e-shared-release/);
  });

  it('detects a pair still on the pre-migration mount and names the fix', () => {
    const state = parseRemoteState([
      line('container', 'wp-staging-mlb', 'running', '', '/srv/artifacts/staging/current/woocommerce-mercadopago', '8.9.3'),
      line('lane', 'staging', 'unknown', '', '', ''),
    ].join('\n'));

    const diagnosis = diagnose(state, config, TTL);
    assert.equal(diagnosis.ready, false);
    assert.match(diagnosis.blockers[0].what, /mount antigo/);
    assert.match(diagnosis.blockers[0].fix, /e2e-shared-infra-migrate/);
  });

  it('flags a 0700 lane, which boots WordPress with no gateway', () => {
    const state = parseRemoteState([
      line('container', 'wp-staging-mlb', 'running', '/srv/artifacts/staging', '', '8.9.3'),
      line('lane', 'staging', '/srv/artifacts/staging', '700,700,700,', '../releases/8.9.3-abc', '1'),
    ].join('\n'));

    const diagnosis = diagnose(state, config, TTL);
    assert.equal(diagnosis.ready, false);
    assert.match(diagnosis.blockers[0].what, /nao e atravessavel/);
    assert.match(diagnosis.blockers[0].fix, /repair-permissions/);
  });

  it('blocks on a live lease but treats an expired one as recyclable', () => {
    const live = diagnose(
      healthyState([line('lease', 'woocommerce-e2e-shared-pair-mlb', 'colega', '600')]),
      config,
      TTL
    );
    assert.equal(live.ready, false);
    assert.match(live.blockers[0].what, /lease ativo ha 10 min/);
    assert.match(live.blockers[0].what, /colega/);

    // A lease older than the TTL is reclaimed automatically; blocking on it would strand the
    // release for hours after a crashed runner.
    const stale = diagnose(
      healthyState([line('lease', 'woocommerce-e2e-shared-pair-mlb', 'colega', '20000')]),
      config,
      TTL
    );
    assert.equal(stale.ready, true);
    assert.match(stale.warnings[0], /expirado/);
  });

  it('separates a publish in flight from a country run', () => {
    const diagnosis = diagnose(
      healthyState([line('lease', 'woocommerce-e2e-shared-pair-publish', 'outro', '120')]),
      config,
      TTL
    );
    assert.match(diagnosis.blockers[0].fix, /Outra publicacao/);
  });

  it('reports the version spread per lane so a half-published lane is visible', () => {
    const state = healthyState();
    state.containers[0].version = '8.9.3';
    const report = formatReport(state, diagnose(state, config, TTL));
    assert.match(report, /8\.9\.4\s+\| 6\/7 lojas/);
    assert.match(report, /wp-staging-mla/);
  });
});
