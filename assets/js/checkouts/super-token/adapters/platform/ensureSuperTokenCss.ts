import type { CoreMonitorMetricsAdapter } from './CoreMonitorMetricsAdapter';
import { SUPER_TOKEN_JS_VERSION } from './constants';

const CSS_ID = 'wc_mercadopago_supertoken_bundle_css';
const FIX_ID = `${CSS_ID}_fix`;
const LOAD_TIMEOUT_MS = 5000;
const checkedLinks = new WeakSet<HTMLLinkElement>();
type LoadResult = 'load' | 'error' | 'timeout';

/** Observe one stylesheet, including a cached load, with bounded listeners and no retries. */
function waitForStylesheet(link: HTMLLinkElement, append: boolean): Promise<LoadResult> {
  return new Promise((resolve) => {
    if (!append && link.sheet) {
      resolve('load');
      return;
    }
    const finish = (result: LoadResult): void => {
      clearTimeout(timer);
      link.removeEventListener('load', loaded);
      link.removeEventListener('error', failed);
      resolve(result);
    };
    const loaded = (): void => finish('load');
    const failed = (): void => finish('error');
    const timer = setTimeout(() => finish('timeout'), LOAD_TIMEOUT_MS);
    link.addEventListener('load', loaded);
    link.addEventListener('error', failed);
    if (append) {
      try {
        // Install listeners before insertion so even a cached response is observed.
        document.head.appendChild(link);
      } catch {
        finish('error');
      }
    }
  });
}

function cssVersion(): string {
  try {
    return getComputedStyle(document.documentElement)
      .getPropertyValue('--mp-super-token-loader-version').trim();
  } catch {
    return '';
  }
}

async function checkStylesheet(
  original: HTMLLinkElement,
  metrics: Pick<CoreMonitorMetricsAdapter, 'sendMetric'>,
): Promise<void> {
  const originalResult = await waitForStylesheet(original, false);
  const found = originalResult === 'timeout' ? 'timeout' : cssVersion() || 'empty';
  if (found === SUPER_TOKEN_JS_VERSION || document.getElementById(FIX_ID)) return;

  let result: 'fixed' | 'fix_failed' = 'fix_failed';
  let folder = 'unknown';
  try {
    // Reuse the loader's exact origin and folder; only replace the query string.
    const url = new URL(original.href, document.baseURI);
    folder = url.pathname.split('/').slice(-2, -1)[0] || 'unknown';
    url.search = `?v=${SUPER_TOKEN_JS_VERSION}`;
    const fix = document.createElement('link');
    fix.id = FIX_ID;
    fix.rel = 'stylesheet';
    fix.href = url.href;
    const loaded = await waitForStylesheet(fix, true);
    if (loaded === 'load' && cssVersion() === SUPER_TOKEN_JS_VERSION) result = 'fixed';
  } catch {
    // CSS repair and telemetry must never stop checkout initialization.
  }
  try {
    metrics.sendMetric('super_token_css_mismatch', found, `${result} folder:${folder}`);
  } catch {
    // Best-effort observability, independent of the checkout runtime.
  }
}

/** One parallel CSS repair per loader link; local mode has no CDN link and is untouched. */
export function ensureSuperTokenCss(metrics: Pick<CoreMonitorMetricsAdapter, 'sendMetric'>): void {
  const original = document.querySelector<HTMLLinkElement>(`link#${CSS_ID}`);
  if (!original || checkedLinks.has(original) || document.getElementById(FIX_ID)) return;
  checkedLinks.add(original);
  void checkStylesheet(original, metrics).catch(() => {
    // Unexpected browser restrictions also leave checkout free to initialize.
  });
}
