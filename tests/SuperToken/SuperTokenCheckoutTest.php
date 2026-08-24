<?php

namespace MercadoPago\Woocommerce\Tests\SuperToken;

use MercadoPago\Woocommerce\SuperToken\SuperTokenCheckout;
use PHPUnit\Framework\TestCase;

class SuperTokenCheckoutTest extends TestCase
{
    public function testGettersReturnProvidedValues(): void
    {
        $checkout = new SuperTokenCheckout([
            'authorized_pseudotoken' => 'pseudo-1',
            'amount'                 => '100',
            'payment_method_id'      => 'visa',
            'payment_type_id'        => 'credit_card',
            'installments'           => '3',
            'token'                  => 'token-1',
            'super_token_validation' => 'true',
        ]);

        $this->assertSame('pseudo-1', $checkout->getAuthorizedPseudotoken());
        $this->assertSame('100', $checkout->getAmount());
        $this->assertSame('visa', $checkout->getPaymentMethodId());
        $this->assertSame('credit_card', $checkout->getPaymentTypeId());
        $this->assertSame('3', $checkout->getInstallments());
        $this->assertSame('token-1', $checkout->getToken());
        $this->assertSame('true', $checkout->getSuperTokenValidation());
    }

    public function testGettersDefaultWhenKeysAreMissing(): void
    {
        $checkout = new SuperTokenCheckout([]);

        $this->assertNull($checkout->getAuthorizedPseudotoken());
        $this->assertNull($checkout->getAmount());
        $this->assertNull($checkout->getPaymentMethodId());
        $this->assertNull($checkout->getPaymentTypeId());
        $this->assertNull($checkout->getInstallments());
        $this->assertNull($checkout->getToken());
        $this->assertFalse($checkout->getSuperTokenValidation());
    }

    public function testIsCreditCard(): void
    {
        $this->assertTrue((new SuperTokenCheckout(['payment_type_id' => 'credit_card']))->isCreditCard());
        $this->assertFalse((new SuperTokenCheckout(['payment_type_id' => 'debit_card']))->isCreditCard());
        $this->assertFalse((new SuperTokenCheckout([]))->isCreditCard());
    }

    public function testToArrayDefaultsSuperTokenValidationToFalseWhenMissing(): void
    {
        $result = (new SuperTokenCheckout(['payment_method_id' => 'visa']))->toArray();

        $this->assertSame('visa', $result['payment_method_id']);
        $this->assertFalse($result['super_token_validation']);
    }

    public function testToArrayPreservesExistingSuperTokenValidation(): void
    {
        $result = (new SuperTokenCheckout(['super_token_validation' => 'false']))->toArray();

        $this->assertSame('false', $result['super_token_validation']);
    }
}
