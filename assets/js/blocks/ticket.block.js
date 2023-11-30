/* globals wc_mercadopago_basic_blocks_params */

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { getSetting } from "@woocommerce/settings";
import { decodeEntities } from "@wordpress/html-entities";
import InputDocument from "./components/InputDocument";
import InputHelper from "./components/InputHelper";
import InputTable from "./components/InputTable";
import TermsAndConditions from "./components/TermsAndConditions";
import TestMode from "./components/TestMode";

const paymentMethodName = "woo-mercado-pago-ticket";

const settings = getSetting(`woo-mercado-pago-ticket_data`, {});
const defaultLabel = decodeEntities(settings.title) || "Checkout Ticket";

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
    labelMessage,
    helperMessage,
    documents,
    ticket_text_label,
    input_table_button,
    input_helper_label,
    payment_methods,
    currency_ratio,
    amount,
    site_id,
    terms_and_conditions_description,
    terms_and_conditions_link_text,
    terms_and_conditions_link_src,
    test_mode,
  } = settings.params;

  return (
    <div className="mp-checkout-container">
      <div className="mp-checkout-ticket-container">
        <div className="mp-checkout-ticket-content">
          {test_mode ? (
            <TestMode
              title={test_mode_title}
              description={test_mode_description}
              link-text={test_mode_link_text}
              link-src={test_mode_link_src}
            />
          ) : null}
          <div className="mp-checkout-ticket-input-document">
            <InputDocument
              label-message={labelMessage}
              helper-message={helperMessage}
              input-name="mercadopago_ticket[docNumber]"
              select-name="mercadopago_ticket[docType]"
              flag-error="mercadopago_ticket[docNumberError]"
              documents='["CI","OTRO"]'
              validate="true"
            />
          </div>
          <p className="mp-checkout-ticket-tex">{ticket_text_label}</p>
          <InputTable
            name={"mercadopago_ticket[paymentMethodId]"}
            buttonName={input_table_button}
            columns={payment_methods}
          />
          <InputHelper
            isVisible={"false"}
            message={input_helper_label}
            inputId={"mp-payment-method-helper"}
            id={"payment-method-helper"}
          />
          <div id="mp-box-loading"></div>

          <div id="mercadopago-utilities" style="display:none;">
            <input
              type="hidden"
              id="site_id"
              value={site_id}
              name="`mercadopago_ticket[site_id]`"
            />
            <input
              type="hidden"
              id="amountTicket"
              value={amount}
              name="`mercadopago_ticket[amount]`"
            />
            <input
              type="hidden"
              id="currency_ratioTicket"
              value={currency_ratio}
              name="mercadopago_ticket[currency_ratio]"
            />
            <input
              type="hidden"
              id="campaign_idTicket"
              name="mercadopago_ticket[campaign_id]"
            />
            <input
              type="hidden"
              id="campaignTicket"
              name="mercadopago_ticket[campaign]"
            />
            <input
              type="hidden"
              id="discountTicket"
              name="mercadopago_ticket[discount]"
            />
          </div>
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
