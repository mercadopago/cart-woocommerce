/* globals wc_mercadopago_custom_checkout_params, woocommerce_params */
class MPThreeDSHandler {
    constructor() {
        this.threedsTarget = 'mp_custom_checkout_security_fields_client';
    }

    set3dsStatusValidationListener() {
        window.addEventListener('message', (e) => {
            if (e.data.status === 'COMPLETE') {
                this.sendMetric('MP_THREE_DS_SUCCESS', '3DS iframe Closed', this.threedsTarget);
                document.getElementById('mp-3ds-modal-content').innerHTML = '';
                this.addLoadSpinner3dsSubmit();
                this.redirectAfter3dsChallenge();
            }
        });
    }

    load3DSFlow(lastFourDigits) {
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

        document.querySelector('#mp-3ds-modal-close').addEventListener('click', () => {
            this.setDisplayOfErrorCheckout(wc_mercadopago_custom_checkout_params.threeDsText.message_close);
            this.removeModal3ds();
        });

        jQuery
            .post(woocommerce_params.wc_ajax_url.replace('%%endpoint%%', 'mp_get_3ds_from_session'))
            .done((response) => {
                if (response.success) {
                    var url_3ds = response.data.data['3ds_url'];
                    var cred_3ds = response.data.data['3ds_creq'];
                    this.threeDSHandler(url_3ds, cred_3ds);
                } else {
                    console.error('Error POST:', response);
                    window.dispatchEvent(
                        new CustomEvent('completed_3ds', {
                            detail: {
                                error: true,
                            },
                        }),
                    );
                    this.removeModal3ds();
                }
            })
            .fail((xhr, textStatus, errorThrown) => {
                console.error('Failed to make POST:', textStatus, errorThrown);
                window.dispatchEvent(
                    new CustomEvent('completed_3ds', {
                        detail: {
                            error: true,
                        },
                    }),
                );
                this.removeModal3ds();
            });
    }

    threeDSHandler(url_3ds, cred_3ds) {
        try {
            if (url_3ds == null || cred_3ds == null) {
                this.removeModal3ds();
                this.sendMetric('MP_THREE_DS_ERROR', '3DS URL or CRED not set', this.threedsTarget);
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
            iframe.onload = () => this.removeLoadSpinner3ds();

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
            this.sendMetric('MP_THREE_DS_ERROR', '3DS Loading error: ' + error, this.threedsTarget);
            alert('Error doing Challenge, try again later.');
        }
    }

    redirectAfter3dsChallenge() {
        jQuery
            .post(woocommerce_params.wc_ajax_url.replace('%%endpoint%%', 'mp_redirect_after_3ds_challenge'))
            .done((response) => {
                if (response.data.redirect) {
                    window.dispatchEvent(
                        new CustomEvent('completed_3ds', {
                            detail: {
                                error: false,
                            },
                        }),
                    );

                    this.sendMetric('MP_THREE_DS_SUCCESS', '3DS challenge complete', this.threedsTarget);
                    this.removeModal3ds();

                    window.location.href = response.data.redirect;
                } else {
                    window.dispatchEvent(
                        new CustomEvent('completed_3ds', {
                            detail: {
                                error: response.data.data.error,
                            },
                        }),
                    );

                    this.setDisplayOfErrorCheckout(response.data.data.error);
                    this.removeModal3ds();
                }
            });
    }

    removeLoadSpinner3ds() {
        var element = document.getElementById('mp-loading-container-3ds');
        if (element) {
            element.remove();
        }
    }

    addLoadSpinner3dsSubmit() {
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

    removeModal3ds() {
        CheckoutPage.clearInputs();
        document.getElementById('mp-3ds-modal-container').remove();
    }

    setDisplayOfErrorCheckout(errorMessage) {
        this.sendMetric('MP_THREE_DS_ERROR', errorMessage, this.threedsTarget);

        if (window.mpFormId !== 'blocks_checkout_form') {
            this.addErrorAlert(errorMessage);
        }
    }

    addErrorAlert(message) {
        this.removeElementsByClass('woocommerce-NoticeGroup-checkout');
        jQuery(mpCheckoutForm).prepend(`
            <div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">
                <ul class="woocommerce-error" role="alert">
                    <li>${message}<li>
                </ul>
            </div>
        `);
        window.scrollTo(0, 0);
    }

    removeElementsByClass(className) {
        const elements = document.getElementsByClassName(className);
        while (elements.length > 0) {
            elements[0].parentNode.removeChild(elements[0]);
        }
    }

    sendMetric(action, label, target) {
        if (typeof window.mPmetrics !== 'undefined') {
            window.mPmetrics.push({
                action: action,
                label: label,
                target: target,
            });
        }
    }
} 