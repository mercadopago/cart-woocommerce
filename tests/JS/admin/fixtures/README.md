# Admin unit-test fixtures

`jquery-3.7.1.min.js` is the unchanged jQuery 3.7.1 distributed by WordPress in
`/usr/src/wordpress/wp-includes/js/jquery/jquery.min.js`. Its MIT license notice
is preserved at the top of the file. Upstream: https://jquery.com/ and
https://jquery.org/license/.

Provenance: the locally cached `docker-flexible-environment-wordpress:latest` image,
image ID `sha256:068fbc31cc614b361a4c06ebcbbcf59fc715f14dfd59bc0e36eb07ac28011650`.
Size: 87553 bytes. SHA-256:
`cb6f2d32c49d1c2b25e9ffc9aaafa3f83075346c01bcd4ae6eb187392a4292cf`.

The fixture runs real selectors, events/delegation and Deferred callbacks against
jsdom; only `jQuery.post` is intercepted as the HTTP boundary. No package/lockfile
or runtime dependency is added. `tests/` is outside Jest's production coverage
include set and the plugin ZIP's `package_items`. The public source mirror may
include tests, so keep the upstream license and provenance with this file.

Markup in the suites represents `templates/admin/settings/`,
`templates/admin/components/credits-info-example.php`,
`templates/admin/order/payment-status-metabox-content.php` and WooCommerce's
gateway settings table. Conditional absence is tested where supported by the
source. Existing product defects involving incomplete DOM are diagnosed separately
instead of inventing missing controls to conceal them.

The owned fixture builders use `createElement`, `createTextNode`, `appendChild`
and `replaceChildren`; strings remain text instead of passing through an HTML
insertion API. Static attributes, element order and whitespace text nodes are
retained so the real handlers still traverse the same DOM. The licensed jQuery
fixture remains unchanged.

The local helper accepts `PSW3844_ADMIN_SOURCE_ROOT` only to run the same suites
against temporary copies for the controlled regression proof. Normal Jest and CI
resolve the real repository assets and execute every case against both the original
`.js` and shipped `.min.js`. A stale/corrupt minified behavior cannot pass merely because
the original source passed. This protects the asserted contracts, without claiming
exhaustive source/minified equivalence. Browser navigation is observed through a
boundary double because jsdom does not implement full navigation/reload.
