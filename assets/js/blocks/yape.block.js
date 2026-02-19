/* globals wc_mercadopago_custom_blocks_params, MercadoPago */

import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useRef } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import {
  addDiscountAndCommission,
  removeDiscountAndCommission,
} from './helpers/cart-update.helper';

import TermsAndConditions from './components/TermsAndConditions';
import TestMode from './components/TestMode';
import InputField from './components/InputField';
import InputCode from './components/InputCode';
import CheckoutNotice from './components/CheckoutNotice';
import RowImageSelect from './components/RowImageSelect';

const targetName = 'mp_checkout_blocks';
const paymentMethodName = 'woo-mercado-pago-yape';

const settings = getSetting(`woo-mercado-pago-yape_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Yape';
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
      sendMetric('MP_YAPE_BLOCKS_SUCCESS', processingResponse.paymentStatus, targetName);
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
      sendMetric('MP_YAPE_BLOCKS_ERROR', processingResponse.paymentStatus, targetName);
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
      imgSrc={settings.params.checkout_blocks_row_image_src}/>
  );
};

const Content = (props) => {
  updateCart(props);

  const {
    test_mode,
    test_mode_title,
    test_mode_description,
    test_mode_link_text,
    test_mode_link_src,
    terms_and_conditions_description,
    terms_and_conditions_link_text,
    terms_and_conditions_link_src,
    input_field_label,
    yape_title,
    yape_subtitle,
    input_code_icon,
    checkout_notice_icon_one,
    checkout_notice_icon_two,
    checkout_notice_message,
    input_code_label,
    footer_text,
    yape_tooltip_text,
    yape_input_code_error_message1,
    yape_input_code_error_message2,
    yape_phone_number_error_message1,
    yape_phone_number_error_message2,
  } = settings.params;

  const ref = useRef(null);

  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  window.mpFormId = 'blocks_checkout_form';
  window.mpCheckoutForm = document.querySelector('.wc-block-components-form.wc-block-checkout__form');

  useEffect(() => {
    if (!hasInitialized) {
      if (typeof MPCheckoutFieldsDispatcher !== 'undefined') {
          MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(
              document.getElementById("checkout__yapePhoneNumber"),
              "focusout",
              "yape_phone_number_filled",
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

      const otp = getCodeValue();
      const phoneNumber = document.getElementById('checkout__yapePhoneNumber').value.replaceAll(' ', '');

      if (otp === '' && phoneNumber === '') {
        document.getElementsByTagName('input-field')[0].validate();
        document.getElementsByTagName('input-code')[0].validate();
        return { type: emitResponse.responseTypes.ERROR };
      }

      const yapeOptions = {
        otp,
        phoneNumber,
      };

      const paymentMethodData = {};
      if (!window.mpSdkInstance) {
        const mp = new MercadoPago(wc_mercadopago_yape_checkout_params.public_key);

        window.mpSdkInstance = mp;
      }
      const yape = window.mpSdkInstance.yape(yapeOptions);

      try {
        const yapeToken = await yape.create();
        paymentMethodData['mercadopago_yape[token]'] = yapeToken.id;
      } catch (error) {
        console.warn('Token creation error: ', error);
        return { type: emitResponse.responseTypes.ERROR };
      }

      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData: {...window.mpHiddenInputDataFromBlocksCheckout, ...paymentMethodData},
        },
      };
    });

    return () => unsubscribe();
  }, [onPaymentSetup, emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS]);

  return (
    <div>
      <div className={'mp-checkout-yape-container'}>
        <div ref={ref} className={'mp-checkout-yape-content'}>
            {test_mode ? (
              <TestMode
                title={test_mode_title}
                description={test_mode_description}
                linkText={test_mode_link_text}
                linkSrc={test_mode_link_src}
              />
            ) : null}
          <div className={'mp-checkout-yape-title-container'}>
            <h2 className={'mp-checkout-yape-title'}>{yape_title}</h2>
            <p className={'mp-checkout-yape-subtitle'}>{yape_subtitle}</p>
          </div>
          <div className={'mp-checkout-yape-inputs'}>
            <InputField labelMessage={input_field_label} emptyErrorMessage={yape_phone_number_error_message1}
                        invalidErrorMessage={yape_phone_number_error_message2}></InputField>
            <InputCode label={input_code_label} src={input_code_icon} emptyErrorMessage={yape_input_code_error_message1}
                       invalidErrorMessage={yape_input_code_error_message2} tooltipText={yape_tooltip_text}></InputCode>
          </div>
          <CheckoutNotice
            message={checkout_notice_message}
            src={checkout_notice_icon_one}
            icon={checkout_notice_icon_two}>
            footerText={footer_text}
          </CheckoutNotice>
        </div>
        <div className={'mp-checkout-yape-terms-and-conditions'}>
          <TermsAndConditions
            description={terms_and_conditions_description}
            linkText={terms_and_conditions_link_text}
            linkSrc={terms_and_conditions_link_src}
          />
        </div>
      </div>

      <div id={'mercadopago-utilities'} style={{ display: 'none' }}>
        <input type="hidden" id="yapeToken" name="mercadopago_yape[token]" />
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
