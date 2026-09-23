import { test, expect } from '../fixtures.js';
import {
  startCustomCheckout,
  startCheckoutReadyToPay,
  expectSuperTokenVisible,
  forceVariant,
  expectVariantLoaded,
  isMethodOffered,
  expectMethodVisibleInList,
  selectPaymentMethodByType,
  expectMethodSelected,
  expectNewCardSelectable,
  recordMetricPayloads,
  PENDING_BUYER,
} from '../flows/super-token.js';

const { buyerFor } = require('../data/country.js');
const { skipIfNotSite } = require('../../helpers/site-guard.js');

const VARIANTS = ['v2', 'v2.1'];
const ALTERNATIVE_METHODS = [
  { type: 'account_money', label: 'account money' },
  { type: 'digital_currency', label: 'credits' },
];
const DISPATCHER_MISSING_VALUE = 'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING';
const CREDITS_DISPATCHER_CONTEXT = 'super_token_consumer_credits_installments_setup';

const metricPayloadsByValue = (metrics, name, value) =>
  metrics.byName(name).filter(({ payload }) => payload?.value === value);

export function alternativeMethodsScenarios(site) {
  const buyer = buyerFor(site);

  test.describe(`Super Token alternative methods — ${site.toUpperCase()}`, () => {
    test.beforeEach(() => {
      skipIfNotSite(test, site.toUpperCase());
      test.skip(!buyer.email, PENDING_BUYER);
    });

    for (const variant of VARIANTS) {
      for (const method of ALTERNATIVE_METHODS) {
        test(`Given the ${variant} variant and an eligible buyer, When ${method.label} is offered, Then it is shown in the payment methods list and can be selected`, async ({
          page,
          faults,
        }) => {
          await forceVariant(page, faults, variant);
          await startCheckoutReadyToPay(page, buyer);
          await expectSuperTokenVisible(page);
          await expectVariantLoaded(page, variant, buyer.email);

          const offered = await isMethodOffered(page, method.type);
          test.skip(!offered, `${method.label} not offered to this buyer (eligibility) — RN-1 exception`);

          await expectMethodVisibleInList(page, method.type);
          await selectPaymentMethodByType(page, method.type);
          await expectMethodSelected(page, method.type);
        });
      }

      test(`Given the ${variant} variant and an eligible buyer, When they open the new-card accordion, Then it is shown and can be selected`, async ({
        page,
        faults,
      }) => {
        await forceVariant(page, faults, variant);
        await startCheckoutReadyToPay(page, buyer);
        await expectSuperTokenVisible(page);
        await expectVariantLoaded(page, variant, buyer.email);

        await expectNewCardSelectable(page);
      });

      test(`Given the ${variant} variant, When account money is selected, cleared and rendered again, Then its lifecycle remains consistent without duplicate chrome`, async ({
        page,
        faults,
      }) => {
        await forceVariant(page, faults, variant);
        await startCustomCheckout(page, buyer);
        await expectSuperTokenVisible(page);
        await expectVariantLoaded(page, variant, buyer.email);

        const lifecycle = await page.evaluate(async () => {
          const controller = window.mpSuperTokenPaymentMethods;
          if (!controller) throw new Error('Super Token payment-method controller not published');
          const methods = [
            {
              id: 'e2e-account-money-lifecycle',
              token: '',
              name: '',
              thumbnail: '',
              type: 'account_money',
              has_account_money: true,
              has_account_money_invested: false,
            },
          ];
          const amount = controller.getAmount() || '100';
          const chrome = () => ({
            headers: document.querySelectorAll('.mp-payment-methods-header').length,
            blocks: document.querySelectorAll('.mp-super-token-block').length,
          });

          controller.reset();
          controller.renderAccountPaymentMethods(methods, amount);
          const firstChrome = chrome();
          const accountMoney = document.querySelector('[data-type="account_money"]');
          if (!accountMoney) throw new Error('Synthetic account-money row was not rendered');

          controller.deselectAllPaymentMethods();
          controller.selectPaymentMethod(accountMoney);
          await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
          const balanceLinesAfterSelect = document.querySelectorAll('.mp-super-token-am-balance-text').length;

          controller.deselectAllPaymentMethods();
          await new Promise((resolve) => setTimeout(resolve, 450));
          const balanceLinesAfterClear = document.querySelectorAll('.mp-super-token-am-balance-text').length;

          controller.reset();
          const chromeAfterReset = chrome();
          controller.renderAccountPaymentMethods(methods, amount);

          return {
            firstChrome,
            chromeAfterReset,
            chromeAfterRerender: chrome(),
            balanceLinesAfterSelect,
            balanceLinesAfterClear,
            lockAfterRerender: controller.isRendering,
          };
        });

        if (variant === 'v2') {
          expect(lifecycle.firstChrome).toEqual({ headers: 1, blocks: 0 });
          expect(lifecycle.balanceLinesAfterSelect).toBe(0);
        } else {
          expect(lifecycle.firstChrome).toEqual({ headers: 0, blocks: 1 });
          expect(lifecycle.balanceLinesAfterSelect).toBe(1);
        }
        expect(lifecycle.balanceLinesAfterClear).toBe(0);
        expect(lifecycle.chromeAfterReset).toEqual({ headers: 0, blocks: 0 });
        expect(lifecycle.chromeAfterRerender).toEqual(lifecycle.firstChrome);
        expect(lifecycle.lockAfterRerender).toBe(false);
      });

      test(`Given the ${variant} variant and one Credits row whose details fail, When the saved methods render, Then that row falls back without removing the remaining methods`, async ({
        page,
        faults,
      }) => {
        await forceVariant(page, faults, variant);
        await startCustomCheckout(page, buyer);
        await expectSuperTokenVisible(page);
        await expectVariantLoaded(page, variant, buyer.email);
        const syntheticRenderMetrics = recordMetricPayloads(page);

        const renderState = await page.evaluate(() => {
          const controller = window.mpSuperTokenPaymentMethods;
          if (!controller) throw new Error('Super Token payment-method controller not published');

          const failingInstallment = {
            installments: 1,
            installment_rate: 0,
            total_amount: 100,
            consumer_credits: { conditions: {} },
            labels: [],
          };
          Object.defineProperty(failingInstallment, 'installment_amount', {
            get() {
              throw new Error('Synthetic Credits details failure');
            },
          });

          const methods = [
            {
              id: 'e2e-credits-row-failure',
              token: '',
              name: '',
              thumbnail: '',
              type: 'digital_currency',
              credits_pricing_id: 'e2e-credits-pricing',
              installments: [failingInstallment],
            },
            {
              id: 'e2e-account-money-after-failure',
              token: '',
              name: '',
              thumbnail: '',
              type: 'account_money',
              has_account_money: true,
              has_account_money_invested: false,
            },
          ];

          controller.reset();
          controller.renderAccountPaymentMethods(methods, controller.getAmount() || '100');

          const creditsRow = document.querySelector('[data-id="e2e-credits-row-failure"]');
          return {
            rows: document.querySelectorAll('.mp-super-token-payment-method').length,
            creditsFallbackWithoutDetails: creditsRow?.querySelector('[data-checkout="installments"]') === null,
            accountMoneyPresent: document.querySelector('[data-id="e2e-account-money-after-failure"]') !== null,
            headers: document.querySelectorAll('.mp-payment-methods-header').length,
            blocks: document.querySelectorAll('.mp-super-token-block').length,
            lockReleased: controller.isRendering === false,
          };
        });

        expect(renderState.rows).toBe(2);
        expect(renderState.creditsFallbackWithoutDetails).toBe(true);
        expect(renderState.accountMoneyPresent).toBe(true);
        expect(renderState.lockReleased).toBe(true);
        expect(renderState.headers).toBe(variant === 'v2' ? 1 : 0);
        expect(renderState.blocks).toBe(variant === 'v2.1' ? 1 : 0);
        await expect
          .poll(
            () => metricPayloadsByValue(
              syntheticRenderMetrics,
              'error_to_render_account_payment_methods',
              'true',
            ).length,
          )
          .toBe(1);
        await expect
          .poll(
            () => metricPayloadsByValue(
              syntheticRenderMetrics,
              'render_consumer_credits_details_inner_html',
              'false',
            ).length,
          )
          .toBe(1);
      });
    }

    test('Given a Credits row without installment_rate_collector, When it is rendered twice, Then its details metric is emitted and missing-dispatcher telemetry remains deduplicated for the Credits gateway', async ({
      page,
    }) => {
      const metrics = recordMetricPayloads(page);
      await startCustomCheckout(page, buyer);
      await expectSuperTokenVisible(page);
      const syntheticRenderMetrics = recordMetricPayloads(page);

      const renderState = await page.evaluate(async () => {
        const controller = window.mpSuperTokenPaymentMethods;
        if (!controller) throw new Error('Super Token payment-method controller not published');

        const methods = [
          {
            id: 'e2e-credits-installments',
            token: '',
            name: '',
            thumbnail: '',
            type: 'digital_currency',
            credits_pricing_id: 'e2e-credits-pricing',
            installments: [
              {
                installments: 1,
                installment_amount: 100,
                installment_rate: 0,
                total_amount: 100,
                consumer_credits: { conditions: {} },
                labels: [],
              },
            ],
          },
        ];

        const originalDispatcher = window.MPCheckoutFieldsDispatcher;
        const originalRenderCreditsContract = controller.mpSdkInstance.renderCreditsContract;
        window.MPCheckoutFieldsDispatcher = undefined;
        controller.mpSdkInstance.renderCreditsContract = () => Promise.resolve({ update() {} });

        try {
          const amount = controller.getAmount() || '100';
          controller.reset();
          controller.renderAccountPaymentMethods(methods, amount);
          controller.reset();
          controller.renderAccountPaymentMethods(methods, amount);
          await Promise.resolve();

          const creditsRow = document.querySelector('[data-type="digital_currency"]');
          return {
            lockAfterRerender: controller.isRendering,
            creditsRows: document.querySelectorAll('[data-type="digital_currency"]').length,
            installmentSelects: creditsRow?.querySelectorAll('[data-checkout="installments"]').length ?? 0,
          };
        } finally {
          controller.mpSdkInstance.renderCreditsContract = originalRenderCreditsContract;
          window.MPCheckoutFieldsDispatcher = originalDispatcher;
        }
      });

      expect(renderState).toEqual({
        lockAfterRerender: false,
        creditsRows: 1,
        installmentSelects: 1,
      });
      await expect
        .poll(
          () =>
            metrics
              .byName('mp_super_token_init_error')
              .filter(
                ({ payload }) =>
                  payload?.value === DISPATCHER_MISSING_VALUE && payload?.message === CREDITS_DISPATCHER_CONTEXT,
              ).length,
        )
        .toBe(1);
      await expect
        .poll(
          () =>
            metricPayloadsByValue(
              syntheticRenderMetrics,
              'render_consumer_credits_details_inner_html',
              'true',
            ).length,
        )
        .toBe(2);
    });
  });
}
