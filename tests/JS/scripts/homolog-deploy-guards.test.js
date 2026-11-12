const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const SCRIPT = path.resolve(
  __dirname,
  '../../../docker-flexible-environment/deploy/deploy.sh'
);

describe('homolog deployment guards', () => {
  let fakeBin;

  beforeEach(() => {
    fakeBin = fs.mkdtempSync(path.join(os.tmpdir(), 'mp-homolog-bin-'));
    const smooth = path.join(fakeBin, 'smooth');
    fs.writeFileSync(smooth, '#!/usr/bin/env bash\nexit 0\n');
    fs.chmodSync(smooth, 0o755);
  });

  afterEach(() => {
    fs.rmSync(fakeBin, { recursive: true, force: true });
  });

  function runDeploy (...args) {
    return spawnSync('bash', [SCRIPT, ...args], {
      encoding: 'utf8',
      env: {
        ...process.env,
        PATH: `${fakeBin}:${process.env.PATH}`,
        SMOOTH_USER: 'synthetic-reviewer',
        HOMOLOG_INSTANCE: 'synthetic-instance',
      },
    });
  }

  test.each(['down', 'destroy', 'config-site', 'prepare-zip'])(
    'rejects the unmanaged prod store for %s',
    (command) => {
      const extraArgs = command === 'config-site' ? ['MP_TEST', 'true'] : [];
      const result = runDeploy(command, 'prod', ...extraArgs);

      expect(result.status).not.toBe(0);
      expect(result.stderr).toContain("site inválido: 'prod'");
    }
  );

  test('blocks a production clone before any remote operation', () => {
    const result = runDeploy('clone', 'prod', 'homolg');

    expect(result.status).not.toBe(0);
    expect(result.stderr).toContain('clone de fonte de produção bloqueado');
  });
});
