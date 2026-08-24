import { test, expect } from "../fixtures.js";
import {
  startCustomCheckout,
  expectSuperTokenVisible,
  selectFirstSavedCard,
  expectMetric,
  recordMetricPayloads,
  assertMetricContract,
  PENDING_BUYER,
} from "../flows/super-token.js";

const { buyerFor } = require("../data/country.js");
const { skipIfNotSite } = require("../../helpers/site-guard.js");

// O ab_variant do adapter vem SÓ do cookie mp_st_variant (getAbVariant), e esse cookie é escrito
// exclusivamente pelo loader — que só roda com MP_SUPER_TOKEN_USE_BUNDLE=true. Para o contrato ser
// determinístico e independente do modo de bundle da loja, injetamos o cookie antes de qualquer
// script da página (addInitScript) e exigimos que as métricas o reflitam. Sem cookie, o adapter
// (em paridade com o legado) reportaria 'unknown'.
const EXPECTED_VARIANT = "v2.1";

// Métricas de init reportadas pelo CoreMonitorMetricsAdapter (via SdkReadinessWatcher) + a de
// seleção de método salvo. Todas passam por sendMetric → buildPayload, logo carregam ab_variant.
const INIT_METRICS = ["super_token_sdk_loaded", "super_token_init_source"];
const SELECT_METRIC = "select_payment_method";

// Só a família de métricas do checkout carrega details.ab_variant. A telemetria de bootstrap do
// VariantConfigAdapter (fetch_ab_config, super_token_ab_variant, ...) posta no MESMO endpoint via
// beacon sem ab_variant — por isso o contrato de variante roda apenas sobre quem o expõe.
const carriesVariant = ({ payload }) => payload?.details?.ab_variant !== undefined;

export function metricsContractScenarios(site) {
  const buyer = buyerFor(site);

  test.describe(`Super Token metrics contract — ${site.toUpperCase()}`, () => {
    test.beforeEach(() => {
      skipIfNotSite(test, site.toUpperCase());
      test.skip(!buyer.email, PENDING_BUYER);
    });

    test("Given a Super Token checkout, When it initializes and a saved payment method is selected, Then every Super Token metric POST carries ab_variant and the required payload shape", async ({ page }) => {
      // Registra os payloads ANTES de navegar — as métricas de init saem já no carregamento do ST.
      const metrics = recordMetricPayloads(page);
      // Injeta a variante A/B antes de qualquer script (adapter e loader leem o cookie): torna o
      // contrato determinístico mesmo quando o loader não roda (USE_BUNDLE=false não seta o cookie).
      await page.addInitScript((v) => {
        document.cookie = `mp_st_variant=${v}; path=/`;
      }, EXPECTED_VARIANT);
      const initArrived = INIT_METRICS.map((name) => expectMetric(page, name));
      const selectArrived = expectMetric(page, SELECT_METRIC);

      await startCustomCheckout(page, buyer);
      await expectSuperTokenVisible(page);
      await Promise.all(initArrived);

      await selectFirstSavedCard(page);
      await selectArrived;

      const expectedVariant = EXPECTED_VARIANT;

      // (a) as métricas-chave saíram COM payload legível e cumprindo o contrato completo.
      for (const name of [...INIT_METRICS, SELECT_METRIC]) {
        assertMetricContract(metrics.last(name), { expectedVariant });
      }

      // (b) toda métrica de checkout capturada (a que expõe ab_variant) cumpre o contrato.
      const checkoutPayloads = metrics.all().filter(carriesVariant);
      expect(checkoutPayloads.length, "at least one Super Token checkout metric captured").toBeGreaterThan(0);
      for (const { name, payload } of checkoutPayloads) {
        assertMetricContract(payload, { expectedVariant });
        expect(payload.details.ab_variant, `${name} carries the served ab_variant`).toBe(expectedVariant);
      }
    });
  });
}
