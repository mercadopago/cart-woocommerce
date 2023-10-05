<?php

/**
 * @var string $public_key
 * @var string $preference_id
 * @var string $wallet_button_title
 * @var string $cancel_url
 * @var string $cancel_url_text
 *
 * @see \MercadoPago\Woocommerce\Gateways\CustomGateway
 */

if (!defined('ABSPATH')) {
	exit;
}

?>

<script>
	window.addEventListener('load', function() {
		window.mp = new MercadoPago('<?= esc_html($public_key); ?>');
		window.checkout = window.mp.checkout({
			preference: {
				id: '<?= esc_html($preference_id); ?>'
			},
			autoOpen: true,
		});
	});
</script>

<div style="margin-bottom: 24px">
	<a id="submit-payment" href="#" onclick="checkout.open()" class="button alt">
		<?= esc_html($wallet_button_title); ?>
	</a>

	<a class="button cancel" href="<?= esc_url($cancel_url); ?>">
		<?= esc_html($cancel_url_text); ?>
	</a>
</div>