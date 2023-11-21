/* globals wc_mercadopago_credits_blocks_params */

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { getSetting } from "@woocommerce/settings";
import { decodeEntities } from "@wordpress/html-entities";
import CheckoutBenefits from './CheckoutBenefits';
import TestMode from './TestMode';

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
  } = paymentMethodParams;

  return (
    <div className="mp-checkout-container">
      <div className="mp-checkout-pro-container">
        <div className="mp-checkout-pro-content">
          <TestMode
            title={test_mode_title}
            description={test_mode_description}
            linkText={test_mode_link_text}
            linkSrc={test_mode_link_src}
          />
          <CheckoutBenefits title={checkout_benefits_title} items={checkout_benefits_items} />
        </div>
      </div>
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
