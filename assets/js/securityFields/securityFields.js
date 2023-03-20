/* globals wc_mercadopago_params, MercadoPago, CheckoutPage, MP_DEVICE_SESSION_ID */

var cardForm;
var hasToken = false;
var mercado_pago_submit = false;
var triggeredPaymentMethodSelectedEvent = false;
var cardFormMounted = false;

var form = document.querySelector('form[name=checkout]');
var formId = 'checkout';

if (form) {
  form.id = formId;
} else {
  formId = 'order_review';
}

function mercadoPagoFormHandler() {
  let formOrderReview = document.querySelector('form[id=order_review]');

  if (formOrderReview) {
    let choCustomContent = document.querySelector('.mp-checkout-custom-container');
    let choCustomHelpers = choCustomContent.querySelectorAll('input-helper');

    choCustomHelpers.forEach((item) => {
      let inputHelper = item.querySelector('div');
      if (inputHelper.style.display !== 'none') {
        removeBlockOverlay();
      }
    });
  }

  setMercadoPagoSessionId();

  if (mercado_pago_submit) {
    return true;
  }

  if (jQuery('#mp_checkout_type').val() === 'wallet_button') {
    return true;
  }

  jQuery('#mp_checkout_type').val('custom');

  if (CheckoutPage.validateInputsCreateToken() && !hasToken) {
    return createToken();
  }

  return false;
}

/**
 * Create a new token
 * @return {boolean}
 */
function createToken() {
  cardForm
    .createCardToken()
    .then((cardToken) => {
      if (cardToken.token) {
        if (hasToken) {
          return;
        }

        document.querySelector('#cardTokenId').value = cardToken.token;
        mercado_pago_submit = true;
        hasToken = true;

        jQuery('form.checkout, form#order_review').submit();
      } else {
        throw new Error('cardToken is empty');
      }
    })
    .catch((error) => {
      console.warn('Token creation error: ', error);
    });

  return false;
}

/**
 * Init cardForm
 */
function initCardForm() {
  var mp = new MercadoPago(wc_mercadopago_params.public_key);

  return new Promise((resolve, reject) => {
    cardForm = mp.cardForm({
      amount: getAmount(),
      iframe: true,
      form: {
        id: formId,
        cardNumber: {
          id: 'form-checkout__cardNumber-container',
          placeholder: '0000 0000 0000 0000',
          style: {
            'font-size': '16px',
            height: '40px',
            padding: '14px',
          },
        },
        cardholderName: {
          id: 'form-checkout__cardholderName',
          placeholder: 'Ex.: María López',
        },
        cardExpirationDate: {
          id: 'form-checkout__expirationDate-container',
          placeholder: wc_mercadopago_params.placeholders.cardExpirationDate,
          mode: 'short',
          style: {
            'font-size': '16px',
            height: '40px',
            padding: '14px',
          },
        },
        securityCode: {
          id: 'form-checkout__securityCode-container',
          placeholder: '123',
          style: {
            'font-size': '16px',
            height: '40px',
            padding: '14px',
          },
        },
        identificationType: {
          id: 'form-checkout__identificationType',
        },
        identificationNumber: {
          id: 'form-checkout__identificationNumber',
        },
        issuer: {
          id: 'form-checkout__issuer',
          placeholder: wc_mercadopago_params.placeholders.issuer,
        },
        installments: {
          id: 'form-checkout__installments',
          placeholder: wc_mercadopago_params.placeholders.installments,
        },
      },
      callbacks: {
        onReady: () => {
          resolve();
        },
        onFormMounted: function (error) {
          cardFormMounted = true;

          if (error) {
            console.log('Callback to handle the error: creating the CardForm', error);
            return;
          }
        },
        onFormUnmounted: function (error) {
          cardFormMounted = false;
          CheckoutPage.clearInputs();

          if (error) {
            console.log('Callback to handle the error: unmounting the CardForm', error);
            return;
          }
        },
        onInstallmentsReceived: (error, installments) => {
          if (error) {
            console.warn('Installments handling error: ', error);
            return;
          }

          CheckoutPage.setChangeEventOnInstallments(CheckoutPage.getCountry(), installments);
        },
        onCardTokenReceived: (error) => {
          if (error) {
            console.warn('Token handling error: ', error);
            return;
          }
        },
        onPaymentMethodsReceived: (error, paymentMethods) => {
          try {
            if (paymentMethods) {
              CheckoutPage.setValue('paymentMethodId', paymentMethods[0].id);
              CheckoutPage.setCvvHint(paymentMethods[0].settings[0].security_code);
              CheckoutPage.changeCvvPlaceHolder(paymentMethods[0].settings[0].security_code.length);
              CheckoutPage.clearInputs();
              CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'remove', 'mp-error');
              CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'none');
              CheckoutPage.setImageCard(paymentMethods[0].thumbnail);
              CheckoutPage.installment_amount(paymentMethods[0].payment_type_id);
              CheckoutPage.loadAdditionalInfo(paymentMethods[0].additional_info_needed);
              CheckoutPage.additionalInfoHandler(additionalInfoNeeded);
            } else {
              CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
              CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'flex');
            }
          } catch (error) {
            CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
            CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'flex');
          }
        },
        onSubmit: function (event) {
          event.preventDefault();
        },
        onValidityChange: function (error, field) {
          if (error) {
            let helper_message = CheckoutPage.getHelperMessage(field);
            let message = wc_mercadopago_params.input_helper_message[field][error[0].code];

            if (message) {
              helper_message.innerHTML = message;
            } else {
              helper_message.innerHTML = wc_mercadopago_params.input_helper_message[field].invalid_length;
            }

            if (field === 'cardNumber') {
              if (error[0].code !== 'invalid_length') {
                CheckoutPage.setBackground('fcCardNumberContainer', 'no-repeat #fff');
                CheckoutPage.removeAdditionFields();
                CheckoutPage.clearInputs();
              }
            }

            let containerField = CheckoutPage.findContainerField(field);
            CheckoutPage.setDisplayOfError(containerField, 'add', 'mp-error');

            return CheckoutPage.setDisplayOfInputHelper(CheckoutPage.inputHelperName(field), 'flex');
          }

          let containerField = CheckoutPage.findContainerField(field);
          CheckoutPage.setDisplayOfError(containerField, 'removed', 'mp-error');

          return CheckoutPage.setDisplayOfInputHelper(CheckoutPage.inputHelperName(field), 'none');
        },
        onError: function (errors) {
          errors.forEach((error) => {
            removeBlockOverlay();

            if (error.message.includes('timed out')) {
              return reject(error);
            } else if (error.message.includes('cardNumber')) {
              CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
              return CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'flex');
            } else if (error.message.includes('cardholderName')) {
              CheckoutPage.setDisplayOfError('fcCardholderName', 'add', 'mp-error');
              return CheckoutPage.setDisplayOfInputHelper('mp-card-holder-name', 'flex');
            } else if ( error.message.includes('expirationMonth') || error.message.includes('expirationYear')) {
              CheckoutPage.setDisplayOfError('fcCardExpirationDateContainer', 'add', 'mp-error' );
              return CheckoutPage.setDisplayOfInputHelper('mp-expiration-date', 'flex');
            } else if (error.message.includes('securityCode')) {
              CheckoutPage.setDisplayOfError('fcSecurityNumberContainer', 'add', 'mp-error');
              return CheckoutPage.setDisplayOfInputHelper('mp-security-code', 'flex');
            } else if (error.message.includes('identificationNumber')) {
              CheckoutPage.setDisplayOfError('fcIdentificationNumberContainer', 'add', 'mp-error');
              return CheckoutPage.setDisplayOfInputHelper('mp-doc-number', 'flex');
            } else {
              return reject(error);
            }
          });
        },
      },
    });
  });
}

