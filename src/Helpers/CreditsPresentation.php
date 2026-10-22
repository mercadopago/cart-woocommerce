<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class CreditsPresentation
{
    private const DEFAULT_TITLES = [
        'Payments without a Mercado Pago card',
        'Checkout without card',
        'Pagos sin Tarjeta de Mercado Pago',
        'Hasta 12 pagos sin tarjeta con Mercado Pago',
        'Linha de Crédito Mercado Crédito',
        'Parcelado sem cartão com Mercado Pago',
    ];

    public static function productName(): string
    {
        return _x('Buy now, pay later', 'credits product name', 'woocommerce-mercadopago');
    }

    public static function resolveTitle(string $title, string $localizedDefault): string
    {
        if ($title !== '' && !in_array($title, self::DEFAULT_TITLES, true)) {
            return $title;
        }

        return $localizedDefault;
    }

    public static function formatCopy(array $copy, string $productName, string $promotionName, string $alternativeName): array
    {
        $replacements = [
            '{credits_name}' => $productName,
            '{credits_promotion_name}' => $promotionName,
            '{credits_alternative_name}' => $alternativeName,
        ];
        foreach ($copy as $key => $text) {
            if (is_string($text)) {
                $copy[$key] = strtr($text, $replacements);
            }
        }

        return $copy;
    }
}
