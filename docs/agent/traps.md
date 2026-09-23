---
type: Traps
version: ced4a587
validated: 2026-09-17
update_when: when a non-obvious gotcha is discovered, add it here in the same PR
---

# Traps

Gotchas that aren't obvious from a quick read of the code, plus deliberate deviations from standard patterns that an agent applying team conventions would silently regress.

## Smooth domain metadata is not the DNS source of truth

The EC2 domain tag displayed by `smooth get-instances` can omit shared hostnames when its value is
near the tag-size limit. Do not infer DNS health from that comma-separated column. The shared gate
uses only the instance ID/name/current IPv4 from `smooth` and independently resolves the 14 hosts
declared in `e2e/config/environments.json`. It requires exactly the current IPv4: a stale address,
an extra A record or a lookup failure all block publication and regression. The check is read-only;
operators must apply the printed `smooth add-domain` commands explicitly and wait for DNS
propagation. Do not add direct Route 53 access or silently mutate DNS from the preflight.

## Shared E2E lanes publish by symlink repoint, never by `wp plugin install`

The seven countries of an environment share one artifacts lane. Each container bind-mounts the
**lane root** read-only at `/e2e-artifacts` and reaches the plugin through
`current/woocommerce-mercadopago`, a symlink into `releases/<version>-<sha16>`. Publishing is an
atomic `rename(2)` of that symlink on the host followed by `apache2ctl -k graceful` per container.

Three things follow, and each one has already broken a release:

- **Never run a WordPress installer against the plugin path.** It resolves into the read-only
  mount, so it fails with an opaque "could not copy files / inconsistent permissions". Were the
  mount writable it would be worse: `clear_destination` would delete the release directory shared
  by all seven countries. The read-only flag is load-bearing, not decoration.
- **Mount the lane root, not the resolved plugin directory.** Docker resolves a bind-mount source
  symlink at mount time, so mounting `current/woocommerce-mercadopago` pins the container to one
  release for its whole lifetime and a repoint only takes effect on recreate.
- **The lane root, `current` and `releases` must be world-traversable (`0755`).** `www-data` is
  uid 33 inside the container and has to walk that hierarchy. This did not matter while the
  resolved directory was mounted directly, because that bypasses the host hierarchy; with the lane
  root mounted, a `0700` lane boots WordPress with no gateway at all.

The graceful reload is not optional: the official `wordpress:` image enables opcache, and PHP
caches resolved symlinks for `realpath_cache_ttl` (120s). Without it the swap lands on disk while
Apache keeps serving the old release, which surfaces as an intermittent wrong-version run.
`e2e/tooling-tests/publish-release-swap.test.js` exercises the real script against a fake lane; it
needs GNU coreutils, so it self-skips off Linux and only truly runs on the shared host.

## Shared release publication is a compensating transaction

A release cannot be committed atomically across the homol and staging lanes. The canonical flow
publishes production first and, if candidate publication fails, repoints staging back to the same
verified production release before releasing the lease. Keep that compensation and its version
verification: merely aborting after the second publish fails leaves a mismatched pair that can
contaminate a later comparison. A failed compensation must remain fail-closed and explicitly
require recovery. Rollback is cheap precisely because old releases are retained under
`releases/` — never prune them as part of a publish.

## WooCommerce locale fallback for Uruguay and Peru

WordPress.org currently publishes WooCommerce catalogs for `es_AR`, `es_CL`, `es_CO` and `es_MX`,
but not for `es_UY` or `es_PE`. Do not switch the store locale to `es_ES`: country-sensitive plugin
behavior depends on the real locale/site configuration. `install-woocommerce-locale.sh` keeps
`WPLANG` unchanged and links the downloaded `es_ES` MO/JSON catalog files under the missing locale.
Keep the fallback allowlisted to `es_UY` and `es_PE`, and preserve its idempotency test.

## Remote checkout completion latency

The shared HTTPS stores can take around 30 seconds to finish `wc-ajax=checkout` even after card
tokenization succeeds. `standard` keeps the historical 60-second approved-payment budget and is the
remote default with one retry. `fast` halves it to 30 seconds, along with the other Playwright
budgets, and is diagnostic-only. Preserve polling until the final `order-received`
document reaches `DOMContentLoaded` and check captured CORS errors on every poll; `page.url()`
changes before the new DOM is ready.

## Interactive Docker setup is additive

- `make interactive` must remain a user-facing wrapper around the existing
  `make up`/`make reset` lifecycle. Do not make E2E, iOS, Super Token or direct
  Docker commands inherit the user's interactive choices. Their historical
  defaults and recipes are deliberately unchanged.
- Credentials are resolved only by the interactive script: a selected site's
  `MP_*_<SITE>` variable wins, with the generic `MP_*` as fallback. Never source
  `.env`, echo resolved values, or pass them as command-line arguments; read only
  the allowlisted names and export the resolved values to Docker Compose.
- The `interactive` Make target executes a temporary snapshot of the menu script.
  Keep that snapshot step: Bash reads scripts progressively, so editing
  `setup-interactive.sh` while a menu is open can otherwise end the running menu
  with a misleading unmatched-quote/EOF error.
- `WORDPRESS` is structural state. Keep it in the store signature and Compose
  build arguments; omitting either can reuse a store built for another core
  version. E2E reset targets must explicitly set test mode, checkout and no
  Order Pay fixture rather than inherit interactive configuration.
- The marker's WordPress selector and the version reported by `wp core version`
  are different values: a `latest` marker can correctly report a concrete core
  release. When the user keeps the current configuration, pass the marker value
  back to `make up`; passing the detected concrete release would falsely reset
  the store.
- `make up` recognizes both legacy and extended `.current-site` markers when
  their structural values match the requested store. Do not downgrade an
  extended marker in the interactive wrapper: its WordPress selector is needed
  to preserve an explicitly versioned store.
- A nonempty `.current-site` marker that matches no supported format is unknown
  structural state. Treat it as destructive and require confirmation; `make up`
  otherwise sees the raw mismatch and removes local volumes without the menu
  being able to describe the prior configuration.
- The Order Pay URL contains the order key. The interactive summary must direct
  the user to `make order-pay-url` instead of invoking that target or printing
  the URL automatically.

## Refund idempotency

- The refund request sends `x-idempotency-key = sha256(payment_id | amount | order_id | already_refunded_before)` (built in `RefundHandler::buildIdempotencyKey`). Invariants that must be preserved: numeric values are canonicalized with `number_format($v, 2, '.', '')` (fixed 2-decimals, locale-independent — do NOT use `Numbers::format`, which returns a float); `already_refunded_before` is the amount refunded **before** this refund (it excludes the current one, because WooCommerce persists the `WC_Order_Refund` before calling `process_refund`, so `get_total_refunded()` already includes it) and is expressed in the **same currency** as `amount` (single-payment: `get_total_refunded() * currency_ratio - amount`; the ratio is applied to both terms). A retry of the **same** `WC_Order_Refund` reads the same `already_refunded_before` → same key → Mercado Pago deduplicates; distinct sequential partials read different totals → different keys. (See the double-click scope note below — the key protects retries, not two concurrent distinct refunds.)

- **Multi-payment note (A1):** `already_refunded_before` is computed **order-wide in the multi-payment branch too** (`get_total_refunded() * currency_ratio - amount`), once, and reused across the loop — only `payment_id`/`amount` vary per iteration, which keeps each payment's key distinct. Because `get_total_refunded()` is updated **synchronously** by WooCommerce (it persists the `WC_Order_Refund` before calling `process_refund`), sequential distinct partials read different totals → different keys, and a retry of the same refund reads the same total → same key. There is **no** metadata-staleness race here — an earlier draft of this note claimed the value came from the per-payment IPN-refreshed metadata (`Mercado Pago - Payment {id}` → `refund`), but the code does not read it for the key. The idempotency key still only protects **retries**, not concurrent distinct refunds; the firm barrier for those is the per-`refund_id` dedup (`_mp_applied_refund_ids`, PSW-4308), with the WC order lock as the monitored fallback noted below.

- **Double-click scope — the key protects retries, not concurrent distinct refunds (single- AND multi-payment):** the idempotency key is a classic *retry* barrier. A retried request of the same `WC_Order_Refund` reads the same `get_total_refunded()` → same key → MP deduplicates. But a **truly concurrent double-click that creates two distinct `WC_Order_Refund` objects** reads different `get_total_refunded()` values → different `already_refunded_before` → different keys → MP does **not** deduplicate (this holds for single-payment too, not only the multi-payment A1 case). The per-`refund_id` dedup of PSW-4308 does **not** cover this either: it guards the notification/reflection path, whereas the `refund_id` is only assigned by MP *after* the outbound call, so two concurrent outbound calls get different ids. Concurrent double-clicks are mitigated by WooCommerce's admin refund-button disable (UI guard); a firm server-side barrier would be a WC order lock (`GET_LOCK` / `SELECT … FOR UPDATE`), kept as a monitored fallback per R-8 (adopt only if duplicate refunds are actually observed in production).

- **Refund logs use a response allowlist.** `RefundException::getResponseData()` intentionally preserves the complete API response for user-facing status mapping, but `getLoggingContext()` reduces `response_data` to `status`, `error` and `cause[*].code`. Do not log the raw response: free-form messages and provider fields can contain credentials or personal data (CWE-532).

## Refund deduplication (PSW-4308): Super Token has no refund_id

A refund can be applied from two origins — the admin panel (`RefundHandler`) and an async MP notification (`OrderStatus::refundedFlow`, via `WebhookNotification` / `CoreNotification`). Two barriers keep it from being applied twice (value-based, then refund-id-based); the non-obvious part is **why Super Token can only use one of them**.

- **Super Token's refund endpoint returns HTTP 200 with an empty body — no `refund_id`.** `POST /ppcore/prod/transaction/v1/payments/{payment_id}/refund` (the Asgard Transaction endpoint) does not echo a `refund_id` for Super Token (documented behaviour, confirmed in testing). `RefundHandler::executeRefund` therefore reads `$result['data']['id']` as `null` and writes nothing to `_mp_applied_refund_ids`. So the refund-id barrier can **never** dedup a Super Token refund — dedup falls entirely on the value barrier `OrderStatus::refundAlreadyProcessed()` (`totalRefundedMP <= totalRefundedWC`), which runs first in `refundedFlow`, is idempotent, and is method-agnostic. The refund-id barrier (`isRefundIdApplied` + `_mp_applied_refund_ids`) is the *second* layer, catching panel-originated refunds on non-Super-Token methods where the response does carry an id.

- **Read the response id with a strict `!== null` check, not `empty()`.** A `refund_id` of `0` / `"0"` is still a valid id worth persisting; `empty()` would drop it and needlessly downgrade dedup to the value barrier (`RefundHandler::executeRefund`).

- **Persist the id only after WooCommerce actually created the refund.** `refundedFlow` calls `addAppliedRefundId` only when `wc_create_refund` did **not** return a `WP_Error` — otherwise the next notification must stay free to retry creating the refund object.

- **Skipping a notification does not drop the `[Refund X]` payment metadata.** On the Webhook path `WebhookNotification::getProcessedStatus` already wrote `[Refund <transaction_amount_refunded>]` **before** `refundedFlow` runs; on the Core path the panel refund's amount was already recorded by `RefundHandler` at refund time (see the source-of-truth note below), so the dedup-skip simply logs and continues. "Skip the refund object, keep the meta" holds on both paths.

- **The per-payment `[Refund X]` amount for panel refunds is written by `RefundHandler` at refund time, NOT reconstructed from the notification (PSW-4412).** `RefundHandler::executeRefund`, right after persisting the `refund_id`, calls `OrderMetadata::addRefundedAmountToPayment($order, $paymentId, $amount)` — it knows the exact payment and amount at that moment. The async notification then recognises the already-applied `refund_id` and the Core dedup-skip **just logs and `continue`s** (no `updatePaymentDetails`, no accumulation, no save). **Do not** reintroduce an "authoritative" accumulation in the notification: an earlier attempt (`_mp_counted_refund_ids` counted-set + a `max()` recompute before it) could not reliably tell a **new** order's first refund from a **legacy** one — nothing initialised the key, so a "migration guard" that seeded the counted-set from `_mp_applied_refund_ids` fired on **every** first refund, seeded the very refund being processed, and skipped it → `[Refund X]` stayed `0` (regression proven by homolog logs: pre-guard the amount accumulated 9→14; with the guard `formatPaymentMetadata` received `0`). Recording the amount at the source removes that new-vs-legacy ambiguity entirely. `addRefundedAmountToPayment` increments only the `[Refund X]` segment (preserving other fields), reloads meta first (HPOS-safe), and never throws (logs+swallows). **Scope:** only **non-Super-Token panel refunds** take this path (they carry a `refund_id`). **MP-origin** refunds have no `RefundHandler` and still accumulate via `CoreNotification::updatePaymentDetails` `current_refund` (`+= current_refund['amount']`). **Super Token panel refunds** have no `refund_id`, so `RefundHandler` cannot record the amount — their `[Refund X]` is set by the notification's `current_refund` path and deduped by the value barrier. WC core remains the source of truth for the order-level refunded total regardless. (Historical: an even earlier draft summed `array_column($payment['refunds'], 'amount')`, which silently returned `0` and zeroed the stored amount on every redelivery — PSW-4213 zeroing bug; do not revert to reading amounts from `payment['refunds']`, which carries no `amount`.)

