<?php

/**
 * @var string $initial_state
 * @var string|null $initial_error
 * @var string $processing_title
 * @var string $processing_description
 * @var string $finalizing_description
 * @var string $delayed_description
 * @var string $waiting_description
 * @var string $long_wait_description
 * @var int $initial_elapsed_ms
 * @var string $info_icon
 * @var string $timeout_description
 * @var string $approved_title
 * @var string $approved_description
 * @var string $rejected_title
 * @var string $rejected_description
 * @var string $insufficient_title
 * @var string $limit_title
 * @var string $alternative_description
 * @var string $new_purchase_url
 * @var string $new_purchase_label
 * @var string $approved_icon
 * @var string $rejected_icon
 * @var string $cancelled_title
 * @var string $cancelled_description
 *
 * @see \MercadoPago\Woocommerce\Gateways\PixGateway
 */

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixBuyerError;

if (!defined('ABSPATH')) {
    exit;
}

$rejections = [
    SmartPixBuyerError::REJECTED             => [$rejected_title, $rejected_description],
    SmartPixBuyerError::INSUFFICIENT_BALANCE => [$insufficient_title, $alternative_description],
    SmartPixBuyerError::LIMIT_EXCEEDED       => [$limit_title, $alternative_description],
];
$initial_error = isset($rejections[$initial_error ?? '']) ? $initial_error : SmartPixBuyerError::REJECTED;
$processing_steps = [$processing_title, $processing_description, $finalizing_description, $delayed_description, $waiting_description];
$elapsed = max(0, $initial_elapsed_ms ?? 0);
$initial_step = count(array_filter([10000, 20000, 30000, 45000], static fn($at) => $elapsed >= $at));
$long_wait = $elapsed >= 120000;
$observation_stopped = $elapsed >= 900000;

?>

