# Smart Pix notification fixtures

Synthetic fixtures for the Core P&P notification route of Smart Pix. Their shape is based on
operator-reported masked samples; they are not a provider contract. The formal notifier contract
is still pending external confirmation, so these files document the shape the integration expects
today and must be revisited once a versioned source exists.

All values are synthetic: identifiers use the `SYNTHETIC` marker, the attempt reference is
`WC-321`, amounts are `125.50` BRL, and no personal data (documents, emails, payer data) is present.

## Layout

- `posted/` — bodies posted to the gateway webhook. Only `notification_id` is read from them; any
  other posted field must never decide state.
  - `core-posted-notification-id.json` — Core object form with a valid PPORD id.
  - `core-posted-raw-string.json` — Bifrost form: a JSON string with the PPORD id only.
  - `core-posted-forged-financial-fields.json` — valid PPORD id plus forged status, amounts,
    external reference and Orders reference that must be ignored.
  - `core-posted-hyphenated-id.json` — hyphenated PPORD id, not a Smart Pix id.
  - `core-posted-unknown-id.json` — id with an unknown prefix, not a Smart Pix id.
- `fetched/` — envelopes returned by `GET /plugins-platforms/v1/notification/order/{id}`. Only the echoed id,
  `transaction_type`, `external_reference` and `payments_details[0].references.order_id` are read;
  status and amount fields are never a decision input. The echoed `PPORD` id is the Core Order id
  used for linkage; `references.order_id` (`ORD…`) only validates the envelope shape.
  - `pp-order-approved-processed.json` — processed `pp_order` for the linked attempt.
  - `pp-order-failed-status-detail.json` — failed `pp_order` with `status_detail`.
  - `pp-order-processing.json` — `pp_order` still processing (late or out-of-order delivery).
  - `pp-order-mismatched-id.json` — echoes another notification id than the requested one.
  - `pp-order-wrong-transaction-type.json` — `payment` transaction type instead of `pp_order`.
  - `pp-order-other-store-reference.json` — `external_reference` with another store prefix.
  - `core-error-response.json` — error body returned with a non-2xx status; its text must never
    be echoed in the notification response.
- `orders/` — normalized Orders query answers in the `smart-pix-internal/v1` contract, loaded by
  `MockSmartPixOrderClient` with this directory as its fixtures root.
  - `notification-query-terminal-success.json` — approved, terminal success.
  - `notification-query-processing.json` — still processing, no terminal decision.
  - `notification-query-terminal-failure.json` — rejected, terminal failure.
  - `notification-query-retryable-failure.json` — rate-limited, retryable failure without a
    terminal decision.

All fixtures are plain `.json` files. `posted/` and `fetched/` hold raw Core shapes, not the
internal contract, so the contract gate in `tests/JS/smart-pix/contract-schema.test.js` skips both
directories; `orders/` is still validated against `contract.schema.json` like every other fixture
under `tests/SmartPix/fixtures/`.