- **`x-request-id` from the ST refund POST cannot be used as a dedup key.** When investigating an alternative identifier for Super Token refunds (PSW-4309), the `x-request-id` response header from `POST /ppcore/prod/transaction/v1/payments/{id}/refund` was tested as a candidate. It does **not** match `refunds[].request_id` from `GET /v1/payments/{id}` — they differ in both format (`de4a478a…` vs `d366d7f4-…`) and value. There is no stable per-refund identifier available in the POST response for Super Token; dedup falls entirely on the value barrier (`refundAlreadyProcessed`).

- **`addAppliedRefundId` never throws, and reloads before writing.** By the time it runs the refund is already committed on MP and WooCommerce, so a persistence failure must not abort the caller (e.g. a multi-payment loop) nor be swallowed by an outer catch mid-flow. It forces a fresh `read_meta_data(true)` before appending (HPOS-safe; observes concurrent panel/notification writes). A failed `wp_json_encode` (never persist a literal `false`) or `save()` is logged at error level and swallowed; a corrupted, non-JSON-array meta makes `getAppliedRefundIds` log an error and return `[]`. In every failure mode the value barrier stays as the safety net.

## Refund amount validation — the "amount ≤ remaining" guard is upstream (WC core), not in RefundHandler

`RefundHandler::processRefund` does **not** reject a refund whose amount exceeds the refundable balance. In the multi-payment branch it distributes the amount across each payment with a remaining balance (`paid - refund`) and stops once the requested amount is exhausted or the payments run out — an amount larger than the total remaining refunds only what is available, **without throwing**. This is intentional: the "amount ≤ maximum refundable" validation lives **upstream in WooCommerce core** (`WC_AJAX::refund_line_items` blocks the request against the remaining refundable amount before `process_refund` is ever called), so the panel flow can never reach `RefundHandler` with an over-limit amount. Do not "harden" `RefundHandler` to reject overflow — it duplicates a guard that already runs earlier. The unit test documenting this (`testRefundAmountExceedingTotalRemainingRefundsAllAvailable`, PSW-4325) asserts the distribute-what's-available behaviour, not a rejection. (The functional spec's TC-08 described a "reject + no API call" guarantee; that guarantee is real but belongs to WC core, not this class — recorded during the PSW-4325 test validation.)

## PHP conventions

- `proccessPaymentInternal()` uses an intentional double-`c` typo — do not correct it. This is the entry point for all gateway payment processing (verifiable in `src/Gateways/AbstractGateway.php`).

- Every PHP source file must start with `if (!defined('ABSPATH')) { exit; }` as its first executable line (verifiable in every file under `src/`). Skipping this guard makes the file directly accessible over HTTP.

- Never access superglobals directly. Always use the sanitization helpers: `Form::sanitizedPostData('key')` instead of `$_POST['key']`, and `Form::sanitizedGetData('key')` instead of `$_GET['key']` (verifiable in `src/Helpers/Form.php`).

- The pre-commit hook `add-index.sh` automatically adds an `index.php` (with `ABSPATH` guard) to any new directory under `src/`, `assets/`, or `templates/`. If you create a new folder, do not add `index.php` manually before committing — the hook handles it and will conflict if the file already exists.

- When calling WooCommerce/WordPress core functions, pass the documented parameter type, never `null` as a shortcut for "use the default". These functions historically declare no runtime type hints and test optional args with a falsy check, so `null` and `''` behave identically — until a third-party plugin registers a **typed** callback on the same hook. Example: `wc_get_template()`/`wc_get_template_html()` document `$template_path` as `string` (default `''`); passing `null` was silently tolerated for years, then Divi 5.9.0 registered a `string $template_path` hook callback and, under PHP 8.x, `null` became a fatal `TypeError` that took stores down (PSW-4264). Match the WooCommerce reference signature — the fix is in `src/Helpers/Template.php`, guarded by `tests/Helpers/TemplateTest.php`.

- Admin access for the plugin must be gated by the WooCommerce **capability** `manage_woocommerce`, never by the administrator-only `manage_options` nor by matching a role name. Two places enforce this and both must stay in sync: (1) the WooCommerce submenu registration in `src/Admin/Settings.php` (`registerMercadoPagoInWoocommerceMenu()`) — using `manage_options` there blocks Shop Managers from even opening the settings screen; (2) the AJAX guard `CurrentUser::validateUserNeededPermissions()`, which must call `currentUserCan('manage_woocommerce')` and never `userHasRoles()`. `manage_woocommerce` is a capability, not a role — `userHasRoles()` intersects against `$user->roles` (only role slugs like `administrator`, `shop_manager`), so a capability passed there never matches and wrongly 403s legitimate Shop Managers. `manage_woocommerce` is the WooCommerce-idiomatic gate (it's what the parent WooCommerce menu and the core Payments settings use) and transparently covers custom roles granted the capability (fix PSW-4078; AJAX guard covered by `tests/Helpers/CurrentUserTest.php`, menu access validated via local E2E with a `shop_manager` user).

## Subscription ZDA — there is no payment webhook (PSW-4438)

- Detect the Zero-Dollar Authorization (ZDA) through `SubscriptionsHelper::isZeroDollarCit()`, which reads `transaction.amount` from the **server-built CIT payload** using `array_key_exists()` plus a numeric comparison. Both the client and gateway must reuse this predicate; do not duplicate it or use `empty()` (`0` is empty in PHP), the posted checkout value, or `payment.transaction_amount` from the response as the authority for this transition.
- `three_d_secure_mode=optional` is a fixed CIT policy for paid transactions, not seller or card data. Core rejects it when `transaction.amount=0`, so omit the field entirely for ZDA; do not send `null`, `false`, or another 3DS value.
- `notification_url` is also unsupported by Core on ZDA. Paid CITs keep it for their asynchronous webhook reconciliation; omit it entirely when the server-built amount is zero because a valid ZDA is concluded synchronously.
- An `approved` payment status alone is insufficient. Synchronous completion requires HTTP 2xx; valid payment, subscription, customer, card and profile identifiers; `profile.status=active`; and an explicitly present `three_ds_info=null`. Numeric identifiers must be positive, while UUID and opaque string formats remain accepted because Core does not use one identifier format across these fields. This is a business-workflow boundary: broadening it can mark an order paid before the recurring profile is usable.
- Persist the Automatic Payments subscription/card metadata and the Mercado Pago payment metadata **before** calling `$order->payment_complete($paymentId)`. WooCommerce owns the resulting `processing`/`completed` choice and already makes repeat calls safe; do not replace it with a direct `update_status()` or the generic approved handler, which intentionally sets paid CIT orders to `pending` while they await a webhook.
- Paid CITs and explicitly rejected ZDAs keep their pre-existing paths. Any other incomplete or non-final ZDA must return a controlled failure before the generic status handler, preserving the cart because this flow has no webhook reconciliation. Log only an allowlisted reason, HTTP status and WooCommerce order id — never the raw Core response, card token, card/customer identifiers or buyer data.
- Do not restore `AutomaticPaymentsClient::cit()`'s paid-CIT orphan exception for ZDA responses. A ZDA `2xx` without `subscription.id` must be returned to `CustomGateway`, whose full response-contract validator emits `subscription_id_missing` and selects the Classic/Blocks/Order Pay failure adapter. Tests that mock `cit()` to return a response do not prove this boundary; keep a client-level regression test covering the real pre-return behavior.
- A direct `['result' => 'failure', 'messages' => ...]` return is not a portable WooCommerce failure contract. Classic discards that array and prints queued notices, Blocks reads singular `message`, and Order Pay needs JSON with `result=fail`. Route initial CIT failures through `CustomGateway::failInitialCit()`; its `result=fail` is deliberately normalized to `failure` by the Blocks Store API after it clears the Classic notice.
- For HTTP failures, Core's stable error identifier is the top-level `code`, not `payment.status_detail`. Pass only that code and the symbolic `error` to `SubscriptionsHelper::mapApiErrorToUserMessage()`; for example, `CPP_TAAP_0000001` must select the allowlisted card retry message even when the accompanying free-form error says `Card token not found`. The CIT handler must never surface `message`/`original_message` or write them to WooCommerce logs.

## Subscription ZDA — an empty installments response is expected (PSW-4486)

- MercadoPago.js CardForm still requests installments when initialized with `amount="0"`. The installments endpoint has no plan for a zero amount, so the SDK can receive an empty collection and fail while reading `response[0].payer_costs`; the plugin receives that failure in `onInstallmentsReceived`. This is not a rejected card or failed CIT, and showing the generic checkout alert leaves the visible select empty so the pre-submit installments gate blocks token creation before the request reaches Automatic Payments.
- Never classify every zero checkout as a subscription ZDA. Zero also occurs during payment-method changes and can be produced by ordinary checkout/order-pay customizations. `CustomGateway::isInitialSubscriptionPaymentContext()` must stay aligned with the initial branch in `process_payment()`: WCS active, automatic payments on, no change-payment request, a parent subscription order on Order Pay or a subscription in the cart. The rendered `data-mp-cit-initial-context` flag is presentation-only; backend payment decisions must ignore it and all posted amount/installments values.
- The browser exception requires all three conditions: that server-derived initial-CIT flag, `MPCardForm.amount` finite and exactly zero, and `#mp_checkout_type === 'custom'`. In that scope, `setZeroDollarInitialCitInstallmentsState()` clears only installments options/error/tax hints, keeps the installments card hidden and restores `#cardInstallments=1` after every relevant CardForm reset. `onInstallmentsReceived` applies that state to every successful callback, whether `payer_costs` is empty or populated, and suppresses only the known SDK failure whose message contains `payer_costs`; do not widen the error exception, because a network, authorization or other SDK error must keep the existing alert and warning. The same isolated installments TypeError can also reach CardForm's global `onError`; it must not validate an untouched holder-name field, while an explicit `cardholderName` error and every other global error retain the existing validation path. `runPreSubmitGates()` skips only the visible installments gate; card/document gates and token creation still run.
- Preserve the normal `payer_costs` rendering and iOS select-to-hidden synchronization for positive amounts and every non-eligible context. Do not pass a fabricated positive amount to the SDK, remove the required installments field from CardForm configuration, trust only the browser flag, or let this helper touch Super Token's shared `#cardInstallments` ownership.

## Version sync — four files must always move together

A version bump requires updating these four locations in the same commit (verifiable in the respective files):

| File | Field |
|---|---|
| `woocommerce-mercadopago.php` | `Version:` in the plugin header comment |
| `src/WoocommerceMercadoPago.php` | `PLUGIN_VERSION` constant |
| `package.json` | `version` field |
| `readme.txt` | `Stable tag:` field |

Missing any one of them makes the plugin report inconsistent versions in WordPress admin and to Mercado Pago telemetry.

## Integrity allowlist — keep the Super Token CSS hand-offs literal

`assets/css/checkouts/super-token/super-token-v2.bundle.min.css` and
`super-token-v2.1.bundle.min.css` are per-variant build/release hand-offs and deliberately stay
outside `integrity-manifest.json`. They must still be built and shipped; only manifest hashing and
orphan validation skip them. The shared list in `bin/integrity-assets.js` must contain those two
complete paths — never replace it with a wildcard, prefix, regex, or directory exclusion, because
that could silently remove future assets from the blocking release gate. The plugin-served
`super-token.bundle.min.css` is not a hand-off and must remain integrity-checked (PSW-4437).

## Document masks (input-document)

The `input-document` Web Component (`packages/narciso/components/input-document/`) formats the holder's document per site via `DocumentHandlerFactory` + one handler per type. Non-obvious rules (PSW-4398):

- **SDK option value and visible label are different contracts (PSW-4239).** In the card checkout, `/identification_types` remains authoritative for which options exist, their order, selection and technical `option.value`; `InputDocument.normalizeDocumentOptionLabels()` changes only `option.textContent` after the SDK populates the select. Never rebuild the list from the local label map and never replace `option.value` with the handoff copy — either change can alter the identification type sent during tokenization. The same `CE` value is deliberately rendered as `CE` in MCO and `C.E` in MPE, so normalization must use `site-id` plus the stable value. Unknown values retain the SDK text. Keep label writes on `textContent`, not `innerHTML`, because the source is external and must not be interpreted as markup (CWE-79). This normalization is specific to the SDK-populated card selector; PSE and Ticket use plugin-owned static lists and must not inherit it accidentally.

- **The SDK-population observer is intentionally one-shot (PSW-4239).** The SDK card form builds every identification option in one `DocumentFragment`, clears the initially empty select and appends the complete batch once during `onFormMounted`; `observeSelectPopulationFromSdk()` therefore normalizes the batch, applies the default and disconnects. There is no current payment-method or installments path that repopulates `/identification_types` on the same component. If the SDK starts appending options incrementally or repopulating the existing select, the observer lifecycle must change with it; keep the integration test that appends an SDK-style batch, because direct tests of `normalizeDocumentOptionLabels()` alone do not protect this wiring.

- **Dynamic document helper messages are text, not markup (PSW-4239).** `InputDocument.updateHelperErrorMessage()` delegates to `InputHelper.updateMessage()`, whose sink is `textContent`. Error strings come from translated attributes and have no supported HTML contract; do not restore the former direct `innerHTML` write. Components that deliberately render legal or terms markup are a separate sanitization problem and must not be used as precedent for document errors.

- **Mask ≠ gate.** A handler's `mask()` is *visual only* — it groups digits with dots/dashes and must never cap length. The typing limit is the DOM `maxlength`, set in `InputDocument.setInputProperties` via `getPermissiveMaxLength()`, which renders develop's raw digit cap (CPF 11, CNPJ 14, CI 8, everything else 20) through the mask so separators never shrink the digit allowance. Do **not** wire `maxlength` to the handler's `CONFIG.max_length_with_mask` (the short Figma display size) — that blocks documents develop accepts (e.g. MCO CE capped at 7 vs develop's 20). The `CONFIG` size fields (`max_length`, `min_length`, `max_length_with_mask`) are reserved metadata for the SDK validation coming in PSW-4397 and do **not** govern the interim `maxlength`; only `placeholder` (and `mask()`) are consumed for display. Do not "simplify" `getPermissiveMaxLength` to read `CONFIG.max_length` — for the variable-length docs (RUC, NIT, MCO_CE, RUT, DNI…) it is the short Figma value and would regress the permissiveness the raw digit cap preserves.

