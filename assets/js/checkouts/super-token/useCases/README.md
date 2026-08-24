# useCases/

Application use cases — orchestrate the domain (`core/`) through the **ports**,
never touching concrete platform code directly.

Populated by **TASK-006**: authorize payment, select payment method, update
security code, reset-on-error (migrated from `MPSuperTokenAuthenticator`,
`MPSuperTokenTriggerHandler`, `MPSuperTokenErrorHandler`).

Depends on: `core/` and `ports/` (interfaces only).
