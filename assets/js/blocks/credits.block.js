/* globals wc_mercadopago_credits_blocks_params */

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { getSetting } from "@woocommerce/settings";
import { decodeEntities } from "@wordpress/html-entities";
import CheckoutBenefits from "./components/CheckoutBenefits";
import ChoRedirectV2 from "./components/ChoRedirectV2";
import TermsAndConditions from "./components/TermsAndConditions";
import TestMode from "./components/TestMode";

const paymentMethodName = "woo-mercado-pago-credits";
const paymentMethodParams = wc_mercadopago_credits_blocks_params;

const settings = getSetting(`woo-mercado-pago-credits_data`, {});
const defaultLabel = decodeEntities(settings.title) || "Checkout Credits";

const Label = (props) => {
  const { PaymentMethodLabel } = props.components;
  return <PaymentMethodLabel text={defaultLabel} />;
};

const Content = () => {
  const {
    test_mode_title,
    test_mode_description,
    test_mode_link_text,
    test_mode_link_src,
    checkout_benefits_title,
    checkout_benefits_items,
    checkout_redirect_text,
    checkout_redirect_src,
    checkout_redirect_alt,
    terms_and_conditions_description,
    terms_and_conditions_link_text,
    terms_and_conditions_link_src,
    test_mode,
  } = paymentMethodParams;

  return (
    <div className="mp-checkout-container">
      <div className="mp-checkout-pro-container">
        <div className="mp-checkout-pro-content">
          {test_mode ? (
            <TestMode
              title={test_mode_title}
              description={test_mode_description}
              linkText={test_mode_link_text}
              linkSrc={test_mode_link_src}
            />
          ) : null}
          <CheckoutBenefits
            title={checkout_benefits_title}
            items={checkout_benefits_items}
          />
          <ChoRedirectV2
            text={checkout_redirect_text}
            src={checkout_redirect_src}
            alt={checkout_redirect_alt}
          />
        </div>
      </div>
      <TermsAndConditions
        description={terms_and_conditions_description}
        linkText={terms_and_conditions_link_text}
        linkSrc={terms_and_conditions_link_src}
      />
    </div>
  );
};

const mercadopagoPaymentMethod = {
  name: paymentMethodName,
  label: <Label />,
  content: <Content />,
  edit: <Content />,
  canMakePayment: () => true,
  ariaLabel: defaultLabel,
  supports: {
    features: settings?.supports ?? [],
  },
};

registerPaymentMethod(mercadopagoPaymentMethod);