- **Peru DNI mask does not truncate.** `MPE_DNIHandler.mask()` groups left-to-right without a cap. An earlier version capped the body at 8 digits + a check digit; that silently blocked 9–20 digit inputs develop accepts. Keep it ungated (mask ≠ gate). CPF and CNPJ masks *do* truncate to their fixed length — that is correct (they are FIJO and develop caps them identically); only the variable-length docs group without a cap.

- **Handler `validate()` is intentionally permissive (interim).** Two behaviours only: DV for CPF/CNPJ/CI; **every other document rejects only empty** (`{result:false, type:'empty'}` when blank, valid otherwise) — CC/CE/NIT and DNI/RUT/RUC/LC/LE/generic all share this. This is deliberately stricter than develop (which validated almost no document): empty is a required-field error, so the real-time feedback matches the submit gate. Do **not** "restore" DV or min/max ranges to these validators — that resurrects the divergent PSW-3095 rules that reject valid documents. Real min/max/DV validation is deferred to the SDK (PSW-4397); broader empty-field-at-submit handling is PSW-4241.

- **The empty helper message mirrors the red state.** `InputDocument.setInvalidState` reveals the `helper-empty` message (`display:flex`) in **both** the empty and the wrong-value branches, so a field that turns red always says why. The empty branch used to leave the helper hidden, so documents whose only failure is "empty" went red without a message. The message clears with the red state on blur (`clearErrorStates`) — it is real-time feedback only. The authoritative empty-on-submit block is separate: `verifyDocument`/`runPreSubmitGates` in `mp-custom-page.js` reject an empty document for every type by reading the field value directly, not `validate()`.

- **The component's `margin-bottom: 24px` stacks with the consumer's own spacing (PSW-4238).** `InputDocument.css` gives `.mp-input-document` a 24px bottom margin. In the **Custom** checkout that margin lands *inside* `#mp-doc-div`'s box (the `input-document` custom element is `display: inline`, which blocks margin collapsing) and therefore **adds to** the `margin-top` of `#mp-checkout-custom-installments-card`. That coupling is why the card's historical `margin-top: 8px` *looked* right: 24 + 8 = the 32px rhythm of `.mp-checkout-custom-card-form`'s flex `gap` — but only while the document field was visible. In sites that hide it (e.g. MLM) the 24px vanished with it and the section was left with 8px. The fix raises the card to `margin-top: 32px` **and** zeroes the component margin **scoped to the Custom checkout** (`#mp-checkout-custom-root … #mp-doc-div .mp-input-document`), where the flex `gap` already supplies the spacing. Do **not** zero that margin in the component: `pse-checkout.php`, `ticket-checkout.php` and `ticket-address-container.php` render the same field **without** a gap-bearing flex container, so there the 24px *is* the spacing. And do not "restore" the card's `margin-top` to 8px — it only ever worked by accident, and only in sites with a document field.

- **The placeholder colour is set in the shared component on purpose.** Without an explicit `::placeholder` rule the field inherits the *store theme's* placeholder colour, so the same input rendered differently per environment (measured on Twenty Twenty-Five: `rgb(181,171,194)` in Classic and Order Pay, `rgb(117,117,117)` in Blocks) and per theme. The rule therefore lives in `InputDocument.css`, not scoped to Custom, because PSE and Ticket had the identical symptom. Do not "scope it properly" to the Custom checkout — that silently regresses the other two. Only `color`/`opacity` are declared; the rest of the typography already matched the cardholder-name field.

## i18n

- Never edit `.pot` or `.mo` files manually. `.pot` is generated by `npm run pot`; `.mo` is compiled from `.po` by the WordPress i18n CLI. Manual edits will be overwritten (verifiable in `i18n/languages/`).

- The text domain must always be `woocommerce-mercadopago` — passed explicitly as the second argument to every `__()`, `esc_html__()`, etc. call (verifiable in `src/Translations/`).

## Super Token — CDN runtime vs local build

Super Token is built locally (`npm run build:super-token:bundle`) but loaded at runtime from the Mercado Pago CDN (`http2.mlstatic.com`), not from the local build output. The local bundle is published to the CDN separately. Editing the local source without publishing the CDN update will have no effect on live stores (verifiable in `assets/js/checkouts/super-token/` and the CDN URL referenced in gateway PHP files).

The plugin is the source of truth for the distributed Super Token version. Change
`SUPER_TOKEN_JS_VERSION` in `constants.ts` and both entries of `SUPER_TOKEN_LOADER_VERSION` in
`main.js` together, run the plugin build, and copy the generated hand-offs byte-for-byte into the
scripts repo. Never bump only the downstream copies: the next plugin build would restore the older
version and make the PRs disagree about what was actually generated.

The new hexagonal **TypeScript** tree at the `super-token/` root (`ports/`, `core/`, `useCases/`, `adapters/`, `composition/`) compiles through dedicated webpack entries and is type-checked by `npm run type-check` (`tsc --noEmit`). It is already the runtime in every mode: dev/self-construct enqueues the unified local entry, while production 8.9.3 keeps the retrocompatible A/B loader and CDN paths but serves one refactored bundle with its variant frozen (`v1/` → v2, `v2.1/` → v2.1). Only the switch to the unified `mp-super-token/` path remains deferred to TASK-013.

The Super Token **SCSS** tree is compiled by `compileSuperTokenCss` into three hand-offs. Dev/self-construct serves the unified `super-token.bundle.min.css`, containing both variants scoped by `data-variant`; production serves the per-variant artifact selected by its CDN path. Each per-variant CSS begins with a `:root` custom-property stamp. The SCSS is a faithful reorganization of the prior CSS and equivalence remains gated by `npm run verify:super-token:scss`.

The SCSS toolchain (`sass`, `stylelint`) requires **Node 20** and is version-pinned for it: the newest `sass`/`stylelint 17` pull `chokidar 4`/`readdirp 5`, which need Node ≥20.19 and crash on the repo's `^20.0.0` floor. Keep `sass` on the `~1.80.7` (chokidar-3) line and `stylelint` on `^16`; run `lint:css`/`verify:super-token:scss`/any build under `nvm use 20`.

## Super Token — bind the variant runtime before starting SDK readiness

`runtimeComposition.ts` binds `recompose.current` only after the A/B variant resolves asynchronously. Starting `SdkReadinessWatcher` before that Promise settles is unsafe when `window.mpSdkInstance` already exists: the watcher immediately invokes the placeholder callback, marks initialization successful and cannot retry the real composition later. `composeRuntime()` therefore returns `Promise<void>`, including its v2 fallback path, and `bootstrap.ts` starts initialization resilience only after it settles. `SdkReadinessWatcher` also treats an explicit `false` callback result as a failed attempt and leaves recovery available. Preserve this ordering; do not move `startInitializationResilience` back to an unconditional module-tail call. `super_token_init_source.elapsed_ms` is measured from watcher construction after variant binding by design.

## Super Token — variant lifecycle and render locks are runtime contracts

The selected variant is not only a render strategy. `SuperTokenPaymentMethods` must delegate all four lifecycle points through the same memoized `VariantViewPort`: initial `renderSavedPaymentMethods`, `decorateSelection`, `clearSelectionDecoration` and `reset`. Reintroducing the controller's old hardcoded v2.1 account-money decoration makes v2 receive v2.1 chrome and lets repeated v2 renders duplicate their list header. The common controller still owns the selected class and ARIA state; the view owns only variant-specific chrome.

The `isRendering` flag is a lock, not a render result. Once acquired, release it in `finally`; a missing checkout container, a view exception or malformed installments data must not strand it as `true` and suppress every later render. SDK installments may omit `installment_rate_collector`; treat that field as an empty list before checking collector membership. SDK readiness follows the same success-only teardown rule: when composition returns `false`, keep the active fallback poll armed so a later tick can retry; clear it only after successful composition or the 15-second deadline.

The 50 ms SDK poll is only an availability detector, not a composition retry cadence. Once the SDK
exists and composition returns `false`, retries are spaced by one second and remain bounded by the
same 15-second deadline. `super_token_recovery_compose_failed` is emitted for the first failure and
once more at exhaustion with the attempt count and last real diagnostic; never emit it on every
50 ms poll tick or rebuild the runtime hundreds of times during a persistent failure.

Saved-method rendering is isolated per row in `buildTypedRow`. If interactive details fail, report
the real error through `error_to_render_account_payment_methods` and retain that method as a
presentation-only fallback; if even the fallback cannot be built, omit only that row. Variant views
must continue rendering the remaining rows and their own header/block chrome instead of propagating
one malformed method and clearing the complete list.

**Preserving the real Super Token error text is deliberate.** `toTelemetryErrorMessage()` must keep unknown SDK/browser diagnostics usable instead of replacing them with `UNKNOWN_ERROR` or another generic message; the stable `MPSuperTokenErrorCodes` catalog is an auxiliary grouping dimension, not a replacement for the message. The formatter redacts known credential, token and e-mail patterns, including complete `api_key`, `client_secret`, password, authorization, Cookie/Set-Cookie, session and JWT values even when quoted or containing whitespace, while preserving a following non-sensitive diagnostic field such as `status=401`. `Authorization:` and `Proxy-Authorization:` consume their complete line before the generic keyed redactor, because Base64 padding such as `==` can otherwise be mistaken for the next key and leak part of a Basic credential. Cookie headers may be serialized with either `:` or `=`; both forms are consumed through the end of their line so semicolon-delimited attributes cannot escape. Upstream free-form text is not fully controlled, so formats not yet catalogued carry a consciously accepted residual data-exposure risk. Do not claim this boundary guarantees removal of every possible URL, payload, stack or PII shape, and do not collapse unknown errors to generic text without explicitly revisiting the observability trade-off with the owning team.

MeliData readiness also needs a finite lock: the adapter keeps one five-second deadline for the whole FIFO buffer. A pending `window.melidataReady` Promise must not retain events forever; timeout/rejection emits exactly one `mp_melidata_load_timeout` with `message=buffered:N`, flushes every buffered `mp_checkout_error` in FIFO order and ignores a late resolution. Do not emit the timeout metric once per event: the original diagnostic is already present on each error's own Core Monitor metric and on the best-effort MeliData event. Missing `MPCheckoutFieldsDispatcher` diagnostics are deduplicated per context, not globally, so card and Credits failures remain independently observable.

The dispatcher-missing contexts are deduplicated by a module-level `Set`, including across render
sessions on the same page. E2E coverage must observe the Core Monitor request before checkout
initialization instead of replacing `window.sendMetric` after the first render. When a scenario
injects a saved method, count its installments select from that method's `[data-type]` row; a global
`[data-checkout="installments"]` query also includes the base Custom Checkout select and produces a
false duplicate. Use a second metric collector installed after checkout initialization when an exact
synthetic-render count is required; the early collector also includes the real initial render.

## Super Token — bundle size baseline & the +5% gate (PSW-4278)

The refactored single bundle is **larger per user** than the legacy per-variant bundle, and this is
expected — not a regression to "fix". Measured homolog×homolog (same minify toolchain, no
non-deterministic prod obfuscation), JS+CSS gzip: legacy `v2.1` (largest legacy variant, the
baseline a user actually downloads) = **33.7 KB**; refactored single `mp-super-token/` = **44.9 KB**
= **+33%** — over the `+5%` ceiling from PSW-4277 (Fatia 3b). The growth is **architectural** (the
hexagonal tree + the ported `SuperTokenPaymentMethods.ts` + the A/B logic moved out of the loader
into `VariantConfigAdapter` + transitional strangler-fig layers `globalBridge`/`legacyDelegationSeams`),
**not** from carrying both A/B variants: variant-specific code is only **472 lines (~4%)** of the
11.2k-line tree. So splitting/lazy-loading by variant is the wrong lever (recovers ~4%, costs an
extra init round-trip and re-introduces the multi-file CDN model ADR-005 removed). The PSW-4278
performance review resolved this as **PASS on latency grounds** (the real delta is ~+10 KB gzip,
once, CDN-cached — immaterial to init/checkout load) with the literal size-budget breach documented
as a conscious trade-off; the `+5%` was a magic number, not a load-impact-derived limit. Real
reduction, if ever needed, comes from deleting the transitional scaffolding at cleanup (PSW-4277
Fatia 4) or tree-shaking — see `.claude/tasks/PSW-4278.md` for the full measurement and the Datadog
init-metric queries (`super_token_init_source` distribution + `elapsed_ms` percentiles).

