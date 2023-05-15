<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 * @package MercadoPago
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<script>
	window.addEventListener("load", function () {
		mp_settings_screen_load();
	});
</script>
<div class="mp-settings">
	<div class="mp-settings-header">
		<div class="mp-settings-header-img"></div>
		<div class="mp-settings-header-logo"></div>
		<hr class="mp-settings-header-hr"/>
		<p><?php echo esc_html($translation_header['title_head_part_one']); ?><b><?php echo esc_html($translation_header['title_head_part_two']); ?></b> <?php echo esc_html($translation_header['title_head_part_three']); ?> </br> <?php echo esc_html($translation_header['title_head_part_four']); ?> <b> <?php echo esc_html($translation_header['title_head_part_six']); ?></b><?php echo esc_html($translation_header['title_head_part_seven']); ?></p>
	</div>
	<div class="mp-settings-requirements">
		<div class="mp-container">
			<div class="mp-block mp-block-requirements mp-settings-margin-right">
				<p class="mp-settings-font-color mp-settings-title-font-size"><?php echo esc_html($translation_header['title_requirements']); ?></p>
				<div class="mp-inner-container">
					<div>
						<p class="mp-settings-font-color mp-settings-subtitle-font-size"><?php echo esc_html($translation_header['ssl']); ?></p>
						<label class="mp-settings-icon-info mp-settings-tooltip"><span class="mp-settings-tooltip-text"><p class="mp-settings-subtitle-font-size"><b><?php echo esc_html($translation_header['ssl']); ?></b></p><?php echo esc_html($translation_header['description_ssl']); ?></span>
						</label>
					</div>
					<div>
						<div id="mp-req-ssl" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
					</div>
				</div>
				<hr>
				<div class="mp-inner-container">
					<div><?php echo esc_html($translation_header['gd_extensions']); ?></p>
						<label class="mp-settings-icon-info mp-settings-tooltip">
							<span class="mp-settings-tooltip-text"><p class="mp-settings-subtitle-font-size"><b><?php echo esc_html($translation_header['gd_extensions']); ?></b></p><?php echo esc_html($translation_header['description_gd_extensions']); ?></span>
						</label>
					</div>
					<div>
						<div id="mp-req-gd" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
					</div>
				</div>
				<hr>
				<div class="mp-inner-container">
					<div>
						<p class="mp-settings-font-color mp-settings-subtitle-font-size"><?php echo esc_html($translation_header['curl']); ?></p><label class="mp-settings-icon-info mp-settings-tooltip">
							<span class="mp-settings-tooltip-text"><p class="mp-settings-subtitle-font-size"><b><?php echo esc_html($translation_header['curl']); ?></b></p><?php echo esc_html($translation_header['description_curl']); ?></span>
						</label>
					</div>
					<div>
						<div id="mp-req-curl" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
					</div>
				</div>
			</div>
			<div class="mp-block mp-block-flex mp-settings-margin-left mp-settings-margin-right">
				<div class="mp-inner-container-settings">
					<div>
						<p class="mp-settings-font-color mp-settings-title-font-size"><?php echo esc_html($translation_header['title_installments']); ?></p>
						<p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color"><?php echo esc_html($translation_header['descripition_installments']); ?>
							<b><?php echo esc_html($translation_header['descripition_installments_one']); ?></b> <?php echo esc_html($translation_header['descripition_installments_two']); ?>
							<b><?php echo esc_html($translation_header['descripition_installments_three']); ?></b> <?php echo esc_html($translation_header['descripition_installments_four']); ?>
						</p>
					</div>
					<div>
						<a target="_blank" href="<?php echo esc_html($links['link_costs']); ?>">
							<button class="mp-button" id="mp-set-installments-button">
								<?php echo esc_html($translation_header['button_installments']); ?>
							</button>
						</a>
					</div>
				</div>
			</div>
			<div class="mp-block mp-block-flex mp-block-manual mp-settings-margin-left">
				<div class="mp-inner-container-settings">
					<div>
						<p class="mp-settings-font-color mp-settings-title-font-size"><?php echo esc_html($translation_header['title_questions']); ?></p>
						<p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color"><?php echo esc_html($translation_header['descripition_questions_one']); ?> <b><span><?php echo esc_html($translation_header['descripition_questions_two']); ?></b></span><?php echo esc_html($translation_header['descripition_questions_three']); ?></p>
					</div>
					<div>
						<a target="_blank" href="<?php echo esc_html($links['link_guides_plugin']); ?>">
							<button id="mp-plugin-guide-button" class="mp-button mp-button-light-blue">
								<?php echo esc_html($translation_header['button_questions']); ?>
							</button>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<hr class="mp-settings-hr"/>
	<div class="mp-settings-credentials">
		<div id="mp-settings-step-one" class="mp-settings-title-align">
			<div class="mp-settings-title-container">
				<span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right"><?php echo esc_html($translation_credential['title_credentials']); ?></span>
				<img class="mp-settings-margin-left mp-settings-margin-right" id="mp-settings-icon-credentials">
			</div>
			<div class="mp-settings-title-container mp-settings-margin-left">
				<img class="mp-settings-icon-open" id="mp-credentials-arrow-up">
			</div>
		</div>
		<div id="mp-step-1" class="mp-settings-block-align-top" style="display: none;">
			<div>
				<p class="mp-settings-subtitle-font-size mp-settings-title-color"><?php echo esc_html($translation_credential['subtitle_credentials_one']); ?> <b><?php echo esc_html($translation_credential['subtitle_credentials_two']); ?></b></p>
			</div>
			<div class="mp-message-credentials">
				<a class="mp-heading-credentials" target="_blank" href="<?php echo esc_html($links['link_credentials']); ?>">
					<button id="mp-get-credentials-button" class="mp-button mp-button-light-blue">
						<?php echo esc_html($translation_credential['button_link_credentials']); ?>
					</button>
				</a>
			</div>

			<div id="msg-info-credentials">
			</div>

			<div class="mp-container">
				<div class="mp-block mp-block-flex mp-settings-margin-right">
					<p class="mp-settings-title-font-size"><b><?php echo esc_html($translation_credential['title_credential_prod']); ?></b></p>
					<p class="mp-settings-label mp-settings-title-color mp-settings-margin-bottom"><?php echo esc_html($translation_credential['subtitle_credential_prod']); ?></p>
					<fieldset class="mp-settings-fieldset">
						<legend class="mp-settings-label mp-settings-font-color">
							<?php echo esc_html($translation_credential['public_key']); ?>
							<span style="color: red;">&nbsp;*</span>
						</legend>
						<input
							type="text"
							id="mp-public-key-prod"
							class="mp-settings-input"
							value="<?php echo esc_html($options_credentials['credentials_public_key_prod']); ?>"
							placeholder="<?php echo esc_html($translation_credential['placeholder_public_key']); ?>"
						>
					</fieldset>

					<fieldset>
						<legend class="mp-settings-label mp-settings-font-color">
							<?php echo esc_html($translation_credential['access_token']); ?>
							<span style="color: red;">&nbsp;*</span>
						</legend>
						<input
							type="text"
							id="mp-access-token-prod"
							class="mp-settings-input"
							value="<?php echo esc_html($options_credentials['credentials_access_token_prod']); ?>"
							placeholder="<?php echo esc_html($translation_credential['placeholder_access_token']); ?>"
						>
					</fieldset>
				</div>

				<div class="mp-block mp-block-flex mp-settings-margin-left">
					<p class="mp-settings-title-font-size"><b> <?php echo esc_html($translation_credential['title_credential_test']); ?> </b> </p>
					<p class="mp-settings-label mp-settings-title-color mp-settings-margin-bottom"><?php echo esc_html($translation_credential['subtitle_credential_test']); ?></p>
					<fieldset class="mp-settings-fieldset">
						<legend class="mp-settings-label mp-settings-font-color"><?php echo esc_html($translation_credential['public_key']); ?></legend>
						<input class="mp-settings-input " id="mp-public-key-test" type="text" value="<?php echo esc_html($options_credentials['credentials_public_key_test']); ?>" placeholder="<?php echo esc_html($translation_credential['placeholder_public_key']); ?>">
					</fieldset>
					<fieldset>
						<legend class="mp-settings-label mp-settings-font-color"><?php echo esc_html($translation_credential['access_token']); ?></legend>
						<input class="mp-settings-input " id="mp-access-token-test" type="text" value="<?php echo esc_html($options_credentials['credentials_access_token_test']); ?>" placeholder="<?php echo esc_html($translation_credential['placeholder_access_token']); ?>" >
					</fieldset>
				</div>
			</div>
			<button class="mp-button" id="mp-btn-credentials" > <?php echo esc_html($translation_credential['button_credentials']); ?></button>
		</div>
	</div>

	<hr class="mp-settings-hr"/>
	<div class="mp-settings-credentials">
		<div id="mp-settings-step-two" class="mp-settings-title-align">
			<div class="mp-settings-title-container">
				<span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right"><?php echo esc_html($translation_store['title_store']); ?></span>
				<img class="mp-settings-margin-left mp-settings-margin-right" id="mp-settings-icon-store">
			</div>
			<div class="mp-settings-title-container mp-settings-margin-left" >
				<img class="mp-settings-icon-open" id="mp-store-info-arrow-up">
			</div>
		</div>
			<div id="mp-step-2" class="mp-message-store mp-settings-block-align-top" style="display: none;">
			<p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color"><?php echo esc_html($translation_store['subtitle_store']); ?></p>
			<div class="mp-heading-store mp-container mp-settings-flex-start" id="block-two">
				<div class="mp-block mp-block-flex mp-settings-margin-right mp-settings-choose-mode">
					<div>
						<p class="mp-settings-title-font-size"><b><?php echo esc_html($translation_store['title_info_store']); ?></b></p>
					</div>
					<div class="mp-settings-standard-margin">
						<fieldset>
							<legend class="mp-settings-label mp-settings-font-color"><?php echo esc_html($translation_store['subtitle_name_store']); ?></legend>
							<input type="text" class="mp-settings-input" id="mp-store-identificator" placeholder= "<?php echo esc_html($translation_store['placeholder_name_store']); ?>" value="<?php echo esc_html($store_identificator); ?>">
						</fieldset>
						<span class="mp-settings-helper"><?php echo esc_html($translation_store['helper_name_store']); ?></span>
					</div>
					<div class="mp-settings-standard-margin">
						<fieldset>
							<legend class="mp-settings-label mp-settings-font-color"><?php echo esc_html($translation_store['subtitle_activities_store']); ?></legend>
							<input type="text" class="mp-settings-input" id="mp-store-category-id" placeholder="<?php echo esc_html($translation_store['placeholder_activities_store']); ?>" value="<?php echo esc_html($category_id); ?>">
						</fieldset>
						<span class="mp-settings-helper"><?php echo esc_html($translation_store['helper_activities_store']); ?></span>
					</div>
					<div class="mp-settings-standard-margin">

						<label class="mp-settings-label mp-container mp-settings-font-color"><?php echo esc_html($translation_store['subtitle_category_store']); ?></label>

						<select name="<?php echo esc_html($translation_store['placeholder_category_store']); ?>" class="mp-settings-select" id="mp-store-categories">

						<?php
						for ( $i = 0; $i < count($categories_store['store_categories_description']); $i++ ) { // phpcs:ignore
								echo "<option value='" . esc_html($categories_store['store_categories_id'][$i])
								. "'" . esc_html(( $category_selected === $categories_store['store_categories_id'][$i] ) ? 'selected' : '' )
								. '>' . esc_html($categories_store['store_categories_description'][$i]) . '</option>';
						}
						?>
						</select>
						<span class="mp-settings-helper"><?php echo esc_html($translation_store['helper_category_store']); ?></span>
					</div>
				</div>

				<div class="mp-block mp-block-flex mp-block-manual mp-settings-margin-left">
					<div>
						<p class="mp-settings-title-font-size"><b><?php echo esc_html($translation_store['title_advanced_store']); ?></b></p>
					</div>
					<p class="mp-settings-subtitle-font-size mp-settings-title-color">
					<?php echo esc_html($translation_store['subtitle_advanced_store']); ?>
					</p>
					<div>
						<p class="mp-settings-blue-text" id="options">
							<?php echo esc_html($translation_store['accordion_advanced_store']); ?>
						</p>
						<div class="mp-settings-advanced-options" style="display:none">
							<div class="mp-settings-standard-margin">
								<fieldset>
									<legend class="mp-settings-label mp-settings-font-color"><?php echo esc_html($translation_store['subtitle_url']); ?></legend>
									<input type="text" class="mp-settings-input" id="mp-store-url-ipn" placeholder="<?php echo esc_html($translation_store['placeholder_url']); ?>" value="<?php echo esc_html($url_ipn); ?>">

									<div>
										<input type="checkbox" id="mp-store-url-ipn-options" <?php echo esc_html($url_ipn_options_checked); ?> />
										<label for="mp-store-url-ipn-options" class="mp-settings-checkbox-options"><?php echo esc_html($translation_store['options_url']); ?></label>
									</div>

									<span class="mp-settings-helper">
										<?php echo esc_html($translation_store['helper_url']); ?>
										<span>
											<a class="mp-settings-blue-text" target="_blank" href="<?php echo esc_html($devsite_links['notifications_ipn']); ?>" >
												<?php echo esc_html($translation_store['helper_url_link']); ?>
											</a>
										</span>
									</span>
								</fieldset>
							</div>
							<div class="mp-settings-standard-margin">
								<fieldset>
									<legend class="mp-settings-label mp-settings-font-color"><?php echo esc_html($translation_store['subtitle_integrator']); ?></legend>
									<input type="text" class="mp-settings-input" id="mp-store-integrator-id" placeholder="<?php echo esc_html($translation_store['placeholder_integrator']); ?>" value="<?php echo esc_html( $integrator_id ); ?>">
									<span class="mp-settings-helper"><?php echo esc_html($translation_store['helper_integrator']); ?></span>
									<span class="mp-settings-helper"><span><a class="mp-settings-blue-text" target="_blank" href="<?php echo esc_html($devsite_links['dev_program']); ?>"> <?php echo esc_html($translation_store['helper_integrator_link']); ?></a></span>
								</fieldset>
							</div>
							<div class="mp-container">
								<!-- Rounded switch -->
								<div>
									<label class="mp-settings-switch">
										<input type="checkbox" value="yes" id="mp-store-debug-mode" <?php echo esc_html(( 'yes' === $debug_mode ) ? 'checked' : ''); ?>>
										<span class="mp-settings-slider mp-settings-round"></span>
									</label>
								</div>
								<div>
									<span class="mp-settings-subtitle-font-size mp-settings-debug mp-settings-font-color">
									<?php echo esc_html($translation_store['title_debug']); ?>
									</span></br>
									<span class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color mp-settings-debug">
									<?php echo esc_html($translation_store['subtitle_debug']); ?>
									</span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<button class="mp-button" id="mp-store-info-save"> <?php echo esc_html($translation_store['button_store']); ?> </button>
		</div>
	</div>

	<hr class="mp-settings-hr"/>
	<div class="mp-settings-payment">
		<div id="mp-settings-step-three" class="mp-settings-title-align">
			<div class="mp-settings-title-container">
				<span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right"><?php echo esc_html($translation_payment['title_payments']); ?></span>
				<img class="mp-settings-margin-left mp-settings-margin-right" id="mp-settings-icon-payment">
			</div>

			<div class="mp-settings-title-container mp-settings-margin-left">
				<img class="mp-settings-icon-open" id="mp-payments-arrow-up">
			</div>
		</div>
		<div id="mp-step-3" class="mp-settings-block-align-top" style="display: none;">
			<p id="mp-payment" class="mp-settings-subtitle-font-size mp-settings-title-color"><?php echo esc_html($translation_payment['subtitle_payments']); ?></p>
			<button id="mp-payment-method-continue" class="mp-button"> <?php echo esc_html($translation_payment['button_payment']); ?></button>
		</div>
	</div>
	<hr class="mp-settings-hr" />
	<div class="mp-settings-mode">
		<div id="mp-settings-step-four" class="mp-settings-title-align">
			<div class="mp-settings-title-container">
				<span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right"><?php echo esc_html($translation_test_mode['title_test_mode']); ?></span>
				<div id="mp-mode-badge" class="mp-settings-margin-left mp-settings-margin-right <?php echo esc_html('yes' === ( $checkbox_checkout_test_mode ) ? 'mp-settings-test-mode-alert' : 'mp-settings-prod-mode-alert'); ?>  ">
				<span id="mp-mode-badge-test" style="display: <?php echo esc_html('yes' === ( $checkbox_checkout_test_mode ) ? 'block;' : 'none;'); ?>">
					<?php echo esc_html( $translation_test_mode['badge_test'] ); ?>
				</span>
				<span id="mp-mode-badge-prod" style="display: <?php echo esc_html('yes' === ( $checkbox_checkout_test_mode ) ? 'none;' : 'block;'); ?>">
					<?php echo esc_html( $translation_test_mode['badge_mode'] ); ?>
				</span>
				</div>
			</div>
			<div class="mp-settings-title-container mp-settings-margin-left">
				<img class="mp-settings-icon-open" id="mp-modes-arrow-up">
			</div>
		</div>
		<div id="mp-step-4" class="mp-message-test-mode mp-settings-block-align-top" style="display: none;">
			<p class="mp-heading-test-mode mp-settings-subtitle-font-size mp-settings-title-color"><?php echo esc_html($translation_test_mode['subtitle_test_mode']); ?></p>
			<div class="mp-container">
				<div class="mp-block mp-settings-choose-mode">
					<div>
						<p class="mp-settings-title-font-size"><b><?php echo esc_html($translation_test_mode['title_mode']); ?></b></p>
					</div>
					<div class="mp-settings-mode-container">
						<div class="mp-settings-mode-spacing">
							<input name="mp-test-prod" type="radio" class="mp-settings-radio-button" value='yes' <?php echo esc_html(( 'yes' === $checkbox_checkout_test_mode ) ? 'checked' : ''); ?> >
						</div>
						<div>
							<span class="mp-settings-subtitle-font-size mp-settings-font-color">
								<?php echo esc_html($translation_test_mode['title_test']); ?>
							</span>
							<br>

							<span class="mp-settings-subtitle-font-size mp-settings-title-color">
								<?php echo esc_html( $translation_test_mode['subtitle_test'] ); ?>
							<span>
							<a id="mp-test-mode-rules-link" class="mp-settings-blue-text" target="_blank" href="<?php echo esc_html($devsite_links['shopping_testing']); ?>">
								<?php echo esc_html($translation_test_mode['subtitle_test_link']); ?>
							</a>
							<span></span>
						</div>
					</div>
					<div class="mp-settings-mode-container">
						<div class="mp-settings-mode-spacing">
							<input name="mp-test-prod" type="radio" class="mp-settings-radio-button" value='no' <?php echo esc_html(( 'no' === $checkbox_checkout_test_mode ) ? 'checked' : ''); ?>>
						</div>
						<div>
							<span class="mp-settings-subtitle-font-size mp-settings-font-color"><?php echo esc_html( $translation_test_mode['title_prod'] ); ?></span><br>

							<span class="mp-settings-subtitle-font-size mp-settings-title-color"><?php echo esc_html( $translation_test_mode['subtitle_prod'] ); ?></span>
						</div>
					</div>
					<div class="mp-settings-alert-payment-methods">
						<div id="mp-red-badge" class="mp-settings-alert-red" style="display:none;" >
							<div class="mp-settings-alert-payment-methods-gray" style="width: 540px">
								<div class="mp-settings-margin-right mp-settings-mode-style">
									<label id="mp-icon-badge-error" class="mp-settings-icon-warning"></label>
								</div>
								<div class="mp-settings-mode-warning">
									<div class="mp-settings-margin-left">
										<div class="mp-settings-alert-mode-title">
											<span id="mp-text-badge"> <?php echo esc_html( $translation_test_mode['title_alert_test'] ); ?></span> </span>
										</div>
										<div id="mp-helper-badge-div" class="mp-settings-alert-mode-body mp-settings-font-color">
											<span id="mp-helper-test-error">
												<?php echo esc_html($translation_test_mode['subtitle_alert_test']); ?>
												<a class="mp-settings-blue-text" id="mp-testmode-credentials-link" target="_blank" href="<?php echo esc_html($links['link_credentials']); ?>"> <?php echo esc_html( $translation_test_mode['title_alert_test_link'] ); ?></a>
												<?php echo esc_html($translation_test_mode['title_alert_tes_one']); ?>
											</span>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="mp-settings-alert-payment-methods">
						<div id="mp-orange-badge" class="<?php echo esc_html('yes' === ( $checkbox_checkout_test_mode ) ? 'mp-settings-alert-payment-methods-orange' : 'mp-settings-alert-payment-methods-green'); ?>"></div>
						<div class="mp-settings-alert-payment-methods-gray">
							<div class="mp-settings-margin-right mp-settings-mode-style">
								<label id="mp-icon-badge" class="<?php echo esc_html('yes' === ( $checkbox_checkout_test_mode ) ? 'mp-settings-icon-warning' : 'mp-settings-icon-success'); ?>  "></label>
							</div>
							<div class="mp-settings-mode-warning">
								<div class="mp-settings-margin-left">
									<div class="mp-settings-alert-mode-title">
										<span id="mp-title-helper-prod" style="display: <?php echo esc_html('yes' === ( $checkbox_checkout_test_mode ) ? 'none;' : 'block;'); ?>">
										<span id="mp-text-badge"> <?php echo esc_html( $translation_test_mode['title_message_prod'] ); ?></span>
										</span>
										<span id="mp-title-helper-test" style="display: <?php echo esc_html('yes' === ( $checkbox_checkout_test_mode ) ? 'block;' : 'none;'); ?>">
										<span id="mp-text-badge" > <?php echo esc_html( $translation_test_mode['title_message_test'] ); ?></span>
										</span>
									</div>
									<div id="mp-helper-badge-div" class="mp-settings-alert-mode-body mp-settings-font-color">
										<span id="mp-helper-test" style="display: <?php echo esc_html('yes' === ( $checkbox_checkout_test_mode ) ? 'block;' : 'none;'); ?>">
											1.<?php echo esc_html($translation_test_mode['subtitle_test1']); ?>
											<a class="mp-settings-blue-text" id="mp-testmode-testuser-link" target="_blank" href="https://www.mercadopago.com/developers/panel/test-users"> <?php echo esc_html( $translation_test_mode['subtitle_link_test1'] ); ?> </a><?php echo esc_html( $translation_test_mode['subtitle_message_test1'] ); ?><br/>
											2.<a class="mp-settings-blue-text" id="mp-testmode-cardtest-link" target="_blank" href="<?php echo esc_html($devsite_links['test_cards']); ?>"> <?php echo esc_html( $translation_test_mode['subtitle_link_test2'] ); ?> </a><?php echo esc_html( $translation_test_mode['subtitle_test2'] ); ?><br/>
											3.<a class="mp-settings-blue-text" id="mp-testmode-store-link" target="_blank" href="<?php echo esc_html(get_permalink( wc_get_page_id( 'shop' ) )); ?>"> <?php echo esc_html( $translation_test_mode['subtitle_link_test3'] ); ?> </a><?php echo esc_html( $translation_test_mode['subtitle_test3'] ); ?>.
										</span>
										<span id="mp-helper-prod"  style="display: <?php echo esc_html('yes' === ( $checkbox_checkout_test_mode ) ? 'none;' : 'block;'); ?>"><?php echo esc_html( $translation_test_mode['subtitle_message_prod'] ); ?></span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<button class="mp-button" id="mp-store-mode-save"><?php echo esc_html( $translation_test_mode['button_mode'] ); ?> </button>
		</div>
	</div>
</div>
<span id='reference' value='{"mp-screen-name":"admin"}'></span>
