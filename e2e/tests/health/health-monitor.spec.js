import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const { wpOption, wpGetOption, isContainerRunning } = require('../../helpers/wp-env');

// Derive BASE_URL from SHOP_URL (strip /shop suffix) or use localhost
const SHOP_URL = process.env.SHOP_URL || 'http://localhost:8080/shop';
const BASE_URL = SHOP_URL.replace(/\/shop\/?$/, '');

// Manifest path: use host filesystem (plugin root is one level up from e2e/)
const PLUGIN_ROOT = path.resolve(__dirname, '..', '..');
const MANIFEST_PATH = path.join(PLUGIN_ROOT, 'integrity-manifest.json');

// Force IPv4 for Node fetch (macOS resolves localhost to ::1 IPv6 first, but Docker binds to 0.0.0.0 IPv4)
const HEALTH_MONITOR_SCRIPT_URL = `${BASE_URL.replace('localhost', '127.0.0.1')}/wp-content/plugins/woocommerce-mercadopago/assets/js/health/mp-health-monitor.js`;

const TEST_PARAMS = {
    plugin_version: '7.0.0',
    theme: 'storefront',
    platform_version: '8.0.0',
    site_id: 'MLB',
    is_checkout: true,
    payment_methods: ['woo-mercado-pago-custom'],
};

/**
 * TIME_TO_MELIDATA_LOAD in mp-health-monitor.js is 3000ms.
 * Tests must wait at least this long after DOMContentLoaded before asserting.
 */
const HEALTH_MONITOR_DELAY = 4000;

/**
 * Spy script inlined into every test page so it runs before any other script.
 * page.addInitScript() does not survive page.setContent() in Playwright 1.43,
 * so we embed the spy directly in the HTML to guarantee execution order.
 */
const BEACON_SPY = `
    window.__beaconCalls = [];
    navigator.sendBeacon = function(url, data) {
        window.__beaconCalls.push({ url: url, data: data || '' });
        return true;
    };
    // page.setContent() runs on about:blank (null origin) where sessionStorage throws
    // SecurityError. The health monitor's rate-limit guard calls sessionStorage.getItem()
    // inside a try/catch — the SecurityError is silently swallowed and sendBeacon is never
    // called. Mock sessionStorage with an in-memory store to fix this.
    try {
        sessionStorage.getItem('__probe__');
    } catch(e) {
        var _ss = {};
        window.__sessionGetItemKeys = [];
        Object.defineProperty(window, 'sessionStorage', {
            value: {
                getItem:    function(k) {
                    window.__sessionGetItemKeys.push(k);
                    return Object.prototype.hasOwnProperty.call(_ss, k) ? _ss[k] : null;
                },
                setItem:    function(k, v) { _ss[k] = String(v); },
                removeItem: function(k) { delete _ss[k]; },
            },
            writable: true, configurable: true,
        });
    }
`;

/**
 * Builds a minimal HTML page that simulates the checkout environment:
 * - installs the sendBeacon spy as the very first script
 * - sets the health monitor global params
 * - inlines the health monitor script
 * - optionally injects a <style> block and body HTML
 */
function buildTestPage(scriptContent, { extraStyle = '', extraBody = '', paramsOverride = {} } = {}) {
    const mergedParams = { ...TEST_PARAMS, ...paramsOverride };
    return `<!DOCTYPE html>
<html>
<head>
  <script>${BEACON_SPY}</script>
  ${extraStyle ? `<style>${extraStyle}</style>` : ''}
</head>
<body>
  ${extraBody}
  <script>var wc_mercadopago_health_monitor_params = ${JSON.stringify(mergedParams)};</script>
  <script>${scriptContent}</script>
  <script>
    // page.setContent() usa document.write() internamente: DOMContentLoaded pode disparar
    // antes do health monitor registrar seu listener. Disparamos manualmente aqui,
    // depois que todos os scripts inline já foram registrados.
    document.dispatchEvent(new Event('DOMContentLoaded', { bubbles: true, cancelable: false }));
  </script>
</body>
</html>`;
}

