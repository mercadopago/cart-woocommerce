/**
 * Ported `MPDebounce` (v2.1/entities/debounce.js) — the input debounce the Super Token e-mail
 * listener uses to avoid re-fetching saved cards on every keystroke while the buyer types.
 *
 * Leaf collaborator (no state beyond the per-invocation timeout, no use case). Faithful 1:1 port
 * of the legacy class, kept as a standalone module so the flip bootstrap can construct it and hand
 * it to `SuperTokenEmailListener` — replacing the legacy `new MPDebounce()`.
 */
import type { EmailInputDebounce } from '@super-token/adapters/runtime/SuperTokenEmailListener';

export class SuperTokenDebounce implements EmailInputDebounce {
  private readonly DEBOUNCE_TIME = 3000;

  inputDebounce(callback: (inputEvent: unknown) => void): (inputEvent: unknown) => void {
    let inputTimeout: ReturnType<typeof setTimeout>;

    return (inputEvent: unknown) => {
      clearTimeout(inputTimeout);

      inputTimeout = setTimeout(() => callback(inputEvent), this.DEBOUNCE_TIME);
    };
  }
}
