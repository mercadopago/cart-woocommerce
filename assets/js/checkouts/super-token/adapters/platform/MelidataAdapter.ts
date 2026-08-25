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
 * This buffer approach intentionally diverges from that mirror; aligning the
 * canonical dispatcher is a separate follow-up.
 */
export class MelidataAdapter {
  private readonly MELIDATA_ERROR_EVENT_NAME = 'mp_checkout_error';
  private readonly MELIDATA_LOAD_TIMEOUT_METRIC = 'mp_melidata_load_timeout';

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
      window.addEventListener('load', () => {
        this.readinessArmed = false;
        this.armReadiness();
      }, { once: true });
    }
  }

  private onReady(): void {
    this.ready = true;
    this.flush();
  }

  private onFailure(): void {
    this.failed = true;
    // Intentional divergence from the legacy `waitForMelidata_` + 5s race (super-token-metrics.js).
    // Legacy: each event raced independently, so mp_melidata_load_timeout fired once per event
    //   with message = cleanMessage (the actual error text).
    // Here: one metric fires for the whole buffer with message = 'buffered:N'.
    //   Benefit: N tells the consumer how many events were lost, which is more actionable
    //   than a per-event timeout race; side-effect: the original error text is not in the metric.
    // Consumers of mp_melidata_load_timeout should expect this new shape from this adapter forward.
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