<div class="mp-smart-pix-status" data-mp-smart-pix-status data-state="<?php echo esc_attr($initial_state); ?>" data-stage="<?php echo esc_attr((string) $initial_step); ?>">
    <div data-mp-smart-pix-state="pending"<?php echo $initial_state === 'pending' ? '' : ' hidden'; ?>>
      <div class="mp-smart-pix-status__card mp-smart-pix-status__card--pending" data-mp-smart-pix-loading<?php echo $long_wait ? ' hidden' : ''; ?>>
        <?php foreach ($processing_steps as $step => $message) : ?>
            <p class="mp-smart-pix-status__description" data-mp-smart-pix-step="<?php echo esc_attr((string) $step); ?>"<?php echo $step === $initial_step ? '' : ' hidden'; ?>><?php echo esc_html($message); ?></p>
        <?php endforeach; ?>
        <!-- This is a timed presentation, not a percentage reported by the payment provider. -->
        <div class="mp-smart-pix-status__progress" aria-hidden="true"><span class="mp-smart-pix-status__progress-fill" style="width: <?php echo esc_attr((string) min(90, $elapsed * 90 / 30000)); ?>%"></span></div>
      </div>
      <div class="mp-smart-pix-status__card mp-smart-pix-status__card--informative" data-mp-smart-pix-waiting<?php echo $long_wait ? '' : ' hidden'; ?>>
        <div class="mp-smart-pix-status__heading">
            <span class="mp-smart-pix-status__icon mp-smart-pix-status__icon--informative" aria-hidden="true"><img src="<?php echo esc_url($info_icon); ?>" alt=""></span>
            <p class="mp-smart-pix-status__title"><?php echo esc_html($delayed_description); ?></p>
        </div>
        <p class="mp-smart-pix-status__description" data-mp-smart-pix-long-wait<?php echo $observation_stopped ? ' hidden' : ''; ?>><?php echo esc_html($long_wait_description); ?></p>
        <p class="mp-smart-pix-status__description" data-mp-smart-pix-timeout<?php echo $observation_stopped ? '' : ' hidden'; ?>><?php echo esc_html($timeout_description); ?></p>
      </div>
    </div>

    <div class="mp-smart-pix-status__card mp-smart-pix-status__card--approved" data-mp-smart-pix-state="approved"<?php echo $initial_state === 'approved' ? '' : ' hidden'; ?>>
        <div class="mp-smart-pix-status__heading">
            <span class="mp-smart-pix-status__icon mp-smart-pix-status__icon--approved" aria-hidden="true"><img src="<?php echo esc_url($approved_icon); ?>" alt=""></span>
            <p class="mp-smart-pix-status__title"><?php echo esc_html($approved_title); ?></p>
        </div>
        <p class="mp-smart-pix-status__description"><?php echo esc_html($approved_description); ?></p>
    </div>

    <div class="mp-smart-pix-status__card mp-smart-pix-status__card--rejected" data-mp-smart-pix-state="rejected"<?php echo $initial_state === 'rejected' ? '' : ' hidden'; ?>>
        <?php foreach ($rejections as $code => [$title, $description]) : ?>
            <div data-mp-smart-pix-error="<?php echo esc_attr($code); ?>"<?php echo $code === $initial_error ? '' : ' hidden'; ?>>
                <div class="mp-smart-pix-status__heading">
                    <span class="mp-smart-pix-status__icon mp-smart-pix-status__icon--rejected" aria-hidden="true"><img src="<?php echo esc_url($rejected_icon); ?>" alt=""></span>
                    <p class="mp-smart-pix-status__title"><?php echo esc_html($title); ?></p>
                </div>
                <p class="mp-smart-pix-status__description"><?php echo esc_html($description); ?></p>
            </div>
        <?php endforeach; ?>
        <a class="mp-smart-pix-status__action" href="<?php echo esc_url($new_purchase_url); ?>"><?php echo esc_html($new_purchase_label); ?></a>
    </div>

    <div class="mp-smart-pix-status__card mp-smart-pix-status__card--cancelled" data-mp-smart-pix-state="cancelled"<?php echo $initial_state === 'cancelled' ? '' : ' hidden'; ?>>
        <div class="mp-smart-pix-status__heading">
            <span class="mp-smart-pix-status__icon mp-smart-pix-status__icon--rejected" aria-hidden="true"><img src="<?php echo esc_url($rejected_icon); ?>" alt=""></span>
            <p class="mp-smart-pix-status__title"><?php echo esc_html($cancelled_title); ?></p>
        </div>
        <p class="mp-smart-pix-status__description"><?php echo esc_html($cancelled_description); ?></p>
    </div>
    <span class="mp-smart-pix-status__announcement" role="status" aria-live="polite" aria-atomic="true"></span>
</div>
<?php
if ($initial_state === 'pending') {
    // Static presentation only: no order credentials, requests, metrics or DOM relocation.
    // WordPress's helper preserves the store's inline-script attribute/CSP nonce filters.
    wp_print_inline_script_tag(<<<'JS'
(function () {
    const root = document.currentScript?.previousElementSibling;
    if (!root?.matches('[data-mp-smart-pix-status]')) return;
    const fill = root.querySelector('.mp-smart-pix-status__progress-fill');
    const startedAt = Date.now();
    root.setAttribute('data-mp-smart-pix-presented-at', String(startedAt));
    if (!fill || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
        || getComputedStyle(root).getPropertyValue('--mp-smart-pix-motion').trim() === 'reduce') return;
    const initialWidth = Number.parseFloat(fill.style.width) || 0;
    function advance() {
        if (root.hasAttribute('data-initialized')) return;
        const width = Math.min(90, initialWidth + Math.max(0, Date.now() - startedAt) * 90 / 30000);
        fill.style.width = width + '%';
        if (width < 90) window.setTimeout(advance, 100);
    }
    advance();
})();
JS
    , ['id' => 'mp-smart-pix-initial-progress']);
}
