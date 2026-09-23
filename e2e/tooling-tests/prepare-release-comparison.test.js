const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const {
  parseArgs,
  publishReleaseUnderLease,
  validateExpectedVersion,
} = require('../prepare-release-comparison');

const completeArgs = [
  '--site', 'MLB',
  '--candidate-artifact', 'woocommerce-mercadopago.zip',
  '--candidate-version', '8.9.4',
  '--production-artifact', 'e2e/results/artifacts/woocommerce-mercadopago.8.9.3.zip',
  '--production-version', '8.9.3',
];

describe('release comparison preparation', () => {
  it('accepts only an explicit RC and production mapping', () => {
    assert.deepEqual(parseArgs([...completeArgs, '--dry-run']), {
      site: 'MLB',
      candidateArtifact: 'woocommerce-mercadopago.zip',
      candidateVersion: '8.9.4',
      productionArtifact: 'e2e/results/artifacts/woocommerce-mercadopago.8.9.3.zip',
      productionVersion: '8.9.3',
      dryRun: true,
    });
  });

  it('rejects equal versions and arbitrary target selection', () => {
    const equalVersions = completeArgs.map((value) => (value === '8.9.3' ? '8.9.4' : value));
    assert.throws(() => parseArgs(equalVersions), /versoes diferentes/);
    assert.throws(() => parseArgs([...completeArgs, '--environment', 'homol']), /nao suportado/);
  });

  it('fails closed when a ZIP version differs from the requested version', () => {
    assert.throws(
      () => validateExpectedVersion({ version: '8.9.2' }, '8.9.3', 'O ZIP produtivo'),
      /8\.9\.2.*8\.9\.3/
    );
  });

  it('publishes production before the candidate', () => {
    const published = [];
    publishReleaseUnderLease({
      candidate: { version: '8.9.4' },
      config: {},
      homol: { environment: 'homol' },
      production: { version: '8.9.3' },
      staging: { environment: 'staging' },
    }, 'developer', {
      publishArtifact: (_config, target, artifact) => {
        published.push(`${target.environment}@${artifact.version}`);
      },
      tokenFactory: () => 'token',
    });

    assert.deepEqual(published, ['homol@8.9.3', 'staging@8.9.4']);
  });

  it('restores staging to production when candidate publication fails', () => {
    const published = [];
    assert.throws(() => publishReleaseUnderLease({
      candidate: { version: '8.9.4' },
      config: {},
      homol: { environment: 'homol' },
      production: { version: '8.9.3' },
      staging: { environment: 'staging' },
    }, 'developer', {
      publishArtifact: (_config, target, artifact) => {
        published.push(`${target.environment}@${artifact.version}`);
        if (target.environment === 'staging' && artifact.version === '8.9.4') {
          throw new Error('[E2E] falha simulada');
        }
      },
      tokenFactory: () => 'token',
    }), /staging foi restaurada/);

    assert.deepEqual(published, [
      'homol@8.9.3',
      'staging@8.9.4',
      'staging@8.9.3',
    ]);
  });

  it('fails closed when candidate publication and rollback both fail', () => {
    assert.throws(() => publishReleaseUnderLease({
      candidate: { version: '8.9.4' },
      config: {},
      homol: { environment: 'homol' },
      production: { version: '8.9.3' },
      staging: { environment: 'staging' },
    }, 'developer', {
      publishArtifact: (_config, target) => {
        if (target.environment === 'staging') throw new Error('[E2E] falha simulada');
      },
      tokenFactory: () => 'token',
    }), /rollback de staging.*falhou/);
  });
});
