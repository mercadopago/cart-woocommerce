import { test, expect } from '../fixtures.js';
import { SELECTORS } from '../selectors.js';
import {
  startCustomCheckout,
  openCheckout,
  expectSuperTokenVisible,
  recordMetricPayloads,
  PENDING_BUYER,
} from '../flows/super-token.js';

const { buyerFor } = require('../data/country.js');
const { skipIfNotSite } = require('../../helpers/site-guard.js');
const { storeToolingAvailable, isPluginInstalled, activatePlugin, deactivatePlugin } = require('../helpers/store.js');

// Plugin de checkout de terceiro compatível com Blocks (transforma em multistep). Instalado
// (desativado) pela Fase A (setup-store.sh); este teste ativa só durante a execução e desativa.
const THIRD_PARTY_PLUGIN = 'fluid-checkout';
const CORE_MONITOR_PATH = '/monitor/v1/event/datadog/big/';
const DISPATCHER_MISSING_VALUE = 'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING';
const CARD_DISPATCHER_CONTEXT = 'super_token_installments_setup';

export function resilienceScenarios(site) {
  const buyer = buyerFor(site);

  test.describe(`Super Token resilience — ${site.toUpperCase()}`, () => {
    test.beforeEach(() => skipIfNotSite(test, site.toUpperCase()));

    test('Given a third-party checkout plugin (Fluid Checkout), When the buyer opens the checkout, Then the Mercado Pago checkout still integrates', async ({
      page,
    }) => {
      test.skip(
        !storeToolingAvailable() || !isPluginInstalled(THIRD_PARTY_PLUGIN),
        'fluid-checkout not installed (run make setup) or WP-CLI unavailable',
      );

      try {
        activatePlugin(THIRD_PARTY_PLUGIN);
        await openCheckout(page, buyer);

        // O Fluid Checkout está mesmo ativo (vira multistep, adiciona a classe no body)...
        await expect(page.locator('body.has-fluid-checkout')).toBeAttached();
        // ...e o checkout do Mercado Pago segue integrado no fluxo (o radio existe no DOM, ainda
        // que oculto no passo não-atual do multistep).
        await expect(page.locator(SELECTORS.customCheckoutRadio).first()).toHaveCount(1);
      } finally {
        deactivatePlugin(THIRD_PARTY_PLUGIN); // não pode vazar para os outros testes
      }
    });

    test('Given a 3G connection, When the buyer opens the Custom Checkout, Then the Super Token still loads', async ({
      page,
      faults,
    }) => {
      test.skip(!buyer.email, PENDING_BUYER);
      await faults.throttle3G();
      // Sob 3G a navegação demora bem mais que o navigationTimeout global (30s) → dá folga só aqui.
      page.setDefaultNavigationTimeout(90000);

      await startCustomCheckout(page, buyer);

      await expectSuperTokenVisible(page, 45000);
    });

    test('Given the MP SDK is already available, When the bundle resolves its variant asynchronously, Then the runtime is published before SDK-ready telemetry', async ({
      page,
    }) => {
      await openCheckout(page, buyer);
      await page.waitForFunction(
        () => window.mpSdkInstance && window.mpSuperTokenTriggerHandler && window.mpSuperTokenPaymentMethods,
        undefined,
        { timeout: 20000 },
      );

      const bundleSource = await page.evaluate(() => {
        const dynamicBundle = document.getElementById('wc_mercadopago_supertoken_bundle_js');
        const candidates = [
          dynamicBundle?.src,
          ...Array.from(document.scripts, (script) => script.src),
          ...performance.getEntriesByType('resource').map((entry) => entry.name),
        ].filter(Boolean);
        return candidates.find((src) =>
          /(?:super-token(?:-v2(?:\.1)?)?\.bundle(?:\.min)?|build\/super-token(?:-v2(?:\.1)?)?\/bootstrap\.ts)\.js(?:[?#]|$)/.test(
            src,
          ),
        );
      });

      expect(bundleSource, 'the loaded Super Token runtime bundle must be discoverable').toBeTruthy();

      await page.evaluate(
        async ({ source, monitorPath }) => {
          window.__stResilienceInitMetrics = [];
          const originalFetch = window.fetch.bind(window);
          window.fetch = (resource, options) => {
            const url = typeof resource === 'string' ? resource : resource?.url || '';
            if (url.includes(monitorPath)) {
              const metricName = url.split(monitorPath)[1]?.split(/[?#/]/)[0];
              if (metricName === 'super_token_sdk_loaded' || metricName === 'super_token_init_source') {
                let payload = null;
                try {
                  payload = options?.body ? JSON.parse(options.body) : null;
                } catch {
                  payload = null;
                }
                window.__stResilienceInitMetrics.push({
                  metricName,
                  payload,
                  triggerHandlerPublished: !!window.mpSuperTokenTriggerHandler,
                  paymentMethodsPublished: !!window.mpSuperTokenPaymentMethods,
                });
              }
            }
            return originalFetch(resource, options);
          };

          delete window.mpSuperTokenTriggerHandler;
          delete window.mpSuperTokenAuthenticator;
          delete window.mpSuperTokenPaymentMethods;
          delete window.mpSuperTokenMetrics;
          delete window.mpSuperTokenErrorHandler;

          const reloaded = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            const url = new URL(source, window.location.href);
            url.searchParams.set('st_resilience_e2e', `${Date.now()}`);
            script.src = url.toString();
            script.onload = resolve;
            script.onerror = () => reject(new Error('Unable to reload the Super Token bundle'));
            document.head.appendChild(script);
          });
          await reloaded;
        },
        { source: bundleSource, monitorPath: CORE_MONITOR_PATH },
      );

      await expect
        .poll(() => page.evaluate(() => window.__stResilienceInitMetrics?.length || 0), { timeout: 10000 })
        .toBe(2);

      const initMetrics = await page.evaluate(() => window.__stResilienceInitMetrics);
      expect(initMetrics.map(({ metricName }) => metricName).sort()).toEqual([
        'super_token_init_source',
        'super_token_sdk_loaded',
      ]);
      for (const snapshot of initMetrics) {
        expect(snapshot.triggerHandlerPublished, `${snapshot.metricName}: trigger handler published`).toBe(true);
        expect(snapshot.paymentMethodsPublished, `${snapshot.metricName}: payment methods published`).toBe(true);
      }
      expect(initMetrics.find(({ metricName }) => metricName === 'super_token_init_source')?.payload?.value).toBe(
        'already_ready',
      );
    });

    test('Given a saved-card row without installment_rate_collector, When rendering fails once, Then the card gateway recovers without retaining its render lock', async ({
      page,
    }) => {
      test.skip(!buyer.email, PENDING_BUYER);

      const metrics = recordMetricPayloads(page);
      await startCustomCheckout(page, buyer);
      await expectSuperTokenVisible(page);

      const renderState = await page.evaluate(() => {
        const controller = window.mpSuperTokenPaymentMethods;
        if (!controller) throw new Error('Super Token payment-method controller not published');

        const methods = [
          {
            id: 'e2e-resilience-card',
            token: '',
            name: '',
            thumbnail: '',
            type: 'credit_card',
            issuer: { name: 'E2E' },
            card: { card_number: { last_four_digits: '4242' } },
            security_code_settings: { mode: 'optional', length: 3 },
            installments: [
              {
                installments: 1,
                installment_amount: 100,
                installment_rate: 0,
                total_amount: 100,
              },
            ],
          },
        ];

        const originalDispatcher = window.MPCheckoutFieldsDispatcher;
        window.MPCheckoutFieldsDispatcher = undefined;

        try {
          const amount = controller.getAmount() || '100';
          controller.reset();
          controller.renderAccountPaymentMethods(methods, amount);
          const lockAfterSuccessfulRender = controller.isRendering;

          controller.reset();
          const checkout = controller.getCustomCheckoutEntireElement();
          if (!checkout?.parentNode) throw new Error('Custom Checkout root not found for failure injection');
          const parent = checkout.parentNode;
          const nextSibling = checkout.nextSibling;
          checkout.remove();
          controller.renderAccountPaymentMethods(methods, amount);
          const lockAfterFailedRender = controller.isRendering;

          parent.insertBefore(checkout, nextSibling);
          controller.renderAccountPaymentMethods(methods, amount);

          const cardRow = document.querySelector('[data-type="credit_card"]');
          return {
            lockAfterSuccessfulRender,
            lockAfterFailedRender,
            lockAfterRecovery: controller.isRendering,
            cardRows: document.querySelectorAll('[data-type="credit_card"]').length,
            installmentSelects: cardRow?.querySelectorAll('[data-checkout="installments"]').length ?? 0,
          };
        } finally {
          window.MPCheckoutFieldsDispatcher = originalDispatcher;
        }
      });

      expect(renderState).toEqual({
        lockAfterSuccessfulRender: false,
        lockAfterFailedRender: false,
        lockAfterRecovery: false,
        cardRows: 1,
        installmentSelects: 1,
      });
      await expect
        .poll(
          () =>
            metrics
              .byName('mp_super_token_init_error')
              .filter(
                ({ payload }) =>
                  payload?.value === DISPATCHER_MISSING_VALUE && payload?.message === CARD_DISPATCHER_CONTEXT,
              ).length,
        )
        .toBe(1);

      await expect.poll(() => metrics.byName('error_to_render_account_payment_methods').length).toBe(1);
      const renderFailure = metrics.last('error_to_render_account_payment_methods');
      expect(renderFailure?.message).toBe('CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND');
      expect(renderFailure?.details?.event).toBe('CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND');
    });
  });
}
