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

if ( ! defined('ABSPATH') ) {
	exit;
}

?><div class="mp-credits-banner-info">
	<div class="mp-credits-banner-round-base">
		<div class="mp-credits-banner-round-background">
			<img alt="mp-logo-hand-shake" class="mp-credits-banner-round-logo" src="<?php echo esc_html(plugins_url('../assets/images/credits/mp-logo-hands-shake.png', plugin_dir_path(__FILE__))); ?>">
		</div>
	</div>
	<div class="mp-credits-banner-text">
		<span><?php echo wp_kses_post($banner_title); ?><span> <?php echo wp_kses_post($banner_title_bold); ?></span> <?php echo wp_kses_post($banner_title_end); ?></span>
	</div>
	<div class="mp-credits-banner-link">
		<span><a id="mp-open-modal"><?php echo esc_html($banner_link); ?></a></span>
		<div id="mp-credits-modal">
			<div id="mp-credits-centralize" class="mp-credits-modal-content-centralize">
				<div class="mp-credits-modal-container">
					<div class="mp-credits-modal-container-content">
						<div class="mp-credits-modal-content">
							<div class="mp-credits-modal-close-button">
								<img id="mp-credits-modal-close-modal" src="<?php echo esc_html(plugins_url('../assets/images/credits/close-icon.png', plugin_dir_path(__FILE__))); ?>">
							</div>
							<div class="mp-logo-img">
								<img src="<?php echo esc_html(plugins_url('../assets/images/credits/logo-mp.png', plugin_dir_path(__FILE__))); ?>">
							</div>

							<div class="mp-credits-modal-titles">
								<div>
									<span><?php echo esc_html($modal_title); ?></span>
									<p>
										<?php echo esc_html($modal_subtitle); ?> <b><?php echo esc_html($modal_subtitle_2); ?></b>
									</p>
								</div>
								<div>
									<div class="mp-credits-modal-how-to-use">
										<div>
											<div>
												<div class="mp-credits-modal-blue-circle">
													<span>
														<img alt="mp-logo-hand-shake" src="<?php echo esc_html(plugins_url('../assets/images/credits/check-blue.png', plugin_dir_path(__FILE__))); ?>">
													</span>
												</div>	
												<span><?php echo esc_html($modal_step_1); ?> <?php echo esc_html($modal_step_1_end); ?></span>
											</div>
											<div>
												<div class="mp-credits-modal-blue-circle">
													<span>
														<img alt="mp-logo-hand-shake" src="<?php echo esc_html(plugins_url('../assets/images/credits/check-blue.png', plugin_dir_path(__FILE__))); ?>">
													</span>
												</div>
												<span><?php echo esc_html($modal_step_2); ?> <?php echo esc_html($modal_step_2_end); ?></span>
											</div>
											<div>
												<div class="mp-credits-modal-blue-circle">
													<span>
														<img alt="mp-logo-hand-shake" src="<?php echo esc_html(plugins_url('../assets/images/credits/check-blue.png', plugin_dir_path(__FILE__))); ?>">
													</span>
												</div>
												<span><?php echo esc_html($modal_step_3); ?></span>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="mp-credits-modal-FAQ">
								<p>
									<?php echo esc_html($modal_footer); ?>
									<a id="mp-modal-footer-link" target="_blank" href="<?php echo esc_html($modal_footer_help_link); ?>"><?php echo esc_html($modal_footer_link); ?></a>
									<?php echo esc_html($modal_footer_end); ?>
								</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