test.describe('Health Monitor — JS Component Tests', () => {
    /** Health monitor JS fetched once per worker and shared across tests. */
    let healthMonitorScript;

    test.beforeAll(async () => {
        const response = await fetch(HEALTH_MONITOR_SCRIPT_URL);
        if (!response.ok) {
            throw new Error(`Failed to fetch health monitor script (${response.status}): ${HEALTH_MONITOR_SCRIPT_URL}`);
        }
        healthMonitorScript = await response.text();
    });

    // =========================================================================
    // I-04 — CSS conflict via theme
    // =========================================================================
    test.describe('I-04 — CSS conflict via theme', () => {
        test('sends mp_css_conflict_detected when critical element is hidden by theme CSS', async ({ page }) => {
            await page.setContent(
                buildTestPage(healthMonitorScript, {
                    extraBody: `
                        <style id="mercadopago-custom-css">
                            .mp-wallet-button-container { display: block; }
                        </style>
                        <style id="theme-override-css">
                            .mp-wallet-button-container { display: none !important; }
                        </style>
                        <div class="mp-wallet-button-container"></div>
                        <script>
                            window.wc_mercadopago_checkout_session_data_register_params = {};
                            window.wc_mercadopago_supertoken_bundle_params = {};
                            window.MercadoPago = {};
                            window.mercadopago_melidata_params = {};
                            window.MelidataClient = {};
                            window.melidata = {};
                        </script>
                    `,
                })
            );

            await page.waitForTimeout(HEALTH_MONITOR_DELAY);

            const calls = await page.evaluate(() => window.__beaconCalls);
            const cssMetric = calls.find((c) => c.url.includes('mp_css_conflict_detected'));

            expect(cssMetric, 'sendBeacon deve ser chamado com mp_css_conflict_detected').toBeTruthy();

            const payload = JSON.parse(cssMetric.data);
            expect(payload.value).toBe('true');
            expect(payload.platform.name).toBe('woocommerce');
            expect(payload.details.site_id).toBe('MLB');
        });
    });

    // =========================================================================
    // I-04b — CSS conflict detectado via evento supertoken_loaded
    //
    // US: US-02
    // Criteria: mp_css_conflict_detected enviada quando .mp-super-token-payment-methods-list
    //           está oculto por CSS de tema, detectado reativamente via supertoken_loaded
    //
    // Arrange: página com plugin stylesheet (id contém "mercadopago") definindo color no
    //          elemento; external style (extraStyle, sem id mercadopago) sobrescreve o color.
    //          O elemento .mp-super-token-payment-methods-list está presente no DOM.
    // Act:     evento supertoken_loaded é disparado manualmente após page.setContent()
    // Assert:  sendBeacon chamado com mp_css_conflict_detected e payload correto
    // =========================================================================
    test.describe('I-04b — CSS conflict via supertoken_loaded event', () => {
        test('sends mp_css_conflict_detected when super token element has CSS override', async ({ page }) => {
            // Arrange
            await page.setContent(
                buildTestPage(healthMonitorScript, {
                    // Theme override — anonymous <style> (no mercadopago id) → treated as external
                    extraStyle: '.mp-super-token-payment-methods-list { color: rgb(255, 0, 0) !important; }',
                    extraBody: `
                        <!-- Plugin stylesheet identified by id containing "mercadopago" -->
                        <style id="mercadopago-super-token-css">
                            .mp-super-token-payment-methods-list { color: rgb(0, 0, 0); }
                        </style>
                        <div class="mp-super-token-payment-methods-list"><span>saved card</span></div>
                        <script>
                            window.wc_mercadopago_checkout_session_data_register_params = {};
                            window.wc_mercadopago_supertoken_bundle_params = {};
                        </script>
                    `,
                })
            );

            // Act — simulate the event that super-token-payment-methods.js dispatches after render
            await page.evaluate(() => {
                document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId: 'e2e-test' } }));
            });

            // Assert
            const calls = await page.evaluate(() => window.__beaconCalls);
            const cssMetric = calls.find((c) => c.url.includes('mp_css_conflict_detected'));

            expect(cssMetric, 'sendBeacon deve ser chamado com mp_css_conflict_detected').toBeTruthy();

            const payload = JSON.parse(cssMetric.data);
            expect(payload.value).toBe('true');
            expect(payload.platform.name).toBe('woocommerce');
            expect(payload.details.site_id).toBe('MLB');
        });
    });

    // =========================================================================
    // I-04c — { once: true } garante execução única do listener supertoken_loaded
    //
    // US: US-02
    // Criteria: checkCssConflicts executada no máximo uma vez por carregamento de página,
    //           mesmo que supertoken_loaded seja disparado múltiplas vezes
    //
    // Arrange: spy em document.querySelector instalado antes do health monitor;
    //          DOM sem o elemento crítico (garante que nenhum rate-limit de sessionStorage
    //          é gravado, assim o segundo disparo poderia chamar querySelector se o
    //          listener não fosse { once: true })
    // Act:     supertoken_loaded disparado duas vezes consecutivas
    // Assert:  querySelector chamado exatamente uma vez com o seletor do Super Token
    // =========================================================================
    test.describe('I-04c — supertoken_loaded listener fires checkCssConflicts only once', () => {
        test('querySelector called exactly once for super token selector even with two events', async ({ page }) => {
            // Arrange — querySelector spy installed in extraBody, before the health monitor script
            await page.setContent(
                buildTestPage(healthMonitorScript, {
                    extraBody: `
                        <script>
                            window.__querySelectorCalls = [];
                            var _origQS = document.querySelector.bind(document);
                            document.querySelector = function(sel) {
                                window.__querySelectorCalls.push(sel);
                                return _origQS(sel);
                            };
                        </script>
                    `,
                })
            );

            // Act — dispatch supertoken_loaded twice; without { once: true } querySelector
            // would be called twice for '.mp-super-token-payment-methods-list'
            await page.evaluate(() => {
                document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId: 'e2e-once-1' } }));
                document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId: 'e2e-once-2' } }));
            });

            // Assert — selector queried at most once
            const calls = await page.evaluate(() =>
                (window.__querySelectorCalls || []).filter((s) => s === '.mp-super-token-payment-methods-list')
            );
            expect(calls).toHaveLength(1);
        });
    });

    // =========================================================================
    // I-05 — Gateway inativo não gera falso positivo
    //
    // US: US-02, US-03
    // Criteria: scripts/CSS de gateways inativos não geram métrica
    //
    // Arrange: página de checkout sem nenhum elemento crítico do MP no DOM
    //          (simula gateway Custom desativado na página)
    //          Spy em sessionStorage.getItem instalado para provar que checkCssConflicts()
    //          e checkScriptGlobals() foram efetivamente chamadas (não apenas que o script
    //          crashou silenciosamente — o try/catch engole qualquer erro).
    // Act:     DOMContentLoaded dispara ambas as verificações
    // Assert:  sendBeacon NÃO chamado; ambas as funções foram chamadas (spy confirma)
    // =========================================================================
    test.describe('I-05 — Inactive gateway: no false positive', () => {
        test('sends no metric when checkout container is absent from DOM', async ({ page }) => {
            await page.setContent(
                buildTestPage(healthMonitorScript, {
                    extraBody: `
                        <script>
                            window.wc_mercadopago_checkout_session_data_register_params = {};
                            window.wc_mercadopago_supertoken_bundle_params = {};
                            window.MercadoPago = {};
                            window.mercadopago_melidata_params = {};
                            window.MelidataClient = {};
                            window.melidata = {};
                        </script>
                        <div class="some-other-gateway">Outro gateway ativo</div>
                    `,
                })
            );

            await page.waitForTimeout(HEALTH_MONITOR_DELAY);

            const calls = await page.evaluate(() => window.__beaconCalls);
            expect(calls).toHaveLength(0);

            const keys = await page.evaluate(() => window.__sessionGetItemKeys || []);
            expect(keys).toContain('mp_health_css_conflict_wallet_sent');
            expect(keys).toContain('mp_health_script_globals_sent');
        });
    });

    // =========================================================================
    // I-07 — Missing JS global detected
    // =========================================================================
    test.describe('I-07 — Missing JS global detected', () => {
        test('sends mp_script_missing_globals when expected global is absent', async ({ page }) => {
            await page.setContent(
                buildTestPage(healthMonitorScript, {
                    extraBody: '<div class="mp-wallet-button-container"></div>',
                })
            );

            await page.waitForTimeout(HEALTH_MONITOR_DELAY);

            const calls = await page.evaluate(() => window.__beaconCalls);
            const scriptMetric = calls.find((c) => c.url.includes('mp_script_missing_globals'));

            expect(scriptMetric, 'sendBeacon deve ser chamado com mp_script_missing_globals').toBeTruthy();

            const payload = JSON.parse(scriptMetric.data);
            expect(payload.value).toBe('true');
            expect(payload.platform.name).toBe('woocommerce');
            expect(payload.details.site_id).toBe('MLB');

            const cssMetric = calls.find((c) => c.url.includes('mp_css_conflict_detected'));
            expect(cssMetric).toBeUndefined();
        });
    });
});

