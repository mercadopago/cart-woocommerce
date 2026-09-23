const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { normalizeVersion, parseArgs, validateZipSummary } = require('../publish-candidate');
const { validateBaselineArtifactLocation } = require('../publish-baseline');

describe('candidate publisher arguments', () => {
  it('accepts only site, artifact and dry-run', () => {
    assert.deepEqual(
      parseArgs(['--site', 'MLB', '--artifact', 'woocommerce-mercadopago.zip', '--dry-run']),
      { site: 'MLB', artifact: 'woocommerce-mercadopago.zip', dryRun: true }
    );
  });

  it('rejects attempts to select homol from the CLI', () => {
    assert.throws(
      () => parseArgs(['--site', 'MLB', '--artifact', 'plugin.zip', '--environment', 'homol']),
      /nao suportado/
    );
  });

  it('accepts and normalizes an expected artifact version', () => {
    assert.deepEqual(
      parseArgs([
        '--site', 'MLB',
        '--artifact', 'woocommerce-mercadopago.zip',
        '--expected-version', 'v8.9.4',
      ]),
      {
        site: 'MLB',
        artifact: 'woocommerce-mercadopago.zip',
        expectedVersion: '8.9.4',
        dryRun: false,
      }
    );
  });

  it('requires the expected version when publishing a production baseline', () => {
    assert.throws(
      () => parseArgs(
        ['--site', 'MLB', '--artifact', 'plugin.zip'],
        { expectedVersionRequired: true }
      ),
      /expected-version e obrigatorio/
    );
  });

  it('rejects injected or malformed versions', () => {
    assert.throws(() => normalizeVersion('8.9.4;touch /tmp/x'), /invalida/);
    assert.throws(() => normalizeVersion('../8.9.4'), /invalida/);
  });

  it('rejects ZIP bombs by entry count or uncompressed size', () => {
    assert.throws(
      () => validateZipSummary('10001 files, 1 bytes uncompressed, 1 bytes compressed: 0.0%'),
      /excede o limite/
    );
    assert.throws(
      () => validateZipSummary('1 file, 524288001 bytes uncompressed, 1 bytes compressed: 100.0%'),
      /excede o limite/
    );
  });

  it('restricts the production publisher to the official artifact cache', () => {
    assert.doesNotThrow(() => validateBaselineArtifactLocation(
      { artifactPath: `${process.cwd()}/results/artifacts/woocommerce-mercadopago.8.9.3.zip` },
      { expectedVersion: '8.9.3' }
    ));
    assert.throws(() => validateBaselineArtifactLocation(
      { artifactPath: `${process.cwd()}/../woocommerce-mercadopago.zip` },
      { expectedVersion: '8.9.3' }
    ), /ZIP oficial/);
  });
});
