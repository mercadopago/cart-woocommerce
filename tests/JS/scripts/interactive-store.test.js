const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const SCRIPT = path.resolve(__dirname, '../../../docker-flexible-environment/setup-interactive.sh');

function chooseCurrentWordPress ({ marker, installedVersion }) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'mp-dev-interactive-'));
  const source = [
    'set -euo pipefail',
    'MP_DEV_SCRIPT_DIR="$2"',
    'source "$1"',
    'printf "%s\\n" "$3" > "$MP_DEV_SCRIPT_DIR/.current-site"',
    'mock_wordpress_version="$4"',
    'docker() { printf "%s\\n" "$mock_wordpress_version"; }',
    'detect_current_store',
    'read_choice() { CHOICE="1"; }',
    'choose_wordpress',
    'ACTION=up',
    'SITE_VALUE=mlb',
    'PHP_VALUE=8.2',
    'THEME_VALUE=storefront',
    'reset=false',
    'if store_requires_reset; then reset=true; fi',
    'printf "RESULT:%s|%s|%s|%s|%s\\n" "$WORDPRESS_VALUE" "$WORDPRESS_PRESERVES_DATA" "$reset" "$CURRENT_WORDPRESS_INSTALLED" "$CURRENT_STORE_MARKER_UNKNOWN"',
  ].join('\n');

  try {
    const result = spawnSync('bash', ['-c', source, 'interactive-store-test', SCRIPT, root, marker, installedVersion], {
      encoding: 'utf8',
    });

    expect(result.status).toBe(0);
    return result.stdout;
  } finally {
    fs.rmSync(root, { recursive: true, force: true });
  }
}

function showOrderPaySummary () {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'mp-dev-interactive-'));
  const capture = path.join(root, 'make-called');
  const source = [
    'set -euo pipefail',
    'MP_DEV_SCRIPT_DIR="$2"',
    'source "$1"',
    'capture_file="$3"',
    'make() { printf "called" > "$capture_file"; printf "http://localhost:8080/checkout/order-pay/1/?key=fixture"; }',
    'show_store_ready_art() { :; }',
    'CREATE_ORDER_PAY_VALUE=true',
    'CHECKOUT_MODE_VALUE=blocks',
    'SITE_VALUE=mlb',
    'WORDPRESS_VALUE=latest',
    'PHP_VALUE=8.2',
    'THEME_VALUE=storefront',
    'MP_MODE_VALUE=test',
    'show_summary',
  ].join('\n');

  try {
    const result = spawnSync('bash', ['-c', source, 'interactive-store-test', SCRIPT, root, capture], {
      encoding: 'utf8',
    });

    expect(result.status).toBe(0);
    return { output: result.stdout, makeWasCalled: fs.existsSync(capture) };
  } finally {
    fs.rmSync(root, { recursive: true, force: true });
  }
}

function keepExtendedStore ({ marker, installedVersion }) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'mp-dev-interactive-'));
  const capture = path.join(root, 'make-calls');
  const source = [
    'set -euo pipefail',
    'MP_DEV_SCRIPT_DIR="$2"',
    'source "$1"',
    'printf "%s\\n" "$3" > "$MP_DEV_SCRIPT_DIR/.current-site"',
    'mock_wordpress_version="$4"',
    'capture_file="$5"',
    'docker() { printf "%s\\n" "$mock_wordpress_version"; }',
    'detect_current_store',
    'read_choice() { CHOICE="1"; }',
    'choose_wordpress',
    'ACTION=up',
    'SITE_VALUE=mlb',
    'PHP_VALUE=8.2',
    'THEME_VALUE=storefront',
    'MAKE_ARGS=("PORT=8080")',
    'make() { printf "%s|%s\\n" "$(cat .current-site)" "$*" >> "$capture_file"; }',
    'run_with_progress',
  ].join('\n');

  try {
    const result = spawnSync('bash', ['-c', source, 'interactive-store-test', SCRIPT, root, marker, installedVersion, capture], {
      encoding: 'utf8',
    });

    if (result.status !== 0) {
      throw new Error(result.stderr || result.stdout);
    }
    return fs.readFileSync(capture, 'utf8');
  } finally {
    fs.rmSync(root, { recursive: true, force: true });
  }
}

describe('interactive local store — WordPress selector lifecycle (PSW-4444)', () => {
  test('preserva uma loja marcada como latest mesmo com core concreto instalado', () => {
    const output = chooseCurrentWordPress({
      marker: 'mlb-wplatest-php8.2-storefront',
      installedVersion: '6.8.2',
    });

    expect(output).toContain('Manter configuração atual: latest (instalado: 6.8.2)');
    expect(output).toContain('RESULT:latest|true|false|6.8.2');
  });

  test('trata o marcador legado como latest ao preservar a loja', () => {
    const output = chooseCurrentWordPress({
      marker: 'mlb-php8.2-storefront',
      installedVersion: '6.8.2',
    });

    expect(output).toContain('Manter configuração atual: latest (instalado: 6.8.2)');
    expect(output).toContain('RESULT:latest|true|false|6.8.2');
  });

  test('preserva um seletor X.Y mesmo quando o core informa a versão X.Y.Z', () => {
    const output = chooseCurrentWordPress({
      marker: 'mlb-wp6.8-php8.2-storefront',
      installedVersion: '6.8.2',
    });

    expect(output).toContain('Manter configuração atual: 6.8 (instalado: 6.8.2)');
    expect(output).toContain('RESULT:6.8|true|false|6.8.2');
  });

  test('retains an extended marker when preserving an explicitly versioned store', () => {
    const marker = 'mlb-wp6.8-php8.2-storefront-mptest-orderpaydatafalse-checkoutblocks';
    const output = keepExtendedStore({ marker, installedVersion: '6.8.2' });

    expect(output).toContain(`${marker}|up`);
  });

  test('requires confirmation when the current store marker is unknown', () => {
    const output = chooseCurrentWordPress({
      marker: 'unsupported-store-marker',
      installedVersion: '6.8.2',
    });

    expect(output).toContain('RESULT:6.8.2|false|true|6.8.2|true');
  });

  test('does not reveal or fetch the Order Pay URL in the interactive summary', () => {
    const { output, makeWasCalled } = showOrderPaySummary();

    expect(makeWasCalled).toBe(false);
    expect(output).toContain('Order Pay: Execute make order-pay-url para consultar');
    expect(output).not.toContain('key=fixture');
  });
});
