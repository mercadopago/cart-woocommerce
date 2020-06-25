/* globals wc_mercadopago_params, Mercadopago */
(function ($) {
  'use strict';

  $(function () {
    var mercado_pago_submit = false;

    var seller = {
      site_id: wc_mercadopago_params.site_id,
      public_key: wc_mercadopago_params.public_key
    };

    var sdkTagScript = document.createElement('script');
    sdkTagScript.src = 'https://secure.mlstatic.com/sdk/javascript/v1/mercadopago.js';
    sdkTagScript.onload = function () {
      Mercadopago.setPublishableKey(seller.public_key);
    };

    var coupon_of_discounts = {
      discount_action_url: wc_mercadopago_params.discount_action_url,
      payer_email: wc_mercadopago_params.payer_email,
      activated: wc_mercadopago_params.coupon_mode,
      status: false
    };

    var objPaymentMethod = {};
    var additionalInfoNeeded = {};

    if ($('form#order_review').length > 0) {
      if (coupon_of_discounts.activated === 'yes') {
        $('#applyCoupon').on('click', discountCampaignsHandler);
      }
      showPaymentsLink();
    }

    // Load woocommerce checkout form
    $('body').on('updated_checkout', function () {

      if (coupon_of_discounts.activated === 'yes') {
        $('#applyCoupon').on('click', discountCampaignsHandler);
      }

      clearCard();
      showPaymentsLink();
    });

    $('body').on('keyup', '#mp-card-number', guessingPaymentMethod);
    $('body').on('change', '#mp-card-number', guessingPaymentMethod);

    /**
     * Clear Card input and all dependents inputs
     */
    function clearCard() {
      if (document.getElementById('mp-card-number').value !== 0) {
        document.getElementById('mp-card-number').value = '';
        resetBackgroundCard();
        clearInstallments();
        clearTax();
        clearIssuer();
        clearDoc();
      }
    }

    /**
      * Show Payments accepted when link was clicked
      */
    function showPaymentsLink() {
      var frame_payments = document.querySelector('#mp-frame-payments');
      $('#button-show-payments').on('click', function () {
          frame_payments.style.display = frame_payments.style.display === 'inline-block' ? 'none' : 'inline-block';
      });
    }

    /**
     * Get Bin from Card Number
     */
    function getBin() {
      var cardnumber = document.getElementById('mp-card-number');
      return cardnumber.value.replace(/[ .-]/g, '').slice(0, 6);
    }

    /**
     * Execute before event focusout on input Card Number
     *
     * @param {object} event
     */
    function guessingPaymentMethod(event) {
      hideErrors();
      clearHolderName();
      clearExpirationDate();
      clearSecurityCode();
      clearIssuer();
      clearInstallments();
      clearTax();
      clearDoc();

      var bin = getBin();

      if (bin.length < 6) {
        resetBackgroundCard();
        return;
      }

      if (event.type === 'keyup') {
        if (bin.length >= 6) {
          Mercadopago.getPaymentMethod({
            'bin': bin
          }, paymentMethodHandler);
        }
      } else {
        setTimeout(function () {
          if (bin.length >= 6) {
            Mercadopago.getPaymentMethod({
              'bin': bin
            }, paymentMethodHandler);
          }
        }, 100);
      }
    }

    /**
    * Get Amount end calculate discount for hide inputs
    */
    function getAmount() {
      return (document.getElementById('mp-amount').value - document.getElementById('mp-discount').value) * document.getElementById('currency_ratio').value;
    }

    /**
     * Handle payment Method response
     *
     * @param {number} status
     * @param {object} response
     */
    function paymentMethodHandler(status, response) {
      if (status === 200) {
        objPaymentMethod = response[0];
        setPaymentMethodId(objPaymentMethod.id);
        setImageCard(objPaymentMethod.secure_thumbnail);
        loadAdditionalInfo(objPaymentMethod.additional_info_needed);
        additionalInfoHandler();
        document.getElementById('mp-card-number').classList.remove('mp-form-control-error');
        document.getElementById('mp-error-E301').style.display = 'none';
      } else {
        resetBackgroundCard();
        document.getElementById('mp-card-number').classList.add('mp-form-control-error');
        document.getElementById('mp-error-E301').style.display = 'inline-block';
      }
    }

    /**
     *
     * Load Additional Info to use for build payment form
     *
     * @param {array} additional_info_needed
     */
    function loadAdditionalInfo(additional_info_needed) {
      additionalInfoNeeded = {
        'issuer': false,
        'cardholder_name': false,
        'cardholder_identification_type': false,
        'cardholder_identification_number': false
      };

      for (var i = 0; i < additional_info_needed.length; i++) {
        if (additional_info_needed[i] === 'issuer_id') {
          additionalInfoNeeded.issuer = true;
        }
        if (additional_info_needed[i] === 'cardholder_name') {
          additionalInfoNeeded.cardholder_name = true;
        }
        if (additional_info_needed[i] === 'cardholder_identification_type') {
          additionalInfoNeeded.cardholder_identification_type = true;
        }
        if (additional_info_needed[i] === 'cardholder_identification_number') {
          additionalInfoNeeded.cardholder_identification_number = true;
        }
      }
    }

    /**
     * Check what information is necessary to pay and show inputs
     */
    function additionalInfoHandler() {
        if (additionalInfoNeeded.cardholder_name) {
          document.getElementById('mp-card-holder-div').style.display = 'block';
        } else {
          document.getElementById('mp-card-holder-div').style.display = 'none';
        }

        if (additionalInfoNeeded.issuer) {
          document.getElementById('mp-issuer-div').style.display = 'block';
          document.getElementById('installments-div').classList.remove('mp-col-md-12');
          document.getElementById('installments-div').classList.add('mp-col-md-8');
          Mercadopago.getIssuers(objPaymentMethod.id, issuersHandler);
        } else {
          clearIssuer();
          setInstallments();
        }

        if (additionalInfoNeeded.cardholder_identification_type) {
          document.getElementById('mp-doc-div').style.display = 'inline-block';
          document.getElementById('mp-doc-type-div').style.display = 'block';
          Mercadopago.getIdentificationTypes();
        } else {
          document.getElementById('mp-doc-type-div').style.display = 'none';
        }

        if (additionalInfoNeeded.cardholder_identification_number) {
          document.getElementById('mp-doc-div').style.display = 'inline-block';
          document.getElementById('mp-doc-number-div').style.display = 'block';
        } else {
          document.getElementById('mp-doc-number-div').style.display = 'none';
        }

        if (!additionalInfoNeeded.cardholder_identification_type && !additionalInfoNeeded.cardholder_identification_number) {
          document.getElementById('mp-doc-div').style.display = 'none';
        }
    }

    /**
    * Remove background image from imput
    */
    function resetBackgroundCard() {
      document.getElementById('mp-card-number').style.background = 'no-repeat #fff';
    }

    /**
     * Set value on paymentMethodId element
     *
     * @param {string} paymentMethodId
     */
    function setPaymentMethodId(paymentMethodId) {
      document.getElementById('paymentMethodId').value = paymentMethodId;
    }

    /**
     * Set Imagem card on element
     *
     * @param {string} secureThumbnail
     */
    function setImageCard(secureThumbnail) {
      document.getElementById('mp-card-number').style.background = 'url(' + secureThumbnail + ') 98% 50% no-repeat #fff';
    }

    /**
     * Resolution 51/2017
     *
     * @param {*} payerCosts
     * @returns {string}
     */
    function argentinaResolution(payerCosts) {
      var dataInput = '';
      if (seller.site_id === 'MLA') {
        for (var l = 0; l < payerCosts.length; l++) {
          if (payerCosts[l].indexOf('CFT_') !== -1) {
            dataInput = 'data-tax="' + payerCosts[l] + '"';
          }
        }
        return dataInput;
      }
      return dataInput;
    }

    /**
     * Get instalments
     *
     * @param {number} status
     * @param {object} response
     */
    function installmentHandler(status, response) {
      if (status === 200) {
        var selectorInstallments = document.getElementById('mp-installments');
        var html_option = '<option value="-1">' + wc_mercadopago_params.choose + '...</option>';
        var payerCosts = [];
        for (var i = 0; i < response.length; i++) {
          if (response[i].processing_mode === 'aggregator') {
            payerCosts = response[i].payer_costs;
          }
        }

        for (var j = 0; j < payerCosts.length; j++) {
          html_option += '<option value="' + payerCosts[j].installments + '" ' + argentinaResolution(payerCosts[j].labels) + '>' +
            (payerCosts[j].recommended_message || payerCosts[j].installments) +
            '</option>';
        }

        selectorInstallments.innerHTML = html_option;
        if (seller.site_id === 'MLA') {
          clearTax();
          $('body').on('change', '#mp-installments', showTaxes);
        }
      } else {
        clearInstallments();
        clearTax();
      }
    }

    /**
    * Show taxes resolution 51/2017 for MLA
    */
    function showTaxes() {
      var selectorInstallments = document.querySelector('#mp-installments');
      var tax = selectorInstallments.options[selectorInstallments.selectedIndex].getAttribute('data-tax');
      var cft = '';
      var tea = '';
      if (tax != null) {
        var tax_split = tax.split('|');
        cft = tax_split[0].replace('_', ' ');
        tea = tax_split[1].replace('_', ' ');
        if (cft === 'CFT 0,00%' && tea === 'TEA 0,00%') {
          cft = '';
          tea = '';
        }
      }
      document.querySelector('#mp-tax-cft-text').innerHTML = cft;
      document.querySelector('#mp-tax-tea-text').innerHTML = tea;
    }

    /**
    * Clear input select
    */
    function clearInstallments() {
      document.getElementById('mp-installments').innerHTML = '';
    }

    /**
    * Clear Tax
    */
    function clearTax() {
      document.querySelector('#mp-tax-cft-text').innerHTML = '';
      document.querySelector('#mp-tax-tea-text').innerHTML = '';
    }

    /**
     * Clear input select and change to default layout
     */
    function clearIssuer() {
      document.getElementById('mp-issuer-div').style.display = 'none';
      document.getElementById('installments-div').classList.remove('mp-col-md-8');
      document.getElementById('installments-div').classList.add('mp-dis-md-12');
      document.getElementById('mp-issuer').innerHTML = '';
    }

    function clearDoc() {
      document.getElementById('mp-doc-div').style.display = 'none';
      document.getElementById('mp-doc-type-div').style.display = 'none';
      document.getElementById('docType').innerHTML = '';
      document.getElementById('docNumber').value = '';
    }
    /**
     * Clear input
     */
    function clearHolderName() {
      document.getElementById('mp-card-holder-name').value = '';
    }
    function clearExpirationDate() {
      document.getElementById('mp-card-expiration-date').value = '';
    }
    function clearSecurityCode() {
      document.getElementById('mp-security-code').value = '';
    }
    /**
     * Call insttalments with issuer ou not, depends on additionalInfoHandler()
     */
    function setInstallments() {
      var params_installments = {};
      var amount = getAmount();
      var issuer = false;
      for (var i = 0; i < objPaymentMethod.additional_info_needed.length; i++) {
        if (objPaymentMethod.additional_info_needed[i] === 'issuer_id') {
          issuer = true;
        }
      }
      if (issuer) {
        var issuerId = document.getElementById('mp-issuer').value;
        params_installments = {
          'bin': getBin(),
          'amount': amount,
          'issuer_id': issuerId
        };

        if (issuerId === '-1') {
          return;
        }
      } else {
        params_installments = {
          'bin': getBin(),
          'amount': amount
        };
      }
      Mercadopago.getInstallments(params_installments, installmentHandler);
    }

    /**
     * Handle issuers response and build select
     *
     * @param {status} status
     * @param {object} response
     */
    function issuersHandler(status, response) {
      if (status === 200) {
        // If the API does not return any bank.
        var issuersSelector = document.getElementById('mp-issuer');
        var fragment = document.createDocumentFragment();

        issuersSelector.options.length = 0;
        var option = new Option(wc_mercadopago_params.choose + '...', '-1');
        fragment.appendChild(option);

        for (var i = 0; i < response.length; i++) {
          var name = response[i].name === 'default' ? 'Otro' : response[i].name;
          fragment.appendChild(new Option(name, response[i].id));
        }

        issuersSelector.appendChild(fragment);
        issuersSelector.removeAttribute('disabled');
        $('body').on('change', '#mp-issuer', setInstallments);
      } else {
        clearIssuer();
      }
    }

    /**
     * Get form
     */
    function getForm() {
      return document.querySelector('#mercadopago-form');
    }

    /**
     * Validate Additional Inputs
     *
     * @return {bool}
     */
    function validateAdditionalInputs() {
      var emptyInputs = false;

      if (additionalInfoNeeded.issuer) {
        var inputMpIssuer = document.getElementById('mp-issuer');
        if (inputMpIssuer.value === '-1' || inputMpIssuer.value === '') {
          inputMpIssuer.classList.add('mp-form-control-error');
          emptyInputs = true;
        }
      }
      if (additionalInfoNeeded.cardholder_name) {
        var inputCardholderName = document.getElementById('mp-card-holder-name');
        if (inputCardholderName.value === '-1' || inputCardholderName.value === '') {
          inputCardholderName.classList.add('mp-form-control-error');
          emptyInputs = true;
        }
      }
      if (additionalInfoNeeded.cardholder_identification_type) {
        var inputDocType = document.getElementById('docType');
        if (inputDocType.value === '-1' || inputDocType.value === '') {
          inputDocType.classList.add('mp-form-control-error');
          emptyInputs = true;
        }
      }
      if (additionalInfoNeeded.cardholder_identification_number) {
        var docNumber = document.getElementById('docNumber');
        if (docNumber.value === '-1' || docNumber.value === '') {
          docNumber.classList.add('mp-form-control-error');
          document.getElementById('mp-error-324').style.display = 'inline-block';
          emptyInputs = true;
        }
      }

      if (emptyInputs) {
        return emptyInputs;
      } else {
        return emptyInputs;
      }
    }

    /**
    * Validate Inputs to Create Token
    *
    * @return {bool}
    */
    function validateInputsCreateToken() {
      hideErrors();
      var fixedInputs = validateFixedInputs();
      var additionalInputs = validateAdditionalInputs();

      if (fixedInputs || additionalInputs) {
        removeBlockOverlay();
        focusInputError();
        return false;
      }

      return true;
    }

    /**
    * Focus input with error
    *
    * @return {bool}
    */
    function focusInputError() {
      if (document.querySelectorAll('.mp-form-control-error') !== undefined) {
        var form_inputs = document.querySelectorAll('.mp-form-control-error');
        form_inputs[0].focus();
      }
    }

    /**
    * Validate fixed Inputs is empty
    *
    * @return {bool}
    */
    function validateFixedInputs() {
      var emptyInputs = false;
      var form = getForm();
      var form_inputs = form.querySelectorAll('[data-checkout]');
      var fixed_inputs = [
        'installments',
        'securityCode',
        'cardExpirationDate',
        'cardNumber'
      ];

      for (var x = 0; x < form_inputs.length; x++) {
        var element = form_inputs[x];
        // Check is a input to create token.
        if (fixed_inputs.indexOf(element.getAttribute('data-checkout')) > -1) {
          if (element.value === '-1' || element.value === '') {
            var span = form.querySelectorAll('span[data-main="#' + element.id + '"]');
            if (span.length > 0) {
              span[0].style.display = 'inline-block';
            }
            element.classList.add('mp-form-control-error');
            emptyInputs = true;
          }
        }
      }

      if (emptyInputs) {
        return emptyInputs;
      } else {
        return emptyInputs;
      }
    }

    /**
     * Hide errors when return of cardToken error
     */
    function hideErrors() {
      for (var x = 0; x < document.querySelectorAll('[data-checkout]').length; x++) {
        var field = document.querySelectorAll('[data-checkout]')[x];
        field.classList.remove('mp-error-input');
        field.classList.remove('mp-form-control-error');
      }

      for (var y = 0; y < document.querySelectorAll('.mp-error').length; y++) {
        var span = document.querySelectorAll('.mp-error')[y];
        span.style.display = 'none';
      }
    }

    /**
     *  Create Token call Mercadopago.createToken
     *
     *  @return {bool}
     */
    function createToken() {
      hideErrors();

      // Show loading.
      document.querySelector('#mp-box-loading').style.background = 'url(' + wc_mercadopago_params.loading + ') 0 50% no-repeat #fff';

      // Form.
      var form = getForm();

      Mercadopago.createToken(form, sdkResponseHandler);

      return false;
    }

    /**
     * Remove Block Overlay from Order Review page
     */
    function removeBlockOverlay() {
      if ($('form#order_review').length > 0) {
        $('.blockOverlay').css('display', 'none');
      }
    }

    /**
     * Handler Response of Mercadopago.createToken
     *
     * @param {number} status
     * @param {object} response
     */
    function sdkResponseHandler(status, response) {
      document.querySelector('#mp-box-loading').style.background = '';

      if (status !== 200 && status !== 201) {
        showErrors(response);
        removeBlockOverlay();
        focusInputError();
      } else {
        var token = document.querySelector('#token');
        token.value = response.id;
        mercado_pago_submit = true;
        $('form.checkout, form#order_review').submit();
      }
    }

    /**
     *
     * @param { obje } response
    */
    function showErrors(response) {
      var form = getForm();
      for (var x = 0; x < response.cause.length; x++) {
        var error = response.cause[x];
        var span = undefined;

        if (error.code === '208' || error.code === '209' || error.code === '325' || error.code === '326') {
          span = form.querySelector('#mp-error-208');
        } else {
          span = form.querySelector('#mp-error-' + error.code);
        }

        if (span !== undefined) {
          var input = form.querySelector(span.getAttribute('data-main'));
          span.style.display = 'inline-block';
          input.classList.add('mp-form-control-error');
        }
      }
      return;
    }

    /**
     *  Discount Campaigns Handler
     */
    function discountCampaignsHandler() {
      clearCard();
      document.querySelector('#mpCouponApplyed').style.display = 'none';

      if (document.querySelector('#couponCode').value === '') {
        coupon_of_discounts.status = false;
        document.querySelector('#mpCouponError').style.display = 'block';
        document.querySelector('#mpCouponError').innerHTML = wc_mercadopago_params.coupon_empty;
        document.querySelector('#couponCode').style.background = null;
        document.querySelector('#applyCoupon').value = wc_mercadopago_params.apply;
        document.querySelector('#mp-discount').value = 0;
      } else if (coupon_of_discounts.status) {
        coupon_of_discounts.status = false;
        document.querySelector('#mpCouponError').style.display = 'none';
        document.querySelector('#applyCoupon').style.background = null;
        document.querySelector('#applyCoupon').value = wc_mercadopago_params.apply;
        document.querySelector('#couponCode').value = '';
        document.querySelector('#couponCode').style.background = null;
        document.querySelector('#mp-discount').value = 0;
      } else {
        document.querySelector('#mpCouponError').style.display = 'none';
        document.querySelector('#couponCode').style.background = 'url(' + wc_mercadopago_params.loading + ') 98% 50% no-repeat #fff';
        document.querySelector('#couponCode').style.border = '1px solid #cecece';
        document.querySelector('#applyCoupon').disabled = true;
        getDiscountCampaigns();
      }
    }

    /**
     * Get Discount Campaigns
     */
    function getDiscountCampaigns() {
      var url = coupon_of_discounts.discount_action_url;
      var sp = '?';
      if (url.indexOf('?') >= 0) {
        sp = '&';
      }
      url += sp + 'site_id=' + wc_mercadopago_params.site_id;
      url += '&coupon_id=' + document.querySelector('#couponCode').value;
      url += '&amount=' + document.querySelector('#mp-amount').value;
      url += '&payer=' + coupon_of_discounts.payer_email;

      $.ajax({
        url: url,
        method: 'GET',
        timeout: 5000,
        error: function () {
          coupon_of_discounts.status = false;
          document.querySelector('#mpCouponApplyed').style.display = 'none';
          document.querySelector('#mpCouponError').style.display = 'none';
          document.querySelector('#applyCoupon').style.background = null;
          document.querySelector('#applyCoupon').value = wc_mercadopago_params.apply;
          document.querySelector('#couponCode').value = '';
          document.querySelector('#couponCode').style.background = null;
          document.querySelector('#mp-discount').value = 0;
        },
        success: function (response) {
          if (response.status === 200) {
            coupon_of_discounts.status = true;
            document.querySelector('#mpCouponApplyed').style.display = 'block';
            document.querySelector('#mp-discount').value = response.response.coupon_amount;
            document.querySelector('#mpCouponApplyed').innerHTML =
              wc_mercadopago_params.discount_info1 + ' <strong>' +
              currencyIdToCurrency(response.response.currency_id) + ' ' +
              Math.round(response.response.coupon_amount * 100) / 100 +
              '</strong> ' + wc_mercadopago_params.discount_info2 + ' ' +
              response.response.name + '.<br>' + wc_mercadopago_params.discount_info3 + ' <strong>' +
              currencyIdToCurrency(response.response.currency_id) + ' ' +
              Math.round(getAmountWithoutDiscount() * 100) / 100 +
              '</strong><br>' + wc_mercadopago_params.discount_info4 + ' <strong>' +
              currencyIdToCurrency(response.response.currency_id) + ' ' +
              Math.round(getAmount() * 100) / 100 + '*</strong><br>' +
              '<i>' + wc_mercadopago_params.discount_info5 + '</i><br>' +
              '<a href="https://api.mercadolibre.com/campaigns/' +
              response.response.id +
              '/terms_and_conditions?format_type=html" target="_blank">' +
              wc_mercadopago_params.discount_info6 + '</a>';
            document.querySelector('#mpCouponError').style.display = 'none';
            document.querySelector('#couponCode').style.background = null;
            document.querySelector('#couponCode').style.background = 'url(' + wc_mercadopago_params.check + ') 94% 50% no-repeat #fff';
            document.querySelector('#couponCode').style.border = '1px solid #cecece';
            document.querySelector('#applyCoupon').value = wc_mercadopago_params.remove;
            document.querySelector('#campaign_id').value = response.response.id;
            document.querySelector('#campaign').value = response.response.name;
          } else {
            coupon_of_discounts.status = false;
            document.querySelector('#mpCouponApplyed').style.display = 'none';
            document.querySelector('#mpCouponError').style.display = 'block';
            document.querySelector('#mpCouponError').innerHTML = response.response.message;
            document.querySelector('#couponCode').style.background = null;
            document.querySelector('#couponCode').style.background = 'url(' + wc_mercadopago_params.error + ') 94% 50% no-repeat #fff';
            document.querySelector('#applyCoupon').value = wc_mercadopago_params.apply;
            document.querySelector('#mp-discount').value = 0;
          }
          document.querySelector('#applyCoupon').disabled = false;
        }
      });
    }

    /**
     * CurrencyId to Currency
     *
     * @param {string} currency_id
     */
    function currencyIdToCurrency(currency_id) {
      if (currency_id === 'ARS') {
          return '$';
      } else if (currency_id === 'BRL') {
          return 'R$';
      } else if (currency_id === 'COP') {
          return '$';
      } else if (currency_id === 'CLP') {
          return '$';
      } else if (currency_id === 'MXN') {
          return '$';
      } else if (currency_id === 'VEF') {
          return 'Bs';
      } else if (currency_id === 'PEN') {
          return 'S/';
      } else if (currency_id === 'UYU') {
          return '$U';
      } else {
          return '$';
      }
    }

    /**
     * Get Amount Without Discount
     *
     * @return {string}
     */
    function getAmountWithoutDiscount() {
      return document.querySelector('#mp-amount').value;
    }

    /**
     * Handler submit
     *
     * @return {bool}
     */
    function mercadoPagoFormHandler() {
      if (mercado_pago_submit) {
        mercado_pago_submit = false;
        return true;
      }

      if (!document.getElementById('payment_method_woo-mercado-pago-custom').checked) {
        return true;
      }

      if (validateInputsCreateToken()) {
        return createToken();
      }

      return false;
    }

    // Process when submit the checkout form.
    $('form.checkout').on('checkout_place_order_woo-mercado-pago-custom', function () {
      return mercadoPagoFormHandler();
    });

    // If payment fail, retry on next checkout page
    $('form#order_review').submit(function () {
      return mercadoPagoFormHandler();
    });

    window.onload = function() {
      var body = document.querySelector('body');
      body.append(sdkTagScript);
    };
  });
}(jQuery));
