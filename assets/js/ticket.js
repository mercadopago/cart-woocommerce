/* jshint es3: false */
/* globals wc_mercadopago_ticket_params */
(function ($) {
    'use strict';

    $(function () {
        var mercado_pago_submit_ticket = false;
        var mercado_pago_docnumber = 'CPF';

        var seller = {
            site_id: wc_mercadopago_ticket_params.site_id
        };

        var coupon_of_discounts = {
            discount_action_url: wc_mercadopago_ticket_params.discount_action_url,
            payer_email: wc_mercadopago_ticket_params.payer_email,
            activated: wc_mercadopago_ticket_params.coupon_mode,
            status: false
        };

        if ($('form#order_review').length > 0) {
            if (coupon_of_discounts.activated === 'yes') {
                $('#applyCouponTicket').on('click', discountCampaignsHandler);
            }
        }

        // Load woocommerce checkout form
        $('body').on('updated_checkout', function () {
            if (seller.site_id === 'MLB') {
                validateDocumentInputs();
            }

            if (coupon_of_discounts.activated === 'yes') {
                $('#applyCouponTicket').on('click', discountCampaignsHandler);
            }
        });

        /**
         * Validate input depending on document type
         */
        function validateDocumentInputs() {
            var mp_box_lastname = document.getElementById('mp_box_lastname');
            var mp_box_firstname = document.getElementById('mp_box_firstname');
            var mp_firstname_label = document.getElementById('mp_firstname_label');
            var mp_socialname_label = document.getElementById('mp_socialname_label');
            var mp_cpf_label = document.getElementById('mp_cpf_label');
            var mp_cnpj_label = document.getElementById('mp_cnpj_label');
            var mp_doc_number = document.getElementById('mp_doc_number');
            var mp_doc_type = document.querySelectorAll('input[type=radio][name="mercadopago_ticket[docType]"]');

            mp_cnpj_label.style.display = 'none';
            mp_socialname_label.style.display = 'none';

            var choose_document = function () {
                if (this.value === 'CPF') {
                    mp_cpf_label.style.display = 'block';
                    mp_box_lastname.style.display = 'grid';
                    mp_firstname_label.style.display = 'block';
                    mp_cnpj_label.style.display = 'none';
                    mp_socialname_label.style.display = 'none';
                    mp_box_firstname.classList.remove('mp-col-md-8');
                    mp_box_firstname.classList.add('mp-col-md-4');
                    mp_doc_number.setAttribute('maxlength', '14');
                    mp_doc_number.setAttribute('onkeyup', 'mpMaskInput(this, mpCpf)');
                    mercado_pago_docnumber = 'CPF';
                } else {
                    mp_cpf_label.style.display = 'none';
                    mp_box_lastname.style.display = 'none';
                    mp_firstname_label.style.display = 'none';
                    mp_cnpj_label.style.display = 'block';
                    mp_socialname_label.style.display = 'block';
                    mp_box_firstname.classList.remove('mp-col-md-4');
                    mp_box_firstname.classList.add('mp-col-md-8');
                    mp_doc_number.setAttribute('maxlength', '18');
                    mp_doc_number.setAttribute('onkeyup', 'mpMaskInput(this, mpCnpj)');
                    mercado_pago_docnumber = 'CNPJ';
                }
            };

            for (var i = 0; i < mp_doc_type.length; i++) {
                mp_doc_type[i].addEventListener('change', choose_document);
            }
        }

        /**
         * Handler form submit
         * @return {bool}
         */
        function mercadoPagoFormHandlerTicket() {

            if (!document.getElementById('payment_method_woo-mercado-pago-ticket').checked) {
                return true;
            }

            if (seller.site_id === 'MLB') {
                if (validateInputs() && validateDocumentNumber()) {
                    mercado_pago_submit_ticket = true;
                } else {
                    removeBlockOverlay();
                }

                return mercado_pago_submit_ticket;
            }

            if (seller.site_id === 'MLU') {
                if (validateDocumentNumber()) {
                    mercado_pago_submit_ticket = true;
                } else {
                    removeBlockOverlay();
                }

                return mercado_pago_submit_ticket;
            }
        }

        // Process when submit the checkout form.
        $('form.checkout').on('checkout_place_order_woo-mercado-pago-ticket', function () {
            return mercadoPagoFormHandlerTicket();
        });

        // If payment fail, retry on next checkout page
        $('form#order_review').submit(function () {
            return mercadoPagoFormHandlerTicket();
        });

        /**
         * Get form
         */
        function getForm() {
            return document.querySelector('#mercadopago-form-ticket');
        }

        /**
         * Validate if all inputs are valid
         */
        function validateInputs() {
            var form = getForm();
            var form_inputs = form.querySelectorAll('[data-checkout]');
            var span = form.querySelectorAll('.mp-erro_febraban');

            //Show or hide error message and border
            for (var i = 0; i < form_inputs.length; i++) {
                var element = form_inputs[i];
                var input = form.querySelector(span[i].getAttribute('data-main'));

                if (element.parentNode.style.display !== 'none' && (element.value === -1 || element.value === '')) {
                    span[i].style.display = 'inline-block';
                    input.classList.add('mp-form-control-error');
                } else {
                    span[i].style.display = 'none';
                    input.classList.remove('mp-form-control-error');
                }
            }

            //Focus on the element with error
            for (var j = 0; j < form_inputs.length; j++) {
                var elementFocus = form_inputs[j];
                if (elementFocus.parentNode.style.display !== 'none' && (elementFocus.value === -1 || elementFocus.value === '')) {
                    elementFocus.focus();
                    return false;
                }
            }

            return true;
        }

        /**
         * Validate document number
         * @return {bool}
         */
        function validateDocumentNumber() {
            var docnumber_input = document.getElementById('mp_doc_number');
            var docnumber_error = document.getElementById('mp_error_docnumber');
            var docnumber_validate = false;

            if (seller.site_id === 'MLB') {
                docnumber_validate = validateDocTypeMLB(docnumber_input.value);
            }

            if (seller.site_id === 'MLU') {
                docnumber_validate = validateDocTypeMLU(docnumber_input.value);
            }

            if (!docnumber_validate) {
                docnumber_error.style.display = 'block';
                docnumber_input.classList.add('mp-form-control-error');
                docnumber_input.focus();
            } else {
                docnumber_error.style.display = 'none';
                docnumber_input.classList.remove('mp-form-control-error');
                docnumber_validate = true;
            }

            return docnumber_validate;
        }

        /**
        * Validate Document number for MLB
        * @param {string} docnumber
        * @return {bool}
        */
        function validateDocTypeMLB(docnumber) {
            if (mercado_pago_docnumber === 'CPF') {
                return validateCPF(docnumber);
            }
            return validateCNPJ(docnumber);
        }

        /**
         * Validate Document number for MLU
         * @param {string} docnumber
         * @return {bool}
         */
        function validateDocTypeMLU(docnumber) {
            if (docnumber !== '') {
                return validateCI(docnumber);
            }
            return false;
        }

        /**
     * Validate CPF
     * @param {string} strCPF
     * @return {bool}
     */
        function validateCPF(strCPF) {
            var Soma;
            var Resto;

            Soma = 0;
            strCPF = strCPF.replace(/[.-\s]/g, '');

            if (strCPF === '00000000000') {
                return false;
            }

            for (var i = 1; i <= 9; i++) {
                Soma = Soma + parseInt(strCPF.substring(i - 1, i), 10) * (11 - i);
            }

            Resto = (Soma * 10) % 11;
            if ((Resto === 10) || (Resto === 11)) {
                Resto = 0;
            }
            if (Resto !== parseInt(strCPF.substring(9, 10), 10)) {
                return false;
            }

            Soma = 0;
            for (var j = 1; j <= 10; j++) {
                Soma = Soma + parseInt(strCPF.substring(j - 1, j), 10) * (12 - j);
            }

            Resto = (Soma * 10) % 11;
            if ((Resto === 10) || (Resto === 11)) {
                Resto = 0;
            }
            if (Resto !== parseInt(strCPF.substring(10, 11), 10)) {
                return false;
            }

            return true;
        }

        /**
         * Validate CNPJ
         * @param {string} strCNPJ
         * @return {bool}
         */
        function validateCNPJ(strCNPJ)
        {
            strCNPJ = strCNPJ.replace(/[^\d]+/g, '');

            if (strCNPJ === '') {
                return false;
            }

            if (strCNPJ.length !== 14) {
                return false;
            }

            if (strCNPJ === '00000000000000' ||
              strCNPJ === '11111111111111' ||
              strCNPJ === '22222222222222' ||
              strCNPJ === '33333333333333' ||
              strCNPJ === '44444444444444' ||
              strCNPJ === '55555555555555' ||
              strCNPJ === '66666666666666' ||
              strCNPJ === '77777777777777' ||
              strCNPJ === '88888888888888' ||
              strCNPJ === '99999999999999') {
              return false;
            }

            var tamanho = strCNPJ.length - 2;
            var numeros = strCNPJ.substring(0, tamanho);
            var digitos = strCNPJ.substring(tamanho);
            var soma = 0;
            var pos = tamanho - 7;
            for (var i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2) {
                    pos = 9;
                }
            }

            var resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;

            if (resultado.toString() !== digitos[0]) {
                return false;
            }

            tamanho = tamanho + 1;
            numeros = strCNPJ.substring(0, tamanho);
            soma = 0;
            pos = tamanho - 7;
            for (i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2) {
                    pos = 9;
                }
            }

            resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;

            if (resultado.toString() !== digitos[1]) {
                return false;
            }

            return true;
        }

        /**
        * Validate CI MLU
        * @param {string} docNumber
        * @return {bool}
        */
        function validateCI(docNumber) {
            var x = 0;
            var y = 0;
            var docCI = 0;
            var dig = docNumber[docNumber.length - 1];

            if (docNumber.length <= 6) {
                for (y = docNumber.length; y < 7; y++) {
                    docNumber = '0' + docNumber;
                }
            }
            for (y = 0; y < 7; y++) {
                x += (parseInt('2987634'[y], 10) * parseInt(docNumber[y], 10)) % 10;
            }
            if (x % 10 === 0) {
                docCI = 0;
            } else {
                docCI = 10 - x % 10;
            }
            return (dig === docCI.toString());
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
        *  Discount Campaigns Handler
        */
        function discountCampaignsHandler() {
            document.querySelector('#mpCouponApplyedTicket').style.display = 'none';

            if (document.querySelector('#couponCodeTicket').value === '') {
                coupon_of_discounts.status = false;
                document.querySelector('#mpCouponErrorTicket').style.display = 'block';
                document.querySelector('#mpCouponErrorTicket').innerHTML = wc_mercadopago_ticket_params.coupon_empty;
                document.querySelector('#couponCodeTicket').style.background = null;
                document.querySelector('#applyCouponTicket').value = wc_mercadopago_ticket_params.apply;
                document.querySelector('#discountTicket').value = 0;

            } else if (coupon_of_discounts.status) {
                coupon_of_discounts.status = false;
                document.querySelector('#mpCouponErrorTicket').style.display = 'none';
                document.querySelector('#applyCouponTicket').style.background = null;
                document.querySelector('#applyCouponTicket').value = wc_mercadopago_ticket_params.apply;
                document.querySelector('#couponCodeTicket').value = '';
                document.querySelector('#couponCodeTicket').style.background = null;
                document.querySelector('#discountTicket').value = 0;

            } else {
                document.querySelector('#mpCouponErrorTicket').style.display = 'none';
                document.querySelector('#couponCodeTicket').style.background = 'url(' + wc_mercadopago_ticket_params.loading + ') 98% 50% no-repeat #fff';
                document.querySelector('#couponCodeTicket').style.border = '1px solid #cecece';
                document.querySelector('#applyCouponTicket').disabled = true;
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
            url += sp + 'site_id=' + wc_mercadopago_ticket_params.site_id;
            url += '&coupon_id=' + document.querySelector('#couponCodeTicket').value;
            url += '&amount=' + document.querySelector('#amountTicket').value;
            url += '&payer=' + coupon_of_discounts.payer_email;

            $.ajax({
                url: url,
                method: 'GET',
                timeout: 5000,
                error: function () {
                    coupon_of_discounts.status = false;
                    document.querySelector('#mpCouponApplyedTicket').style.display = 'none';
                    document.querySelector('#mpCouponErrorTicket').style.display = 'none';
                    document.querySelector('#applyCouponTicket').style.background = null;
                    document.querySelector('#applyCouponTicket').value = wc_mercadopago_ticket_params.apply;
                    document.querySelector('#couponCodeTicket').value = '';
                    document.querySelector('#couponCodeTicket').style.background = null;
                    document.querySelector('#discountTicket').value = 0;
                },
                success: function (response) {
                    if (response.status === 200) {
                        coupon_of_discounts.status = true;
                        document.querySelector('#mpCouponApplyedTicket').style.display = 'block';
                        document.querySelector('#discountTicket').value = response.response.coupon_amount;
                        document.querySelector('#mpCouponApplyedTicket').innerHTML =
                            wc_mercadopago_ticket_params.discount_info1 + ' <strong>' +
                            currencyIdToCurrency(response.response.currency_id) + ' ' +
                            Math.round(response.response.coupon_amount * 100) / 100 +
                            '</strong> ' + wc_mercadopago_ticket_params.discount_info2 + ' ' +
                            response.response.name + '.<br>' + wc_mercadopago_ticket_params.discount_info3 + ' <strong>' +
                            currencyIdToCurrency(response.response.currency_id) + ' ' +
                            Math.round(getAmountWithoutDiscount() * 100) / 100 +
                            '</strong><br>' + wc_mercadopago_ticket_params.discount_info4 + ' <strong>' +
                            currencyIdToCurrency(response.response.currency_id) + ' ' +
                            Math.round(getAmount() * 100) / 100 + '*</strong><br>' +
                            '<i>' + wc_mercadopago_ticket_params.discount_info5 + '</i><br>' +
                            '<a href="https://api.mercadolibre.com/campaigns/' +
                            response.response.id +
                            '/terms_and_conditions?format_type=html" target="_blank">' +
                            wc_mercadopago_ticket_params.discount_info6 + '</a>';
                        document.querySelector('#mpCouponErrorTicket').style.display = 'none';
                        document.querySelector('#couponCodeTicket').style.background = null;
                        document.querySelector('#couponCodeTicket').style.background = 'url(' + wc_mercadopago_ticket_params.check + ') 94% 50% no-repeat #fff';
                        document.querySelector('#couponCodeTicket').style.border = '1px solid #cecece';
                        document.querySelector('#applyCouponTicket').value = wc_mercadopago_ticket_params.remove;
                        document.querySelector('#campaign_idTicket').value = response.response.id;
                        document.querySelector('#campaignTicket').value = response.response.name;
                    } else {
                        coupon_of_discounts.status = false;
                        document.querySelector('#mpCouponApplyedTicket').style.display = 'none';
                        document.querySelector('#mpCouponErrorTicket').style.display = 'block';
                        document.querySelector('#mpCouponErrorTicket').innerHTML = response.response.message;
                        document.querySelector('#couponCodeTicket').style.background = null;
                        document.querySelector('#couponCodeTicket').style.background = 'url(' + wc_mercadopago_ticket_params.error + ') 94% 50% no-repeat #fff';
                        document.querySelector('#applyCouponTicket').value = wc_mercadopago_ticket_params.apply;
                        document.querySelector('#discountTicket').value = 0;
                    }
                    document.querySelector('#applyCouponTicket').disabled = false;
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
            return document.querySelector('#amountTicket').value;
        }

        /**
        * Get Amount end calculate discount for hide inputs
        */
        function getAmount() {
            return document.getElementById('amountTicket').value - document.getElementById('discountTicket').value;
        }

    });

}(jQuery));
