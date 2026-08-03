/**
 * Testes do gate de integridade dos assets (PPSP-1529) — bin/verify-integrity.js.
 *
 * Follow-up do postmortem da v8.7.23: garante que o verificador bloqueia a
 * release quando um asset do integrity-manifest.json está ausente ou divergente.
 */

jest.mock('fs');

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const { verifyIntegrity, parseArgs, main } = require('../../../bin/verify-integrity.js');

const hashOf = (content) => crypto.createHash('sha256').update(content).digest('hex');

// Os dois assets que sumiram na v8.7.23.
const ASSET_JS = 'assets/js/checkouts/mp-plugins-components.min.js';
const ASSET_CSS = 'assets/css/checkouts/mp-plugins-components.min.css';
const CONTENT_JS = 'console.log("mp-plugins-components");';
const CONTENT_CSS = '.mp-checkout{color:#009ee3}';

/**
 * Configura os mocks de fs para um "filesystem" virtual.
 *
 * @param {Object<string,string>} manifest  mapa path->hash servido como integrity-manifest.json
 * @param {Object<string,string>} present   mapa relativePath->conteúdo dos arquivos que existem
 */
function mockFs (manifest, present) {
  const manifestJson = JSON.stringify(manifest);

  fs.existsSync.mockImplementation((p) => {
    const s = String(p);
    if (s.endsWith('integrity-manifest.json')) {
      return true;
    }
    return Object.keys(present).some((rel) => s.endsWith(rel));
  });

  fs.statSync.mockImplementation(() => ({ isFile: () => true }));

  fs.readFileSync.mockImplementation((p) => {
    const s = String(p);
    if (s.endsWith('integrity-manifest.json')) {
      return manifestJson;
    }
    const rel = Object.keys(present).find((r) => s.endsWith(r));
    if (rel) {
      return Buffer.from(present[rel]);
    }
    throw new Error(`ENOENT: ${s}`);
  });
}

describe('verify-integrity — gate de integridade dos assets (PPSP-1529)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  test('ok quando todos os assets estão presentes e com hash correto', () => {
    const manifest = { [ASSET_JS]: hashOf(CONTENT_JS), [ASSET_CSS]: hashOf(CONTENT_CSS) };
    mockFs(manifest, { [ASSET_JS]: CONTENT_JS, [ASSET_CSS]: CONTENT_CSS });

    const result = verifyIntegrity();

    expect(result.ok).toBe(true);
    expect(result.checked).toBe(2);
    expect(result.missing).toEqual([]);
    expect(result.mismatched).toEqual([]);
  });

  test('reporta arquivo AUSENTE (cenário do incidente v8.7.23)', () => {
    const manifest = { [ASSET_JS]: hashOf(CONTENT_JS), [ASSET_CSS]: hashOf(CONTENT_CSS) };
    mockFs(manifest, { [ASSET_CSS]: CONTENT_CSS }); // .min.js ausente

    const result = verifyIntegrity();

    expect(result.ok).toBe(false);
    expect(result.missing).toContain(ASSET_JS);
    expect(result.mismatched).toEqual([]);
  });

  test('reporta hash DIVERGENTE', () => {
    const manifest = { [ASSET_JS]: hashOf(CONTENT_JS) };
    mockFs(manifest, { [ASSET_JS]: 'conteudo-adulterado' });

    const result = verifyIntegrity();

    expect(result.ok).toBe(false);
    expect(result.mismatched).toContain(ASSET_JS);
    expect(result.missing).toEqual([]);
  });

  test('reporta .min órfão presente no pacote mas ausente do manifest', () => {
    const manifest = { [ASSET_JS]: hashOf(CONTENT_JS) };
    const manifestJson = JSON.stringify(manifest);

    fs.existsSync.mockImplementation((p) => {
      const s = String(p);
      if (s.endsWith('integrity-manifest.json')) {
        return true;
      }
      if (s.endsWith(`${path.sep}assets`)) {
        return true; // o diretório assets/ existe
      }
      return s.endsWith(ASSET_JS); // o asset listado no manifest existe
    });
    fs.statSync.mockReturnValue({ isFile: () => true });
    fs.readFileSync.mockImplementation((p) => (
      String(p).endsWith('integrity-manifest.json') ? manifestJson : Buffer.from(CONTENT_JS)
    ));
    fs.readdirSync.mockReturnValue([
      path.join('js', 'checkouts', 'mp-plugins-components.min.js'),
      path.join('js', 'checkouts', 'orphan.min.js')
    ]);

    const result = verifyIntegrity();

    expect(result.ok).toBe(false);
    expect(result.orphans).toContain('assets/js/checkouts/orphan.min.js');
    expect(result.missing).toEqual([]);
    expect(result.mismatched).toEqual([]);
  });

  test('trata caminho existente que não é arquivo como ausente', () => {
    const manifest = { [ASSET_JS]: hashOf(CONTENT_JS) };
    fs.existsSync.mockReturnValue(true);
    fs.statSync.mockImplementation((p) => ({
      isFile: () => !String(p).endsWith(ASSET_JS) // o asset é um "diretório"
    }));
    fs.readFileSync.mockImplementation((p) => {
      if (String(p).endsWith('integrity-manifest.json')) {
        return JSON.stringify(manifest);
      }
      return Buffer.from(CONTENT_JS);
    });

    const result = verifyIntegrity();

    expect(result.ok).toBe(false);
    expect(result.missing).toContain(ASSET_JS);
  });

  test('lança erro quando o manifest está ausente', () => {
    fs.existsSync.mockReturnValue(false);

    expect(() => verifyIntegrity()).toThrow(/não encontrado/);
  });

  test('lança erro quando o manifest é um JSON inválido', () => {
    fs.existsSync.mockImplementation((p) => String(p).endsWith('integrity-manifest.json'));
    fs.readFileSync.mockReturnValue('{ not json');

    expect(() => verifyIntegrity()).toThrow(/inválido/);
  });

  test('lança erro quando o manifest está vazio', () => {
    fs.existsSync.mockImplementation((p) => String(p).endsWith('integrity-manifest.json'));
    fs.readFileSync.mockReturnValue('{}');

    expect(() => verifyIntegrity()).toThrow(/vazio/);
  });

  test('resolve os assets sob o diretório root informado', () => {
    const manifest = { [ASSET_JS]: hashOf(CONTENT_JS) };
    mockFs(manifest, { [ASSET_JS]: CONTENT_JS });

    verifyIntegrity({ root: '/tmp/woocommerce-mercadopago' });

    const calledUnderRoot = fs.existsSync.mock.calls.some(
      (call) => String(call[0]).includes('/tmp/woocommerce-mercadopago')
    );
    expect(calledUnderRoot).toBe(true);
  });
});

