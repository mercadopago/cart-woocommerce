const { execFileSync } = require('child_process');
const path = require('path');
const { wpGetOption } = require('./wp-env');
const { getRuntimeTargetFromEnvironment, VALID_SITES } = require('./environment-config');

const FETCH_SITE_ID_SCRIPT = path.resolve(__dirname, 'fetch-site-id.mjs');
const httpSiteIdCache = new Map();

// HTTP fallback for external stores where the local docker wp-cli can't reach the store.
// Memoized per URL — detection runs once per process, not once per beforeEach.
function fetchStoreSiteIdViaHttp(shopUrl) {
  if (!shopUrl) throw new Error('[E2E] SHOP_URL obrigatoria para detectar o site_id.');
  if (httpSiteIdCache.has(shopUrl)) return httpSiteIdCache.get(shopUrl);
  let siteId;
  try {
    siteId = execFileSync('node', [FETCH_SITE_ID_SCRIPT, shopUrl], { encoding: 'utf-8', timeout: 20000 })
      .trim()
      .toUpperCase();
  } catch {
    throw new Error('[E2E] Nao foi possivel confirmar o site_id da loja externa.');
  }
  if (!VALID_SITES.has(siteId)) {
    throw new Error('[E2E] site_id ausente ou invalido na loja externa.');
  }
  httpSiteIdCache.set(shopUrl, siteId);
  return siteId;
}

function isSharedLaneEnvironment(environment = process.env) {
  return environment.WP_EXTERNAL_STORE === '1'
    && environment.E2E_REMOTE_MAIN_ONLY === '1'
    && ['staging', 'homol'].includes(String(environment.E2E_ENVIRONMENT || '').toLowerCase())
    && ['classic', 'blocks'].includes(String(environment.CHECKOUT || '').toLowerCase());
}

function getStoreSiteId(environment = process.env, dependencies = {}) {
  const getRuntimeTarget = dependencies.getRuntimeTargetFromEnvironment
    || getRuntimeTargetFromEnvironment;
  const getWpOption = dependencies.wpGetOption || wpGetOption;
  const fetchSiteId = dependencies.fetchStoreSiteIdViaHttp || fetchStoreSiteIdViaHttp;

  // global-setup validates this exact allowlisted target and its site_id over HTTP before any test
  // starts. Reuse that immutable target here instead of spawning one extra HTTP detector per worker;
  // an unavailable/mismatched store already fails the preflight rather than becoming a false skip.
  if (isSharedLaneEnvironment(environment)) {
    return getRuntimeTarget().site;
  }
  // Personal remote stores (including Super Token) use WP_EXTERNAL_STORE too, but do not carry the
  // immutable staging/homol tuple. Keep their historical HTTP identity detection and never invoke
  // the local WP-CLI fallback against the wrong Docker store.
  if (environment.WP_EXTERNAL_STORE === '1') {
    return fetchSiteId(environment.SHOP_URL || '');
  }

  const fromWpCli = (getWpOption('_site_id_v1') || '').toUpperCase();
  if (fromWpCli) return fromWpCli;
  return fetchSiteId(environment.SHOP_URL || '');
}

function skipIfNotSite(test, expectedSiteId) {
  test.skip(getStoreSiteId() !== expectedSiteId.toUpperCase(), `Store is not ${expectedSiteId} — skipping`);
}

module.exports = { getStoreSiteId, isSharedLaneEnvironment, skipIfNotSite };
