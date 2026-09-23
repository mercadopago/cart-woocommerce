export type MetricSender = (metricName: string, value: string, message: string) => void;

interface BufferedErrorEvent {
  message: string;
  errorOrigin: string;
}

/**
 * Platform adapter: emits Super Token error events to MeliData (RN-2).
 *
 * MeliData (`window.melidata`) is provided by a SEPARATE external CDN bundle,
 * loaded async/defer after `load` — its readiness is a network round-trip the
 * plugin cannot order via script enqueue. So this adapter is event-driven: when
 * MeliData is ready it dispatches immediately; otherwise it buffers events and
 * flushes them once `window.melidataReady` resolves. `.then()` fires even when
 * subscribed after resolution, so no signal is missed.
 *
 * Three readiness paths (tried in order in `armReadiness`):
 *   1. `window.melidata` already present → flush immediately.
 *   2. `window.melidataReady` exists → subscribe `.then`/`.catch`.
 *   3. Neither present yet → use `load` event OR, if `load` already fired
 *      (`document.readyState === 'complete'`), call `onFailure` directly so
 *      buffered events are never silently discarded.
 *
 * FIFO ordering is preserved by `isMelidataReady()`: it only short-circuits to
 * "ready" via `window.melidata` when the buffer is empty — while the buffer has
 * pending events any new call enqueues and waits for the same flush.
 *
 * Note: `super-token-metrics.js` (legacy) mirrored `MPCustomEventDispatcher`
 * (`mp-checkout-error-dispatcher.js`) with a per-event `waitForMelidata` + 5s race.
 * This adapter keeps one 5s deadline for the whole FIFO buffer rather than one timer
 * per event, preventing a never-settled readiness promise from retaining events forever.
 */
export class MelidataAdapter {
  private readonly MELIDATA_ERROR_EVENT_NAME = 'mp_checkout_error';
  private readonly MELIDATA_LOAD_TIMEOUT_METRIC = 'mp_melidata_load_timeout';
  private readonly MELIDATA_LOAD_TIMEOUT_MS = 5000;

  private readonly sendMetric: MetricSender;
  /**
   * Currently typed as `BufferedErrorEvent[]` because `dispatchMelidataErrorEvent`
   * is the only public method and the only event type flowing through the adapter.
   * When TASK-006+ adds other event types (loading-start, payment-method-selected,
   * etc.), the buffer will need to become a discriminated union so events of
   * different shapes can be buffered and flushed in FIFO order.
   */
  private readonly buffer: BufferedErrorEvent[] = [];
  private ready = false;
  private failed = false;
  private readinessArmed = false;
  private loadListenerAdded = false;
  private readinessTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(sendMetric: MetricSender) {
    this.sendMetric = sendMetric;
  }

  dispatchMelidataErrorEvent(errorMessage: string, errorOrigin: string): void {
    const cleanMessage = errorMessage?.replace(/^\[mercado pago\]:\s*/i, '').trim() || errorMessage;
    const event: BufferedErrorEvent = { message: cleanMessage, errorOrigin: `${errorOrigin}_mercado_pago` };

    if (this.isMelidataReady()) {
      this.dispatch(event);
      return;
    }

    this.buffer.push(event);
    this.armReadiness();
  }

  /**
   * Returns true only when it is safe to dispatch without breaking FIFO order.
   * If the buffer has pending events, subsequent calls must enqueue even if
   * `window.melidata` is now available — otherwise new events would arrive in
   * MeliData before earlier buffered ones.
   */
  private isMelidataReady(): boolean {
    return this.ready || this.failed || (!!window.melidata && this.buffer.length === 0);
  }

  private armReadiness(): void {
    if (this.readinessArmed) {
      return;
    }
    this.readinessArmed = true;

    if (window.melidata) {
      this.onReady();
      return;
    }

    this.armTimeout();

    if (window.melidataReady && typeof window.melidataReady.then === 'function') {
      window.melidataReady.then(() => this.onReady()).catch(() => this.onFailure());
      return;
    }

    // `window.melidataReady` not yet defined (the local melidata-client.js loader
    // has not run). If `load` already fired we will never receive it again — call
    // onFailure directly so the buffer is not silently discarded.
    if (document.readyState === 'complete') {
      this.onFailure();
      return;
    }

    // `load` has not fired yet — re-arm once it does (without polling). Register the
    // listener only once: consecutive events before `load` would otherwise each add a
    // new `{ once: true }` listener. Re-arming happens inside the callback, not here.
    if (!this.loadListenerAdded) {
      this.loadListenerAdded = true;
      window.addEventListener(
        'load',
        () => {
          this.readinessArmed = false;
          this.armReadiness();
        },
        { once: true },
      );
    }
  }

  private armTimeout(): void {
    if (this.readinessTimer || this.ready || this.failed) {
      return;
    }
    this.readinessTimer = setTimeout(() => this.onFailure(), this.MELIDATA_LOAD_TIMEOUT_MS);
  }

  private clearTimeout(): void {
    if (!this.readinessTimer) {
      return;
    }
    clearTimeout(this.readinessTimer);
    this.readinessTimer = null;
  }

  private onReady(): void {
    if (this.ready || this.failed) {
      return;
    }
    this.ready = true;
    this.clearTimeout();
    this.flush();
  }

  private onFailure(): void {
    if (this.ready || this.failed) {
      return;
    }
    this.failed = true;
    this.clearTimeout();
    // The deadline belongs to the buffer, not to each buffered event. Emit one timeout signal
    // and keep the individual diagnostics on their original Core Monitor metrics + FIFO events.
    this.sendMetric(this.MELIDATA_LOAD_TIMEOUT_METRIC, 'true', `buffered:${this.buffer.length}`);
    this.flush();
  }

  private flush(): void {
    while (this.buffer.length > 0) {
      this.dispatch(this.buffer.shift() as BufferedErrorEvent);
    }
  }

  private dispatch(event: BufferedErrorEvent): void {
    document.dispatchEvent(
      new CustomEvent(this.MELIDATA_ERROR_EVENT_NAME, {
        detail: { message: event.message, errorOrigin: event.errorOrigin },
      }),
    );
  }
}
