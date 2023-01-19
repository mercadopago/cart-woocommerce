<?php

/**
 * @var string $title
 * @var string $step_one_text
 * @var string $step_two_text_one
 * @var string $step_two_text_two
 * @var string $step_two_text_highlight_one
 * @var string $step_two_text_highlight_two
 * @var string $step_three_text
 * @var string $observation_one
 * @var string $observation_two
 * @var string $button_about_pix
 * @var string $observation_three
 * @var string $link_title_one
 * @var string $link_url_one
 * @var string $link_url_two
 *
 * @see \MercadoPago\Woocommerce\Gateways\PixGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div>
	<h3 class="mp_subtitle_body">
        <?= $title ?>
    </h3>

	<ul class="mp-row-flex">
		<li class="mp-col-md-3 mp-pb-10">
			<p class="mp-number-checkout-body">1</p>
			<p class="mp-text-steps mp-px-20">
				<?= $step_one_text ?>
			</p>
		</li>

		<li class="mp-col-md-3 mp-pb-10">
			<p class="mp-number-checkout-body">2</p>
			<p class="mp-text-steps mp-px-20">
				<?= $step_two_text?>
			</p>
		</li>

		<li class="mp-col-md-3 mp-pb-10">
			<p class="mp-number-checkout-body">3</p>
			<p class="mp-text-steps mp-px-20">
				<?= $step_three_text ?>
			</p>
		</li>
	</ul>

	<div class="mp-col-md-12 mp-division-line-steps">
		<p class="mp-text-observation mp-gray-text">
			<?= $observation_one ?>
            </br>
			<?= $observation_two ?>
		</p>
	</div>

	<div class="mp-col-md-12 mp_store_link">
		<p class="">
			<a href=<?= $link_url_one ?> target="_blank"><?= $button_about_pix ?></a>
		</p>
	</div>

	<div class="mp-col-md-12 mp-pb-10">
		<p class="mp-text-observation mp-gray-text">
			<?= $observation_three ?>
			<a href=<?= $link_url_two ?> target="_blank"><?= $link_title_one ?></a>
		</p>
	</div>

</div>
