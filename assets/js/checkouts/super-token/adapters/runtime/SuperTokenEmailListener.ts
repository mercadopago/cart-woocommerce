/**
 * Ported `WCEmailListener` (v2.1/entities/email-listener.js) — the buyer-email collaborator the
 * Super Token trigger handler holds as `wcEmailListener`. It owns e-mail validation, reading the
 * current e-mail from the checkout form, and the change-listener registration that lets the flow
 * re-fetch saved cards when the buyer switches accounts mid-checkout.
 *
 * It is a leaf collaborator (state `_callbacks` + DOM/e-mail primitives), so — unlike
 * `SuperTokenErrorHandler` — it delegates to no use case; it is a faithful port of the legacy
 * class. The external contract the trigger handler consumes (`isValid`, `getEmail`,
 * `onEmailChange`, `setupEmailChangeHandlers`) is preserved 1:1.
 *
 * jQuery and the input debounce are injected (with a `window.jQuery` fallback), matching the
 * tree's platform-adapter pattern (`InitializationHealthChecker`): faithful to the real
 * WooCommerce jQuery at runtime, unit-testable in jsdom without loading real jQuery.
 *
 * Part of the port-then-flip deletion of `v2/`/`v2.1/`: inert until the flip (not yet constructed
 * at runtime; `.ts` is invisible to the CDN bundle concat), unit-tested for parity with the legacy
 * class. At the flip the bundle bootstrap constructs it and hands it to the ported trigger handler.
 */

/** The `MPDebounce` surface the listener uses to debounce the input handler. */
export interface EmailInputDebounce {
  inputDebounce(callback: (inputEvent: unknown) => void): (inputEvent: unknown) => void;
}

/** Minimal jQuery surface the listener uses: read the field value and bind a delegated handler. */
export interface EmailJQueryApi {
  (target: unknown): {
    val(): string | undefined;
    on(event: string, selector: string, handler: (inputEvent: unknown) => void): unknown;
  };
}

type EmailChangeCallback = (email: string, isValid: boolean) => void;

export class SuperTokenEmailListener {
  private readonly EMAIL_FIELD_SELECTOR =
    'form[name="checkout"] input[type="email"], #email, #billing_email';
  private readonly INTERVAL_TIME = 1500;

  private readonly callbacks: EmailChangeCallback[] = [];

  private readonly jquery: EmailJQueryApi;

  constructor(
    private readonly mpDebounce: EmailInputDebounce,
    jquery?: EmailJQueryApi,
  ) {
    this.jquery = jquery ?? (window.jQuery as EmailJQueryApi);
  }

  isValid(email: string): boolean {
    if (!email || email.length > 254) return false;

    const localPart = email.split('@')[0];
    if (localPart && localPart.length > 64) return false;

    const regex =
      /^[a-zA-Z0-9_%+-]+(\.[a-zA-Z0-9_%+-]+)*@[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/i;
    return regex.test(email);
  }

  getEmail(): string | undefined {
    return this.jquery(this.EMAIL_FIELD_SELECTOR).val()?.trim();
  }

  onEmailChange(callback: EmailChangeCallback): this {
    this.callbacks.push(callback);
    return this;
  }

  setupEmailChangeHandlers(): void {
    const handleEmailUpdate = () => {
      const email = this.jquery(this.EMAIL_FIELD_SELECTOR).val();
      if (email) {
        this.callbacks.forEach((callback) => callback(email, this.isValid(email)));
      }
    };

    this.jquery(document).on(
      'input',
      this.EMAIL_FIELD_SELECTOR,
      this.mpDebounce.inputDebounce(handleEmailUpdate),
    );

    setTimeout(() => handleEmailUpdate(), this.INTERVAL_TIME);
  }
}
