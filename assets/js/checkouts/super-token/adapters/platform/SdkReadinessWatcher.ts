import type { MetricsPort } from '@super-token/ports';

export const MP_SDK_INSTANCE_READY_EVENT = 'mp_sdk_instance_ready';
export const CARD_FORM_MOUNTED_EVENT = 'mp_card_form_mounted';
export const FALLBACK_POLL_INTERVAL_MS = 50;
export const FALLBACK_POLL_MAX_WAIT_MS = 15000;

export const INIT_SOURCE = {
  ALREADY_READY: 'already_ready',
  SDK_INSTANCE_EVENT: 'sdk_event',
  SDK_INSTANCE_EVENT_AFTER_LEGACY_WINDOW: 'sdk_event_recovered',
  FALLBACK_POLL: 'fallback_poll',
  CARD_FORM_RECOVERY: 'card_form_recovery',
} as const;

export type InitSource = (typeof INIT_SOURCE)[keyof typeof INIT_SOURCE];

export interface SdkReadinessWatcherDeps {
  metrics: MetricsPort;
  readSdkInstance?: () => unknown;
  now?: () => number;
}

/**
 * Watches for the MP SDK instance and triggers Super Token composition exactly once,
 * using three tiers in order of availability:
 *
 *   1. Already present at construction time  → compose immediately (ALREADY_READY)
 *   2. Arrives via `mp_sdk_instance_ready`   → compose on event (SDK_INSTANCE_EVENT
 *                                               or SDK_INSTANCE_EVENT_AFTER_LEGACY_WINDOW if >15s)
 *   3. Appears in `window.mpSdkInstance`     → compose on 50ms poll (FALLBACK_POLL),
 *                                               poll capped at 15s
 *
 * After the poll cap, temporary load delays can still be recovered via
 * recoverIfSdkIsNowAvailable() — call it at natural page checkpoints
 * (e.g. when the card form mounts) to give a second chance before giving up.
 *
 * Reports `super_token_sdk_loaded` and `super_token_init_source` (with elapsed_ms)
 * through the MetricsPort. Never writes to `window.*`.
 */
export class SdkReadinessWatcher {
  private readonly metrics: MetricsPort;
  private readonly readSdkInstance: () => unknown;
  private readonly now: () => number;
  private readonly startedAt: number;

  private initialized = false;
  private pendingCompose: (() => void) | null = null;
  private activePoll: ReturnType<typeof setInterval> | null = null;

  constructor(deps: SdkReadinessWatcherDeps) {
    this.metrics = deps.metrics;
    this.readSdkInstance = deps.readSdkInstance ?? (() => window.mpSdkInstance);
    this.now = deps.now ?? (() => Date.now());
    this.startedAt = this.now();
  }

  /**
   * Begins watching for SDK availability and calls `compose` exactly once.
   * The compose callback is stored so recoverIfSdkIsNowAvailable() can use it later.
   *
   * Idempotent: a second call is silently ignored to guard against accidental
   * double-registration of the event listener and poll (TASK-013 wiring safety).
   *
   * Note for TASK-013: in the already_ready path compose() is called synchronously
   * and any exception it throws propagates out of start(). Wrap start() in a
   * try-catch at the bundle entrypoint so a temporary compose failure does not abort
   * the bundle bootstrap before recoverIfSdkIsNowAvailable() has a chance to retry.
   */
  start(compose: () => void): void {
    if (this.pendingCompose !== null) {
      return;
    }
    this.pendingCompose = compose;

    if (this.readSdkInstance()) {
      this.composeWith(INIT_SOURCE.ALREADY_READY);
      return;
    }

    document.addEventListener(
      MP_SDK_INSTANCE_READY_EVENT,
      () => this.composeWith(INIT_SOURCE.SDK_INSTANCE_EVENT),
      { once: true },
    );

    this.activePoll = setInterval(() => {
      if (this.readSdkInstance()) {
        this.composeWith(INIT_SOURCE.FALLBACK_POLL);
      }
    }, FALLBACK_POLL_INTERVAL_MS);

    setTimeout(() => {
      if (this.activePoll !== null) {
        clearInterval(this.activePoll);
        this.activePoll = null;
      }
    }, FALLBACK_POLL_MAX_WAIT_MS);
  }

  /**
   * Registers a listener that calls recoverIfSdkIsNowAvailable() when the card form
   * mounts. Call this after start() to cover the case where the SDK arrives after the
   * 15s poll window.
   *
   * Intended wiring point for the composition root (TASK-013):
   *   watcher.start(compose);
   *   watcher.registerFormMountedRecovery();
   */
  registerFormMountedRecovery(): void {
    document.addEventListener(CARD_FORM_MOUNTED_EVENT, () =>
      this.recoverIfSdkIsNowAvailable(),
    );
  }

  /**
   * Recovery entry point for temporary SDK load delays.
   *
   * Call this at a natural page checkpoint after the poll window closes (e.g. when
   * the card form mounts). If the SDK is now available and composition has not yet
   * happened, composes with source CARD_FORM_RECOVERY. Does nothing if already
   * initialized or if the SDK is still unavailable.
   */
  recoverIfSdkIsNowAvailable(): void {
    if (this.initialized || !this.pendingCompose) {
      return;
    }
    if (!this.readSdkInstance()) {
      return;
    }
    this.composeWith(INIT_SOURCE.CARD_FORM_RECOVERY);
  }

  private composeWith(source: InitSource): void {
    if (this.initialized || !this.readSdkInstance() || !this.pendingCompose) {
      return;
    }

    if (this.activePoll !== null) {
      clearInterval(this.activePoll);
      this.activePoll = null;
    }

    this.pendingCompose();
    this.initialized = true;
    this.metrics.superTokenSdkLoaded();
    this.reportInitSource(source);
  }

  private reportInitSource(source: InitSource): void {
    const elapsedMs = this.now() - this.startedAt;
    const arrivedLateViaSdkEvent =
      source === INIT_SOURCE.SDK_INSTANCE_EVENT && elapsedMs > FALLBACK_POLL_MAX_WAIT_MS;
    const reportedSource = arrivedLateViaSdkEvent
      ? INIT_SOURCE.SDK_INSTANCE_EVENT_AFTER_LEGACY_WINDOW
      : source;

    this.metrics.reportInitSource(reportedSource, elapsedMs);
  }
}