## Super Token — dev variant follows the localized constant, not the cookie

In dev/self-construct mode (`MP_SUPER_TOKEN_USE_BUNDLE=false`) the served stylesheet is a single compiled file (`registerSuperTokenStyles` serves `super-token.bundle.min.css`) with both A/B variants scoped by `data-variant`; the rendered variant follows the PHP constant `MP_SUPER_TOKEN_VERSION`. `bootstrap.ts` must render that **same** variant (and stamp `data-variant` on the root) or DOM and CSS mismatch — e.g. a v2.1 DOM styled with v2 CSS shows the block header/email unstyled. So the constant is passed to JS via the `super_token_version` localize param and `bootstrap.ts` resolves the variant from it first (`params.super_token_version ?? readVariantCookie() ?? 'v2'`); in bundle/production mode the variant is resolved at runtime inside the bundle (`VariantConfigAdapter`), not by the loader/cookie. **To test a variant in dev, flip only the constant** — the injected `mp_st_variant` cookie no longer selects the rendered variant in self-construct mode.

**Super Token variant parity (PSW-4276):** saved-method selection (account_money / credits and the saved-card authorization flow) works on both A/B variants. Both `v2` and `v2.1` dispatch their saved-method rows through the shared typed-row builder (`adapters/view/shared/typedRow.ts`), which wires the click/keydown selection — earlier the refactored v2 fell back to a presentation-only row and its selection did not register. Keep new per-variant views going through that builder so the parity holds before the TASK-013 CDN cutover.

## Super Token — init-check metrics moved from window.sendMetric to Core Monitor

The four `checkIfSuperTokenWasInitialized` signals (`SUPER_TOKEN_INITIALIZATION_SUCCESS/ERROR`, `SUPER_TOKEN_CLASSES_NOT_EXISTS`, `SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING`) previously called the plugin-global `window.sendMetric(name, message, level)`. In the hexagonal tree they are emitted through `MetricsPort` → `CoreMonitorMetricsAdapter`, which posts to the Core Monitor endpoint (`/ppcore/prod/monitor/v1/event/datadog/big`). Before wiring these modules into the runtime (TASK-013), confirm the PSW-4167 dashboard queries read from Core Monitor (not from the legacy sink) and align with the PSW-4334 pre-merge metrics gate. Failing to do so will silently drop these signals from the existing dashboard (verifiable in `adapters/platform/CoreMonitorMetricsAdapter.ts` init-resilience section and `docs/agent/` platform README).

## Super Token — `ab_variant` metric dimension

Every Super Token bundle metric (`super-token-metrics.js`, trees `v2` and `v2.1`) carries a `details.ab_variant` dimension, read from the `mp_st_variant` cookie set by the A/B loader and constrained to `v2` / `v2.1` / `unknown` (cardinality 3, to protect the Datadog tag). Before assignment (loader hasn't run, or the kill switch cleared the cookie) it is `unknown`. It is forward-compatible with the Core Monitor allowlist: until `ab_variant` is registered in the `details` allowlist of `monitor/v1/event/datadog/big` it is sent and silently dropped before reaching Datadog — no redeploy is needed once Core registers it. Any change to a bundle source must bump `SUPER_TOKEN_LOADER_VERSION` (both variants in `main.js`) and regenerate the `*.min.js` (`npm run build:js`) + `integrity-manifest.json` (`npm run build:integrity`), since dev mode (`MP_SUPER_TOKEN_USE_BUNDLE=false`) serves the minified assets.

## Super Token — preserve diagnostic errors, but never telemetry credentials (PSW-4279)

Core Monitor metric *values and messages* are persisted in the observability backend. Preserve the real SDK/browser error text because generic `UNKNOWN_ERROR`, `async_error` or `UNEXPECTED_RESPONSE` values are insufficient for production troubleshooting; pass every caught error through `toTelemetryErrorMessage()`, which keeps the diagnostic context and redacts only concrete credential/PII values such as email addresses, bearer tokens, Cookie/Set-Cookie headers, session identifiers, JWTs, `token`, `pseudotoken`, PAN and CVV fields. `toSafeTelemetryErrorCode()` remains an auxiliary low-cardinality classification for `details.event`; it must not replace the diagnostic message. `authorized_pseudotoken` sends the boolean `'true'`, and `error_to_update_security_code` sends `paymentMethod.id`, never their credential fields. The same invariant applies to the **PHP backend** (`Datadog::sendEvent`, not only the JS Core Monitor): the unsafe `authorized_pseudotoken_mismatch` metric was removed entirely in PSW-4292 because it carried no meaning that could be reported without exposing credentials. Verifiable in `assets/js/checkouts/super-token/core/checkoutSession/ErrorClassification.ts`, `adapters/platform/CoreMonitorMetricsAdapter.ts` and `src/SuperToken/SuperTokenValidator.php`.

`authorized_pseudotoken` is also an explicit external trust boundary: this plugin validates that the field is present, then delegates token validity and ownership enforcement to the Mercado Pago payment API through `saveWithSuperToken()`. Treat an API rejection as authoritative and confirm that downstream contract with Payments Core before changing the flow; a client-side comparison would not establish ownership and must not be introduced as an authorization control.

## Super Token — `authenticator()` can resolve falsy without throwing

`mpSdkInstance.authenticator(...)` can resolve to a falsy value (`null`/`false`/`0`/`undefined`) **without rejecting**. That path never enters the `catch` in `buildAuthenticator` (which emits `error_to_build_authenticator`) and is swallowed downstream by `if (!authenticator) return null` in `getAccountPaymentMethods` — historically a silent funnel drop where the buyer fell back to the Custom checkout while the error metric stayed flat (21/07/2026 PRAPI/`MANIFEST.json` incident). It is now instrumented from inside `buildAuthenticator` via `super_token_authenticator_falsy` (`value` = the returned falsy value, `message` = `typeof:<type>`), mirroring the existing `super_token_authenticator_null` guards. A thrown/rejected SDK error is a different path (`error_to_build_authenticator`) — the falsy-resolve case is not an error and does not dispatch a Melidata event. Trees `v2` and `v2.1` are kept in lock-step (verifiable in `assets/js/checkouts/super-token/{v2,v2.1}/entities/super-token-authenticator.js`).

## Dual checkout mode — Classic and Blocks are not interchangeable

The plugin maintains two separate frontend implementations: Classic (vanilla JS, `assets/js/checkouts/`) and Blocks (React, `assets/js/blocks/`). A change to one does NOT automatically apply to the other. Features and fixes must be implemented in both if they apply to both checkout modes. Importing Classic patterns (jQuery, direct DOM) into Blocks code is wrong and will break the Blocks environment.

## Checkout resilience — three root-cause patterns

The plugin runs in uncontrolled environments. Every WooCommerce store can have multi-step checkouts, custom required fields (terms checkbox, address number), and third-party plugins that alter submit flow or intercept events. Never assume a standard WooCommerce checkout flow.

- **Ghost State**: JS flags (`formMounted`, `isLoading`) can desync from the DOM after `updated_checkout` or third-party DOM mutations. Always verify DOM state before mount/unmount/submit; if DOM contradicts state, trust DOM.
- **Error Cascade**: Missing `?.` in `catch`/`finally` blocks causes a secondary error that freezes the checkout. Every error path must use optional chaining (`?.`) for property access and `window.*` for globals. Always classify errors before handling.
- **Fluid Checkout (most common third-party conflict)**: Creates a hybrid Classic+Blocks environment where DOM-based checkout type detection fails. Always use `wc.wcBlocksRegistry` APIs for checkout type detection — never DOM selectors alone.

Full rules and code examples are in `.claude/rules/checkout-resilience.md`.

## Currency conversion — ppcore exchange endpoint does not accept a `to` currency

`Currency::getCurrencyConversion()` calls the ppcore exchange endpoint via `SDK::getExchangeInstance()->getExchangeRate($fromCurrency)` (`src/Helpers/Currency.php`). Unlike the old `/currency_conversions/search?from={}&to={}` endpoint, the new one accepts only the `from` (store) currency and resolves the `to` (account) currency internally from the bearer token. Do not add a second currency parameter — the API will reject it. The `toCurrency` variable in `getCurrencyConversion()` is kept solely for the Datadog error metric (`to_currency` dimension) and is never sent to the API. The cache key is `currency-conversion-{clientId}-{fromCurrency}`.

The `httpStatus` sent to the Datadog error metric comes from `ApiException::getApiStatus()`, which reads the `status` field of the **response body** — not the HTTP transport status. For 5xx errors, the SDK throws a plain `\Exception("Internal API Error")` with no HTTP status attached, so the metric receives `0` as the status value. This is a known limitation of the SDK's exception model (verifiable in `vendor/mp-plugins/php-sdk/src/Common/Manager.php::handleResponse()`).

## Deferred assets — Custom checkout form readiness

`MPCustomCheckoutHandler` can load before WooCommerce has inserted its checkout form, or before the Mercado Pago SDK, `CheckoutElements`, `CheckoutPage`, card-form, 3DS or event-handler scripts have executed, when an optimizer such as WP Meteor defers scripts. Its WordPress handles must keep the chains SDK → card-form and `CheckoutElements` → `CheckoutPage` → card-form/3DS/event-handler, but do not rely only on that ordering: initialization must retain the bounded global wait for either a usable SDK instance or `MercadoPago`, DOM observation, no-`MutationObserver` polling fallback and jQuery wait before binding handlers. The bounded wait is restricted to checkout, order-pay, and add-payment-method pages. A timeout must emit only `dependencies_timeout`, `form_discovery_timeout` or `jquery_timeout` through `mp_custom_checkout_initialization_error`; buyer, order, and card fields must never be included. Verify this behavior manually in Classic and Blocks with and without script optimization.

## Card BIN validity — the `cardBinIsValid` flag on `MPCardForm`

In the Custom checkout, whether the typed card number has a **recognised BIN** cannot be derived from the SDK's field-format callbacks. Two SDK callbacks look similar but answer different questions (verifiable in `assets/js/checkouts/custom/entities/card-form.js`):

- `onValidityChange(error, field)` validates **format only** (length/type of the field), fires on every keystroke, and is independent of whether the BIN is real.
- `onPaymentMethodsReceived(error, paymentMethods)` is the **only** reliable signal that the BIN is recognised (it reflects the `getPaymentMethods()` API response), but it fires **only when the BIN prefix changes** — not on every keystroke.

Because of this, `MPCardForm` keeps a `cardBinIsValid` flag fed **exclusively** by `onPaymentMethodsReceived`. `onValidityChange` only removes the card-number error when `cardBinIsValid` is `true`. Without this, editing a digit without changing the BIN (so `onPaymentMethodsReceived` does not re-fire) would let `onValidityChange` clear a still-valid BIN error prematurely. Do not "simplify" `onValidityChange` to remove the error unconditionally — that reintroduces the bug.

The flag is reset optimistically to `true` in `onReady` (every mount/remount). This is safe: a remount recreates the Secure Fields iframes **empty** (card data never persists — PCI), so an invalid BIN cannot survive a remount and any re-entry re-fires `onPaymentMethodsReceived`. The optimistic default is intentional — at the instant the format becomes valid the BIN verdict is not yet known, and favouring the common case (valid cards) avoids a false error flash on every valid card.

### `onBinChange` — clear the previous card's state early, keyed on a real BIN change

A third `cardForm` callback, **`onBinChange(bin)`** (cardForm-level; the field-level `field.on('binChange')` never fires — do not use it), fires **~200 ms before** `onPaymentMethodsReceived` with the **raw BIN string**. It is the only signal that a *new* BIN verdict is on the way. `MPCardForm` uses it to reset the previous card's state **the moment the BIN changes**, instead of waiting for the verdict: it sets `cardBinIsValid = true` (optimistic, same rationale as `onReady`), removes the stale card-number error, and clears the residual `#paymentMethodId` (so a stale id can't be submitted in the ~200 ms window — the success verdict repopulates it). `onPaymentMethodsReceived` records `this.lastVerdictBin = this.currentBin`; `onBinChange` acts **only** when `bin !== lastVerdictBin`, so editing **within the same BIN** does not reset anything (keeps the persisting error — same guarantee as the `cardBinIsValid` guard above). The `#paymentMethodId` clear is **skipped under Super Token** (`#mp_checkout_type === 'super_token'`), which owns that shared field (see next section). Do not clear on every keystroke or unconditionally — that would either churn state or wipe the id of a valid card after its verdict lands.

> ~~Note: Luhn (checksum) validation is **not** available in this flow.~~ **Outdated** — `enableLuhnValidation: true` now works on `cardNumber` when the payment method is `data-validation="standard"`. See the *Custom checkout — Luhn & card-token validation* section below.

## Super Token owns the shared `#paymentMethodId` / `#cardInstallments` fields

The Super Token flow reuses the Custom checkout's hidden submit fields: the payment-methods runtime (`adapters/runtime/SuperTokenPaymentMethods.ts`) writes `#paymentMethodId` and the trigger handler (`adapters/runtime/SuperTokenTriggerHandler.ts`) writes `#cardInstallments`. But it also unmounts the Custom card form during `resetCustomCheckout` (`unmountCardForm`), which fires `onFormUnmounted → CheckoutPage.clearInputs() → clearCardState()`.

