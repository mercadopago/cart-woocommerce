<?php

use MercadoPago\Woocommerce\Helpers\Template;

/**
 * @var bool $test_mode
 * @var array $i18n
 * @var string $site_id
 * @var array $payment_methods
 * @var ?float $amount
 * @var \MercadoPago\Woocommerce\Helpers\Url $url
 *
 * @see \MercadoPago\Woocommerce\Gateways\BasicGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

if ($amount === null) {
    Template::render('public/checkouts/alert-message', ['message' => $i18n['currency_conversion_error']]);
    return;
}

?>

<div class='mp-checkout-container'>
    <div class="mp-checkout-pro-container">
        <div class="mp-checkout-pro-content">

        <?php if ($test_mode) : ?>
                <test-mode title="<?= esc_attr($i18n['test_mode_title']) ?>" description="<?= esc_attr($i18n['test_mode_description']) ?>"
                    link-text="<?= esc_attr($i18n['test_mode_link_text']) ?>" link-src="<?= esc_url($links['docs_integration_test']) ?>">
                </test-mode>
        <?php endif ?>

            <h4 class="mp-checkout-pro-benefits-title">
                <?= wp_kses_post($i18n['benefits_title']) ?>
            </h4>
            <div class="mp-checkout-pro-benefits">
                <?php foreach (['first' => ['MLB' => 'shield', 'ROLA' => 'wallet'], 'second' => ['MLB' => 'dollar-sign', 'ROLA' => 'shield']] as $benefit => $icons) : ?>
                    <div class="mp-checkout-pro-benefit">
                        <img src="<?= esc_url($url->getImageAsset('checkouts/basic/' . ($icons[$site_id] ?? $icons['ROLA']) . '.svg')) ?>"
                            class="mp-checkout-pro-benefit-icon">
                        <p class="mp-checkout-pro-benefit-description">
                            <?= wp_kses_post($i18n["{$benefit}_benefit_description"]) ?>
                        </p>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="mp-checkout-pro-payment-methods">
                <?php $payment_method_labels = [
                    'amex' => 'American Express',
                    'clabe' => 'SPEI',
                    'master' => 'Mastercard',
                    'naranja' => 'Naranja X',
                    'bancomer' => 'BBVA',
                    'account-money' => $i18n['account_money']
                ]; ?>
                <?php foreach ($payment_methods as $payment_method) : ?>
                    <img src="<?= esc_url($url->getImageAsset("checkouts/basic/payment-methods/$payment_method.svg")) ?>"
                        alt="<?= esc_attr($payment_method_labels[$payment_method] ?? ucfirst($payment_method)) ?>"
                        class="mp-checkout-pro-payment-method-icon">
                <?php endforeach ?>
            </div>
            <?php if ($method === 'redirect') : ?>
                <div class="mp-checkout-pro-redirect">
                    <p class="mp-checkout-pro-redirect-title">
                        <img src="<?= esc_url($url->getImageAsset("checkouts/basic/redirect-logo.svg")) ?>"
                            class="mp-checkout-pro-redirect-icon">
                        <?= esc_html($i18n['redirect_title']) ?>
                    </p>
                    <p class="mp-checkout-pro-redirect-description">
                        <?= esc_html($i18n['redirect_description']) ?>
                    </p>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>
