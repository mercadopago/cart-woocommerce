/* globals wc_mercadopago_yape_checkout_params, MercadoPago */

async function mercadoPagoFormHandlerYape() {
  if (!document.getElementById('payment_method_woo-mercado-pago-yape').checked) {
    return true;
  }

  if (!isFormValid()) {
    return false;
  }

  const yapeOptions = {
    otp: getCodeValue(),
    phoneNumber: document.getElementById("checkout__yapePhoneNumber").value.replaceAll(' ', '')
  };

  if (!window.mpSdkInstance) {
    const mp = new MercadoPago(wc_mercadopago_yape_checkout_params.public_key);

    window.mpSdkInstance = mp;
  }

  const yape = window.mpSdkInstance.yape(yapeOptions);

  try {
    const yapeToken = await yape.create();
    document.getElementById("yapeToken").value = yapeToken.id;
    return true;
  } catch (error) {
    console.error(error); // TODO: Show error message to user
  }

  return false;
}

const getCodeValue = function() {
  const inputs = document.querySelectorAll('.mp-yape-code-input');
  let code = '';
  inputs.forEach(input => {
    code += input.value;
  });
  return code;
};

const getForm = () => {
  const checkoutForm = document.querySelector('form.checkout');
  const orderReviewForm = document.querySelector('form#order_review');

  return checkoutForm || orderReviewForm;
};

const isFormValid = () => {
  const form = getForm();

  if (!form) {
    return false;
  }

  document.getElementsByTagName('input-field')[0].validate();
  document.getElementsByTagName('input-code')[0].validate();

  return !Array
    .from(form.querySelectorAll('input-helper'))
    .some(item => item.querySelector('div').style.display !== 'none')
};

// Process when submit the checkout form
jQuery('form.checkout').on('checkout_place_order_woo-mercado-pago-yape', (_event, wc_checkout_form) => {
  const $token = jQuery("#yapeToken");

  if ($token.data('fresh')) {
    $token.data('fresh', null);
    return true;
  }

  mercadoPagoFormHandlerYape().then((success) => {
    if (success) {
      $token.data('fresh', 'true');
      wc_checkout_form.$checkout_form.trigger('submit');
    }
  });

  return false;
});

// If payment fail, retry on next checkout page
jQuery('form#order_review').submit(async function (event) {
  event.preventDefault();
  await mercadoPagoFormHandlerYape();
  this.submit();
});