`clearCardState()` clears those same fields — so, in the Super Token flow, the ST-populated `#paymentMethodId` / `#cardInstallments` were being blanked right before submit, and the server rejected the payment at the required-fields gate (`payment_method_id` / `installments` empty) **before any API call** (`InvalidCheckoutDataException` in `CustomGateway::proccessPaymentInternal`). This is Super-Token-only and was a regression from PSW-4326 — the Custom flow was unaffected because its card form is not unmounted mid-flow.

Because of this, `clearCardState()` — and `removeAdditionFields(clearInstallmentsValue)` — skip clearing `#paymentMethodId` / `#cardInstallments` when `#mp_checkout_type === 'super_token'`. Do not remove this guard: the Custom flow **must** keep clearing them (invalid BIN / reset), but the Super Token flow owns those shared fields and unmounts the card form after populating them. (PSW-4342)

> Ordering invariant this guard depends on: `onFormUnmounted → clearInputs() → clearCardState()` runs **synchronously** while `#mp_checkout_type` is still `super_token` — before `reset()` flips it back to `custom`. This holds today (no live bug). If the SDK ever makes `onFormUnmounted` async, the guard could read `custom` and the regression would return silently — re-verify this ordering before trusting the guard.

## Hidden submit fields must be re-synced from the visible control at submit — don't trust `change`

The Custom checkout submits **hidden** fields, not the controls the buyer interacts with. The installments `<select id="form-checkout__installments">` is what the buyer sees, but WooCommerce posts the hidden `#cardInstallments` (`name="mercadopago_custom[installments]"`, in `templates/public/checkouts/custom-checkout.php`). The hidden is normally fed from the select by the select's **`change`** listener (`setChangeEventOnInstallments` in `mp-custom-page.js`). Any path that changes the visible control **without firing `change`** leaves the hidden empty or stale — and because the pre-submit gate `installmentsWasSelected()` reads the **select**, not the hidden, the gate passes while the posted value is wrong:

- **The iOS native `<select>` picker auto-selects an option without firing `change`** → the hidden stays **empty** → the backend rejects with `missing_fields: installments` (`InvalidCheckoutDataException`). Conversion-loss incident (PSW-4381 / postmortem PSW-4388).
- **Installments reload on an amount change** (shipping/coupon → SDK `onInstallmentsReceived` → `setChangeEventOnInstallments` resets the select to the placeholder) does **not** clear the hidden — only the BIN-change path (`onPaymentMethodsReceived → clearInputs`) does. Combined with the iOS quirk (silent re-select afterwards), the hidden goes **stale** (e.g. `6`) while the select shows `1` → charges 6x showing 1x, silently.

Rule: whenever a hidden submit field mirrors a visible control, **re-sync it from the visible control at submit time, treating the visible control as the source of truth** — never rely on `change`/`input` having fired. `runPreSubmitGates` does this via `syncInstallmentsFromSelect()`, mirroring `#form-checkout__installments` → `#cardInstallments` **unconditionally** when the select has a value (idempotent no-op in the normal case, since the `change` listener keeps them equal; overwrites a stale/empty hidden otherwise). It covers all three checkouts because `runPreSubmitGates` is the single gate called from `createToken()` in both `event-handler.js` (Classic + Order Pay) and `blocks/custom.block.js` (Blocks). **Super Token is the exception** — it owns `#cardInstallments` and routes through `handleWithSuperTokenSubmit` (see the section above), so this mirror never runs there. (PSW-4385 — the definitive in-plugin fix for the temporary CDN hotfix `syncInstallmentsOnSubmit()` in `fury_mp-op-pp-woocommerce-scripts` `behavior-tracking.js`.)

### iOS regression harness preserves the native precondition

- `e2e/ios/` must not select installments, dispatch `change`, mutate the select/hidden, or monkey-patch the page in the PSW-4390 regression. Its value is proving the native Safari state (`select=1`, hidden empty/stale) and the submit-time repair. If the selected Simulator runtime does not reproduce that state naturally, classify the runtime as incompatible and fail; never manufacture the incident.
- Safari in the Simulator reaches the Docker store through the loopback-only Caddy profile at `https://localhost:8443`. The runner trusts only Caddy's generated public root certificate in the disposable Simulator. It does not require—and must not silently fall back to—ngrok, trycloudflare, another public tunnel, or disabled TLS validation.
- Playwright is only the test runner/reporter here. Browser control is `selenium-webdriver → Appium/XCUITest → Safari`; replacing it with Playwright WebKit removes the native Safari picker behavior the regression exists to cover. Keep one XCUITest session and alternate contexts: use `WEBVIEW` for the checkout DOM, but map the Mercado Pago cross-origin PCI iframes to their `NATIVE_APP` text fields because WebKit blocks Selenium atoms from entering those frames. Close the native keyboard between secure fields so Safari viewport changes do not map a value to the wrong control.
- CoreSimulator can remain briefly in `Shutting Down` after teardown. The iOS helper must wait for `Shutdown` before booting or erasing the dedicated device; immediate back-to-back runs otherwise fail while installing Caddy's public root certificate.
- Appium drivers are extension-registry entries, not merely npm packages. `make install` must register the pinned `appium-xcuitest-driver` in the same project-local `APPIUM_HOME=e2e` used to start Appium, and `make doctor` must require that exact version. A dependency present in `node_modules` is not sufficient evidence that a clean checkout can create an XCUITest session.
- An erased Simulator can spend several minutes compiling or starting WebDriverAgent before the first fixture is usable, and fresh Safari/WebKit may expose its debuggable context slowly. Keep the five-minute Playwright test budget and 90-second `webviewConnectTimeout`; shorter cold-start limits can fail the first test and leave the next session without a connected web application.
- The release regression is intentionally human-initiated: its author runs `bash e2e/run-all-report.sh --release` on macOS, and the command gates the consolidated result on the iOS suite. It must clear any inherited `IOS_TEST_GREP` diagnostic filter and read `make`'s status from `PIPESTATUS[0]` rather than `tee`; otherwise a zero-test or failing iOS run can be reported as passed. Do not move this check to a Linux runner or silently skip it when Xcode is unavailable.

## Order Pay (`form#order_review`) — submit lifecycle differs from the standard checkout

On the Order Pay page the Custom checkout hooks the **raw** `submit` of `form#order_review` (`event-handler.js`, `handleOrderReviewSubmit` bound in the constructor). This is the only checkout that hooks a native `<form>` submit — Classic uses WooCommerce's `checkout_place_order_*` event and Blocks uses a React callback. Two non-obvious consequences, both of which a **pre-submit gate short-circuit** (invalid card / installments / document) will regress if ignored:

- **The loading overlay is WooCommerce's, and is only cleared downstream of tokenization.** On submit, WC's `blockOnSubmit` puts a `.blockOverlay` over the form. The only code that removes it is `cardForm.removeBlockOverlay()`, reached from the SDK cardForm `onError` callback and from `handle3dsPayOrderFormSubmission` — both **after** `createCardToken`. A gate that `return`s **before** tokenization never reaches that cleanup, so the overlay stays forever (stuck loader). The gate short-circuits therefore call `deferBlockOverlayRemoval(cardForm)`, which removes the overlay in a **microtask** (`Promise.resolve().then(...)`). The defer is mandatory: our raw-submit handler runs at handler index 0, **before** WC's `blockOnSubmit` at index 1, so a synchronous `removeBlockOverlay()` in the gate runs before the overlay exists and is a no-op. (`mp-custom-page.js` `deferBlockOverlayRemoval` / `runPreSubmitGates`; `card-form.js` `removeBlockOverlay()` is Order-Pay-guarded and a no-op elsewhere.)

- **The MP SDK re-fires the form submit programmatically (`requestSubmit`).** After the click, the SDK cardForm calls `HTMLFormElement.requestSubmit()` (~400ms later) with `submitter === null`, re-entering the raw `submit` handler a second time per user click — so a per-submit metric double-counts. `handleOrderReviewSubmit` guards against this by ignoring the programmatic re-submit: `if (event?.originalEvent && event.originalEvent.submitter === null) { stopImmediatePropagation(); preventDefault(); return false; }`. Read `submitter` from `event.originalEvent` — jQuery 3.7 does not copy `submitter` onto its wrapped event. The `stopImmediatePropagation()` is required, not decorative: in jQuery `return false` means `preventDefault()` + `stopPropagation()` only, so WC's `blockOnSubmit` at index 1 (same form, after ours at index 0) still runs and re-adds `.blockOverlay` on this spurious re-submit — leaving the pay form stuck behind the loader. `stopImmediatePropagation` stops that same-form handler too. Do not remove this guard: without it the pre-submit gate (and its metric) runs twice per click and the overlay can stick. (`event-handler.js` `handleOrderReviewSubmit`; PSW-3962 gate exposed this pre-existing double-submit; the overlay leak was confirmed on a real Order Pay page.)

## Seller config — `_site_id_v1` is only populated via OAuth; never assume it is set

`Seller::setSiteId()` is called exclusively from `IntegrationWebhook::setStoreAndSellerInfo()`, which runs only when a merchant completes the OAuth credential-linking flow. Merchants migrated from the old plugin had `checkout_country` (now deprecated) set via a manual dropdown that has since been removed — their `_site_id_v1` was never created and remains empty even though they hold valid credentials and have active sales.

Consequences of an empty `_site_id_v1`:
- `getSiteId()` returns `''`, causing `updatePaymentMethodsBySiteId()` to build the URI `/sites//payment_methods`, which returns a 404 from the Mercado Pago API on every WooCommerce request (visible in the `mp_api_error` Datadog metric as `api_route=/sites/payment_methods`, PSW-4377).
- `checkout_country` is **not** a reliable fallback — the value can be stale for merchants who changed country in the old plugin without re-linking.

Fix (PSW-4377, `src/Configs/Seller.php`): `getSiteId()` lazily calls `GET /users/me` with the production access token if `_site_id_v1` is absent, persists the recovered value via `setSiteId()`, and auto-heals on the first WooCommerce request. `updatePaymentMethodsBySiteId()` guards against calling the API when `siteId` is still empty after recovery (e.g., fresh installs with no credentials). The recovered `site_id` is validated against the supported marketplaces (`Country::isValidSiteId()`) before being persisted, so an unexpected upstream response can never poison `_site_id_v1` nor the `/sites/{siteId}/payment_methods` route. Because `getSiteId()` runs inside `MetricContext::buildBaseMetricDetails()` (metrics are emitted on API errors, including a failed `/users/me`), it sets a negative per-request memo **before** the network call so a metric-triggered reentry short-circuits instead of firing a second `/users/me`.

## Deliberate deviations from standard/team patterns

- **`wp_localize_script()` for PHP-to-JS config bridge**: PHP passes all gateway configuration to JavaScript via `window.wc_mercadopago_{method}_params` (using `wp_localize_script`), not via a REST API call. This is deliberate — it avoids an extra round-trip on checkout load. Code that tries to fetch initial config via AJAX on page load will duplicate data already in the global (verifiable in any `Gateway::__construct()` enqueue call).

- **Notification routing through `NotificationFactory`**: All IPN and Webhook callbacks from Mercado Pago, regardless of payment method, go through a single factory that inspects the payload to route to the correct handler. Adding a new notification type requires registering it in `NotificationFactory`, not just adding a new endpoint handler. This is a deliberate architecture choice to centralise routing logic (verifiable in `src/Notification/NotificationFactory.php`).

- **Metrics sent via Mercado Pago's own Datadog endpoint, not MELI's internal Datadog**: `src/Libraries/Metrics/Datadog.php` posts to `https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog`. Metrics do not go to the standard Fury/MELI observability stack. This is intentional for this plugin since it runs in external merchant environments (verifiable in `src/Libraries/Metrics/Datadog.php`).

- **Three-class pattern per payment method**: Each payment method requires three coupled classes — `{Method}Gateway`, `{Method}Transaction`, and `{Method}Block`. Adding a new payment method that omits any of the three will break Classic checkout, API payload construction, or Blocks support respectively (verifiable by comparing the existing gateway classes).

## Metrics gate (`metrics-review`) — non-obvious operational facts

`.github/workflows/metrics-gate.yml` (PSW-4334) gates PRs to `master` via the `metrics-ok` label. Gotchas:

