/**
 * Testes de integração do CLI bin/verify-integrity.js (PPSP-1529).
 *
 * Executa o binário como subprocesso (Node real, sem mocks) para cobrir o
 * caminho main() — o contrato de exit code (0/1) e as mensagens consumidos
 * pelo GitHub Actions e pelo create-release-zip.sh — com fixtures em disco.
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { spawnSync } = require('child_process');

const BIN = path.resolve(__dirname, '../../../bin/verify-integrity.js');
const hashOf = (content) => crypto.createHash('sha256').update(content).digest('hex');

const ASSET_JS = 'assets/js/checkouts/mp-plugins-components.min.js';
const ASSET_CSS = 'assets/css/checkouts/mp-plugins-components.min.css';
const CONTENT_JS = 'console.log("mp-plugins-components");';
const CONTENT_CSS = '.mp-checkout{color:#009ee3}';

let root;

/** Escreve um arquivo (criando diretórios) sob o root de fixtures. */
function writeAsset (relativePath, content) {
  const absolutePath = path.join(root, relativePath);
  fs.mkdirSync(path.dirname(absolutePath), { recursive: true });
  fs.writeFileSync(absolutePath, content);
}

/** Escreve o integrity-manifest.json (mapa path->sha256) no root. */
function writeManifest (manifest) {
  fs.writeFileSync(path.join(root, 'integrity-manifest.json'), JSON.stringify(manifest, null, 2));
}

/** Roda o binário contra o root de fixtures; retorna { status, stdout, stderr }. */
function run () {
  return spawnSync(
    process.execPath,
    [BIN, '--root', root, '--manifest', path.join(root, 'integrity-manifest.json')],
    { encoding: 'utf8' }
  );
}

describe('verify-integrity CLI — exit codes e mensagens (PPSP-1529)', () => {
  beforeEach(() => {
    root = fs.mkdtempSync(path.join(os.tmpdir(), 'verify-cli-'));
  });

  afterEach(() => {
    fs.rmSync(root, { recursive: true, force: true });
  });

  test('exit 0 quando o pacote está íntegro', () => {
    writeAsset(ASSET_JS, CONTENT_JS);
    writeAsset(ASSET_CSS, CONTENT_CSS);
    writeManifest({ [ASSET_JS]: hashOf(CONTENT_JS), [ASSET_CSS]: hashOf(CONTENT_CSS) });

    const { status, stdout } = run();

    expect(status).toBe(0);
    expect(stdout).toContain('OK: 2 assets');
  });

  test('exit 1 e lista o arquivo quando um asset está ausente', () => {
    writeAsset(ASSET_CSS, CONTENT_CSS); // ASSET_JS não é criado
    writeManifest({ [ASSET_JS]: hashOf(CONTENT_JS), [ASSET_CSS]: hashOf(CONTENT_CSS) });

    const { status, stderr } = run();

    expect(status).toBe(1);
    expect(stderr).toContain('AUSENTES');
    expect(stderr).toContain(ASSET_JS);
  });

  test('exit 1 quando um asset tem hash divergente', () => {
    writeAsset(ASSET_JS, CONTENT_JS + '/*tampered*/');
    writeManifest({ [ASSET_JS]: hashOf(CONTENT_JS) });

    const { status, stderr } = run();

    expect(status).toBe(1);
    expect(stderr).toContain('DIVERGENTE');
    expect(stderr).toContain(ASSET_JS);
  });

  test('exit 1 quando existe um .min órfão fora do manifest', () => {
    writeAsset(ASSET_JS, CONTENT_JS);
    writeAsset('assets/js/checkouts/orphan.min.js', 'console.log("orphan");');
    writeManifest({ [ASSET_JS]: hashOf(CONTENT_JS) }); // orphan.min.js não listado

    const { status, stderr } = run();

    expect(status).toBe(1);
    expect(stderr).toContain('FORA do manifest');
    expect(stderr).toContain('assets/js/checkouts/orphan.min.js');
  });

  test('exit 1 quando o manifest está ausente', () => {
    writeAsset(ASSET_JS, CONTENT_JS); // sem manifest

    const { status, stderr } = run();

    expect(status).toBe(1);
    expect(stderr).toContain('não encontrado');
  });
});
