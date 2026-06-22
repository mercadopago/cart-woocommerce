<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server-side checkout form validator.
 *
 * Subclass of WC_Checkout that reuses WooCommerce's official validation pipeline
 * (validate_posted_data + woocommerce_after_checkout_validation hook) without
 * running the gateway's validate_fields() or check_cart_items() (which have side
 * effects). Used by the pre-Super Token validation endpoint to detect form errors
 * before tokenization, independently of CSS classes or DOM state.
 */
class CheckoutValidator extends \WC_Checkout
{
    /**
     * Run WooCommerce's checkout validation against the posted data and return the
     * collected errors. Clears notices at the end so nothing leaks into the real
     * checkout submission.
     *
     * @return \WP_Error
     */
    public function validate(): \WP_Error
    {
        $errors = new \WP_Error();
        $data   = $this->get_posted_data();

        // Note: third-party callbacks on this action may have side effects beyond adding error
        // notices (e.g. writing to the session/cart). They run once here in pre-validation and
        // again in the real WC_Checkout::process_checkout() — i.e. twice per payment attempt.
        // Firing the hook is required to capture errors reported via notices (it does not receive
        // the $errors object), so this is an accepted trade-off, not an oversight.
        do_action('woocommerce_checkout_process');

        $this->validate_posted_data($data, $errors);

        if (
            empty($data['woocommerce_checkout_update_totals'])
            && empty($data['terms'])
            && !empty($data['terms-field'])
        ) {
            $errors->add(
                'terms',
                // Verbatim copy of WC_Checkout::validate_checkout's string; kept under the
                // 'woocommerce' text domain on purpose so it reuses WooCommerce core's own
                // translations instead of relying on the plugin catalog being complete.
                __('Please read and accept the terms and conditions to proceed with your order.', 'woocommerce')
            );
        }

        do_action('woocommerce_after_checkout_validation', $data, $errors);

        // Some third-party callbacks (notably those hooked to woocommerce_checkout_process,
        // which does not receive the $errors object) report failures via WooCommerce error
        // notices instead of WP_Error. Fold those into the result before clearing notices,
        // otherwise they would be silently dropped and the form reported as valid.
        foreach (wc_get_notices('error') as $notice) {
            // Since WooCommerce 3.9 each notice is an array: ['notice' => '...', 'data' => [...]].
            $message = $notice['notice'] ?? '';
            if ($message !== '') {
                $errors->add('checkout_process', $message);
            }
        }

        wc_clear_notices();

        return $errors;
    }
}