- **The workflow is not blocking until you make it required.** The check must be marked as a **required** status check in `master` branch protection (UI config, not versioned). Merging the workflow file alone does nothing to block merges.
- **The `metrics-ok` label must be pre-created in the repo.** GitHub refuses to apply a non-existent label, so the label was created in both this repo and `fury_mp-op-pp-woocommerce-scripts`. A fresh clone/fork won't have it.
- **`on: pull_request` runs the workflow file from the PR head merge ref, and `branches: [master]` filters the base.** So the gate can be smoke-tested with a throwaway PR whose head already contains the file targeting `master` — the file does **not** need to be on `master` first. Conversely, a PR to `develop` never triggers it (base doesn't match).
- **New commits invalidate a prior `metrics-ok`.** The `synchronize` step removes the label and fails the run, so approval always reflects the code actually merging. Authorization is only checked on the `labeled` event (the payload has no "who applied the label" on other events), so the label must be treated as untrusted state — this is why `synchronize` clears it rather than trusting persistence.
- **The PR author cannot approve their own PR.** The `labeled` step rejects the label when `sender.login === pull_request.user.login`, even if the author is on the allowlist — otherwise the "someone else looked at the metrics" intent collapses under deadline pressure.
- **`pull-requests: write` works empirically for comments/labels on a PR, but the workflow also declares `issues: write`.** The comment/label endpoints live under the GitHub Issues API namespace; granting `issues: write` removes reliance on the (subtle, undocumented-for-this-case) PR-vs-issue permission distinction for a *required* merge gate. Both scopes are intentional.
- **The allowlist is hardcoded and must be kept in sync with `.github/CODEOWNERS` in both repos.** Deriving it from CODEOWNERS at runtime was rejected on purpose: CODEOWNERS can carry team handles (`@org/team`), path-scoped owners, or emails that a naive `@([\w-]+)` parse would misread, silently broadening an authorization list. An explicit, reviewed list is safer for a security control than one derived from a file editable for unrelated reasons.
- **The gate is self-modifiable under `pull_request`** (the workflow definition comes from the PR head merge ref, not the base branch). A PR that edits `metrics-gate.yml` itself could make the check pass — an in-file guard can't prevent this since the attacker controls the file. The mitigations are platform-level: CODEOWNERS `*` already requires review approval for any `.github/workflows/` change (the bypass needs a human approving a visible diff), and a repo ruleset restricting who can modify `.github/workflows/` is the recommended complement. `pull_request_target` (which would use the base-branch definition) was deliberately avoided per PSW-4334.

## Custom checkout — Luhn & card-token validation (PSW-4338)

- **Luhn needs TWO flags.** The SDK cardForm only runs Luhn when the payment method is `data-validation="standard"` **and** `enableLuhnValidation: true` is set on the `cardNumber` field. `enableLuhnValidation` is absent from the public cardForm docs but works (confirmed in the secure-fields iframe source). It does not cover 100% of cards — only `validation:standard` BINs.

- **`onValidityChange` field reset is scoped to the empty case only.** In `card-form.js`, the reset of derived fields (`clearInputs`/`removeAdditionFields`) fires only when `error[0].code === 'invalid_type'` (empty), NOT for `invalid_value` (Luhn) or `invalid_length` (incomplete) — a Luhn failure keeps a valid BIN, so installments/issuer must be preserved. Widening this back to "any error" causes parcels to vanish on a Luhn typo.

- **Luhn + BIN-race edge (racy path only).** A buyer can finish typing a Luhn-invalid number before `onPaymentMethodsReceived` resolves for the BIN. Sequence: `onValidityChange(invalid_value)` sets `mp-error` on the container → BIN lookup returns → `onPaymentMethodsReceived` success removes `mp-error` (line ~234 in `card-form.js`, pre-existing behaviour). On the next submit the gate reads the class and sees no error, so it passes. The `createCardToken` call then rejects with the Luhn validation array and `callSdkWithMetrics` emits `mp_api_error / invalid_security_fields / reason: cardNumber` — the payment does not go through. Do **not** remove the `mp-error` clear from the success path to "fix" this — that would break BIN-change UX for valid cards.

- **`createCardToken` rejects client-side field validation as an ARRAY, never hitting the API.** When the form is submitted with invalid or empty fields (card number, CVV, expiration, cardholder name, or document), the SDK rejects synchronously before any network call. `callSdkWithMetrics` (`mp-sdk-metrics.js`) classifies this array instead of recording it as the opaque `mp_api_error / value=0 / "Unknown SDK error"` — which is indistinguishable from a real API outage (in production, 93% of `mp_api_error{api_route:createcardtoken}` was this opaque case, ~1.3k/day across 732 sellers).
  - **Two array shapes, validated in STAGES (mutually exclusive per call) — confirmed in the SDK source (`fields/index.fields.ts` `createCardToken`):** stage 1 is a synchronous `validateParams` over the **non-PCI** data (cardholderName / identification); if it has errors it does `Promise.reject(errors)` and **early-returns** — stage 2 (the PCI iframe) never runs. So a single rejection is **either** the non-PCI array **or** the PCI array, **never both**:
    - **PCI secure fields** (stage 2, iframe): `[{cause,message,field,details:{reason}}]` — field via `field`; expirationMonth/Year collapse to `expirationDate`.
    - **non-PCI** (stage 1, `validateParams`): `[{code,message}]` — no field/details; code→field: 221/316 → cardholderName, 212/322/214/324 → document. `cardholderName` + `document` **can** co-occur here (same `validateParams` batch) → both emit `invalid_cardholder_fields`.
    - Consequence: an empty **cardholderName/document masks** the secure-field errors (stage 1 early-returns, so a simultaneously-empty card is never reported in the same call). `cardNumber` and `cardholderName` therefore never appear together.
  - **Metric** (kept in `mp_api_error`, aligned with PSW-3970 / Adobe `ApiErrorCategoryMapper` — no new event): `value='0'`, `api_route='createCardToken'`; `message` = category: `invalid_security_fields` (PCI — only secure fields are "security fields"); `invalid_cardholder_fields` (any non-PCI failure — cardholderName and/or document, including co-occurrence); `details.reason` = the detail the message does not convey (secure → which fields; cardholder → the SDK `error.message` of **all** failing non-PCI fields, so a co-occurring field is never dropped — e.g. an empty cardholderName + a punctuated document that our deterministic document gate passed but the SDK's `identificationNumber` alphanumeric rule `/^[a-zA-Z\d]*$/` rejects (code 324); **unmapped code** → `message` stays the opaque `"Unknown SDK error"` and `reason` = the raw message, so it is diagnosable).
  - **Surgical (does not touch other checkouts):** only `createCardToken` (Custom) both rejects an array AND is wrapped by `callSdkWithMetrics`. Yape (`yape.create` → Error), Super Token (`authorizePayment`/`buildAuthenticator` → AuthenticationError) reject non-arrays; `getInstallments`/`getPaymentMethods`/`getIssuers` reject arrays but are not wrapped. The `#mp_checkout_type === 'custom'` guard scopes the shared wrapper to the Custom flow (new-card-in-Super-Token is also `'custom'`; ST saved-card uses `authorizePayment`).
  - **Backstop, not a gate:** runs after the pre-submit gates; if the racy card gate misses (Order Pay/Blocks read the async `mp-error` class), the token fails and payment does not happen — this only *classifies* the error. A deterministic pre-submit card signal would require migrating to Core Methods (the `fields.create()` API), which is a separate effort.
  - **Known gap — other SDK methods still opaque:** `buildAuthenticator`, `yape.create` and an `N/A` api_route also show `unknown_sdk_error` in production. Each method rejects with a different error shape (AuthenticationError, plain Error, etc.) that requires its own investigation before a safe classification can be added. This is intentionally out of scope here; do not extend `callSdkWithMetrics` to classify these methods without first confirming their reject shapes from the SDK source.

## E2E — store-mutating specs must be tagged `@serial-store`

- A Playwright spec that changes a **store-wide option** — MP credentials (`_mp_access_token_*` / `_mp_public_key_*`), the gateway `subscriptions_*` settings, or the WCS `accept_manual_renewals` / `turn_off_automatic_payments` options — cannot run in the parallel phase: the E2E store and MP sandbox are shared, so a concurrent checkout spec would lose the gateway or fail payment, producing order-dependent false failures until the `finally`/`afterAll` restores state. Tag such specs `@serial-store` (on the `describe`).
- `run-all-report.sh` runs any site that has `@serial-store` specs in **two phases**: phase 1 = everything except `@serial-store` (workers=2), phase 2 = `@serial-store` alone (workers=1). Isolation only holds through that runner (or `--workers=1`) — a plain `npx playwright test tests/<site>/` still runs them in parallel. Keep the store mutation + its restore inside the tagged `describe` (snapshot in `beforeAll`, restore in `afterAll`; or clear/restore inside the test's `try/finally`) so the state is contained even if a run is interrupted.
- Current `@serial-store` specs: `amount_config`, every binary-mode-on card scenario, the binary-mode-off and invalid-credential branches of `refund` (MLB), `credentials_gate` (MLB), currency-conversion specs, and the manual-renewal subscription specs (MLB + per-country). If you add a spec that mutates a shared option, tag it too.

## E2E shared pairs — remote setup is read-only and each staging/homol pair is a unit

- `WP_EXTERNAL_STORE=1` changes the contract of `global-setup.js`: it validates the versioned
  staging/homol target over HTTP and returns without Docker reset, WP-CLI, credentials injection or
  checkout-page rewrites. `wp-env.js` fails closed if any local WP-CLI helper is reached in this
  mode. Do not weaken that guard to make a mutating spec pass.
- `SHOP_URL` and `CHECKOUT_URL` are not free-form overrides. They must exactly match
  `e2e/config/environments.json`, use HTTPS, share an origin and belong to the static host
  allowlist. This is both a parity invariant and the SSRF boundary of the runner. HTTP preflight
  uses `redirect: manual`; do not switch the site-ID fetch to `follow`, because an allowlisted URL
  could redirect the HTTP client through a non-allowlisted destination.
- The immutable site/container shortcut applies only to the complete shared-run tuple
  (`WP_EXTERNAL_STORE=1`, `E2E_REMOTE_MAIN_ONLY=1`, `E2E_ENVIRONMENT=staging|homol`,
  `CHECKOUT=classic|blocks`). Personal external and Super Token stores still discover identity over
  HTTP and must never fall through to local WP-CLI. That fallback reads localized params from
  `/checkout/`, accepts only HTTPS hosts under the static `*.ppolimpo.io` allowlist, follows a
  bounded number of same-origin redirects manually and fails hard when identity is absent. Fetching
  the shop homepage or returning an empty identity silently converts country suites into skips.
- Site identity is read from the versioned Classic checkout URL even during a Blocks run. An empty
  Blocks checkout may not enqueue the localized gateway params that expose `site_id`; requiring
  identity from the selected Blocks URL makes a healthy store fail preflight before cart setup.
- HTTP readiness retries once after a transient timeout/unavailable response. Redirects outside the
  allowlisted origin and site-identity mismatches remain immediate hard failures; do not retry those
  validation errors or turn the preflight into a permissive health check.
- The fixed Blocks route must be the `woocommerce_checkout_page_id`. Payment blocks are not
  registered reliably on an arbitrary page containing the checkout block. The fixed Classic route
  still works through `[woocommerce_checkout]`, so making Blocks official does not require toggling
  this option during runs.
- Candidate ZIP validation is also a resource boundary: besides path traversal and symlink checks,
  keep the entry-count and total-uncompressed-size limits. The 100 MiB file-size limit alone does
  not prevent a ZIP bomb from exhausting the shared host during installation.
- Release preparation has a fixed trust and lane mapping: the productive ZIP comes only from
  `downloads.wordpress.org` after strict version validation, production is installed in homol and
  the locally built RC in staging, and WP-CLI must confirm both installed versions. Do not add a
  caller-controlled URL, environment flag or automatic staging→homol promotion. The paired runner
  records these versions because an unlabelled comparison cannot support a release decision.
  Publication across two WordPress containers is not transactional; if either install fails, the
  pair may temporarily contain different versions. Every dual runner must therefore verify both
  installed versions under the country lease before Playwright starts. Never trust only the version
  labels supplied by the caller or continue after this preflight fails.
- A clean host has no `artifacts/<lane>/current/woocommerce-mercadopago` target. Seed both validated
  version/SHA release directories before `shared-up`; Compose must fail before container creation if
  either lane is missing or invalid. The seed is bootstrap-only—normal publishers own later changes.
  `assertSeededArtifacts` also rejects a lane the container could not traverse, so a `0700` lane
  fails there, naming the directory, instead of as a WordPress boot with a missing gateway.
- Publication and test runs use **two different lock scopes**, and the distinction is load-bearing.
  A test run is a reader of one country, so it keeps the per-country lease that lets the matrix run
  four countries at once. A publish is a writer over the whole lane — it swaps the release for all
  seven countries — so it takes `<lock.name>-publish`. They are kept mutually exclusive by
  **ordering, not by a single global lock**, which would serialise the matrix: the publisher takes
  the writer and only then probes the country leases, while a runner takes its country lease and
  only then probes the writer. For both to proceed, the publisher would have to see a country free
  after the runner took it while the runner saw the writer free before the publisher took it, which
  the ordering forbids. Both may back off; neither can overlap. Do not "simplify" this by dropping
  either probe or by reusing a country lease for publication.
- Shared runs use the **per-country** remote lease. This permits different
  countries to overlap but prevents publish/test or Classic/Blocks from racing on one store pair.
  The canonical release holds a single lease from the first publication until both checkout modes
  finish and renews it every 60 seconds; granular publish/test commands use the same heartbeat for
  their own bounded runs. Acquire/renew/release are serialized remotely with `flock`. Renewal makes
  up to three bounded SSH attempts in the same heartbeat to absorb a transient connection failure;
  token mismatch fails immediately. Exhausted renewal is fail-closed: terminate active process
  groups and persist both lanes as `LEASE_LOST`, never accept results that may overlap a new owner.
  A stale lease from a crashed runner is reclaimed
  only after its bounded TTL; do not add a `--force-unlock` escape hatch to the developer CLI.
  Publish complete lock metadata by atomic directory rename, renew `acquired_at` by atomic file
  rename and detach the whole directory before release. An incomplete legacy lock is recoverable
  under the guard; never leave a newly acquired lock visible before token/owner/timestamp exist.
- On Unix, each lane is spawned as its own process group so an interrupt terminates Playwright and
  its shell/npm descendants before releasing the lease. Killing only the immediate bash child
  leaves browsers running after the lock is free and permits overlapping runs. Wait for `close`
  after `SIGTERM`, with a bounded `SIGKILL`, before releasing the lease.
- The target matrix has 14 persistent stores and runs on the ARM64 `big-shared-xlarge`
  (`t4g.xlarge`, 4 vCPUs/16 GiB). Images must be rebuilt on that host instead of reusing x86 layers.
  `shared-up` must pass `--build` while starting every WordPress pair; rebuilding only Caddy and
  recreating the stores leaves entrypoint/runtime fixes unapplied in stale images. The host-side
  validation also requires Node.js `>=20.19`, `npm` and PHP CLI.
  Keep one Playwright worker per lane. The matrix may schedule at most four countries (eight lanes)
  at once, starts the historically longest countries first and must serialize Classic/Blocks within
  each country; benchmark CPU, memory, swap and latency before raising that hard limit or changing
  the execution order.
- Caddy blocks public `wp-login.php`, `wp-admin`, `xmlrpc.php` and `/wp-content/debug.log`, but must keep the exact
  `/wp-admin/admin-ajax.php` route ahead of that matcher: Classic WooCommerce frontend requests use
  it, and blocking the broader admin prefix first silently breaks checkout. Block both exact paths
  and `PATH_INFO` variants (`/wp-login.php/*`, `/xmlrpc.php/*`, `/wp-content/debug.log/*`). Shared
  boot must also force WordPress debug/log/display off and delete a log left in a persistent volume;
  the proxy rule is defense in depth, not permission to keep producing public-volume logs. Because
  `Caddyfile.e2e` is bind-mounted, updating it does not make the long-running Caddy process reload
  the file; `shared-up` must force-recreate `caddy-e2e` before the live boundary probes.
- Never execute candidate plugin code as root in a shared container. `/wp-cli.sh` intentionally
  preserves `--allow-root` only for the local developer mode; shared setup, publication and version
  verification run as `www-data`. Root may repair the narrowly scoped plugin/upgrade ownership
  before installation, but the WP-CLI command that loads or activates the plugin must remain
  unprivileged. Persistent volumes created before this rule can leave `wp-config.php` owned by
  `root`; the entrypoint must temporarily transfer that single file to `www-data` for the debug
  constant migration and immediately restore `root:www-data` with mode `0640`. Omitting either side
  of that transition causes a restart loop or leaves the web process able to modify configuration.
  Old stores can also contain both a custom `WP_DEBUG=true` and WordPress's default definition;
  `wp config set` alone can update only the later occurrence. Delete every duplicate debug constant
  before recreating its single canonical `false` value.
- When `SMOOTH_PK_PATH` is present, the SSH option builder must add `IdentitiesOnly=yes` together
  with `-i`. Without it, OpenSSH may offer agent identities before the approved key, causing
  intermittent authentication failure or using an unintended identity. Agent-only access remains
  supported when no explicit key path is configured.
- Every SSH/SCP operation requires `SMOOTH_KNOWN_HOSTS_PATH` with an owner-verified entry for the
  configured host and `StrictHostKeyChecking=yes`. Do not restore `accept-new`, a disposable empty
  file or blind `ssh-keyscan`; those reintroduce first-connection trust. Reject non-regular, empty,
  oversized or group/other-writable trust files before spawning OpenSSH.
- The first remote POC excludes `@serial-store`. Enabling it before snapshot restore is implemented
  can corrupt both lanes and invalidate every subsequent comparison. Keep the exclusion on the
  failed-test rerun as well; retry must not silently expand into state-mutating coverage.
- Visual regression must compare the same theme on both sides. A homol theme A screenshot against a
  staging theme B screenshot mixes two variables and cannot identify a plugin regression.
- Editing `deploy/secrets/<site>.env` does not alter the environment of existing containers. Rotate
  the country file atomically and force-recreate that staging/homol pair. `/sync-runtime-config.php` then
  reads credentials from the process environment and persists them without putting values in
  WP-CLI arguments or logs. Never revert to `wp option update "$TOKEN"`: the token becomes visible
  in the process argument list. `WP_ADMIN_USER` remains stable after the first bootstrap; only its
  password/email and the mapped MP credentials are rotation-safe.
- `/sync-runtime-config.php` is shared-only. Running the common image locally must not replace admin
  choices, custom domain, test mode or Mercado Pago credentials on every boot. Shared sync consumes
  only test keys, clears stale site-method state and retries the complete cache refresh up to three
  times before failing closed.
- The WordPress bootstrap URL is local by design, but every shared-container start must replace
  `home` and `siteurl` with the allowlisted `MP_CUSTOM_DOMAIN`. Otherwise public pages emit
  `localhost` asset/navigation URLs even though Caddy returns HTTP 200.
- Runtime credential sync must refresh both credential-based and site-based payment-method caches
  and fail closed if the site list remains empty. Seller capabilities can still legitimately be
  absent: record those in `e2e/config/shared-capabilities.json` so the remote suite produces an
  explicit expected skip. Revalidate the manifest whenever a seller changes.
- WooCommerce locale installation is also shared-only. Local/personal stores must not acquire a
  fatal WordPress.org dependency on boot; shared downloads/fallback symlinks must remain owned by
  `www-data` so later unprivileged WP-CLI updates work.
- Never diagnose shared secrets with plain `docker compose config`, `docker inspect`, `env`,
  `printenv` or shell tracing. Docker access can expose container env vars, so SSH/Docker access is
  also credential access. The safe checks are `validate-shared-secrets.sh` and
  `docker compose ... config --quiet`.
- The live monitor is deliberately metadata-only. Its Playwright reporter may persist test title,
  source location, retry/final status and aggregate counters, but never error messages, stdout,
  attachments, environment variables or API responses. Keep `E2E_PROGRESS_FILE` confined to the
  exact `results/dual/<24-hex-run-id>/<staging|homol>/progress.json` shape, write atomically with
  mode `0600` and treat malformed/oversized state as unavailable rather than rendering it.
- A missing, corrupt or zero-scenario Playwright JSON is an unavailable lane, never an empty valid
  comparison. Always write `report.md` and `comparison.json` with `valid=false` and the affected lane.
- Comparison versions are mandatory even for granular/matrix diagnostics. Equal versions require
  the explicit `PARITY_VALIDATION=1` escape hatch and must remain `releaseEligible=false`, with
  `PARITY_*` classifications that never imply candidate causality. The canonical release path must
  continue rejecting equal versions. For real release comparisons, split flaky outcomes by
  candidate-only, baseline-only and both-lanes instability, and expose every `EXPECTED_SKIP` as a
  coverage limitation. Direct `/checkout-*` entry proves checkout behavior but does not cover the
  cart→checkout transition.

## Super Token CDN — the `v1/`/`v2.1/` folder↔variant map is externally frozen (PSW-4417)

- **Already-shipped loaders permanently map `v2 → v1/` and `v2.1 → v2.1/`.** Plugin versions through 8.9.3 embed a Super Token loader whose `SUPER_TOKEN_VARIANT_FOLDER` fixes that mapping; those loaders are live in stores and cannot be changed. The CDN retrocompat bundles served at `v1/` and `v2.1/` are the refactored runtime built with the A/B variant **frozen per path** (`ST_FIXED_VARIANT=v2` for `v1/`, `=v2.1` for `v2.1/`, injected via webpack `DefinePlugin` — see `build:super-token:webpack`). This mirrors the loader's map on purpose.
- **Changing either pin, or swapping which folder a variant is built into, silently serves the wrong runtime to stores on 8.9.3 or earlier** (e.g. `v1/` rendering v2.1) — no error, no metric, just the wrong checkout UI. `v1/` must stay pinned to `v2` and `v2.1/` to `v2.1` for as long as those versions have traffic. Even under the A/B kill-switch (`active:false`) the loader fetches the `default`'s folder, which serves the right variant through this same pin — so the invariant is what keeps the kill-switch correct too.
- The future single-path `mp-super-token/` bundle resolves the variant at runtime (`VariantConfigAdapter`) and carries **no** pin. Its production cutover was reverted from 8.9.3 and remains TASK-013; only the current per-path bundles are frozen.
- Each per-path CSS artifact must retain `--mp-super-token-loader-version` with the same value injected into its JS runtime. The release scripts verify this stamp; compiling the SCSS without prefixing the version silently breaks JS/CSS parity checks even when the visual CSS is equivalent.

## Accessibility of the card form — attributes that break in non-obvious ways (PSW-4232)

- **`aria-describedby` is announced even while the referenced element is hidden.** The reference to an error message must be toggled with the error state, never left in place — a permanent reference makes the screen reader read the error on every focus, with no error present. What must survive both states goes in a separate slot: `describedByAlways` in `CheckoutPage.A11Y_ERROR_TARGETS`, and `InputDocument.INSTRUCTION_ID` in `setDocumentValidity`.
- **A field instruction must be `aria-describedby`, never `aria-label`.** `aria-label` replaces the accessible name, so an instruction written as a label announces "Enter the 11 digits…" instead of "Document number", destroying the association the `<label for>` provides.
- **`aria-describedby` displaces the `placeholder`.** Most screen readers stop announcing the placeholder once the field has a description, so any format hint that lived only there must be re-exposed as text (`#mp-card-holder-name-example`). Adding the description alone is a net regression.
- **`.mp-sr-only` must keep the clip-rect technique.** `display:none` and `visibility:hidden` also remove the element from the accessibility tree. It is scoped under `.mp-checkout-container`; anything using it must live inside that wrapper or the text renders visibly.
- **`aria-invalid` is derived from the resulting DOM classes, not from the operator passed to `setDisplayOfError`.** The two error classes are swapped across focus transitions (`mp-error` ↔ `mp-error-2px`), so a single call does not tell whether the field ended up valid. Only `mp-error`/`mp-error-2px` mean invalid; `mp-label-error*` are cosmetic and must not flag the field.
- **The label component must render a `<label>`, not a `<div>`, and selectors must key on the class (`.mp-input-label`), not the tag.** A tag-coupled selector makes the document label stop turning red on error **with no visible symptom** until someone enters an invalid document.
- **Fixed-length documents declare only `max_length` and omit `min_length`** (`CPFHandler`, `CNPJHandler`); `RUCHandler` declares both with the same value; every other handler declares a real range. Treating an absent `min_length` as a range produces "Enter between **undefined** and 11 digits". Build test cases from the real handlers — a hand-written `{ min_length: 11, max_length: 11 }` config does not exist in the codebase and hides the bug.
- **The SDK secure fields (card number, expiration, security code) can only be described through the `<iframe title>`** (WCAG technique H64). The iframe element is ours; only its content is cross-origin. `srLabel`/`ariaRequired` exist in `mp.fields.create()` and the iframe already consumes them, but `cardForm` discards them — PSW-4458 / CHOAPIGIN-2815. The title is brand-specific (Amex is 15/4 instead of 16/3), so `clearCardState` must reset it alongside the detected-brand announcement, or it keeps describing a card that is gone.
- **`setSecureFieldInstructions()` belongs in the card form promise's `.then()`, not in `onReady`.** Three callbacks resolve that promise (`onReady`, `onFormMounted`, `onFormUnmounted`), which reads like a race, and automated reviewers flag it as one. It is not: `onFormMounted` fires **first** and the iframes already exist when it does — measured on a live store, the titles land **1 ms** after the mount event, while `onReady` only fires ~340 ms later. Moving the call into `onReady` therefore delays the titles by that margin and makes them depend on `onReady` firing at all, where the `.then()` only depends on whichever callback fires first. Writing `title` on the iframe element is a parent-document operation, so the iframe's content does not need to be ready.
- **The SDK keeps the `cardForm.form` wrapper after its Secure Fields are unmounted.** Object existence therefore does not prove that `form.update(field, { invalid })` is safe. During `onFormUnmounted`, `formMounted` is set to `false` before `clearInputs()` removes the three error classes; that cleanup still flows through `setSecureFieldValidity`. The SDK does not throw for the missing fields — it writes `cardNumber` / `cardExpirationDate` / `securityCode not found` warnings internally — so the surrounding `try/catch` cannot suppress them. Keep the strict `formMounted === true` gate before forwarding `aria-invalid`; the container-side `aria-describedby` cleanup must still run while unmounted.

- **A reported digit length of `0` must fall back to the default, not be substituted.** The payment method can report `security_code.length === 0` (observed in MLC, PSW-4345); a nullish check lets it through and leaves the `{digits}` placeholder unreplaced for the screen reader to read out loud. `setSecureFieldInstruction` uses a falsy check for that reason.
- **`input-document` is mounted more than once per page in Classic and Order Pay** (Custom plus whichever of PSE/Ticket the country enables; Blocks renders one at a time and unmounts the previous). **ARIA references cannot be scoped to a container**, unlike every JS consumer, which scopes its lookup to its own gateway (`getInputHelperElement`) — which is why the duplication was harmless before ARIA existed here. The helper keeps its shared `input-id` for those selectors and carries a per-instance `id` used only by ARIA; changing `input-id` breaks the selectors and the R-1 submit gate.
- **`elementId()` keys on `select-id`, which is *not* unique by construction — the ids only stay distinct because the gateways that share the value are mutually exclusive by country.** Three templates pass the literal `select-id='doc_type'`: `pse-checkout.php`, `ticket-checkout.php` and `ticket-address-container.php`. They never coexist, and the reason is a country gate in each one — PSE is MCO-only (`PseGateway::isAvailable`), the Ticket's own document field is MLU-only (`ticket-checkout.php`, `$site_id === 'MLU'`) and the address container is MLB-only (same file, `$site_id === 'MLB'`). Measured across MLB, MCO and MLU in all three modes: exactly one `doc_type` component per page, every `aria-labelledby`/`aria-describedby` resolving inside its own component, and one label per select. In MCO the Ticket box renders with **no** `input-document` at all. **Treat this as a coincidence of configuration, not an invariant:** enabling PSE outside MCO, or giving any other gateway a document field with the same `select-id`, silently makes the second component's `aria-labelledby` and `aria-describedby` resolve into the **first** one (so its error is announced from a helper that has no error), and leaves its type select with **zero** associated labels, hence no accessible name. That failure mode is reproducible in a unit test today by mounting two components with the same `select-id`; it just has no template combination that produces it. A genuinely unique instance key is the fix, and it cannot be derived from `input-id` — the Custom template does not pass one.
- **The ids that *do* duplicate today are inert.** `form-checkout__identificationNumber-container`, `mp-doc-number-helper`, `mercadopago-utilities`, `mp-box-loading` and `site_id` repeat 2–4× per page (PSE reuses `input-id="mp-doc-number-helper"` on helpers outside the document component too). Measured: none of them is the target of any `aria-describedby`, `aria-labelledby` or `for`, so they cost nothing in the accessibility tree — do not justify cleaning them up as an accessibility fix.
- **The document field has two inputs, and only one of them belongs in the accessibility tree.** `#form-checkout__identificationNumber` is `type="hidden"` and carries the normalized submit value — `verifyDocument`, `clearInputs` and the gate's reason classifier read it correctly, but an `aria-*` write there has no effect at all. The control the buyer navigates is the visible one, and it must be selected by its own class (`.mp-document`), scoped to the Custom container: it has **no id**, its `name` is swapped to the `flag-error` value by `setInvalidState` while the document is invalid and not empty, `data-cy` is a test hook and `data-checkout` belongs to the SDK. The submit gate is the only path that marks this field without the buyer having typed, so `setInvalidState` never ran — that is why the wrong target went unnoticed.

- **The document number input has no `<label>` of its own.** The visible label is associated with the *type select* (`for`), because the field is a compound control; the number input borrows it through `aria-labelledby`. Removing that downgrades the field's accessible name to the mask placeholder ("999.999.999-99").
- **`InputDocument` is the sole owner of that field's `aria-describedby`.** Its `A11Y_ERROR_TARGETS` entry declares no `describedBy*` key and `setControlValidity` returns early for such targets. A fixed id there clobbers the description the component writes, and resolves across gateways.
- **The red asterisk in a label means nothing to a screen reader** — it is a `<b>*</b>` announced as "star". The required state is declared with `aria-required` on the control, and the reader announces it in **its own language**, not the page's (pt-BR VoiceOver says "necessário"), so never assert a specific word in a manual script without checking the reader's locale. The five controls that are ours declare it; the three inside the SDK iframe cannot (PSW-4458). **Do not hide the asterisk from the accessibility tree** while that holds — for those three it is the only required signal there is.
- **The pre-submit gates run in order: card → installments → document.** Only the first failing gate paints its field, so the document label turns red only when everything before it passes. Separately, while the buyer types, `setInvalidState` adds `mp-label-error` and `clearDocumentLabelErrorOnInput` removes it on the same keystroke — the red label is a submit-time state, not a typing-time one.
- **Two paths write the error classes without going through `setDisplayOfError`, so they have to sync the accessible state themselves.** `toggleErrorBorder` writes them directly (cardholder name, installments) and `InputDocument`'s type-change handler used to clear them inline. Left alone, the first leaves the field announced as **valid while visibly red**, described by the informative helper whose Spanish text is nearly identical to the error one — the buyer only gets a signal when the payment attempt fails. The second leaves it announced as **invalid after the value was cleared**, described by a message that is no longer on screen. The cardholder name syncs in `updateCardholderNameState`, the funnel both of its transitions pass through; the document field pairs `clearErrorStates` with `setDocumentValidity`, which is what the focus-out path already did.

- **`role="alert"` belongs to the error helper only; supporting text uses `role="status"`.** `alert` is assertive and interrupts whatever is being announced. The exposing path is not obvious: while the buyer types an **invalid card number**, `onValidityChange` reveals the **cardholder name** info helper (`card-form.js`), so an `alert` there cuts off the keystroke echo to read a hint belonging to another field. A single `role` for all types reads as a simplification and brings the interruption back.
- **The VoiceOver cursor (`Ctrl+Option+Arrow`) does not leave the card number field; `Tab` does.** Reproduces on `develop`, so it is not caused by the accessibility work. Use `Tab` to traverse the three iframes — it works in both directions.
- **Safari truncates the error announcement on the three iframe fields; Chrome reads it in full.** Same markup and same attribute writes, so it is a browser difference: Safari's VoiceOver bridge preempts a pending live-region utterance to restore the focus context, and no ARIA attribute suppresses that. `role="status"` is not the fix — polite may never be spoken, and on field change it lands after the next field's name, ambiguous about which field failed; firing only on blur competes with the next field's focus event. The message stays visible, Chrome reads it fully, and entering the field announces the description through the container's `aria-describedby`.

## Funnel entity subclass — a typed property here is a fatal error once the SDK adds it

`src/Funnel/UpdateSellerFunnelBase.php` extends the SDK entity to declare fields the installed SDK
does not have yet. It exists because `AbstractEntity::__set()` guards on `property_exists()` and
returns without error, and `toArray()` (which builds the JSON body) enumerates `get_object_vars()`
— so assigning an undeclared field is **discarded in silence**, with no exception, no log and no
metric. A `public` property on the subclass sidesteps `__set()` entirely and is picked up by
`get_object_vars()`, so the field travels.

The trap is the type hint. The SDK declares its properties **untyped**, and in PHP a typed child
property over an untyped parent one is a fatal error:

```
PHP Fatal error: Type of MercadoPago\Woocommerce\Funnel\UpdateSellerFunnelBase::$is_subscription_enabled
                 must be omitted to match the parent definition
```

So every property added here is a **countdown**: it works only until the SDK declares the same
field, and the day someone bumps the pin it breaks the plugin at autoload. This already happened
— `is_subscription_enabled` was declared locally on 22/05/2026 and the SDK shipped it in 3.6.0 on
01/06/2026, which is what kept the pin stuck at 3.5.0 for two releases. Same story for
`email`/`country` and SDK 3.7.0.

When bumping `mp-plugins/php-sdk`, check every property left in this subclass against the parent
and delete the ones the SDK now owns (the assignment keeps working — `__set()` finds the property
on the parent). `tests/Funnel/FunnelTest.php` catches the collision in CI before merge, because
`Mockery::mock(UpdateSellerFunnelBase::class)` triggers the autoload — but the error message points
at the subclass line, not at the bump, so the diagnosis is easy to misread.

## Funnel — a nested step that fails still emits a success event

`Funnel::runWithTreatment()` catches, reports and **does not rethrow**. That is deliberate — a
funnel failure must never block activation or checkout — but it has a consequence that only shows up
when a step is nested inside another one's `$after` callback.

`updateStepSellerContact()` runs inside `create()`'s `$after`, which runs inside `create()`'s own
`runWithTreatment`. So when the inner step fails:

```
create()
└── runWithTreatment(A)                    ← outer try/catch
    ├── saves the entity, persists installation_id/key
    └── $after()
        └── updateStepSellerContact()
            └── runWithTreatment(B, sanitizedError: <generic message>)   ← inner try/catch
                └── throws → logs, sendErrorEvent(), does NOT rethrow
    ↑ $after() returns normally, A completes → sendSuccessEvent()
```

Datadog receives **`funnel/error` followed by `funnel/success` for the same activation**. Nothing is
broken — activation completes and the funnel record exists, only `email`/`country` are missing — but
a dashboard counting `funnel/success` counts that activation as fine. The only way to tell which
step failed is the message text, and for the seller-contact step that text is deliberately generic
(`$sanitizedError`, because the SDK exception can echo the request body with the email). So the
attribution is by convention, not by structure.

This is not specific to the contact step: **any** step chained through an `$after` inherits it.

**There is no retry, and reordering does not add one.** The instinct is to move `$disableActivate()`
to after the contact step so `_mp_execute_activate` stays on and the next page load tries again. It
does not work: `installation_id`/`installation_key` were already persisted by then, so `created()`
is true and `activatePlugin()` takes the early branch (`updateStepActivate()` + `return`), never
reaching the contact step. The one-shot behavior is structural — a real retry needs its own
condition, separate from `created()`.

**And `catch (Exception)` does not catch `Error`.** If `$GLOBALS['mercadopago']` is not mounted, the
log line itself throws `Error: Call to a member function error() on null`, which escapes both the
inner and the outer catch. In practice it does not happen — `activatePlugin()` runs after the plugin
bootstrap sets that global — but it is the one way a funnel failure stops degrading silently and
takes activation down with it.

## Funnel — the installer's email is unreachable, so `admin_email` is the source

`Funnel::getStoreEmail()` reads `get_option('admin_email')`. The obvious "improvement" is to send the
address of whoever installed the plugin — `wp_get_current_user()->user_email` — since that is the
person the contact campaign actually targets. **It returns an empty address most of the time.**

Activation and the funnel call are two different requests. `mp_register_activate()` only writes the
`_mp_execute_activate` flag; what consumes it is `add_action('init', ..., 1)`, on **whatever request
reaches the store next** — a cron tick, a REST call, a bot hitting the homepage. There is no logged-in
user in those, so `wp_get_current_user()` yields ID 0.

Capturing the installer at activation time and stashing it until the send would mean **persisting the
email in `wp_options`** — the exact thing this feature avoids (CWE-312), and what
`testGivenSellerContactStepRunsThenTheEmailIsNeverWrittenBackToTheStore` now forbids.

So the choice is between the addresses already stored by the platform, and `admin_email` is the only
one WordPress guarantees to exist (it is required at install). `woocommerce_email_from_address` can be
empty and is usually a role account.

**The known cost:** on a store built by an agency or a freelancer, `admin_email` is often the
developer's address rather than the merchant's. Accepted deliberately — reaching the store's
administrative address beats having no address at all. If the contact base turns out to be low
quality, the fix is not another option key: it is asking the seller for the address, which is a
product decision, not a code one.

## `getPluginDefaultCountry()` gates payment methods, not just the funnel

`Country::siteIdToCountry()` ends in `?? self::COUNTRY_CODE_MLA` — every value it does not
recognize answers `AR`, and `getPluginDefaultCountry()` prefers the persisted `_site_id_v1` over
the store's WooCommerce country without validating it. A stored value outside the seven supported
site ids therefore makes the store claim to be Argentine.

**No ticket ever specified this fallback**, so do not read intent into it. It arrived as a bare
`'AR'` literal in a links-and-translations PR (2022), became a constant lookup inside a
currency-helper refactor (2023), and took its present `??` form in a unit-testing task (2025) that
also added `testSiteIdToCountryDefault` — a characterization test pinning what it found, not a rule.
Note the asymmetry: the mirror function `countryToSiteId()` answers `''` for an unknown country.

It was reviewed in PSW-4249 and **kept deliberately** — not because it is right, but because
changing it is wider than it looks.

The reason it deserves care is reach: `PixGateway`, `PseGateway` and `YapeGateway` decide
`isAvailable()` by comparing this return to `MLB`/`MCO`/`MPE`, and `Hooks\Gateway::registerGateway()`
only adds the class to the `woocommerce_payment_gateways` filter when it is available. **Changing
this helper changes which payment methods a store is offered**, so a store with a corrupted
`_site_id_v1` currently has no PIX at all — absent from the settings screen, not merely hidden.

Validating with `isValidSiteId()` before trusting the stored value would fix that, and the guard
already exists. Three sources agree the supported set is exactly those seven — the constants here,
`overview.md`, and the public plugin documentation, which lists `AR BR CL CO MX PE UY` under
"Disponibilidade por país". The Core stores `siteId` as free text with no enum, so it adds no
constraint either way. **There is no known legitimate value outside the seven**, which means the
only way an unsupported one reaches `_site_id_v1` is corruption or an unvalidated write — and
`IntegrationWebhook::setStoreAndSellerInfo()` is exactly that: it persists whatever the credentials
API returns, without checking, unlike the `/users/me` recovery path in `Seller::getSiteId()`.

So the guard is very likely simply correct, and the reason it was not applied in PSW-4249 is scope,
not risk: that delivery was about the onboarding funnel and had no business changing which payment
methods a store is offered. Whoever picks this up should still confirm the reach on the three
gateways rather than assume it.

## Funnel — `site_id` is resolved in one place, and guards the AR fallback locally

`create()` and `updateStepCredentials()` both send `site_id`, and they had drifted: the first
normalized the empty string to `null`, the second sent `''`. The same store reported two different
values for "unknown" depending on which step ran, and `contracts.md` documented only the normalized
behaviour. Both go through `Funnel::resolveSiteId()` now; a third sender must use it too.

That resolver also refuses to trust a persisted site id outside the seven, so such a store is
reported with its own country instead of `MLA` (see the `getPluginDefaultCountry()` trap above),
whatever put the value there. **The guard is deliberately local to the funnel**: the same
helper decides which payment methods a store is offered, and correcting it there would change
checkout behaviour — which the delivery that added this had no business doing. Anything that needs
the corrected value today has to go through `resolveSiteId()`.
