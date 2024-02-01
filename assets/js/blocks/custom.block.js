/* globals wc_mercadopago_custom_blocks_params */

import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useRef, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { addDiscountAndCommission, handleCartTotalChange, removeDiscountAndCommission } from './helpers/cart-update.helper';

import InputDocument from './components/InputDocument';
import InputHelper from './components/InputHelper';
import InputLabel from './components/InputLabel';
import PaymentMethods from './components/PaymentMethods';
import TermsAndConditions from './components/TermsAndConditions';
import TestMode from './components/TestMode';
import sendMetric from "./helpers/metrics.helper";

const targetName = "mp_checkout_blocks";
const paymentMethodName = 'woo-mercado-pago-custom';

const settings = getSetting(`woo-mercado-pago-custom_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Custom';

const updateCart = (props) => {
  const { extensionCartUpdate } = wc.blocksCheckout;
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;
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
    test_mode,
    test_mode_title,
    test_mode_description,
    test_mode_link_text,
    test_mode_link_src,
    wallet_button,
    wallet_button_image,
    wallet_button_title,
    wallet_button_description,
    wallet_button_button_text,
    available_payments_title_icon,
    available_payments_title,
    available_payments_image,
    payment_methods_items,
    payment_methods_promotion_link,
    payment_methods_promotion_text,
    site_id,
    card_form_title,
    card_number_input_label,
    card_number_input_helper,
    card_holder_name_input_label,
    card_holder_name_input_helper,
    card_expiration_input_label,
    card_expiration_input_helper,
    card_security_code_input_label,
    card_security_code_input_helper,
    card_document_input_label,
    card_document_input_helper,
    card_installments_title,
    card_issuer_input_label,
    card_installments_input_helper,
    terms_and_conditions_description,
    terms_and_conditions_link_text,
    terms_and_conditions_link_src,
    amount,
    currency_ratio,
  } = settings.params;

  const ref = useRef(null);
  const [checkoutType, setCheckoutType] = useState('custom');

  const { eventRegistration, emitResponse, onSubmit } = props;
  const { onPaymentSetup, onCheckoutSuccess, onCheckoutFail } = eventRegistration;

  window.mpFormId = 'blocks_checkout_form';
  window.mpCheckoutForm = document.querySelector('.wc-block-components-form.wc-block-checkout__form');

  jQuery(window.mpCheckoutForm).prop('id', mpFormId);

  const submitWalletButton = (event) => {
    event.preventDefault();
    setCheckoutType('wallet_button');
    onSubmit();
  };

  const collapsibleEvent = () => {
    const availablePayment = document.getElementsByClassName('mp-checkout-custom-available-payments')[0];
    const collapsible = availablePayment.getElementsByClassName('mp-checkout-custom-available-payments-header')[0];

    const icon = collapsible.getElementsByClassName('mp-checkout-custom-available-payments-collapsible')[0];
    const content = availablePayment.getElementsByClassName('mp-checkout-custom-available-payments-content')[0];

    if (content.style.maxHeight) {
      icon.src = settings.params.available_payments_chevron_down;
      content.style.maxHeight = null;
      content.style.padding = '0px';
    } else {
      let hg = content.scrollHeight + 15 + 'px';
      icon.src = settings.params.available_payments_chevron_up;
      content.style.setProperty('max-height', hg, 'important');
      content.style.setProperty('padding', '24px 0px 0px', 'important');
    }
  };

  useEffect(() => {
    handleCartTotalChange(props.billing.cartTotal.value, props.billing.currency);
  }, [props.billing.cartTotal.value]);

  useEffect(() => {
    if (cardFormMounted) {
      cardForm.unmount();
    }
    initCardForm();

    const unsubscribe = onPaymentSetup(async () => {
      const cardholderName = document.querySelector('#form-checkout__cardholderName');
      const cardholderNameErrorMessage = document.querySelector('#mp-card-holder-name-helper');

      if(cardholderName.value == ''){
        setInputDisplayStyle(cardholderNameErrorMessage, 'flex');
      }

      function setInputDisplayStyle(inputElement, displayValue) {
        if (inputElement && inputElement.style) {
          inputElement.style.display = displayValue;
        }
      }

      if (document.querySelector('#mp_checkout_type').value !== 'wallet_button') {
        try {
          if (CheckoutPage.validateInputsCreateToken()) {
            const cardToken = await cardForm.createCardToken();
            document.querySelector('#cardTokenId').value = cardToken.token;
          } else {
            return { type: emitResponse.responseTypes.ERROR };
          }
        } catch (error) {
          console.warn('Token creation error: ', error);
        }
      }

      const checkoutInputs = ref.current;
      const paymentMethodData = {};

      checkoutInputs.childNodes.forEach((input) => {
        if (input.tagName === 'INPUT' && input.name) {
          paymentMethodData[input.name] = input.value;
        }
      });

      // asserting that next submit will be "custom", unless the submitWalletButton function is fired
      setCheckoutType('custom');

      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData,
        },
      };
    });

    return () => unsubscribe();
  }, [onPaymentSetup, emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS]);

  useEffect(() => {
    const handle3ds = onCheckoutSuccess(async (checkoutResponse) => {
      const  processingResponse = checkoutResponse.processingResponse;
      const paymentDetails = checkoutResponse.processingResponse.paymentDetails;

      if (paymentDetails.three_ds_flow) {
        const threeDsPromise = new Promise((resolve, reject) => {
          window.addEventListener('completed_3ds', (e) => {
            if (e.detail.error) {
              console.log('rejecting with ' + e.detail.error);
              reject(e.detail.error);
            }

            resolve();
          });
        });

        load3DSFlow(paymentDetails.last_four_digits);

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

  return (
    <div>
      <div className={'mp-checkout-custom-load'}>
        <div className={'spinner-card-form'}></div>
      </div>
      <div className={'mp-checkout-container'}>
        <div className={'mp-checkout-custom-container'}>
          {test_mode ? (
            <div className={'mp-checkout-pro-test-mode'}>
              <TestMode
                title={test_mode_title}
                description={test_mode_description}
                linkText={test_mode_link_text}
                linkSrc={test_mode_link_src}
              />
            </div>
          ) : null}

          {wallet_button === 'yes' ? (
            <div className={'mp-wallet-button-container'}>
              <img src={wallet_button_image} />

              <div className={'mp-wallet-button-title'}>
                <span>{wallet_button_title}</span>
              </div>

              <div className={'mp-wallet-button-description'}>{wallet_button_description}</div>

              <div className={'mp-wallet-button-button'}>
                <button id={'mp-wallet-button'} type={'button'} onClick={submitWalletButton}>
                  {wallet_button_button_text}
                </button>
              </div>
            </div>
          ) : null}

          <div id={'mp-custom-checkout-form-container'}>
            <div className={'mp-checkout-custom-available-payments'}>
              <div className={'mp-checkout-custom-available-payments-header'} onClick={collapsibleEvent}>
                <div className={'mp-checkout-custom-available-payments-title'}>
                  <img src={available_payments_title_icon} className={'mp-icon'} />
                  <p className={'mp-checkout-custom-available-payments-text'}>{available_payments_title}</p>
                </div>

                <img src={available_payments_image} className={'mp-checkout-custom-available-payments-collapsible'} />
              </div>

              <div className={'mp-checkout-custom-available-payments-content'}>
                <PaymentMethods methods={payment_methods_items} />

                {site_id === 'MLA' ? (
                  <>
                    <span id={'mp_promotion_link'}> | </span>
                    <a
                      href={payment_methods_promotion_link}
                      id={'mp_checkout_link'}
                      className={'mp-checkout-link mp-pl-10'}
                      target={'_blank'}
                    >
                      {payment_methods_promotion_text}
                    </a>
                  </>
                ) : null}
                <hr />
              </div>
            </div>

            <div className={'mp-checkout-custom-card-form'}>
              <p className={'mp-checkout-custom-card-form-title'}>{card_form_title}</p>

              <div className={'mp-checkout-custom-card-row'}>
                <InputLabel isOptinal={false} message={card_number_input_label} forId={'mp-card-number'} />
                <div className={'mp-checkout-custom-card-input'} id={'form-checkout__cardNumber-container'}></div>
                <InputHelper isVisible={false} message={card_number_input_helper} inputId={'mp-card-number-helper'} />
              </div>

              <div className={'mp-checkout-custom-card-row'} id={'mp-card-holder-div'}>
                <InputLabel message={card_holder_name_input_label} isOptinal={false} />

                <input
                  className={'mp-checkout-custom-card-input mp-card-holder-name'}
                  placeholder={'Ex.: María López'}
                  id={'form-checkout__cardholderName'}
                  name={'mp-card-holder-name'}
                  data-checkout={'cardholderName'}
                />

                <InputHelper
                  isVisible={false}
                  message={card_holder_name_input_helper}
                  inputId={'mp-card-holder-name-helper'}
                  dataMain={'mp-card-holder-name'}
                />
              </div>

              <div className={'mp-checkout-custom-card-row mp-checkout-custom-dual-column-row'}>
                <div className={'mp-checkout-custom-card-column'}>
                  <InputLabel message={card_expiration_input_label} isOptinal={false} />

                  <div
                    id={'form-checkout__expirationDate-container'}
                    className={'mp-checkout-custom-card-input mp-checkout-custom-left-card-input'}
                  />

                  <InputHelper
                    isVisible={false}
                    message={card_expiration_input_helper}
                    inputId={'mp-expiration-date-helper'}
                  />
                </div>

                <div className={'mp-checkout-custom-card-column'}>
                  <InputLabel message={card_security_code_input_label} isOptinal={false} />

                  <div id={'form-checkout__securityCode-container'} className={'mp-checkout-custom-card-input'} />
                  <p id={'mp-security-code-info'} className={'mp-checkout-custom-info-text'} />

                  <InputHelper
                    isVisible={false}
                    message={card_security_code_input_helper}
                    inputId={'mp-security-code-helper'}
                  />
                </div>
              </div>

              <div id={'mp-doc-div'} className={'mp-checkout-custom-input-document'} style={{ display: 'none' }}>
                <InputDocument
                  labelMessage={card_document_input_label}
                  helperMessage={card_document_input_helper}
                  inputName={'identificationNumber'}
                  hiddenId={'form-checkout__identificationNumber'}
                  inputDataCheckout={'docNumber'}
                  selectId={'form-checkout__identificationType'}
                  selectName={'identificationType'}
                  selectDataCheckout={'docType'}
                  flagError={'docNumberError'}
                />
              </div>
            </div>

            <div id={'mp-checkout-custom-installments'} className={'mp-checkout-custom-installments-display-none'}>
              <p className={'mp-checkout-custom-card-form-title'}>{card_installments_title}</p>

              <div id={'mp-checkout-custom-issuers-container'} className={'mp-checkout-custom-issuers-container'}>
                <div className={'mp-checkout-custom-card-row'}>
                  <InputLabel isOptinal={false} message={card_issuer_input_label} forId={'mp-issuer'} />
                </div>

                <div className={'mp-input-select-input'}>
                  <select name={'issuer'} id={'form-checkout__issuer'} className={'mp-input-select-select'}></select>
                </div>
              </div>

              <div
                id={'mp-checkout-custom-installments-container'}
                className={'mp-checkout-custom-installments-container'}
              />

              <InputHelper
                isVisible={false}
                message={card_installments_input_helper}
                inputId={'mp-installments-helper'}
              />

              <select
                style={{ display: 'none' }}
                data-checkout={'installments'}
                name={'installments'}
                id={'form-checkout__installments'}
                className={'mp-input-select-select'}
              />

              <div id={'mp-checkout-custom-box-input-tax-cft'}>
                <div id={'mp-checkout-custom-box-input-tax-tea'}>
                  <div id={'mp-checkout-custom-tax-tea-text'}></div>
                </div>
                <div id={'mp-checkout-custom-tax-cft-text'}></div>
              </div>
            </div>

            <div className={'mp-checkout-custom-terms-and-conditions'}>
              <TermsAndConditions
                description={terms_and_conditions_description}
                linkText={terms_and_conditions_link_text}
                linkSrc={terms_and_conditions_link_src}
                checkoutClass={'custom'}
              />
            </div>
          </div>
        </div>
      </div>

      <div ref={ref} id={'mercadopago-utilities'} style={{ display: 'none' }}>
        <input type={'hidden'} id={'cardTokenId'} name={'mercadopago_custom[token]'} />
        <input type={'hidden'} id={'mpCardSessionId'} name={'mercadopago_custom[session_id]'} />
        <input type={'hidden'} id={'cardExpirationYear'} data-checkout={'cardExpirationYear'} />
        <input type={'hidden'} id={'cardExpirationMonth'} data-checkout={'cardExpirationMonth'} />
        <input type={'hidden'} id={'cardInstallments'} name={'mercadopago_custom[installments]'} />
        <input type={'hidden'} id={'paymentMethodId'} name={'mercadopago_custom[payment_method_id]'} />
        <input type={'hidden'} id={'mp-amount'} defaultValue={amount} name={'mercadopago_custom[amount]'} />

        <input
          type={'hidden'}
          id={'currency_ratio'}
          defaultValue={currency_ratio}
          name={'mercadopago_custom[currency_ratio]'}
        />

        <input
          type={'hidden'}
          id={'mp_checkout_type'}
          name={'mercadopago_custom[checkout_type]'}
          value={checkoutType}
        />
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
