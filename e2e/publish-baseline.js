#!/usr/bin/env node
const path = require('node:path');
const { runPublisher } = require('./publish-candidate');

function validateBaselineArtifactLocation(artifact, options) {
  const expectedPath = path.resolve(
    __dirname,
    'results',
    'artifacts',
    `woocommerce-mercadopago.${options.expectedVersion}.zip`
  );
  if (artifact.artifactPath !== expectedPath) {
    throw new Error('[E2E] A baseline deve usar o ZIP oficial obtido por e2e-shared-fetch-production.');
  }
}

function main() {
  runPublisher('homol', process.argv.slice(2), {
    expectedVersionRequired: true,
    scriptName: 'publish-baseline.js',
    validateArtifactLocation: validateBaselineArtifactLocation,
  });
}

if (require.main === module) {
  try {
    main();
  } catch (error) {
    const message = error.message && error.message.startsWith('[E2E]')
      ? error.message
      : '[E2E] Falha inesperada na publicacao da baseline.';
    process.stderr.write(`${message}\n`);
    process.exitCode = 1;
  }
}

module.exports = { validateBaselineArtifactLocation };
