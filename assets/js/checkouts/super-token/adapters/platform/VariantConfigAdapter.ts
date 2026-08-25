import type { VariantConfigPort } from '@super-token/ports';
import type { WoocommerceScriptsParams } from '@super-token/types/external-globals';
import {
  SUPER_TOKEN_AB_CONFIG_URL,
  SUPER_TOKEN_ALLOWED_VARIANTS,
  SUPER_TOKEN_FALLBACK_VARIANT,
  SUPER_TOKEN_VARIANT_COOKIE,
} from '@super-token/adapters/platform/constants';
import { sendToCoreMonitor } from './coreMonitorPayload';
import type { CoreMonitorPayload } from './coreMonitorPayload';

interface AbConfig {
  active?: unknown;
  default?: string;
  variants?: Record<string, { weight?: number }>;
  cookie_ttl_days?: number;
}

/**
 * Platform adapter: resolves the A/B variant string (RN-4), porting the selection
 * logic of `super-token-loader.js` into the hexagonal tree. It reads the remote
 * config, cookie and weighted assignment and returns the logical variant — it does
 * NOT load the CDN bundle nor pick a view (that stays with the loader today and
 * moves to the single-bundle runtime resolution in TASK-008/013, ADR-005).
 *
 * Behavior preserved 1:1, including the `source:*` telemetry and the cookie as a
 * resilience fallback. The only consolidation: the loader had two error layers
 * (sync try/catch + async .catch); with `await` in one try/catch they collapse into
 * a single fallback path emitting the same load-failure metric.
 *
 * TODO: the selection logic (fetch → kill-switch → cookie → weighted → fallback) is
 * complex. Consider extracting it into a dedicated VariantSelectionStrategy or
 * simplifying via a state machine in a future task.
 */
export class VariantConfigAdapter implements VariantConfigPort {
  // Variant names, the A/B cookie, the CDN config URL and the allowed set live in
  // config/constants.ts (single source shared with bootstrap and the metrics adapter). Only the
  // selection-timing knobs and metric names below are internal to this adapter.
  private readonly SUPER_TOKEN_VARIANT_COOKIE_DEFAULT_TTL_DAYS = 30;
  private readonly SUPER_TOKEN_CONFIG_FETCH_TIMEOUT_MS = 3000;
  private readonly SUPER_TOKEN_FETCH_FAILED_COOKIE_TTL_DAYS = 2 / 24;

  private readonly METRIC_LOAD_SUPER_TOKEN_BUNDLE = 'load_super_token_bundle';
  private readonly METRIC_FETCH_AB_CONFIG = 'fetch_ab_config';
  private readonly METRIC_FETCH_AB_CONFIG_TIME = 'fetch_ab_config_loading_time';
  private readonly METRIC_SUPER_TOKEN_AB_VARIANT = 'super_token_ab_variant';
  private readonly METRIC_STATUS_FAILURE = 'false';

  private readonly MILLISECONDS_PER_DAY = 864e5;

  private readonly params: WoocommerceScriptsParams;

  // Params are injected by the composition root (createPlatformAdapters) so this
  // adapter stays free of `window.*` reads; the fallback keeps it usable standalone.
  constructor(params?: WoocommerceScriptsParams) {
    this.params = params ?? window.wc_mercadopago_woocommerce_scripts_params ?? {};
  }

  async resolve(): Promise<string> {
    try {
      const cachedVariant = this.getVariantCookie();

      // Always fetch config so the kill switch (active:false) propagates immediately
      // to all visitors, including returning ones with a valid cookie.
      const abConfig = await this.fetchAbConfig(SUPER_TOKEN_AB_CONFIG_URL, this.SUPER_TOKEN_CONFIG_FETCH_TIMEOUT_MS);

      if (!abConfig) {
        // Fetch failed — use cookie as resilience fallback to preserve user experience.
        if (cachedVariant && this.isAllowed(cachedVariant)) {
          this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, cachedVariant, 'source:cookie');
          return cachedVariant;
        }
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:fetch_failed');
        this.setVariantCookie('fetch_failed', this.SUPER_TOKEN_FETCH_FAILED_COOKIE_TTL_DAYS);
        return SUPER_TOKEN_FALLBACK_VARIANT;
      }

      if (typeof abConfig.active !== 'boolean') {
        // active absent, null, or wrong type — malformed config, not a kill switch
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:config_invalid');
        return SUPER_TOKEN_FALLBACK_VARIANT;
      }

      if (!abConfig.active) {
        // active === false — Kill switch: clears cookie and returns default for ALL visitors.
        this.clearVariantCookie();
        const defaultVariant = abConfig.default && this.isAllowed(abConfig.default) ? abConfig.default : SUPER_TOKEN_FALLBACK_VARIANT;
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, defaultVariant, 'source:kill_switch');
        return defaultVariant;
      }

      if (!abConfig.variants || typeof abConfig.variants !== 'object') {
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:config_invalid');
        return SUPER_TOKEN_FALLBACK_VARIANT;
      }

