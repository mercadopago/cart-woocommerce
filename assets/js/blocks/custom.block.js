/* globals jQuery, wc, MPCheckoutErrorDispatcher, sendMetric, CheckoutPage, MPSuperTokenErrorCodes */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { addDiscountAndCommission, handleCartTotalChange, removeDiscountAndCommission } from './helpers/cart-update.helper';
import { VALIDATION_STORE_KEY } from '@woocommerce/block-data';
import { select } from '@wordpress/data';

import RowImageSelect from './components/RowImageSelect';

const targetName = "mp_checkout_blocks";
const paymentMethodName = 'woo-mercado-pago-custom';

const settings = getSetting(`woo-mercado-pago-custom_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Custom';

const Label = () => {
  const feeTitle = decodeEntities(settings?.params?.fee_title || '');
  const text = `${defaultLabel} ${feeTitle}`;

  return (
    <RowImageSelect
      text={text}
      imgSrc={settings.params.icon} />
  );
};

const Content = (props) => {
  const { eventRegistration, emitResponse, onSubmit } = props;
  const { onPaymentSetup, onCheckoutSuccess, onCheckoutFail } = eventRegistration;
  const [totalValue, setTotalValue] = useState(null);
  const { extensionCartUpdate } = wc.blocksCheckout;

  useEffect(() => {
    addDiscountAndCommission(extensionCartUpdate, paymentMethodName)
      .then((result) => {
        setTotalValue(result?.totals?.total_price);
      })
      .catch((error) => {
        console.error(error);
      });

    return () => {
      removeDiscountAndCommission(extensionCartUpdate, paymentMethodName);
      return onPaymentSetup(() => {
        return { type: emitResponse.responseTypes.SUCCESS };
      })();
    };
  }, [onPaymentSetup]);

  useEffect(() => {
    if (props.billing.cartTotal.value == totalValue) {
      handleCartTotalChange(props.billing.cartTotal.value, props.billing.currency, settings.params.currencyRatio)
    } else {
      addDiscountAndCommission(extensionCartUpdate, paymentMethodName)
        .then((result) => {
          setTotalValue(result?.totals?.total_price);
        })
        .catch((error) => {
          console.error(error);
        });
    }
  }, [props.billing.cartTotal.value, totalValue]);

  useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      window.mpSuperTokenPaymentMethods?.hideSuperTokenError();

      switch (document.querySelector('#mp_checkout_type')?.value) {
        case 'super_token': {
          try {
            const superTokenPaymentMethods = window.mpSuperTokenPaymentMethods;
            const superTokenAuthenticator = window.mpSuperTokenAuthenticator;
            const superTokenMetrics = window.mpSuperTokenMetrics;

            if (!superTokenPaymentMethods) throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND);
            if (!superTokenAuthenticator) throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND);
            if (!superTokenMetrics) throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_METRICS_NOT_FOUND);

            superTokenMetrics.registerClickOnPlaceOrderButton();

            const activeMethod = superTokenPaymentMethods.getActivePaymentMethod();
            const isSuperTokenValid = activeMethod && superTokenPaymentMethods.isSelectedPaymentMethodValid();

            if (activeMethod && !isSuperTokenValid) {
              superTokenPaymentMethods.forceShowValidationErrors();
            }

            if (select(VALIDATION_STORE_KEY).hasValidationErrors()) {
              superTokenPaymentMethods.selectLastPaymentMethodChoosen();

              break;
            }

            if (!activeMethod) throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR);

            if (!isSuperTokenValid) throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID);

            await superTokenAuthenticator.authorizePayment(activeMethod.token);

            await superTokenPaymentMethods.updateSecurityCode();
          } catch (exception) {
            window.mpSuperTokenErrorHandler?.handleError(exception);
            window.mpCustomCheckoutHandler.cardForm.removeLoadSpinner();

            return { type: emitResponse.responseTypes.ERROR };
          }

          break;
        }
        case 'wallet_button':
          break;
        default:
          try {
            const cardToken = await window.mpCustomCheckoutHandler.cardForm.form.createCardToken();
            document.querySelector('#cardTokenId').value = cardToken.token;
          } catch (error) {
            console.warn('token creation error after submit: ', error);
            window.mpCustomCheckoutHandler.cardForm.removeLoadSpinner();
            window.mpCustomCheckoutHandler.cardForm.scrollToCardForm();
            return { type: emitResponse.responseTypes.ERROR };
          }

          if (typeof CheckoutPage !== 'undefined' && !CheckoutPage.installmentsWasSelected()) {
            window.mpCustomCheckoutHandler.cardForm.removeLoadSpinner();
            CheckoutPage.setInstallmentsErrorState(true);
            window.mpCustomCheckoutHandler.cardForm.scrollToCardForm();
            return { type: emitResponse.responseTypes.ERROR };
          }
          break;
      }

      const checkoutInputs = document.querySelector('#mercadopago-utilities');
      const paymentMethodData = {};

      if (checkoutInputs) {
        checkoutInputs.querySelectorAll('input[name]').forEach((input) => {
          paymentMethodData[input.name] = input.value;
        });
      }

      const docTypeSelect = document.querySelector('#form-checkout__identificationType');
      const docNumberInput = document.querySelector('#form-checkout__identificationNumber');

      if (docTypeSelect && docTypeSelect.value) {
        paymentMethodData['mercadopago_custom[doc_type]'] = docTypeSelect.value;
      }

      if (docNumberInput && docNumberInput.value) {
        paymentMethodData['mercadopago_custom[doc_number]'] = docNumberInput.value;
      }

      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData: { ...window.mpHiddenInputDataFromBlocksCheckout, ...paymentMethodData },
        },
      };
    });

    return () => unsubscribe();
  }, [onPaymentSetup]);

  useEffect(() => {
    return () => {
      const paymentMethods = window.mpSuperTokenPaymentMethods;
      if (paymentMethods?.securityFieldsActiveInstance && typeof paymentMethods.unmountActiveSecurityCodeInstance === 'function') {
        paymentMethods.unmountActiveSecurityCodeInstance();
      }

      const cardForm = window.mpCustomCheckoutHandler?.cardForm;
      if (cardForm?.formMounted && cardForm?.form && typeof cardForm.form.unmount === 'function') {
        cardForm.form.unmount();
      }
    };
  }, []);

  useEffect(() => {
    const handle3ds = onCheckoutSuccess(async (checkoutResponse) => {
      const processingResponse = checkoutResponse.processingResponse;
      const paymentDetails = checkoutResponse.processingResponse.paymentDetails;

      if (paymentDetails.three_ds_flow) {
        const threeDsPromise = new Promise((resolve, reject) => {
          window.addEventListener('completed_3ds', (e) => {
            if (e.detail.error) {
              reject(e.detail.error);
            }
            resolve();
          });
        });

        window.mpCustomCheckoutHandler.threeDSHandler.load3DSFlow(paymentDetails.last_four_digits);

        // await for completed_3ds response
        return await threeDsPromise
          .then(() => {
            return {
              type: emitResponse.responseTypes.SUCCESS,
            };
          })
          .catch((error) => {
            return {
              type: emitResponse.responseTypes.FAIL,
              message: error,
              messageContext: emitResponse.noticeContexts.PAYMENTS,
            };
          });
      }
      sendMetric("MP_CUSTOM_BLOCKS_SUCCESS", processingResponse.paymentStatus, targetName);
      return { type: emitResponse.responseTypes.SUCCESS };
    });

    return () => handle3ds();
  }, [onCheckoutSuccess]);

  useEffect(() => {
    const unsubscribe = onCheckoutFail(checkoutResponse => {
      if (typeof MPCheckoutErrorDispatcher !== 'undefined') {
        MPCheckoutErrorDispatcher.dispatchEventWhenBlocksCheckoutErrorOccurred(checkoutResponse);
      }

      window.mpSuperTokenTriggerHandler?.resetSuperTokenOnError();

      const processingResponse = checkoutResponse.processingResponse;
      sendMetric("MP_CUSTOM_BLOCKS_ERROR", processingResponse.paymentStatus, targetName);
      return {
        type: emitResponse.responseTypes.FAIL,
        messageContext: emitResponse.noticeContexts.PAYMENTS,
        message: processingResponse.paymentDetails.message,
      };
    });

    return () => unsubscribe();
  }, [onCheckoutFail]);

  // Initialize form after content is rendered
  useEffect(() => {

    window.mpFormId = 'blocks_checkout_form';
    window.mpCheckoutForm = document.querySelector('.wc-block-components-form.wc-block-checkout__form');

    if (window.mpCheckoutForm) {
      jQuery(window.mpCheckoutForm).prop('id', window.mpFormId);
    }

    // Add wallet button click handler if it exists
    const walletButton = document.getElementById('mp-wallet-button');
    if (walletButton) {
      walletButton.addEventListener('click', (event) => {
        event.preventDefault();
        document.querySelector('#mp_checkout_type').value = 'wallet_button';
        onSubmit();
      });
    }
  }, []);

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
