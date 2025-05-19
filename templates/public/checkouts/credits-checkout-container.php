<?php

use MercadoPago\Woocommerce\Helpers\Template;

/**
 * @var bool $test_mode
 * @var string[] $i18n
 * @var string $test_mode_link_src
 * @var string $checkout_redirect_src
 * @var string $amount
 *
 * @see \MercadoPago\Woocommerce\Gateways\CreditsGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

if ($amount === null) {
    Template::render('public/checkouts/alert-message', ['message' => $i18n['currency_conversion_error']]);
    return;
}

$tabIndex = 1;

?>

<div class='mp-checkout-container'>
    <div class="mp-checkout-credits-container">
        <div class="mp-checkout-credits-content">
            <?php if ($test_mode) : ?>
                <div class="mp-checkout-credits-test-mode">
                    <test-mode title="<?= esc_html($i18n['test_mode_title']) ?>"
                        description="<?= esc_html($i18n['test_mode_description']) ?>"
                        link-text="<?= esc_html($i18n['test_mode_link_text']) ?>"
                        link-src="<?= esc_html($test_mode_link_src) ?>">
                    </test-mode>
                </div>
            <?php endif; ?>

            <div class="mp-credits-checkout-benefits">
                <div class="mp-checkout-benefits-list-container">
                    <p class="mp-checkout-benefits-list-title" tabindex="<?= $tabIndex++ ?>">
                        <?= $i18n['checkout_benefits_title'] ?>
                    </p>
                    <div class="mp-checkout-benefits-list">
                        <?php foreach (['one', 'two', 'three'] as $index => $step) : ?>
                            <div class="mp-checkout-benefits-list-item">
                                <p class="mp-checkout-benefits-list-count-list-item">
                                    <?= $index + 1 ?>.
                                </p>
                                <span tabindex="<?= $tabIndex++ ?>">
                                    <?= $i18n["checkout_step_$step"] ?>
                                </span>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>

            <div class="mp-checkout-credits-redirect">
                <div class="mp-checkout-redirect-v3-container" data-cy="checkout-redirect-v3-container">
                    <div class="mp-checkout-redirect-v3-title-container">
                        <img class="mp-checkout-redirect-v3-mp-logo-image" aria-hidden="true"
                            src="<?= $checkout_redirect_src ?>" alt="<?= $i18n['checkout_redirect_alt'] ?>">
                        <p class="mp-checkout-redirect-v3-title" tabindex="<?= $tabIndex++ ?>">
                            <?= $i18n['checkout_redirect_title'] ?>
                        </p>
                    </div>
                    <p class="mp-checkout-redirect-v3-description" tabindex="<?= $tabIndex++ ?>">
                        <?= $i18n['checkout_redirect_description'] ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
