# PHP diagnostic libraries

Run the focused suite from the plugin root:

```sh
vendor/bin/phpunit tests/Libraries --no-coverage
```

The tests execute the production `File`, `Remote` and `Datadog` methods. Remote
also executes the real `Requester`, `Store` and `Options`; File uses the real
store configuration path. Doubles stop only at WordPress functions, WooCommerce's
`WC_Logger` persistence boundary and the external `HttpClientInterface` port.
No request reaches a live store or monitoring endpoint.

## Inventory and scenarios

| Production class | Test | Verified behavior |
|---|---|---|
| `Logs\Transports\File` | `Logs/Transports/FileTest.php` | All five severity methods; exact message/source handoff; pretty Unicode JSON and scalar/default context; debug gates; logger persistence exception propagation |
| `Logs\Transports\Remote` | `Logs/Transports/RemoteTest.php` | All five severity methods; complete payload, route and JSON headers at the HTTP port; present/absent host and URI components; debug gates; HTTP exception containment after the real Requester reports the failure |
| `Metrics\Datadog` | `Metrics/DatadogTest.php` | Existing URL/team, payload, timeout and optional-field cases; absent WooCommerce version; method-only/details-only payloads; explicit details precedence; encoding and HTTP exceptions |

`Logs` only stores its transport dependencies and `LogLevels` only declares
constants. Their meaningful use is verified through the severity-specific
transport outputs; neither receives a separate constructor/constant test.

The debug matrix is intentional:

| Store debug setting | `WP_DEBUG` | Error | Warning / notice / info | Debug |
|---|---|---|---|---|
| `yes` | `true` | emitted | emitted | emitted |
| `yes` | `false` | emitted | emitted | suppressed |
| `no` | either | emitted | suppressed | suppressed |

The store setting requires the exact string `yes`. `WP_DEBUG` is immutable, so
File and Remote cases run in separate processes with global-state preservation
disabled. Remote restores its synthetic server and WooCommerce/plugin globals
in teardown. Fixtures contain no customer data, credentials or payment details.

## Scope and limits

- File delegates persistence to WooCommerce. Assertions verify the logger's
  method, message and source; they do not claim to test WooCommerce files or disk.
- File currently propagates a logger exception. Remote and Datadog absorb
  `Exception` from their send paths. The tests characterize that distinction;
  they do not add retries, fallbacks or error handling to production code.
- URI cases verify that Remote calls `wp_unslash` and `sanitize_text_field` for
  each present component and uses the returned values. WordPress sanitization
  itself is outside this unit suite.
- The HTTP port and WordPress HTTP functions are doubles. No live endpoint,
  network failure or real observability delivery is validated here.
- `phpunit.xml` currently excludes `src/Libraries` from its coverage include.
  These tests execute the libraries even while they are absent from that report.
  PSW-3830 owns their eventual inclusion; this ticket does not expand the include
  or claim global Libraries coverage before that dependency is incorporated.
- E2E was waived for PSW-3840 because its final diff changes only tests and docs.

## Assertion effectiveness (PSW-3840)

The original focused suite passed before and after a controlled mutation check
in an isolated source copy. All ten deliberately introduced production
regressions were detected by the new cases: File's error level, Unicode encoding
and WordPress debug gate; Remote's target, context, WordPress debug gate and
exception containment; Datadog's details precedence, missing-version fallback
and HTTP exception containment. Source hashes in the working branch stayed
unchanged. This verifies those particular assertions, rather than claiming a
complete mutation score for the libraries.
