<?php

namespace MercadoPago\CartWoocommerce\Notices;

class WordpressNotices
{
    private static function admin_notice($message, $type, $dismiss)
    {
        add_action(
            'admin_notices',
            function () use ($message, $type, $dismiss) {
                ?>
                    <div class="notice <?= $type ?> <?= $dismiss ?>">
                        <p><?= $message ?></p>
                    </div>
                <?php
            }
        );
    }

    public static function admin_notice_info($message, $dismiss = true)
    {
        self::admin_notice($message, 'notice-info', $dismiss);
    }

    public static function admin_notice_success($message, $dismiss = true)
    {
        self::admin_notice($message, 'notice-success', $dismiss);
    }

    public static function admin_notice_warning($message, $dismiss = true)
    {
        self::admin_notice($message, 'notice-warning', $dismiss);
    }

    public static function admin_notice_error($message, $dismiss = true)
    {
        self::admin_notice($message, 'notice-error', $dismiss);
    }
}