describe('verify-integrity — main (contrato de exit code)', () => {
  let exitSpy;
  let logSpy;
  let errorSpy;
  const originalArgv = process.argv;

  beforeEach(() => {
    jest.clearAllMocks();
    // process.exit lança para interromper main() no ponto da saída (como o real faria).
    exitSpy = jest.spyOn(process, 'exit').mockImplementation((code) => {
      throw new Error(`__exit__${code}`);
    });
    logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
    errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    process.argv = ['node', 'verify-integrity.js'];
  });

  afterEach(() => {
    process.argv = originalArgv;
    exitSpy.mockRestore();
    logSpy.mockRestore();
    errorSpy.mockRestore();
  });

  /** Executa main() e devolve o código passado a process.exit. */
  const runMain = () => {
    try {
      main();
    } catch (error) {
      const match = /^__exit__(\d+)$/.exec(error.message);
      if (match) {
        return Number(match[1]);
      }
      throw error;
    }
    return undefined;
  };

  test('sai com 0 e loga OK quando o pacote está íntegro', () => {
    mockFs({ [ASSET_JS]: hashOf(CONTENT_JS) }, { [ASSET_JS]: CONTENT_JS });

    expect(runMain()).toBe(0);
    expect(logSpy).toHaveBeenCalledWith(expect.stringContaining('OK'));
  });

  test('sai com 1 e reporta os ausentes quando um asset falta', () => {
    mockFs({ [ASSET_JS]: hashOf(CONTENT_JS) }, {}); // asset ausente

    expect(runMain()).toBe(1);
    expect(errorSpy).toHaveBeenCalledWith(expect.stringContaining('AUSENTES'));
  });

  test('sai com 1 e reporta ERRO quando o manifest está ausente', () => {
    fs.existsSync.mockReturnValue(false);

    expect(runMain()).toBe(1);
    expect(errorSpy).toHaveBeenCalledWith(expect.stringContaining('ERRO'));
  });
});

describe('verify-integrity — parseArgs', () => {
  test('lê --root e --manifest na forma separada por espaço', () => {
    expect(parseArgs(['--root', '/tmp/pkg', '--manifest', '/repo/integrity-manifest.json']))
      .toEqual({ root: '/tmp/pkg', manifest: '/repo/integrity-manifest.json' });
  });

  test('lê --chave=valor', () => {
    expect(parseArgs(['--root=/tmp/pkg', '--manifest=/repo/m.json']))
      .toEqual({ root: '/tmp/pkg', manifest: '/repo/m.json' });
  });

  test('retorna objeto vazio sem argumentos', () => {
    expect(parseArgs([])).toEqual({});
  });
});
