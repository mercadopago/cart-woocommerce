/* globals wc_mercadopago_pix_blocks_params */

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { getSetting } from "@woocommerce/settings";
import { decodeEntities } from "@wordpress/html-entities";
import PixTemplate from "./components/PixTemplate";
import TermsAndConditions from "./components/TermsAndConditions";
import TestMode from "./components/TestMode";

const paymentMethodName = "woo-mercado-pago-pix";
const paymentMethodParams = wc_mercadopago_pix_blocks_params;

const settings = getSetting(`woo-mercado-pago-pix_data`, {});
const defaultLabel = decodeEntities(settings.title) || "Checkout Pix";

const Label = (props) => {
  const { PaymentMethodLabel } = props.components;
  return <PaymentMethodLabel text={defaultLabel} />;
};

const Content = () => {
  const {
    test_mode_title,
    test_mode_description,
    pix_template_title,
    pix_template_subtitle,
    pix_template_src,
    pix_template_alt,
    terms_and_conditions_description,
    terms_and_conditions_link_text,
    terms_and_conditions_link_src,
    test_mode,
  } = settings.params;

  console.log("PIX:" + JSON.stringify(settings))

  return (
    <div className="mp-checkout-container">
      <div className="mp-checkout-pix-container">
        <div className="mp-checkout-pix-content">
          {test_mode ? (
            <TestMode
              title={test_mode_title}
              description={test_mode_description}
            />
          ) : null}
          <PixTemplate
            title={pix_template_title}
            subTitle={pix_template_subtitle}
            alt={pix_template_alt}
            linkSrc={pix_template_src}
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
