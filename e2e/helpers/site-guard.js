const { wpGetOption } = require('./wp-env');

function getStoreSiteId() {
  return (wpGetOption('_site_id_v1') || '').toUpperCase();
}

function skipIfNotSite(test, expectedSiteId) {
  test.skip(getStoreSiteId() !== expectedSiteId.toUpperCase(), `Store is not ${expectedSiteId} — skipping`);
}

module.exports = { getStoreSiteId, skipIfNotSite };
