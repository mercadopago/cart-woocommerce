<?php

/**
 * @var string $qr_code
 * @var string $img_pix
 * @var string $amount
 * @var string $qr_base64
 * @var string $title_purchase_pix
 * @var string $title_how_to_pay
 * @var string $step_one
 * @var string $step_two
 * @var string $step_three
 * @var string $step_four
 * @var string $text_amount
 * @var string $text_scan_qr
 * @var string $text_time_qr_one
 * @var string $qr_date_expiration
 * @var string $text_description_qr
 * @var string $qr_code
 * @var string $text_button
 *
 * @see \MercadoPago\Woocommerce\Gateways\PixGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<p class="mp-details-title">
    <?= esc_html($title_purchase_pix); ?>
</p>

<div class="mp-details-pix">
    <div class="mp-row-checkout-pix">

        <div class="mp-col-md-4">

            <img src="<?= esc_html($img_pix); ?>" class="mp-details-pix-img"/>

            <p class="mp-details-pix-title">
                <?= esc_html($title_how_to_pay); ?>
            </p>
            <ul class="mp-steps-congrats mp-pix-left">
                <li class="mp-details-list">
                    <p class="mp-details-pix-number-p">1</p>
                    <p class="mp-details-list-description"><?= esc_html($step_one); ?></p>
                </li>
                <li class="mp-details-list">
                    <p class="mp-details-pix-number-p">
                        2
                    </p>
                    <p class="mp-details-list-description"><?= esc_html($step_two); ?></p>
                </li>
                <li class="mp-details-list">
                    <p class="mp-details-pix-number-p">
                        3
                    </p>
                    <p class="mp-details-list-description"><?= esc_html($step_three); ?></p>
                </li>
                <li class="mp-details-list">
                    <p class="mp-details-pix-number-p">
                        4
                    </p>
                    <p class="mp-details-list-description"><?= esc_html($step_four); ?></p>
                </li>
            </ul>

        </div>

        <div class="mp-col-md-8 mp-text-center mp-pix-right">
            <p class="mp-details-pix-amount">
                <span class="mp-details-pix-qr">
                    <?= esc_html($text_amount); ?>
                </span>
                <span class="mp-details-pix-qr-value">
                    <?= esc_html($amount); ?>
                </span>
            </p>
            <p class="mp-details-pix-qr-title">
                <?= esc_html($text_scan_qr); ?>
            </p>
            <img data-cy="qrcode-pix" class="mp-details-pix-qr-img" src="data:image/jpeg;base64,<?= esc_html($qr_base64); ?>" alt="Qr code"/>
            <p class="mp-details-pix-qr-subtitle">
                <?= esc_html($text_time_qr_one); ?><?php esc_html_e($qr_date_expiration, 'woocommerce-mercadopago'); ?>
            </p>
            <div class="mp-details-pix-container">
                <p class="mp-details-pix-qr-description">
                    <?= esc_html($text_description_qr); ?>
                </p>
                <div class="mp-row-checkout-pix-container">
                    <input id="mp-qr-code" value="<?= esc_html($qr_code); ?>" class="mp-qr-input"></input>

                    <button
                        onclick="copy_qr_code()"
                        class="mp-details-pix-button"
                    >
                        <?= esc_html($text_button); ?>
                    </button>

                    <script>
                        function copy_qr_code() {
                            const copyText = document.getElementById("mp-qr-code");
                            copyText.select();
                            copyText.setSelectionRange(0, 99999)
                            document.execCommand("copy");
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
