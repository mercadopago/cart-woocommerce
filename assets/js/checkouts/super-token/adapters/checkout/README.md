# adapters/checkout/

Checkout-environment adapters: `ClassicCheckout` and `BlocksCheckout` — wire the
use cases into WooCommerce's Classic (`event-handler.js`) and Blocks
(`custom.block.js`) flows.

Populated by **TASK-009** (finalization), which also removes the direct `window.*`
reads from the Classic/Blocks consumers now kept alive by the bridge.

Depends on: `useCases/` and `ports/`.
