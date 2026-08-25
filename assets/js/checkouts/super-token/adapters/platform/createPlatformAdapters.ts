import type { PaymentSdkPort, MetricsPort, DomPort, VariantConfigPort } from '@super-token/ports';
import type { RawMpSdkInstance, SuperTokenBundleParams, WoocommerceScriptsParams } from '@super-token/types/external-globals';
import { MpSdkAdapter } from './MpSdkAdapter';
import { CoreMonitorMetricsAdapter } from './CoreMonitorMetricsAdapter';
import { WooDomAdapter } from './WooDomAdapter';
import { VariantConfigAdapter } from './VariantConfigAdapter';

/** The platform edge the domain (TASK-006) is built on. */
export interface PlatformAdapters {
  paymentSdk: PaymentSdkPort;
  metrics: MetricsPort;
  dom: DomPort;
  variantConfig: VariantConfigPort;
}

export interface PlatformAdaptersOptions {
  sdk?: RawMpSdkInstance;
  superTokenJsVersion?: string | null;
  params?: SuperTokenBundleParams;
  scriptsParams?: WoocommerceScriptsParams;
}

const PARAMS_FALLBACK: SuperTokenBundleParams = {
  plugin_version: '',
  platform_version: '',
  site_id: '',
  cust_id: '',
  location: '',
  platform_id: '',
};

/**
 * Step 1 of the composition root (TASK-005): build the platform adapters.
 *
 * All `window.*` reads happen here once so the individual adapters stay free of
 * globals and are testable with injected values. `params` defaults to
 * `window.wc_mercadopago_supertoken_bundle_params`; if neither is present the
 * metrics adapter is built with empty strings, which the Core Monitor endpoint
 * will reject — this is intentional fail-visible behaviour rather than silent
 * success with useless telemetry.
 */
export function createPlatformAdapters(options: PlatformAdaptersOptions = {}): PlatformAdapters {
  const sdk = options.sdk ?? window.mpSdkInstance;
  if (!sdk) {
    // Fail-visible: the MP JS SDK is required to build the payment adapter. Casting
    // undefined away would defer the failure to the first SDK call deep in the domain.
    throw new Error('createPlatformAdapters: MP JS SDK instance unavailable (window.mpSdkInstance is undefined).');
  }
  const superTokenJsVersion = options.superTokenJsVersion ?? null;
  const params = options.params ?? window.wc_mercadopago_supertoken_bundle_params ?? PARAMS_FALLBACK;
  const scriptsParams = options.scriptsParams ?? window.wc_mercadopago_woocommerce_scripts_params ?? {};

  return {
    paymentSdk: new MpSdkAdapter(sdk),
    metrics: new CoreMonitorMetricsAdapter(sdk, superTokenJsVersion, params),
    dom: new WooDomAdapter(),
    variantConfig: new VariantConfigAdapter(scriptsParams),
  };
}
