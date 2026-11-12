# useCases/

Application use cases — orchestrate the domain (`core/`) through the **ports**,
never touching concrete platform code directly.

Populated by **TASK-006**: authorize payment, select payment method, update
security code, reset-on-error (migrated from `MPSuperTokenAuthenticator`,
`MPSuperTokenTriggerHandler`, `MPSuperTokenErrorHandler`).

`CoordinateAccountPaymentMethods` serializes different buyer/amount identities, but an explicit
checkout invalidation aborts every queued generation and releases the tail immediately. The abort
signal is propagated through `GetAccountPaymentMethods`, which checks it after each asynchronous SDK
boundary before storing the shared authenticator or fast-payment token.

Depends on: `core/` and `ports/` (interfaces only).
