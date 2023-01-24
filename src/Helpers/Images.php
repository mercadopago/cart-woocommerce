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
     * @param string $imageName
     */
    public function getErrorImage(string $imageName): void
    {
        header('Content-type: image/png');

        $errorImage = "{${dirname(__FILE__)}}/../../assets/images/{$imageName}.png";
        $image = imagecreatefrompng($errorImage);
        $image = imagescale($image, 447);
        imagepng($image);
        imagedestroy($image);

        exit();
    }
}
