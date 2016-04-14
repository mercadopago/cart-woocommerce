(function( $ ) {
	'use strict';

	$( function() {

		var formSubmit = false;

		$( 'form.checkout' ).on( 'click', '#place_order', function() {
			return openCheckout();
		} );

		$( 'form.checkout' ).on( 'checkout_place_order_mercadopago-credit-card', {
			preserveMercadoPagoCheckoutSubmitValue: true
		}, isCheckoutInvalid );

		$( 'form#order_review' ).submit( function() {
			return openCheckout();
		} );

		/**
		 * Check if checkout is valid.
		 *
		 * @param {Object} evt
		 *
		 * @return {Bool}
		 */
		function isCheckoutInvalid( evt ) {
			var requiredInputs = null;

			// If this submit is a result of the request callback firing,
			// let submit proceed by returning true immediately.
			if ( formSubmit ) {
				if ( 'undefined' !== typeof evt && 'undefined' !== typeof evt.data ) {
					if ( 'undefined' !== typeof evt.data.preserveMercadoPagoCheckoutSubmitValue && ! evt.data.preserveMercadoPagoCheckoutSubmitValue ) {
						formSubmit = false;
					}
				}
				return true;
			}

			if ( ! $( '#payment_method_mercadopago-credit-card' ).is( ':checked' ) ) {
				return true;
			}

			if ( 0 < $( 'input[name=mercadopago_checkout_token]' ).length ) {
				return true;
			}

			if ( 1 === $( 'input#terms' ).size() && 0 === $( 'input#terms:checked' ).size() ) {
				return true;
			}

			if ( $( '#createaccount' ).is( ':checked' ) && $( '#account_password' ).length && '' === $( '#account_password' ).val() ) {
				return true;
			}

			// Check to see if we need to validate shipping address.
			if ( $( '#ship-to-different-address-checkbox' ).is( ':checked' ) ) {
				requiredInputs = $( '.woocommerce-billing-fields .validate-required, .woocommerce-shipping-fields .validate-required' );
			} else {
				requiredInputs = $( '.woocommerce-billing-fields .validate-required' );
			}

			if ( requiredInputs.size() ) {
				var requiredError = false;

				requiredInputs.each( function() {
					if ( '' === $( this ).find( 'input.input-text, select' ).not( $( '#account_password, #account_username' ) ).val() ) {
						requiredError = true;
					}
				});

				if ( requiredError ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Check if object exists.
		 *
		 * @param {Object} obj
		 * @return {Bool}
		 */
		function isset( obj ) {
			return 0 < obj.length;
		}

		/**
		 * Get only numbers.
		 *
		 * @param {String} str
		 * @return {String}
		 */
		function getNumbers( str ) {
			return str.replace( /[^\d]/g, '' );
		}

		/**
		 * Get customer fields from checkout form.
		 *
		 * @param {Object} form [description]
		 * @return {Object}
		 */
		function getCustomerFields( form ) {
			var phone,
				data = {};

			data.customerName  = $.trim( $( '#billing_first_name', form ).val() + ' ' + $( '#billing_last_name', form ).val() );
			data.customerEmail = $( '#billing_email', form ).val();

			// Address fields.
			if ( isset( $( '#billing_address_1' ) ) ) {
				data.customerAddressStreet        = $( '#billing_address_1' ).val();
				data.customerAddressComplementary = $( '#billing_address_2' ).val();
				data.customerAddressZipcode       = getNumbers( $( '#billing_postcode' ).val() );

				if ( isset( $( '#billing_number' ) ) ) {
					data.customerAddressStreetNumber = $( '#billing_number' ).val();
				}

				if ( isset( $( '#billing_neighborhood' ) ) ) {
					data.customerAddressNeighborhood = $( '#billing_neighborhood' ).val();
				}
			}

			// Phone fields.
			if ( isset( $( '#billing_phone' ) ) ) {
				phone = getNumbers( $( '#billing_phone' ).val() );

				data.customerPhoneDdd    = phone.substr( 0, 2 );
				data.customerPhoneNumber = phone.substr( 2 );
			}

			if ( isset( $( '#billing_persontype' ) ) ) {
				if ( '1' === $( '#billing_persontype' ).val() ) {
					data.customerDocumentNumber = getNumbers( $( '#billing_cpf' ).val() );
				} else {
					data.customerName           = $( '#billing_company' ).val();
					data.customerDocumentNumber = getNumbers( $( '#billing_cnpj' ).val() );
				}
			} else if ( isset( $( '#billing_cpf' ) ) ) {
				data.customerDocumentNumber = getNumbers( $( '#billing_cpf' ).val() );
			} else if ( isset( $( '#billing_cnpj' ) ) ) {
				data.customerName           = $( '#billing_company' ).val();
				data.customerDocumentNumber = getNumbers( $( '#billing_cnpj' ).val() );
			}

			return data;
		}

		/**
		 * Open Checkout modal.
		 */
		function openCheckout() {
			// Check if checkout is invalid and allow to be submitted and validated.
			if ( isCheckoutInvalid() ) {
				return true;
			}

			var checkout, customer, params,
				form        = $( 'form.checkout, form#order_review' ),
				inline_data = $( '#mercadopago-checkout-params', form );

			// Create checkout.
			checkout = new MercadoPagoCheckout.Checkout({
				encryption_key: wcMercadoPagoParams.encryptionKey,
				success: function( data ) {
					formSubmit = true;

					// Remove any old token input.
					$( 'input[name=mercadopago_checkout_token]', form ).remove();

					// Add the token input.
					form.append( $( '<input name="mercadopago_checkout_token" type="hidden" />' ).val( data.token ) );

					// Submit the form.
					form.submit();
				}
			});

			if ( wcMercadoPagoParams.checkoutPayPage ) {
				customer = wcMercadoPagoParams.customerFields;
			} else {
				customer = getCustomerFields( form );
			}

			// Set params.
			params = $.extend({}, {
				paymentMethods:   'credit_card',
				customerData:     false,
				amount:           inline_data.data( 'total' ),
				createToken:      true,
				interestRate:     wcMercadoPagoParams.interestRate,
				maxInstallments:  inline_data.data( 'max_installment' ),
				freeInstallments: wcMercadoPagoParams.freeInstallments,
				postbackUrl:      wcMercadoPagoParams.postbackUrl,
				uiColor:          wcMercadoPagoParams.uiColor
			}, customer );

			// Open modal.
			checkout.open( params );

			return false;
		}
	});

}( jQuery ));