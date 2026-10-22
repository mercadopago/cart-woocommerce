<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\CreditsPresentation;
use PHPUnit\Framework\TestCase;

class CreditsPresentationTest extends TestCase
{
    /**
     * @testWith ["", "Mercado Parcelado"]
     *           ["Checkout without card", "Mercado Parcelado"]
     *           ["Payments without a Mercado Pago card", "Mercado Parcelado"]
     *           ["Parcelado sem cartão com Mercado Pago", "Mercado Parcelado"]
     *           ["Linha de Crédito Mercado Crédito", "Mercado Parcelado"]
     *           ["Hasta 12 pagos sin tarjeta con Mercado Pago", "Mercado Parcelado"]
     *           ["Pagos sin Tarjeta de Mercado Pago", "Mercado Parcelado"]
     *           ["Mercado Crédito", "Mercado Crédito"]
     *           ["Buy now, pay later", "Buy now, pay later"]
     *           ["Pague com meu crédito", "Pague com meu crédito"]
     *           ["Meu Mercado Pago - Parcelado", "Meu Mercado Pago - Parcelado"]
     */
    public function testOnlyKnownDefaultsAreRenamed(string $stored, string $expected): void
    {
        $this->assertSame($expected, CreditsPresentation::resolveTitle($stored, 'Mercado Parcelado'));
    }

    public function testProductNamesTypedInAnotherLanguageRemainCustomTitles(): void
    {
        $this->assertSame('Mercado Parcelado', CreditsPresentation::resolveTitle('Mercado Parcelado', 'Mercado Crédito'));
        $this->assertSame('Mercado Parcelado', CreditsPresentation::resolveTitle('Mercado Parcelado', 'Buy now, pay later'));
        $this->assertSame('Mercado Crédito', CreditsPresentation::resolveTitle('Mercado Crédito', 'Buy now, pay later'));
        $this->assertSame('My installments', CreditsPresentation::resolveTitle('My installments', 'Buy now, pay later'));
        $this->assertSame('0', CreditsPresentation::resolveTitle('0', 'Mercado Parcelado'));
    }

    public function testCopyPreservesMarkupPercentagesAndDifferentSellerVariants(): void
    {
        $copy = [
            'buyer' => '<b>12x</b> com {credits_name}.',
            'seller_default' => 'Até 12x com {credits_promotion_name}. Saiba mais',
            'seller_alternative' => 'Com {credits_alternative_name}, leve agora e pague mensalmente.',
            'guarantee' => '100% protected by Mercado Pago',
        ];
        $actual = CreditsPresentation::formatCopy($copy, 'Mercado Parcelado', 'Crédito de Mercado Parcelado', 'Mercado Parcelado');
        $this->assertSame('<b>12x</b> com Mercado Parcelado.', $actual['buyer']);
        $this->assertSame('Até 12x com Crédito de Mercado Parcelado. Saiba mais', $actual['seller_default']);
        $this->assertSame('Com Mercado Parcelado, leve agora e pague mensalmente.', $actual['seller_alternative']);
        $this->assertSame($copy['guarantee'], $actual['guarantee']);
    }

    public function testCopyPreservesNonStringValues(): void
    {
        $copy = ['optional' => null, 'enabled' => false, 'id' => 3, 'nested' => ['value' => '{credits_name}']];
        $this->assertSame($copy, CreditsPresentation::formatCopy($copy, 'Mercado Crédito', 'Crédito de Mercado Pago', 'Crédito de Mercado Pago'));
    }
}
