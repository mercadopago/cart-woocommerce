<?php

use MercadoPago\Woocommerce\Helpers\Template;

if (!defined('ABSPATH')) {
    exit;
}

?>
<div id="mp-admin-notices-region" class="mp-admin-notices-region">
    <div id="mp-jaiminho-notices-slot" class="mp-jaiminho-notices-slot"></div>
    <div id="mp-native-notices-slot" class="mp-native-notices-slot"<?php if (!$showNativeNotice) : ?> hidden<?php endif; ?>>
        <?php if ($showNativeNotice) : ?>
            <?php Template::render('admin/notices/onboarding-notice', [
                'title' => $title,
                'message' => $message,
                'buttonText' => $buttonText,
                'buttonLink' => $buttonLink,
                'thumbnail' => $thumbnail,
            ]); ?>
        <?php endif; ?>
    </div>
</div>
