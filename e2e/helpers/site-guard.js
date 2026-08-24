const { execFileSync } = require('child_process');
const path = require('path');
const { wpGetOption } = require('./wp-env');

const FETCH_SITE_ID_SCRIPT = path.resolve(__dirname, 'fetch-site-id.mjs');
const httpSiteIdCache = new Map();

// HTTP fallback for external stores where the local docker wp-cli can't reach the store.
// Memoized per URL — detection runs once per process, not once per beforeEach.
function fetchStoreSiteIdViaHttp(shopUrl) {
  if (!shopUrl) return '';
  if (httpSiteIdCache.has(shopUrl)) return httpSiteIdCache.get(shopUrl);
  let siteId = '';
  try {
    siteId = execFileSync('node', [FETCH_SITE_ID_SCRIPT, shopUrl], { encoding: 'utf-8', timeout: 20000 })
      .trim()
      .toUpperCase();
  } catch {
    siteId = '';
  }
  httpSiteIdCache.set(shopUrl, siteId);
  return siteId;
}

function getStoreSiteId() {
  const shopUrl = process.env.SHOP_URL || '';
  // External store (run-e2e.sh sets WP_EXTERNAL_STORE=1): the store under test is at SHOP_URL, not
  // the local docker container. Read site_id over HTTP — the local wp-cli would report the local
  // store's site_id whenever mp-wc-dev happens to be running, skipping/selecting country specs
  // against the wrong store.
  if (process.env.WP_EXTERNAL_STORE === '1') {
    return fetchStoreSiteIdViaHttp(shopUrl);
  }
  const fromWpCli = (wpGetOption('_site_id_v1') || '').toUpperCase();
  if (fromWpCli) return fromWpCli;
  return fetchStoreSiteIdViaHttp(shopUrl);
}

function skipIfNotSite(test, expectedSiteId) {
  test.skip(getStoreSiteId() !== expectedSiteId.toUpperCase(), `Store is not ${expectedSiteId} — skipping`);
}

module.exports = { getStoreSiteId, skipIfNotSite };
