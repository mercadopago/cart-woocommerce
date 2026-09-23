const assert = require('node:assert/strict');
const { describe, it } = require('node:test');

function response(status, { body = '', headers = {} } = {}) {
  const normalized = new Map(Object.entries(headers).map(([key, value]) => [key.toLowerCase(), value]));
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: { get: (name) => normalized.get(name.toLowerCase()) || null },
    text: async () => body,
  };
}

describe('external store site-id fetch', () => {
  it('reads identity from /checkout/ and follows only same-origin redirects', async () => {
    const { fetchStoreSiteId } = await import('../helpers/fetch-site-id.mjs');
    const requested = [];
    const siteId = await fetchStoreSiteId('https://developer-prod.ppolimpo.io', {
      fetchImpl: async (url, options) => {
        requested.push({ options, url: url.toString() });
        if (requested.length === 1) {
          return response(302, { headers: { location: '/cart/' } });
        }
        return response(200, { body: '<script>window.mp={"site_id":"MLB"}</script>' });
      },
    });

    assert.equal(siteId, 'MLB');
    assert.deepEqual(requested.map(({ url }) => url), [
      'https://developer-prod.ppolimpo.io/checkout/',
      'https://developer-prod.ppolimpo.io/cart/',
    ]);
    assert.equal(requested[0].options.redirect, 'manual');
  });

  it('blocks non-allowlisted destinations before fetch', async () => {
    const { fetchStoreSiteId } = await import('../helpers/fetch-site-id.mjs');
    let calls = 0;
    await assert.rejects(
      fetchStoreSiteId('https://example.com', { fetchImpl: async () => { calls += 1; } }),
      /allowlist/
    );
    assert.equal(calls, 0);
  });

  it('blocks cross-origin redirects before issuing the redirected request', async () => {
    const { fetchStoreSiteId } = await import('../helpers/fetch-site-id.mjs');
    let calls = 0;
    await assert.rejects(
      fetchStoreSiteId('https://developer-prod.ppolimpo.io', {
        fetchImpl: async () => {
          calls += 1;
          return response(302, { headers: { location: 'https://attacker.example/collect' } });
        },
      }),
      /cross-origin/
    );
    assert.equal(calls, 1);
  });

  it('blocks credentials injected by a same-origin redirect', async () => {
    const { fetchStoreSiteId } = await import('../helpers/fetch-site-id.mjs');
    await assert.rejects(
      fetchStoreSiteId('https://developer-prod.ppolimpo.io', {
        fetchImpl: async () => response(302, {
          headers: { location: 'https://user:password@developer-prod.ppolimpo.io/checkout/' },
        }),
      }),
      /credenciais/
    );
  });

  it('fails hard when the checkout does not expose a site_id', async () => {
    const { fetchStoreSiteId } = await import('../helpers/fetch-site-id.mjs');
    await assert.rejects(
      fetchStoreSiteId('https://developer-prod.ppolimpo.io', {
        fetchImpl: async () => response(200, { body: '<html>checkout</html>' }),
      }),
      /site_id ausente/
    );
  });
});
