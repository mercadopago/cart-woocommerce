const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it } = require('node:test');

const caddyfile = fs.readFileSync(
  path.resolve(__dirname, '..', '..', 'docker-flexible-environment', 'deploy', 'Caddyfile.e2e'),
  'utf8'
);
const liveCheck = fs.readFileSync(
  path.resolve(__dirname, '..', '..', 'docker-flexible-environment', 'deploy', 'test-shared-caddy-live.sh'),
  'utf8'
);

describe('shared Caddy boundary', () => {
  it('blocks WordPress admin PATH_INFO variants after preserving admin-ajax', () => {
    const ajaxHandler = caddyfile.indexOf('handle /wp-admin/admin-ajax.php');
    const privateMatcher = caddyfile.indexOf('@private_admin path');
    assert.ok(ajaxHandler >= 0 && ajaxHandler < privateMatcher);
    assert.match(caddyfile, /@private_admin path[^\n]*\/wp-login\.php\/\*/);
    assert.match(caddyfile, /@private_admin path[^\n]*\/xmlrpc\.php\/\*/);
    assert.match(caddyfile, /@private_admin path[^\n]*\/wp-content\/debug\.log(?:\s|$)/);
    assert.match(caddyfile, /@private_admin path[^\n]*\/wp-content\/debug\.log\/\*/);
  });

  it('probes the exact admin path and accepts only expected admin-ajax statuses', () => {
    assert.match(liveCheck, /^\s*\/wp-admin$/m);
    assert.match(liveCheck, /case "\$ajax_status" in 2\?\?\|3\?\?\|400\)/);
  });
});
