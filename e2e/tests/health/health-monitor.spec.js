import { test, expect } from '@playwright/test';
import fs from 'fs';

const BASE_URL = process.env.BASE_URL || 'http://localhost';
const MANIFEST_PATH = '/var/www/html/wp-content/plugins/woocommerce-mercadopago/integrity-manifest.json';
const HEALTH_MONITOR_SCRIPT_URL = `${BASE_URL}/wp-content/plugins/woocommerce-mercadopago/assets/js/health/mp-health-monitor.js`;

const TEST_PARAMS = {
    plugin_version: '7.0.0',
    theme: 'storefront',
    platform_version: '8.0.0',
    site_id: 'MLB',
    is_checkout: true,
};

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
        Object.defineProperty(window, 'sessionStorage', {
            value: {
                getItem:    function(k) { return Object.prototype.hasOwnProperty.call(_ss, k) ? _ss[k] : null; },
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
function buildTestPage(scriptContent, { extraStyle = '', extraBody = '' } = {}) {
    return `<!DOCTYPE html>
<html>
<head>
  <script>${BEACON_SPY}</script>
  ${extraStyle ? `<style>${extraStyle}</style>` : ''}
</head>
<body>
  ${extraBody}
  <script>var wc_mercadopago_health_monitor_params = ${JSON.stringify(TEST_PARAMS)};</script>
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
    //
    // US: US-02
    // Criteria: mp_css_conflict_detected enviada quando elemento crítico está oculto
    //
    // Arrange: página com .mp-checkout-custom-container presente no DOM,
    //          mas oculto por CSS do tema (display: none)
    // Act:     DOMContentLoaded dispara checkCssConflicts()
    // Assert:  sendBeacon chamado com mp_css_conflict_detected e payload correto
    // =========================================================================
    test.describe('I-04 — CSS conflict via theme', () => {
        test('sends mp_css_conflict_detected when critical element is hidden by theme CSS', async ({ page }) => {
            // Arrange
            await page.setContent(
                buildTestPage(healthMonitorScript, {
                    extraStyle: '.mp-checkout-custom-container { display: none !important; }',
                    extraBody: `
                        <div class="mp-checkout-custom-container"></div>
                        <script>
                            window.wc_mercadopago_checkout_session_data_register_params = {};
                            window.wc_mercadopago_supertoken_bundle_params = {};
                        </script>
                    `,
                })
            );

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
            // Arrange — spy sobre sessionStorage.getItem instalado ANTES do health monitor.
            // checkCssConflicts() e checkScriptGlobals() chamam getItem() como primeira
            // instrução (guard de rate-limit), provando que foram efetivamente executadas.
            await page.setContent(
                buildTestPage(healthMonitorScript, {
                    extraBody: `
                        <script>
                            var _origGetItem = sessionStorage.getItem;
                            sessionStorage.getItem = function(k) {
                                (window.__sessionGetItemKeys = window.__sessionGetItemKeys || []).push(k);
                                return _origGetItem(k);
                            };
                        </script>
                        <div class="some-other-gateway">Outro gateway ativo</div>
                    `,
                })
            );

            // Assert — nenhum beacon disparado
            const calls = await page.evaluate(() => window.__beaconCalls);
            expect(calls).toHaveLength(0);

            // Assert — ambas as funções foram chamadas (prova que o script não crashou)
            const keys = await page.evaluate(() => window.__sessionGetItemKeys || []);
            expect(keys).toContain('mp_health_css_conflict_sent');
            expect(keys).toContain('mp_health_script_globals_sent');
        });
    });

    // =========================================================================
    // I-07 — Script global ausente detectado
    //
    // US: US-03
    // Criteria: mp_script_dequeued_detected enviada quando global JS esperado está ausente
    //
    // Arrange: página com .mp-checkout-custom-container presente no DOM
    //          mas wc_mercadopago_checkout_session_data_register_params não definido
    //          (simula script desativado por customização do tema/plugin de terceiro)
    // Act:     DOMContentLoaded dispara checkScriptGlobals()
    // Assert:  sendBeacon chamado com mp_script_dequeued_detected e payload correto;
    //          mp_css_conflict_detected NÃO é disparado (CSS está correto)
    // =========================================================================
    test.describe('I-07 — Missing JS global detected', () => {
        test('sends mp_script_dequeued_detected when expected global is absent', async ({ page }) => {
            // Arrange — container presente com CSS correto; global JS intencionalmente ausente
            await page.setContent(
                buildTestPage(healthMonitorScript, {
                    extraBody: '<div class="mp-checkout-custom-container"></div>',
                })
            );

            // Assert — métrica correta enviada
            const calls = await page.evaluate(() => window.__beaconCalls);
            const scriptMetric = calls.find((c) => c.url.includes('mp_script_missing_globals'));

            expect(scriptMetric, 'sendBeacon deve ser chamado com mp_script_missing_globals').toBeTruthy();

            const payload = JSON.parse(scriptMetric.data);
            expect(payload.value).toBe('true');
            expect(payload.platform.name).toBe('woocommerce');
            expect(payload.details.site_id).toBe('MLB');

            // Assert — métrica de CSS não foi disparada (nenhum conflito de CSS)
            const cssMetric = calls.find((c) => c.url.includes('mp_css_conflict_detected'));
            expect(cssMetric).toBeUndefined();
        });
    });
});

// =========================================================================
// I-06 — Admin não quebra sem integrity manifest
//
// US: US-01
// Criteria: FileIntegrityChecker não causa Fatal error quando manifest está ausente.
//           FileIntegrityChecker::runWithRateLimit() é registrado em admin_init —
//           roda exclusivamente em páginas admin, não no frontend.
//
// Arrange: integrity-manifest.json removido do filesystem do container antes do teste
// Act:     login no WP admin (dispara admin_init → FileIntegrityChecker::runWithRateLimit())
// Assert:  página admin carrega sem Fatal error (WP_DEBUG=1 exibiria erros inline)
//
// Cleanup: afterAll restaura o manifest independentemente do resultado do teste
// =========================================================================
test.describe('I-06 — Admin does not break without integrity manifest', () => {
    let manifestBackup = null;

    test.beforeAll(() => {
        if (fs.existsSync(MANIFEST_PATH)) {
            manifestBackup = fs.readFileSync(MANIFEST_PATH, 'utf8');
            fs.unlinkSync(MANIFEST_PATH);
        }
    });

    test.afterAll(() => {
        if (manifestBackup !== null) {
            fs.writeFileSync(MANIFEST_PATH, manifestBackup, 'utf8');
            manifestBackup = null;
        }
    });

    test('WP admin loads without PHP errors when integrity manifest is missing', async ({ page }) => {
        // Act — login dispara admin_init que chama FileIntegrityChecker::runWithRateLimit()
        await page.goto(`${BASE_URL}/wp-admin/`);
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'admin');
        await page.click('#wp-submit');
        // Aguarda o redirect pós-login aterrissar no dashboard antes de inspecionar o DOM.
        // waitForLoadState('domcontentloaded') sozinho pode resolver ainda na página de login
        // (durante o redirect 302), resultando em falha intermitente ao buscar #wpcontent.
        await page.waitForURL(/\/wp-admin\/(?!.*wp-login)/);
        await page.waitForLoadState('domcontentloaded');

        // Assert — nenhum erro PHP visível
        const content = await page.content();
        expect(content).not.toMatch(/Fatal error/i);
        expect(content).not.toMatch(/Uncaught/i);

        // Admin dashboard renderizou
        await expect(page.locator('#wpcontent')).toBeVisible();
    });
});
