<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server-side CPF validation for the existing order billing document (`_billing_cpf`); Smart
 * Transfer collects no document in the checkout. Never log or expose the input or the normalized value.
 */
final class SmartPixCpf
{
    public const TYPE = 'CPF';

    /**
     * Strip only the usual mask characters, then require 11 digits with valid check digits.
     *
     * @param mixed $value
     * @return string|null Digits only, or null when the document is not a valid CPF.
     */
    public static function normalize($value): ?string
    {
        if (!is_string($value) || strlen($value) > 32) {
            return null;
        }
        $digits = preg_replace('/[.\-\s]/', '', $value);
        if (!is_string($digits) || preg_match('/^[0-9]{11}$/D', $digits) !== 1 || preg_match('/^(\d)\1{10}$/D', $digits) === 1) {
            return null;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;
            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $digits[$index] * ($length + 1 - $index);
            }
            $checkDigit = ($sum * 10) % 11 % 10;
            if ($checkDigit !== (int) $digits[$length]) {
                return null;
            }
        }

        return $digits;
    }
}
