import { test } from "../fixtures.js";
import {
  startCheckoutReadyToPay,
  expectSuperTokenVisible,
  forceVariant,
  expectVariantLoaded,
  isMethodOffered,
  expectMethodVisibleInList,
  selectPaymentMethodByType,
  expectMethodSelected,
  expectNewCardSelectable,
  PENDING_BUYER,
} from "../flows/super-token.js";

const { buyerFor } = require("../data/country.js");
const { skipIfNotSite } = require("../../helpers/site-guard.js");

const VARIANTS = ["v2", "v2.1"];
const ALTERNATIVE_METHODS = [
  { type: "account_money", label: "account money" },
  { type: "digital_currency", label: "credits" },
];

export function alternativeMethodsScenarios(site) {
  const buyer = buyerFor(site);

  test.describe(`Super Token alternative methods — ${site.toUpperCase()}`, () => {
    test.beforeEach(() => {
      skipIfNotSite(test, site.toUpperCase());
      test.skip(!buyer.email, PENDING_BUYER);
    });

    for (const variant of VARIANTS) {
      for (const method of ALTERNATIVE_METHODS) {
        test(`Given the ${variant} variant and an eligible buyer, When ${method.label} is offered, Then it is shown in the payment methods list and can be selected`, async ({ page, faults }) => {
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

      test(`Given the ${variant} variant and an eligible buyer, When they open the new-card accordion, Then it is shown and can be selected`, async ({ page, faults }) => {
        await forceVariant(page, faults, variant);
        await startCheckoutReadyToPay(page, buyer);
        await expectSuperTokenVisible(page);
        await expectVariantLoaded(page, variant, buyer.email);

        await expectNewCardSelectable(page);
      });
    }
  });
}
