/* globals wc_mercadopago_pix_blocks_params */

import { useEffect } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { addDiscountAndCommission, removeDiscountAndCommission } from './helpers/cart-update.helper';

import TestMode from './components/TestMode';
import PixTemplate from './components/PixTemplate';
import TermsAndConditions from './components/TermsAndConditions';
import RowImageSelect from './components/RowImageSelect';

const targetName = "mp_checkout_blocks";
const paymentMethodName = 'woo-mercado-pago-pix';

const settings = getSetting(`woo-mercado-pago-pix_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Pix';

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
      checkoutResponse.processingResponse.message = paymentMethodName;
      const processingResponse = checkoutResponse.processingResponse;
      sendMetric("MP_PIX_BLOCKS_SUCCESS", processingResponse.paymentStatus, targetName);
      return { type: emitResponse.responseTypes.SUCCESS };
    });

    return () => unsubscribe();
  }, [onCheckoutSuccess]);

  useEffect(() => {
    const unsubscribe = onCheckoutFail(checkoutResponse => {
      const processingResponse = checkoutResponse.processingResponse;
      sendMetric("MP_PIX_BLOCKS_ERROR", processingResponse.paymentStatus, targetName);
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
    amount,
    message_error_amount,
  } = settings.params;

  if (amount == null) {
    return (<><p className={'alert-message'}>{message_error_amount}</p></>);
  }

  return (
    <div className="mp-checkout-container">
      <div className="mp-checkout-pix-container">
        <div className="mp-checkout-pix-content">
          {test_mode ? <TestMode title={test_mode_title} description={test_mode_description} /> : null}

          <PixTemplate
            title={pix_template_title}
            subtitle={pix_template_subtitle}
            alt={pix_template_alt}
            linkSrc={pix_template_src}
          />
        </div>
      </div>

      <TermsAndConditions
        description={terms_and_conditions_description}
        linkText={terms_and_conditions_link_text}
        linkSrc={terms_and_conditions_link_src}
        checkoutClass={'pix'}
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
