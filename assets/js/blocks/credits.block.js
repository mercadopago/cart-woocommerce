/* globals wc_mercadopago_credits_blocks_params */

import { useEffect } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { addDiscountAndCommission, removeDiscountAndCommission } from './helpers/cart-update.helper';

import TestMode from './components/TestMode';
import ChoRedirectV2 from './components/ChoRedirectV2';
import CheckoutBenefits from './components/CheckoutBenefits';
import TermsAndConditions from './components/TermsAndConditions';
import sendMetric from "./helpers/metrics.helper";

const targetName = "mp_checkout_blocks";
const paymentMethodName = 'woo-mercado-pago-credits';

const settings = getSetting(`woo-mercado-pago-credits_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Creditss';

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
      sendMetric("MP_CREDITS_BLOCKS_SUCCESS", processingResponse.paymentStatus, targetName);
      return { type: emitResponse.responseTypes.SUCCESS };
    });

    return () => unsubscribe();
  }, [onCheckoutSuccess]);
    
  useEffect(() => {
    const unsubscribe = onCheckoutFail(checkoutResponse => {
      const processingResponse = checkoutResponse.processingResponse;
      sendMetric("MP_CREDITS_BLOCKS_ERROR", processingResponse.paymentStatus, targetName);
      return {
        type: emitResponse.responseTypes.FAIL,        
        messageContext: emitResponse.noticeContexts.PAYMENTS,
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
    checkout_benefits_title,
    checkout_benefits_items,
    checkout_redirect_text,
    checkout_redirect_src,
    checkout_redirect_alt,
    terms_and_conditions_description,
    terms_and_conditions_link_text,
    terms_and_conditions_link_src,
    test_mode,
  } = settings.params;

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
            titleAlign="left"
            listMode="count"
          />

          <ChoRedirectV2 text={checkout_redirect_text} src={checkout_redirect_src} alt={checkout_redirect_alt} />
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