function getAmount() {
  const amount = parseFloat(
    document.getElementById('mp-amount').value.replace(',', '.')
  );

  const currencyRatio = parseFloat(
    document.getElementById('currency_ratio').value.replace(',', '.')
  );

  return String(amount * currencyRatio);
}

/**
 * Get and set MP Armor to improve payment approval
 * @return {void}
 */
function setMercadoPagoSessionId() {
  try {
    document.querySelector('#mpCardSessionId').value = MP_DEVICE_SESSION_ID;
  } catch (e) {
    console.warn(e);
  }
}

function removeBlockOverlay() {
  if (jQuery('form#order_review').length > 0) {
    jQuery('.blockOverlay').css('display', 'none');
  }
}

function cardFormLoad() {
  if (document.getElementById('payment_method_woo-mercado-pago-custom').checked) {
    setTimeout(() => {
      if (!cardFormMounted) {
        handleCardFormLoad();
      }
    }, 1000);
  } else {
    if (cardFormMounted) {
      cardForm.unmount();
    }
  }
}

function handleCardFormLoad() {
  initCardForm()
    .then(() => {
      sendMetric('MP_CARDFORM_SUCCESS', 'Security fields loaded');
    })
    .catch((error) => {
      const parsedError = handleCardFormErrors(error);
      sendMetric('MP_CARDFORM_ERROR', parsedError);
      console.error('Mercado Pago cardForm error: ', parsedError);
    });
}

function handleCardFormErrors(cardFormErrors) {
  if (cardFormErrors.length) {
    const errors = [];
    cardFormErrors.forEach((e) => {
      errors.push(e.description || e.message);
    });

    return errors.join(',');
  }

  return cardFormErrors.description || cardFormErrors.message;
}

function sendMetric(name, message) {
  const url = 'https://api.mercadopago.com/v1/plugins/melidata/errors';
  const payload = {
    name,
    message,
    target: 'mp_custom_checkout_security_fields_client',
    plugin: {
      version: wc_mercadopago_params.plugin_version,
    },
    platform: {
      name: 'woocommerce',
      uri: window.location.href,
      version: wc_mercadopago_params.platform_version,
      location: `${wc_mercadopago_params.location}_${wc_mercadopago_params.theme}`,
    },
  };

  navigator.sendBeacon(url, JSON.stringify(payload));
}

jQuery('form.checkout').on(
  'checkout_place_order_woo-mercado-pago-custom',
  function () {
    return mercadoPagoFormHandler();
  }
);

jQuery('body').on('payment_method_selected', function () {
  if (!triggeredPaymentMethodSelectedEvent) {
    cardFormLoad();
  }
});

jQuery('form#order_review').submit(function () {
  if (document.getElementById('payment_method_woo-mercado-pago-custom').checked) {
    return mercadoPagoFormHandler();
  } else {
    cardFormLoad();
  }
});

jQuery(document.body).on('checkout_error', () => {
  hasToken = false;
  mercado_pago_submit = false;
});

if (!triggeredPaymentMethodSelectedEvent) {
  jQuery('body').trigger('payment_method_selected');
}