      // active:true — use existing valid cookie without re-assigning the variant.
      if (cachedVariant && this.isAllowed(cachedVariant)) {
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, cachedVariant, 'source:cookie');
        return cachedVariant;
      }

      // No cookie or unknown/corrupted variant — clear and assign a new one.
      if (cachedVariant) {
        this.clearVariantCookie();
      }

      const assignedVariant = this.selectVariantByWeight(abConfig.variants);

      if (!this.isAllowed(assignedVariant)) {
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:config_invalid');
        return SUPER_TOKEN_FALLBACK_VARIANT;
      }

      const cookieTtlDays = abConfig.cookie_ttl_days && abConfig.cookie_ttl_days > 0
        ? abConfig.cookie_ttl_days
        : this.SUPER_TOKEN_VARIANT_COOKIE_DEFAULT_TTL_DAYS;

      this.setVariantCookie(assignedVariant, cookieTtlDays);
      this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, assignedVariant, 'source:assigned');
      return assignedVariant;
    } catch (error) {
      const errorMessage = (error as { message?: string })?.message || 'async_error';
      this.trackMetric(this.METRIC_LOAD_SUPER_TOKEN_BUNDLE, this.METRIC_STATUS_FAILURE, errorMessage);
      return SUPER_TOKEN_FALLBACK_VARIANT;
    }
  }

  private isAllowed(variant: string): boolean {
    return SUPER_TOKEN_ALLOWED_VARIANTS[variant] === true;
  }

  private buildPayload(value: unknown, message: string): CoreMonitorPayload {
    const p = this.params;
    return {
      value: `${value}`,
      message,
      plugin_version: p.plugin_version || '',
      platform: {
        name: 'woocommerce',
        uri: p.theme || '',
        version: p.platform_version || '',
        url: `${window.location.origin}${window.location.pathname}`,
      },
      details: {
        site_id: p.site_id || '',
        environment: 'prod',
        cust_id: p.cust_id || '',
      },
    };
  }

  private trackMetric(metricName: string, value: unknown, message: string): void {
    sendToCoreMonitor(metricName, this.buildPayload(value, message), 'beacon');
  }

  private getVariantCookie(): string | null {
    const cookiePattern = new RegExp('(^|;\\s*)' + SUPER_TOKEN_VARIANT_COOKIE + '=([^;]+)');
    const cookieMatch = document.cookie.match(cookiePattern);
    return cookieMatch ? cookieMatch[2] : null;
  }

  private setVariantCookie(variantValue: string, ttlInDays: number): void {
    try {
      const expiration = new Date(Date.now() + ttlInDays * this.MILLISECONDS_PER_DAY).toUTCString();
      document.cookie = SUPER_TOKEN_VARIANT_COOKIE + '=' + variantValue
        + ';expires=' + expiration + ';path=/;SameSite=Lax;Secure';
    } catch (_) {
      // Intentionally swallow cookie errors to avoid breaking checkout flow.
    }
  }

  private clearVariantCookie(): void {
    document.cookie = SUPER_TOKEN_VARIANT_COOKIE
      + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax;Secure';
  }

  private selectVariantByWeight(variants: Record<string, { weight?: number }>): string {
    const variantNames = Object.keys(variants);
    const totalWeight = variantNames.reduce((weightSum, variantName) => weightSum + (variants[variantName].weight || 0), 0);

    if (totalWeight <= 0) {
      return SUPER_TOKEN_FALLBACK_VARIANT;
    }

    const randomPoint = Math.random() * totalWeight;
    let accumulatedWeight = 0;

    for (let i = 0; i < variantNames.length; i++) {
      accumulatedWeight += variants[variantNames[i]].weight || 0;
      if (randomPoint < accumulatedWeight) {
        return variantNames[i];
      }
    }

    return variantNames[0];
  }

  private fetchAbConfig(configUrl: string, timeoutMs: number): Promise<AbConfig | null> {
    const fetchStartTime = Date.now();
    let hasFetchTimedOut = false;

    const fetchTimeoutPromise = new Promise<AbConfig | null>((resolve) => {
      setTimeout(() => {
        hasFetchTimedOut = true;
        resolve(null);
      }, timeoutMs);
    });

    const fetchConfigPromise = fetch(configUrl, { cache: 'no-cache' })
      .then((response) => {
        if (!response.ok) {
          if (!hasFetchTimedOut) {
            this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'error', 'http:' + response.status);
          }
          return null;
        }
        return response.json()
          .then((parsedConfig: AbConfig) => {
            // Guard: if timeout already fired, discard result to avoid
            // emitting both 'timeout' and 'success' metrics for the same request.
            if (hasFetchTimedOut) return null;
            const elapsedMs = Date.now() - fetchStartTime;
            this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'success', 'success');
            this.trackMetric(this.METRIC_FETCH_AB_CONFIG_TIME, elapsedMs, '');
            return parsedConfig;
          })
          .catch(() => {
            if (!hasFetchTimedOut) {
              this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'error', 'invalid_json');
            }
            return null;
          });
      })
      .catch(() => {
        if (!hasFetchTimedOut) {
          this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'error', 'network_or_cors');
        }
        return null;
      });

    return Promise.race([fetchConfigPromise, fetchTimeoutPromise]).then((resolvedConfig) => {
      if (hasFetchTimedOut) {
        this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'timeout', 'elapsed_ms:' + (Date.now() - fetchStartTime));
      }
      return resolvedConfig;
    });
  }
}
