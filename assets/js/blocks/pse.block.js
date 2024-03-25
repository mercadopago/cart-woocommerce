/* globals wc_mercadopago_pse_blocks_params */

import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useRef } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { addDiscountAndCommission, removeDiscountAndCommission } from './helpers/cart-update.helper';

import InputDocument from './components/InputDocument';
import TermsAndConditions from './components/TermsAndConditions';
import TestMode from './components/TestMode';
import sendMetric from "./helpers/metrics.helper";
import InputSelect from './components/InputSelect';


const targetName = "mp_checkout_blocks";
const paymentMethodName = 'woo-mercado-pago-pse';

const settings = getSetting(`woo-mercado-pago-pse_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Pse';

const updateCart = (props) => {
  const { extensionCartUpdate } = wc.blocksCheckout;
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup, onCheckoutSuccess, onCheckoutFail } = eventRegistration;

  useEffect(() => {
    addDiscountAndCommission(extensionCartUpdate, paymentMethodName);

    const unsubscribe = onPaymentSetup(() => {
      return { type: emitResponse.responseTypes.SUCCESS };
    });

    return () => {
      removeDiscountAndCommission(extensionCartUpdate, paymentMethodName);
      return unsubscribe();
    };
  }, [onPaymentSetup]);

  useEffect(() => {

    const unsubscribe = onCheckoutSuccess(async (checkoutResponse) => {
      const processingResponse = checkoutResponse.processingResponse;
      sendMetric("MP_PSE_BLOCKS_SUCCESS", processingResponse.paymentStatus, targetName);
      return { type: emitResponse.responseTypes.SUCCESS };
    });

    return () => unsubscribe();
  }, [onCheckoutSuccess]);

  useEffect(() => {
    const unsubscribe = onCheckoutFail(checkoutResponse => {
      const processingResponse = checkoutResponse.processingResponse;
      sendMetric("MP_PSE_BLOCKS_ERROR", processingResponse.paymentStatus, targetName);
      return {
        type: emitResponse.responseTypes.FAIL,
        messageContext: emitResponse.noticeContexts.PAYMENTS,
        message: processingResponse.paymentDetails.message,
      };
    });

    return () => unsubscribe();
  }, [onCheckoutFail]);

};

const Label = (props) => {
  const { PaymentMethodLabel } = props.components;

  const feeTitle = decodeEntities(settings?.params?.fee_title || '');
  const text = `${defaultLabel} ${feeTitle}`;

  return <PaymentMethodLabel text={text} />;
};

const Content = (props) => {
  updateCart(props);

  const {
    test_mode_title,
    test_mode_description,
    test_mode_link_text,
    test_mode_link_src,
    input_document_label,
    input_document_helper,
    pse_text_label,
    person_type_label,
    amount,
    site_id,
    terms_and_conditions_description,
    terms_and_conditions_link_text,
    terms_and_conditions_link_src,
    test_mode,
    financial_institutions,
    financial_institutions_label,
    financial_institutions_helper,
    financial_placeholder,
  } = settings.params;

  const ref = useRef(null);

  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  let inputDocumentConfig = {
    labelMessage: input_document_label,
    helperMessage: input_document_helper,
    validate: 'true',
    selectId: 'doc_type',
    flagError: 'mercadopago_pse[docNumberError]',
    inputName: 'mercadopago_pse[docNumber]',
    selectName: 'mercadopago_pse[docType]',
    documents: '["CC","CE","NIT"]',
  };

  useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      //const pseContent = ref.current.querySelector('.mp-checkout-pse-container');
      const inputDocHelper = document.querySelector('.mp-checkout-pse-input-document').querySelector('.mp-input-document > input-helper > div');

      const paymentMethodData = {
        'mercadopago_pse[site_id]': site_id,
        'mercadopago_pse[amount]': amount.toString(),
        'mercadopago_pse[doc_type]': ref.current.querySelector('#doc_type')?.value,
        'mercadopago_pse[doc_number]': ref.current.querySelector(
          '#form-checkout__identificationNumber-container > input',
        )?.value,
        'mercadopago_pse[bank]': ref.current.querySelector('#mercadopago_pse\\[bank\\]').value,
        'mercadopago_pse[person_type]': ref.current.querySelector('#mercadopago_pse\\[person_type\\]').value,

      };

      if (inputDocumentConfig.documents && paymentMethodData['mercadopago_pse[doc_number]'] === '') {
        setInputDisplayStyle(inputDocHelper, 'flex');
      }

      let financialData = document.querySelector('#mercadopago_pse\\[bank\\]');
      let financialHelpers =  document.querySelector('.mp-checkout-pse-bank').querySelector('input-helper > div');
      if (financialData.value === '' || {financial_placeholder} === financialData.value ) {
        setInputDisplayStyle(financialHelpers, 'flex');
      }

      const hasErrorInForm = isInputDisplayFlex(financialHelpers) || isInputDisplayFlex(inputDocHelper);

      function setInputDisplayStyle(inputElement, displayValue) {
        if (inputElement && inputElement.style) {
          inputElement.style.display = displayValue;
        }
      }
      function isInputDisplayFlex(inputElement) {
        return inputElement && inputElement.style.display === 'flex';
      }

      return {
        type: hasErrorInForm ? emitResponse.responseTypes.ERROR : emitResponse.responseTypes.SUCCESS,
        meta: { paymentMethodData },
      };
    });

    return () => unsubscribe();
  }, [emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS, onPaymentSetup]);

  return (
    <div className="mp-checkout-container">
      <p className="mp-checkout-pse-text" data-cy="checkout-pse-text">
        {pse_text_label}
      </p>
      <div className="mp-checkout-pse-container">
        <div ref={ref} className="mp-checkout-pse-content">
          {test_mode ? (
            <TestMode
              title={test_mode_title}
              description={test_mode_description}
              link-text={test_mode_link_text}
              link-src={test_mode_link_src}
            />
          ) : null}
          <div className="mp-checkout-pse-person">
            <InputSelect name={'mercadopago_pse[person_type]'}
                         label={person_type_label}
                         optional={false}
                         options={'[{"id":"individual", "description": "individual"},{"id":"association", "description": "asociación"}]'}>
            </InputSelect>
          </div>
          <div className="mp-checkout-pse-input-document">
            {inputDocumentConfig.documents ? <InputDocument {...inputDocumentConfig} /> : null}
          </div>

          <div className="mp-checkout-pse-bank">
            <InputSelect
              name={'mercadopago_pse[bank]'}
              label={financial_institutions_label}
              optional={false}
              options={financial_institutions}
              hidden-id={'hidden-financial-pse'}
              helper-message={financial_institutions_helper}
              default-option={financial_placeholder}>
            </InputSelect>
          </div>
          <div id="mp-box-loading"></div>
        </div>
      </div>

      <TermsAndConditions
        description={terms_and_conditions_description}
        linkText={terms_and_conditions_link_text}
        linkSrc={terms_and_conditions_link_src}
        checkoutClass={'pse'}
      />
    </div>
  )
    ;
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
