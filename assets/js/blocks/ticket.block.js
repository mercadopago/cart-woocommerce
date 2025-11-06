/* globals wc_mercadopago_ticket_blocks_params */

import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { addDiscountAndCommission, removeDiscountAndCommission } from './helpers/cart-update.helper';
import RowImageSelect from './components/RowImageSelect';

const targetName = "mp_checkout_blocks";
const paymentMethodName = 'woo-mercado-pago-ticket';

const settings = getSetting(`woo-mercado-pago-ticket_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Ticket';
let hasInitialized = false;

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
      sendMetric("MP_TICKET_BLOCKS_SUCCESS", processingResponse.paymentStatus, targetName);
      return { type: emitResponse.responseTypes.SUCCESS };
    });

    return () => unsubscribe();
  }, [onCheckoutSuccess]);

  useEffect(() => {
    const unsubscribe = onCheckoutFail(checkoutResponse => {
      if (typeof MPCheckoutErrorDispatcher !== 'undefined') {
        MPCheckoutErrorDispatcher.dispatchEventWhenBlocksCheckoutErrorOccurred(checkoutResponse);
      }

      const processingResponse = checkoutResponse.processingResponse;
      sendMetric("MP_TICKET_BLOCKS_ERROR", processingResponse.paymentStatus, targetName);
      return {
        type: emitResponse.responseTypes.FAIL,
        messageContext: emitResponse.noticeContexts.PAYMENTS,
        message: processingResponse.paymentDetails.message,
      };
    });

    return () => unsubscribe();
  }, [onCheckoutFail]);

};

const Label = () => {
  const feeTitle = decodeEntities(settings?.params?.fee_title || '');
  const text = `${defaultLabel} ${feeTitle}`;

  return (
    <RowImageSelect
      text={text}
      imgSrc={settings.params.icon}/>
  );
};

const Content = (props) => {
  updateCart(props);

  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  const mlbTicketRequiredFields = [
    'address_city',
    'address_federal_unit',
    'address_zip_code',
    'address_street_name',
    'address_street_number',
    'address_neighborhood',
    'address_complement'
  ];

  useEffect(() => {
    if (!hasInitialized) {
      if (typeof MPCheckoutFieldsDispatcher !== 'undefined') {
          MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(
              document.getElementById("mp-ticket-gateway-document-input"),
              "focusout",
              "ticket_document_filled",
              {
                  dispatchOnlyIf: (e) => e?.target?.value.length
              }
          );
      }

      hasInitialized = true;
    }
  }, []);

  useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      const inputDocDiv = document.querySelector('.mp-input');
      const inputDocHelper = document.getElementById('mp-doc-number-helper');
      const inputPaymentMethod = document.getElementById('mp-payment-method-helper');
      const paymentMethodData = {
        'mercadopago_ticket[site_id]': document.getElementsByName('mercadopago_ticket[site_id]')[0].value,
        'mercadopago_ticket[amount]': document.getElementsByName('mercadopago_ticket[amount]')[0].value,
        'mercadopago_ticket[doc_type]': document.getElementsByName('mercadopago_ticket[doc_type]')[0]?.value,
        'mercadopago_ticket[doc_number]':
          document.getElementsByName('mercadopago_ticket[doc_number]')[0]?.value ??
          document.getElementsByName('mercadopago_ticket[docNumberError]')[0]?.value,
      };

      document
        .querySelector('.mp-checkout-ticket-container')
        .querySelectorAll('.mp-input-radio-radio')
        .forEach((item) => {
          if (item.checked) {
            paymentMethodData['mercadopago_ticket[payment_method_id]'] = item.value;
            inputPaymentMethod.style.display = 'none';
          }
      });

      if (!paymentMethodData['mercadopago_ticket[payment_method_id]']) {
        const hiddenPaymentMethod = document.querySelector('input[name="mercadopago_ticket[payment_method_id]"][type="hidden"]');
        if (hiddenPaymentMethod && hiddenPaymentMethod.value) {
          paymentMethodData['mercadopago_ticket[payment_method_id]'] = hiddenPaymentMethod.value;
          inputPaymentMethod.style.display = 'none';
        }
      }

      const siteId = document.getElementsByName('mercadopago_ticket[site_id]')[0].value;

      if (siteId == 'MLB') {
        mlbTicketRequiredFields.forEach(element => {
          paymentMethodData[`mercadopago_ticket[${element}]`] = document.querySelector(`#form-checkout__${element}`).value;
        });
      }

      if ((siteId == 'MLB' || siteId == 'MLU') && paymentMethodData['mercadopago_ticket[doc_number]'] === '') {
        inputDocDiv.classList.add('mp-error');
        setInputDisplayStyle(inputDocHelper, 'flex');
      }

      if (!paymentMethodData['mercadopago_ticket[payment_method_id]']) {
        setInputDisplayStyle(inputPaymentMethod, 'flex');
      }
                                                        // global js function from mp-ticket-checkout.js
      const hasErrorInAdressFields = siteId == 'MLB' && !addressFieldsFromTicketRowAreValid();
      const hasErrorInForm =
        isInputDisplayFlex(inputDocHelper) ||
        isInputDisplayFlex(inputPaymentMethod) ||
        hasErrorInAdressFields;

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
        meta: {
          paymentMethodData: {...window.mpHiddenInputDataFromBlocksCheckout, ...paymentMethodData}
        },
      };
    });

    return () => unsubscribe();
  }, [emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS, onPaymentSetup]);

  return <div dangerouslySetInnerHTML={{ __html: settings.params.content }} />;
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
