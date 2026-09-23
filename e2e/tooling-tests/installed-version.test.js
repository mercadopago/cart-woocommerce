const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const { verifyInstalledVersion } = require('../helpers/installed-version');

const config = {
  lock: {
    sshHost: 'shared.example.test',
    sshUser: 'developer',
  },
};
const target = {
  containerName: 'wp-staging-mlb',
  environment: 'staging',
  site: 'MLB',
};

describe('installed plugin version preflight', () => {
  it('checks the exact allowlisted container as www-data before a dual run', () => {
    let command;
    verifyInstalledVersion(config, target, '8.9.4', {
      sshOptions: ['-o', 'BatchMode=yes'],
      spawnSync: (...args) => {
        command = args;
        return { status: 0 };
      },
    });

    assert.equal(command[0], 'ssh');
    assert.deepEqual(command[1].slice(-5), [
      'sh', '-s', '--', 'wp-staging-mlb', '8.9.4',
    ]);
    assert.match(command[2].input, /docker exec --user www-data/);
    assert.match(command[2].input, /plugin get woocommerce-mercadopago --field=version/);
  });

  it('fails closed on a mismatched or unreadable installed version', () => {
    assert.throws(
      () => verifyInstalledVersion(config, target, '8.9.4', {
        sshOptions: [],
        spawnSync: () => ({ status: 1 }),
      }),
      /nao possui a versao esperada/
    );
  });

  it('rejects caller-controlled container names and malformed versions', () => {
    assert.throws(
      () => verifyInstalledVersion(config, { ...target, containerName: 'wp;id' }, '8.9.4'),
      /Parametros invalidos/
    );
    assert.throws(
      () => verifyInstalledVersion(config, target, '8.9.4;id'),
      /Parametros invalidos/
    );
  });
});
