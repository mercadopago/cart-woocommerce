# core/

Domain center of the hexagon — pure logic with **no** dependency on the DOM, the
SDK, WooCommerce, or `window.*`. Everything else points inward, to here.

Extracted by **TASK-006** (PSW-4270) from the legacy `MPSuperTokenPaymentMethods`
(`v2.1/entities/super-token-payment-methods.js`), preserving the business rules
(RN-NEG) exactly. The core produces plain data; rendering and escaping are the view's
job (TASK-008). It is not yet wired into any bundle — consumed by TASK-008/009.

Depends on: nothing but other `core/` modules and the shared types in `../types/`.

## Layout

- `constants.ts` — domain invariants (method types, caps, disclaimer sites). Not config.
- `config.ts` — `SuperTokenDomainConfig`: the exact slice of localized store config the
  domain needs, injected by the composition root (the core never reads params directly).
- `shared/formatting.ts` — pure currency formatting (`Intl`).
- `checkoutSession/`
  - `PaymentMethodClassifier.ts` — type predicates + `paymentMethodIdentifier` (RN-6).
  - `PaymentMethodCatalog.ts` — ordering + `MAX_CREDIT_CARDS` cap (RN-1).
  - `SuperTokenState.ts` — session state + per-error retry counter (RN-2).
  - `PaymentMethodEligibility.ts` — CVV/ESC re-fetch rules; the DOM double-check guard is a parameter (RN-3).
  - `ErrorClassification.ts` — error-code catalog + pure error-message resolution (single source).
- `paymentMethods/`
  - `BasePaymentMethod.ts` / `BasePaymentMethodWithInstallments.ts` — common behavior;
    installments belong only to the second base (RN-8, RN-4/RN-5).
  - `CreditCardMethod`, `ConsumerCreditsMethod` (with installments), `DebitCardMethod`,
    `AccountMoneyMethod`, `PrepaidCardMethod`, `NewCardMethod` — one module per method (RN-7).
  - `registry.ts` — `resolve(pm)` → the owning module; `resolve(pm).decorate(pm)` is the
    pure equivalent of the legacy `normalizeAccountPaymentMethods`.