// =========================================================================
// I-06 — Admin does not break without integrity manifest
// =========================================================================
test.describe('I-06 — Admin does not break without integrity manifest', () => {
    let manifestBackup = null;
    let originalSiteUrl = null;

    test.beforeAll(async () => {
        if (fs.existsSync(MANIFEST_PATH)) {
            manifestBackup = fs.readFileSync(MANIFEST_PATH, 'utf8');
            fs.unlinkSync(MANIFEST_PATH);
        }

        // When running from host against Docker, ensure siteurl matches BASE_URL
        // so wp-admin redirects resolve correctly for the browser.
        if (isContainerRunning()) {
            originalSiteUrl = wpGetOption('siteurl');
            if (originalSiteUrl && originalSiteUrl !== BASE_URL) {
                wpOption('siteurl', BASE_URL);
                wpOption('home', BASE_URL);
            }
        }
    });

    test.afterAll(async () => {
        if (manifestBackup !== null) {
            fs.writeFileSync(MANIFEST_PATH, manifestBackup, 'utf8');
            manifestBackup = null;
        }

        if (originalSiteUrl) {
            wpOption('siteurl', originalSiteUrl);
            wpOption('home', originalSiteUrl);
        }
    });

    test('WP admin loads without PHP errors when integrity manifest is missing', async ({ page }) => {
        await page.goto(`${BASE_URL}/wp-admin/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'admin');
        await page.click('#wp-submit');
        await page.waitForURL(/\/wp-admin\//, { timeout: 30000 });
        await page.waitForLoadState('domcontentloaded');

        const content = await page.content();
        expect(content).not.toMatch(/Fatal error/i);
        expect(content).not.toMatch(/Uncaught/i);

        await expect(page.locator('#wpcontent')).toBeVisible();
    });
});
