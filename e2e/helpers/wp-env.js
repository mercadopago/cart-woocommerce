const { execSync } = require('child_process');
const fs = require('fs');

const CONTAINER = 'mp-wc-dev';

// Detect if running inside the Docker container (no docker CLI available)
// or on the host (docker CLI available). This allows the same helpers to
// work in both `make e2e` (host) and `make e2e-docker` (container) contexts.
const INSIDE_CONTAINER = fs.existsSync('/.dockerenv') || process.env.INSIDE_CONTAINER === '1';

function wpCli(command) {
  const cmd = INSIDE_CONTAINER
    ? `wp --allow-root --path=/var/www/html ${command}`
    : `docker exec ${CONTAINER} wp --allow-root ${command}`;
  try {
    return execSync(cmd, { encoding: 'utf-8', timeout: 15000, stdio: ['pipe', 'pipe', 'pipe'] }).trim();
  } catch (err) {
    if (process.env.E2E_DEBUG) {
      // eslint-disable-next-line no-console
      console.error(`[wp-cli] failed: ${cmd}`, err.stderr?.substring(0, 200));
    }
    return null;
  }
}

function wpOption(name, value) {
  return wpCli(`option update ${name} "${value}"`);
}

function wpGetOption(name) {
  return wpCli(`option get ${name}`);
}

function enableGateway(gatewayId) {
  return wpCli(`eval '$s = get_option("woocommerce_${gatewayId}_settings", []); $s["enabled"] = "yes"; update_option("woocommerce_${gatewayId}_settings", $s);'`);
}

function setGatewaySetting(gatewayId, key, value) {
  return wpCli(`eval '$s = get_option("woocommerce_${gatewayId}_settings", []); $s["${key}"] = "${value}"; update_option("woocommerce_${gatewayId}_settings", $s);'`);
}

function isContainerRunning() {
  if (INSIDE_CONTAINER) return true;
  try {
    const result = execSync(`docker ps --filter name=${CONTAINER} --format "{{.Names}}"`, { encoding: 'utf-8', timeout: 5000 }).trim();
    return result === CONTAINER;
  } catch {
    return false;
  }
}

function wpEval(php) {
  const cmd = INSIDE_CONTAINER
    ? `wp --allow-root --path=/var/www/html eval '${php}'`
    : `docker exec ${CONTAINER} wp --allow-root eval '${php}'`;
  try {
    return execSync(cmd, { encoding: 'utf-8', timeout: 15000, stdio: ['pipe', 'pipe', 'pipe'] }).trim();
  } catch (err) {
    if (process.env.E2E_DEBUG) {
      // eslint-disable-next-line no-console
      console.error(`[wp-cli] failed: ${cmd}`, err.stderr?.substring(0, 200));
    }
    return null;
  }
}

const path = require('path');

const DOCKER_DIR = path.resolve(__dirname, '..', '..', 'docker-flexible-environment');

function getCurrentStoreSite() {
  try {
    const flag = path.join(DOCKER_DIR, '.current-site');
    if (!fs.existsSync(flag)) return null;
    // File contains e.g. "mlb-php7.4"
    const content = fs.readFileSync(flag, 'utf-8').trim();
    return content.split('-')[0].toUpperCase();
  } catch {
    return null;
  }
}

function resetStore(site) {
  if (INSIDE_CONTAINER) {
    // Inside container: store is already running, no Docker reset possible.
    // The global-setup will configure it via wp-cli instead.
    // eslint-disable-next-line no-console
    console.log(`[E2E] Running inside container — skipping Docker reset (store already active)`);
    return true;
  }
  const siteLower = site.toLowerCase();
  // eslint-disable-next-line no-console
  console.log(`[E2E] Resetting Docker store for ${site}... (this takes ~30s)`);
  try {
    execSync(`make -C "${DOCKER_DIR}" reset SITE=${siteLower}`, {
      encoding: 'utf-8',
      timeout: 180000,
      stdio: ['pipe', 'pipe', 'pipe'],
    });
    return true;
  } catch (err) {
    // eslint-disable-next-line no-console
    console.error(`[E2E] Failed to reset store: ${err.stderr?.substring(0, 300) || err.message}`);
    return false;
  }
}

module.exports = { wpCli, wpOption, wpGetOption, wpEval, enableGateway, setGatewaySetting, isContainerRunning, getCurrentStoreSite, resetStore };
