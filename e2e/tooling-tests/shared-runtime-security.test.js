const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it } = require('node:test');

const root = path.resolve(__dirname, '..', '..');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

describe('shared WordPress runtime security', () => {
  it('disables and removes the public WordPress debug log on every boot', () => {
    const entrypoint = read('docker-flexible-environment/entrypoint.sh');
    assert.match(entrypoint, /run_wp_config delete WP_DEBUG --type=constant/);
    assert.match(entrypoint, /run_wp_config set WP_DEBUG false --raw --type=constant/);
    assert.match(entrypoint, /run_wp_config delete WP_DEBUG_LOG --type=constant/);
    assert.match(entrypoint, /run_wp_config set WP_DEBUG_LOG false --raw --type=constant/);
    assert.match(entrypoint, /rm -f \/var\/www\/html\/wp-content\/debug\.log/);
    assert.equal(entrypoint.match(/^\s*harden_shared_runtime$/gm)?.length, 2);
  });

  it('temporarily grants the shared WP-CLI ownership of legacy wp-config and locks it back down', () => {
    const entrypoint = read('docker-flexible-environment/entrypoint.sh');
    assert.match(entrypoint, /run_wp_config\(\)[\s\S]*chown www-data:www-data \/var\/www\/html\/wp-config\.php/);
    assert.match(
      entrypoint,
      /trap 'chown root:www-data \/var\/www\/html\/wp-config\.php; chmod 640 \/var\/www\/html\/wp-config\.php' EXIT/
    );
    assert.match(entrypoint, /run_wp_config set WP_DEBUG false --raw --type=constant/);
    assert.match(entrypoint, /run_wp_config set MP_AUTOMATIC_PAYMENTS_BASE_PATH/);
  });

  it('publishes without writing inside a container and inspects as www-data', () => {
    const publisher = read('e2e/publish-candidate.js');
    assert.doesNotMatch(publisher, /wp --allow-root/);
    // Lanes are bind-mounted read-only and the plugin path is a symlink into them. A WordPress
    // installer run through that path would delete the release directory shared by all seven
    // countries, so publishing must stay a symlink repoint on the host.
    assert.doesNotMatch(publisher, /plugin install/);
    assert.doesNotMatch(publisher, /docker cp/);
    assert.match(publisher, /mv -T "\$temporary_link"/);
    assert.match(publisher, /docker exec --user www-data[^\n]*plugin get/);
    assert.match(publisher, /docker exec --user www-data[^\n]*plugin activate/);
  });

  it('routes container setup through the shared-aware WP-CLI wrapper', () => {
    for (const relativePath of [
      'docker-flexible-environment/entrypoint.sh',
      'docker-flexible-environment/setup-store.sh',
      'docker-flexible-environment/configure-interactive-store.sh',
    ]) {
      const script = read(relativePath);
      assert.match(script, /\/wp-cli\.sh/);
      assert.doesNotMatch(script, /wp --allow-root/);
    }

    const dockerignore = read('docker-flexible-environment/.dockerignore');
    assert.match(dockerignore, /^!wp-cli\.sh$/m);
    assert.match(dockerignore, /^!configure-interactive-store\.sh$/m);
  });

  it('keeps @serial-store excluded from shared reruns', () => {
    const runner = read('e2e/run-all-report.sh');
    assert.match(
      runner,
      /REMOTE_MAIN_ONLY[^\n]+[\s\S]*playwright_args\+=\("--grep-invert" "@serial-store" "--workers=1"\)/
    );
  });

  it('promotes the non-serial JSON when shared runs intentionally omit the serial phase', () => {
    const runner = read('e2e/run-all-report.sh');
    assert.match(
      runner,
      /if \[ -f "\$nonserial_json" \] && \[ -f "\$serial_json" \]; then[\s\S]*elif \[ -f "\$nonserial_json" \]; then mv "\$nonserial_json" "\$json_file"/
    );
  });

  it('synchronizes the remote locale only inside the shared environment guard', () => {
    const entrypoint = read('docker-flexible-environment/entrypoint.sh');
    assert.match(
      entrypoint,
      /if \[ "\$\{E2E_SHARED_ENVIRONMENT:-0\}" = "1" \]; then[\s\S]*\/install-woocommerce-locale\.sh "\$CURRENT_LOCALE"; then/
    );
    assert.equal(entrypoint.match(/\/install-woocommerce-locale\.sh/g)?.length, 1);
  });

  it('rebuilds every shared WordPress pair before recreating it', () => {
    const deployMakefile = read('docker-flexible-environment/deploy/Makefile');
    assert.match(
      deployMakefile,
      /\$\(SHARED_COMPOSE\) up -d --build --force-recreate caddy-e2e/
    );
    assert.match(
      deployMakefile,
      /\$\(SHARED_COMPOSE\) up -d --build "wp-staging-\$\$site" "wp-homol-\$\$site"/
    );
    assert.match(
      deployMakefile,
      /shared-recreate:[^\n]*shared-artifacts-check/
    );
  });
});
