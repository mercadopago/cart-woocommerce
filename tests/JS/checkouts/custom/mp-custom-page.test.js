const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');

const MP_CUSTOM_PAGE_PATH = resolveAlias('assets/js/checkouts/custom/mp-custom-page.js');

function loadCheckoutPage() {
  return loadFile(MP_CUSTOM_PAGE_PATH, 'CheckoutPage', {
    wc_mercadopago_custom_checkout_params: {
      site_id: 'MLC',
      input_helper_message: {
        installments: {
          interest_free_option_text: 'sem juros',
          bank_interest_hint_text: 'Sujeito a juros do banco emissor',
        },
      },
    },
    wc_mercadopago_custom_page_params: {
      installments_select_placeholder_text: 'Selecione as parcelas',
    },
    CheckoutElements: {},
  });
}

describe('CheckoutPage', () => {
  let CheckoutPage;

  beforeEach(() => {
    CheckoutPage = loadCheckoutPage();
  });

  describe('hasThirdPartyInterestFreeInstallment()', () => {
    test('Given installmentsData is undefined, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(undefined)).toBe(false);
    });

    test('Given installmentsData is null, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(null)).toBe(false);
    });

    test('Given installmentsData has no payer_costs, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment({})).toBe(false);
    });

    test('Given payer_costs is not an array, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment({ payer_costs: 'invalid' })).toBe(false);
    });

    test('Given payer_costs is an empty array, When called, Then returns false', () => {
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment({ payer_costs: [] })).toBe(false);
    });

    test('Given installment_rate_collector is null, When called, Then returns false', () => {
      const data = {
        payer_costs: [
          { installments: 3, installment_rate: 0, installment_rate_collector: null },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(false);
    });

    test('Given installment_rate_collector is missing, When called, Then returns false', () => {
      const data = {
        payer_costs: [
          { installments: 3, installment_rate: 0 },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(false);
    });

    test('Given collector has THIRD_PARTY but installment_rate is not 0, When called, Then returns false', () => {
      const data = {
        payer_costs: [
          { installments: 6, installment_rate: 12.5, installment_rate_collector: ['THIRD_PARTY'] },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(false);
    });

    test('Given installment_rate is 0 but collector is MERCADOPAGO, When called, Then returns false', () => {
      const data = {
        payer_costs: [
          { installments: 6, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'] },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(false);
    });

    test('Given at least one payer_cost has THIRD_PARTY and installment_rate is 0, When called, Then returns true', () => {
      const data = {
        payer_costs: [
          { installments: 1, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'] },
          { installments: 3, installment_rate: 0, installment_rate_collector: ['THIRD_PARTY'] },
        ],
      };
      expect(CheckoutPage.hasThirdPartyInterestFreeInstallment(data)).toBe(true);
    });
  });
});
