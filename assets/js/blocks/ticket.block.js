/* globals wc_mercadopago_ticket_blocks_params */

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { getSetting } from "@woocommerce/settings";
import { useEffect, useRef } from "@wordpress/element";
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

const Content = (props) => {
  const {
    test_mode_title,
    test_mode_description,
    test_mode_link_text,
    test_mode_link_src,
    input_document_label,
    input_document_helper,
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

  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;
  const ref = useRef(null);

  let inputDocumentConfig = {
    labelMessage: input_document_label,
    helperMessage: input_document_helper,
    inputName: "mercadopago_ticket[docNumber]",
    selectId: "docType",
    selectName: "mercadopago_ticket[docType]",
    flagError: "mercadopago_ticket[docNumberError]",
    documents: null,
    validate: "true",
  };

  if (site_id === "MLB") {
    inputDocumentConfig.documents = '["CPF","CNPJ"]';
  } else if (site_id === "MLU") {
    inputDocumentConfig.documents = '["CI","OTRO"]';
  }

  useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      const paymentMethodData = {};
      paymentMethodData["payment_from_blocks"] = true;
      paymentMethodData["amount"] = amount.toString();
      paymentMethodData["site_id"] = site_id;
      paymentMethodData["currency_ratio"] = currency_ratio;
      paymentMethodData["mercadopago_ticket_doc_number"] =
        ref.current.querySelector(
          "#form-checkout__identificationNumber-container > input"
        ).value;
      paymentMethodData["mercadopago_ticket_doc_type"] =
        ref.current.querySelector("#docType").value;

      const checkedPaymentMethod = payment_methods.find((method) => {
        const selector = `#${method.id}`;
        const element = ref.current.querySelector(selector);
        return element && element.checked;
      });

      if (checkedPaymentMethod) {
        paymentMethodData["mercadopago_ticket_payment_method"] =
          ref.current.querySelector(`#${checkedPaymentMethod.id}`).value;
      }

      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData,
        },
      };
    });
    return () => {
      unsubscribe();
    };
  }, [
    emitResponse.responseTypes.ERROR,
    emitResponse.responseTypes.SUCCESS,
    onPaymentSetup,
  ]);

  return (
    <div className="mp-checkout-container">
      <div className="mp-checkout-ticket-container">
        <div ref={ref} className="mp-checkout-ticket-content">
          {test_mode ? (
            <TestMode
              title={test_mode_title}
              description={test_mode_description}
              link-text={test_mode_link_text}
              link-src={test_mode_link_src}
            />
          ) : null}
          {inputDocumentConfig ? (
            <InputDocument {...inputDocumentConfig} />
          ) : null}
          <p className="mp-checkout-ticket-tex">{ticket_text_label}</p>
          <InputTable
            name={"mercadopago_ticket[paymentMethodId]"}
            buttonName={input_table_button}
            columns={JSON.stringify(payment_methods)}
          />
          <InputHelper
            isVisible={"false"}
            message={input_helper_label}
            inputId={"mp-payment-method-helper"}
            id={"payment-method-helper"}
          />
          <div id="mp-box-loading"></div>
        </div>
      </div>
      <TermsAndConditions
        description={terms_and_conditions_description}
        linkText={terms_and_conditions_link_text}
        linkSrc={terms_and_conditions_link_src}
        checkoutClass={'ticket'}
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
