<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Images
{
    /**
     * Get base 64 image
     *
     * @param string $base64
     *
     * @return void
     */
    public function getBase64Image(string $base64): void
    {
        header('Content-type: image/png');

        $image = base64_decode($base64);
        $image = imagecreatefromstring($image);
        $image = imagescale($image, 447);

        imagepng($image);
        imagedestroy($image);

        exit();
    }

    /**
     * Get error image
     *
     * @return void
     */
    public function getErrorImage(): void
    {
        header('Content-type: image/png');

        $image = dirname(__FILE__) . '/../../assets/images/checkouts/pix/qr-code-expired.png';
        $image = imagecreatefrompng($image);
        $image = imagescale($image, 447);

        imagepng($image);
        imagedestroy($image);

        exit();
    }
}
