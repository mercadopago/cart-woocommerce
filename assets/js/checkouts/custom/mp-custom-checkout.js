/* globals wc_mercadopago_custom_checkout_params, MercadoPago, CheckoutPage, MP_DEVICE_SESSION_ID */

var cardForm;
var hasToken = false;
var mercado_pago_submit = false;
var triggeredPaymentMethodSelectedEvent = false;
var cardFormMounted = false;
var threedsTarget = 'mp_custom_checkout_security_fields_client';

var mpCheckoutForm = document.querySelector('form[name=checkout]');
var mpFormId = 'checkout';

if (mpCheckoutForm) {
  mpCheckoutForm.id = mpFormId;
} else {
  mpFormId = 'order_review';
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

// Create a new token
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

        if (mpFormId === 'order_review') {
          handle3dsPayOrderFormSubmission();
          return false;
        }

        jQuery('form.checkout').submit();
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
function initCardForm(amount = getAmount()) {
  var mp = new MercadoPago(wc_mercadopago_custom_checkout_params.public_key);

  return new Promise((resolve, reject) => {
    cardForm = mp.cardForm({
      amount: amount,
      iframe: true,
      form: {
        id: mpFormId,
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
          placeholder: wc_mercadopago_custom_checkout_params.placeholders['cardExpirationDate'],
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
          placeholder: wc_mercadopago_custom_checkout_params.placeholders['issuer'],
        },
        installments: {
          id: 'form-checkout__installments',
          placeholder: wc_mercadopago_custom_checkout_params.placeholders['installments'],
        },
      },
      callbacks: {
        onReady: () => {
          removeLoadSpinner();
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
              CheckoutPage.setImageCard(paymentMethods[0].secure_thumbnail || paymentMethods[0].thumbnail);
              CheckoutPage.installment_amount(paymentMethods[0].payment_type_id);
              const additionalInfoNeeded = CheckoutPage.loadAdditionalInfo(paymentMethods[0].additional_info_needed);
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
            let message = wc_mercadopago_custom_checkout_params.input_helper_message[field][error[0].code];

            if (message) {
              helper_message.innerHTML = message;
            } else {
              helper_message.innerHTML =
                wc_mercadopago_custom_checkout_params.input_helper_message[field]['invalid_length'];
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
            } else if (error.message.includes('expirationMonth') || error.message.includes('expirationYear')) {
              CheckoutPage.setDisplayOfError('fcCardExpirationDateContainer', 'add', 'mp-error');
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
  const amount = parseFloat(document.getElementById('mp-amount').value.replace(',', '.'));

  return String(amount);
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
  const checkoutCustomPaymentMethodElement = document.getElementById('payment_method_woo-mercado-pago-custom');

  if (checkoutCustomPaymentMethodElement && checkoutCustomPaymentMethodElement.checked) {
    setTimeout(() => {
      if (!cardFormMounted) {
        createLoadSpinner();
        handleCardFormLoad();
      }
    }, 2500);
  } else {
    if (cardFormMounted) {
      cardForm.unmount();
    }
  }
}

function setCardFormLoadInterval() {
  var cardFormInterval = setInterval(() => {
    const checkoutCustomPaymentMethodElement = document.getElementById('payment_method_woo-mercado-pago-custom');
    const cardInput = document.getElementById('form-checkout__cardNumber-container');

    // Checkout Custom is not selected, so we can stop checking
    if (!checkoutCustomPaymentMethodElement || !checkoutCustomPaymentMethodElement.checked) {
      clearInterval(cardFormInterval);
      return;
    }

    // CardForm iframe is rendered, so we can stop checking
    if (cardInput && cardInput.childElementCount > 0) {
      clearInterval(cardFormInterval);
      return;
    }

    // CardForm is mounted but the iframe is not rendered, so we reload the CardForm
    if (cardFormMounted) {
      cardForm.unmount();
      cardFormLoad();
    }
  }, 1000);
}

function handleCardFormLoad() {
  initCardForm()
    .then(() => {
      sendMetric('MP_CARDFORM_SUCCESS', 'Security fields loaded', threedsTarget);
    })
    .catch((error) => {
      const parsedError = handleCardFormErrors(error);
      sendMetric('MP_CARDFORM_ERROR', parsedError, threedsTarget);
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

jQuery('form.checkout').on('checkout_place_order_woo-mercado-pago-custom', mercadoPagoFormHandler);

jQuery('body').on('payment_method_selected', function () {
  if (!triggeredPaymentMethodSelectedEvent) {
    cardFormLoad();
  }
});

jQuery('form#order_review').submit(function (event) {
  const selectPaymentMethod = document.getElementById('payment_method_woo-mercado-pago-custom');

  if (selectPaymentMethod && selectPaymentMethod.checked) {
    event.preventDefault();
    return mercadoPagoFormHandler();
  } else {
    cardFormLoad();
  }
});

jQuery(document.body).on('checkout_error', () => {
  hasToken = false;
  mercado_pago_submit = false;
});

jQuery(document).on('updated_checkout', function () {
  const checkoutCustomPaymentMethodElement = document.getElementById('payment_method_woo-mercado-pago-custom');

  // Checkout Custom is not selected, so we can stop checking
  if (checkoutCustomPaymentMethodElement && checkoutCustomPaymentMethodElement.checked) {
    if (cardFormMounted) {
      cardForm.unmount();
    }

    handleCardFormLoad();
    return;
  }
});

jQuery(document).ready(() => {
  setCardFormLoadInterval();
});

if (!triggeredPaymentMethodSelectedEvent) {
  jQuery('body').trigger('payment_method_selected');
}

function createLoadSpinner() {
  document.querySelector('.mp-checkout-custom-container').style.display = 'none';
  document.querySelector('.mp-checkout-custom-load').style.display = 'flex';
}

function removeLoadSpinner() {
  document.querySelector('.mp-checkout-custom-container').style.display = 'block';
  document.querySelector('.mp-checkout-custom-load').style.display = 'none';
}

function removeLoadSpinner3ds() {
  var element = document.getElementById('mp-loading-container-3ds');
  if (element) {
    element.remove();
  }
}

function addLoadSpinner3dsSubmit() {
  var modalContent = document.getElementById('mp-3ds-modal-content');
  modalContent.innerHTML =
    '<div id="mp-loading-container-3ds">' +
    '   <div>' +
    '     <div class="mp-spinner-3ds"></div>' +
    '       <div class="mp-loading-text-3ds">' +
    '         <p>' +
    wc_mercadopago_custom_checkout_params.threeDsText.title_loading_response +
    '          </p>' +
    '       </div>' +
    '   </div>' +
    ' <div>';
}

function removeModal3ds() {
  CheckoutPage.clearInputs();
  document.getElementById('mp-3ds-modal-container').remove();
}

function threeDSHandler(url_3ds, cred_3ds) {
  try {
    if (url_3ds == null || cred_3ds == null) {
      removeModal3ds();
      sendMetric('MP_THREE_DS_ERROR', '3DS URL or CRED not set', threedsTarget);
      console.log('Invalid parameters for 3ds');
      return;
    }

    var divMpCardInfo = document.createElement('div');
    divMpCardInfo.className = 'mp-card-info';
    divMpCardInfo.innerHTML =
      '<div class="mp-alert-color-success"></div>' +
      '<div class="mp-card-body-3ds">' +
      '<div class="mp-icon-badge-info"></div>' +
      '<div><span class="mp-text-subtitle">' +
      wc_mercadopago_custom_checkout_params.threeDsText.title_frame +
      '</span></div>' +
      '</div>';

    var divModalContent = document.getElementById('mp-3ds-modal-content');

    var iframe = document.createElement('iframe');
    iframe.name = 'mp-3ds-frame';
    iframe.id = 'mp-3ds-frame';
    iframe.onload = () => removeLoadSpinner3ds();

    document.getElementById('mp-3ds-title').innerText = wc_mercadopago_custom_checkout_params.threeDsText.tooltip_frame;
    divModalContent.appendChild(divMpCardInfo);

    divModalContent.appendChild(iframe);
    var idocument = iframe.contentWindow.document;

    var form3ds = idocument.createElement('form');
    form3ds.name = 'mp-3ds-frame';
    form3ds.className = 'mp-modal';
    form3ds.setAttribute('target', 'mp-3ds-frame');
    form3ds.setAttribute('method', 'post');
    form3ds.setAttribute('action', url_3ds);

    var hiddenField = idocument.createElement('input');
    hiddenField.setAttribute('type', 'hidden');
    hiddenField.setAttribute('name', 'creq');
    hiddenField.setAttribute('value', cred_3ds);

    form3ds.appendChild(hiddenField);
    iframe.appendChild(form3ds);

    form3ds.submit();
  } catch (error) {
    console.log(error);
    sendMetric('MP_THREE_DS_ERROR', '3DS Loading error: ' + error, threedsTarget);
    alert('Error doing Challenge, try again later.');
  }
}

function load3DSFlow(lastFourDigits) {
  var divModalContainer = document.createElement('div');
  divModalContainer.setAttribute('id', 'mp-3ds-modal-container');
  divModalContainer.className = 'mp-3ds-modal';

  var divModalContent = document.createElement('div');
  divModalContent.id = 'mp-3ds-modal-content';
  divModalContent.innerHTML =
    '<div><div id="mp-modal-3ds-title">' +
    '<span id="mp-3ds-title"></span>' +
    '<span id="mp-3ds-modal-close" >&times;</span>' +
    '</div>' +
    '<div id="mp-loading-container-3ds">' +
    '   <div>' +
    '     <div class="mp-spinner-3ds"></div>' +
    '       <div class="mp-loading-text-3ds">' +
    '         <p>' +
    wc_mercadopago_custom_checkout_params.threeDsText.title_loading +
    '<br>' +
    '           (' +
    document.getElementById('paymentMethodId').value +
    '****' +
    lastFourDigits +
    ') ' +
    wc_mercadopago_custom_checkout_params.threeDsText.title_loading2 +
    '          </p>' +
    '       </div>' +
    '       <p class="mp-normal-text-3ds">' +
    wc_mercadopago_custom_checkout_params.threeDsText.text_loading +
    '</p>' +
    '   </div>' +
    ' <div></div>';
  divModalContainer.appendChild(divModalContent);
  document.body.appendChild(divModalContainer);

  document.querySelector('#mp-3ds-modal-close').addEventListener('click', function () {
    setDisplayOfErrorCheckout(wc_mercadopago_custom_checkout_params.threeDsText.message_close);
    removeModal3ds();
  });

  jQuery
    .post(woocommerce_params.wc_ajax_url.replace('%%endpoint%%', 'mp_get_3ds_from_session'))
    .done(function (response) {
      if (response.success) {
        var url_3ds = response.data.data['3ds_url'];
        var cred_3ds = response.data.data['3ds_creq'];
        threeDSHandler(url_3ds, cred_3ds);
      } else {
        console.error('Error POST:', response);
        window.dispatchEvent(
          new CustomEvent('completed_3ds', {
            detail: {
              error: true,
            },
          }),
        );
        removeModal3ds();
      }
    })
    .fail(function (xhr, textStatus, errorThrown) {
      console.error('Failed to make POST:', textStatus, errorThrown);
      window.dispatchEvent(
        new CustomEvent('completed_3ds', {
          detail: {
            error: true,
          },
        }),
      );
      removeModal3ds();
    });
}

function redirectAfter3dsChallenge() {
  jQuery
    .post(woocommerce_params.wc_ajax_url.replace('%%endpoint%%', 'mp_redirect_after_3ds_challenge'))
    .done(function (response) {
      if (response.data.redirect) {
        window.dispatchEvent(
          new CustomEvent('completed_3ds', {
            detail: {
              error: false,
            },
          }),
        );

        sendMetric('MP_THREE_DS_SUCCESS', '3DS challenge complete', threedsTarget);
        removeModal3ds();

        window.location.href = response.data.redirect;
      } else {
        window.dispatchEvent(
          new CustomEvent('completed_3ds', {
            detail: {
              error: response.data.data.error,
            },
          }),
        );

        setDisplayOfErrorCheckout(response.data.data.error);
        removeModal3ds();
      }
    });
}

function handle3dsPayOrderFormSubmission() {
  var serializedForm = jQuery('#order_review').serialize();

  jQuery
    .post('#', serializedForm)
    .done(function (response) {
      if (response.three_ds_flow) {
        load3DSFlow(response.last_four_digits);
        return;
      }

      if (response.redirect) {
        window.location.href = response.redirect;
      }

      window.location.reload();
    })
    .error(function () {
      window.location.reload();
    });
}

window.addEventListener('message', (e) => {
  if (e.data.status === 'COMPLETE') {
    sendMetric('MP_THREE_DS_SUCCESS', '3DS iframe Closed', threedsTarget);
    document.getElementById('mp-3ds-modal-content').innerHTML = '';
    addLoadSpinner3dsSubmit();
    redirectAfter3dsChallenge();
  }
});

function setDisplayOfErrorCheckout(errorMessage) {
  sendMetric('MP_THREE_DS_ERROR', errorMessage, threedsTarget);

  if (window.mpFormId !== 'blocks_checkout_form') {
    removeElementsByClass('woocommerce-NoticeGroup-checkout');
    var divWooNotice = document.createElement('div');
    divWooNotice.className = 'woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout';
    divWooNotice.innerHTML =
      '<ul class="woocommerce-error" role="alert">' + '<li>'.concat(errorMessage).concat('<li>') + '</ul>';
    mpCheckoutForm.prepend(divWooNotice);
    window.scrollTo(0, 0);
  }
}

function removeElementsByClass(className) {
  const elements = document.getElementsByClassName(className);
  while (elements.length > 0) {
    elements[0].parentNode.removeChild(elements[0]);
  }
}

function sendMetric(name, message, target) {
  const url = 'https://api.mercadopago.com/v1/plugins/melidata/errors';
  const payload = {
    name,
    message,
    target: target,
    plugin: {
      version: wc_mercadopago_custom_checkout_params.plugin_version,
    },
    platform: {
      name: 'woocommerce',
      uri: window.location.href,
      version: wc_mercadopago_custom_checkout_params.platform_version,
      location: `${wc_mercadopago_custom_checkout_params.location}_${wc_mercadopago_custom_checkout_params.theme}`,
    },
  };

  navigator.sendBeacon(url, JSON.stringify(payload));
}
