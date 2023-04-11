<?php

/**
 * @var string $title_installment_cost
 * @var string $title_installment_total
 * @var string $text_installments
 * @var string $currency
 * @var float $total_paid_amount
 * @var float $transaction_amount
 * @var float $total_diff_cost
 * @var float $installment_amount
 * @var float $installments
 *
 * @see \MercadoPago\Woocommerce\Gateways\CustomGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div>
	<table style="margin-top:-24px; margin-bottom:60px;">
		<tfoot>
			<tr>
				<th style="width: 55.5%;">
                    <?= esc_html($title_installment_cost); ?>
                </th>

				<td class="order_details">
                    <?= esc_html($currency); ?>
                    <?= esc_html($total_diff_cost); ?>
                </td>
			</tr>

			<tr>
				<th style="width: 55.5%;">
                    <?= esc_html($title_installment_total); ?>
                </th>

				<td class="order_details">
                    <?= esc_html($currency); ?>
                    <?= esc_html($total_paid_amount); ?>
                    (
                        <?= esc_html($installments); ?>
                        <?= esc_html($text_installments); ?>
                        <?= esc_html($currency); ?>
                        <?= esc_html($installment_amount); ?>
                    )
                </td>
			</tr>
		</tfoot>
	</table>
</div>
