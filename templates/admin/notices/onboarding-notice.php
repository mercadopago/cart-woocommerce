<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<section
    id="mp-native-onboarding-notice"
    class="mp-native-onboarding-notice"
    role="status"
    aria-labelledby="mp-native-onboarding-notice-title"
    aria-describedby="mp-native-onboarding-notice-message"
>
    <div class="mp-native-onboarding-notice__thumbnail" aria-hidden="true">
        <img src="<?= esc_url($thumbnail) ?>" alt="" width="40" height="40">
        <span class="mp-native-onboarding-notice__badge">!</span>
    </div>
    <div class="mp-native-onboarding-notice__content">
        <h2 id="mp-native-onboarding-notice-title" class="mp-native-onboarding-notice__title"><?= esc_html($title) ?></h2>
        <p id="mp-native-onboarding-notice-message" class="mp-native-onboarding-notice__message"><?= esc_html($message) ?></p>
        <a id="mp-native-onboarding-notice-link" class="mp-native-onboarding-notice__link" href="<?= esc_url($buttonLink) ?>"><?= esc_html($buttonText) ?></a>
    </div>
    <button
        id="mp-native-onboarding-notice-close"
        class="mp-native-onboarding-notice__close"
        type="button"
        aria-label="<?= esc_attr__('Close', 'woocommerce-mercadopago') ?>"
    >
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
            <path d="M4.35156 5.19496L9.15406 9.99746L4.35156 14.8L5.20009 15.6485L10.0026 10.846L14.7963 15.6397L15.6449 14.7912L10.8511 9.99746L15.6449 5.20371L14.7963 4.35518L10.0026 9.14894L5.20009 4.34644L4.35156 5.19496Z" fill="currentColor"/>
        </svg>
    </button>
</section>
