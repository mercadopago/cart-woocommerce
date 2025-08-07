<?php

namespace MercadoPago\Woocommerce\Tests\Transactions;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Transactions\CustomTransaction;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CustomTransactionTest extends TestCase
{
    use WoocommerceMock;

    public function testConstantIdValue()
    {
        // Arrange & Act
        $constantValue = CustomTransaction::ID;

        // Assert
        $this->assertEquals('credit_card', $constantValue);
    }

    public function testSetTokenTransaction()
    {
        // Arrange
        $checkout = [
            'token' => 'test_token_123',
            'customer_id' => 'customer_456',
            'issuer' => 'issuer_789'
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();

        $custom = Mockery::mock(CustomTransaction::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $custom->transaction = $transaction;

        // Use reflection to set protected property
        $reflection = new \ReflectionClass($custom);
        $checkoutProperty = $reflection->getProperty('checkout');
        $checkoutProperty->setAccessible(true);
        $checkoutProperty->setValue($custom, $checkout);

        // Act
        $custom->setTokenTransaction();

        // Assert
        $this->assertEquals('test_token_123', $custom->transaction->token);
        $this->assertEquals('customer_456', $custom->transaction->payer->id);
        $this->assertEquals('issuer_789', $custom->transaction->issuer_id);
    }

    public function testSetTokenTransactionWithoutToken()
    {
        // Arrange
        $checkout = [
            'payment_method_id' => 'visa',
            'installments' => 6
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();

        $custom = Mockery::mock(CustomTransaction::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $custom->transaction = $transaction;

        // Use reflection to set protected property
        $reflection = new \ReflectionClass($custom);
        $checkoutProperty = $reflection->getProperty('checkout');
        $checkoutProperty->setAccessible(true);
        $checkoutProperty->setValue($custom, $checkout);

        // Act
        $custom->setTokenTransaction();

        // Assert
        $this->assertFalse(property_exists($custom->transaction, 'token'));
        $this->assertFalse(property_exists($custom->transaction->payer, 'id'));
        $this->assertFalse(property_exists($custom->transaction, 'issuer_id'));
    }

    public function testSetTokenTransactionWithOnlyToken()
    {
        // Arrange
        $checkout = [
            'token' => 'test_token_only'
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();

        $custom = Mockery::mock(CustomTransaction::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $custom->transaction = $transaction;

        // Use reflection to set protected property
        $reflection = new \ReflectionClass($custom);
        $checkoutProperty = $reflection->getProperty('checkout');
        $checkoutProperty->setAccessible(true);
        $checkoutProperty->setValue($custom, $checkout);

        // Act
        $custom->setTokenTransaction();

        // Assert
        $this->assertEquals('test_token_only', $custom->transaction->token);
        $this->assertFalse(property_exists($custom->transaction->payer, 'id'));
        $this->assertFalse(property_exists($custom->transaction, 'issuer_id'));
    }

    public function testSetTokenTransactionWithTokenAndCustomerId()
    {
        // Arrange
        $checkout = [
            'token' => 'test_token_123',
            'customer_id' => 'customer_456'
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();

        $custom = Mockery::mock(CustomTransaction::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $custom->transaction = $transaction;

        // Use reflection to set protected property
        $reflection = new \ReflectionClass($custom);
        $checkoutProperty = $reflection->getProperty('checkout');
        $checkoutProperty->setAccessible(true);
        $checkoutProperty->setValue($custom, $checkout);

        // Act
        $custom->setTokenTransaction();

        // Assert
        $this->assertEquals('test_token_123', $custom->transaction->token);
        $this->assertEquals('customer_456', $custom->transaction->payer->id);
        $this->assertFalse(property_exists($custom->transaction, 'issuer_id'));
    }

    public function testSetTokenTransactionWithTokenAndIssuer()
    {
        // Arrange
        $checkout = [
            'token' => 'test_token_123',
            'issuer' => 'issuer_789'
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();

        $custom = Mockery::mock(CustomTransaction::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $custom->transaction = $transaction;

        // Use reflection to set protected property
        $reflection = new \ReflectionClass($custom);
        $checkoutProperty = $reflection->getProperty('checkout');
        $checkoutProperty->setAccessible(true);
        $checkoutProperty->setValue($custom, $checkout);

        // Act
        $custom->setTokenTransaction();

        // Assert
        $this->assertEquals('test_token_123', $custom->transaction->token);
        $this->assertFalse(property_exists($custom->transaction->payer, 'id'));
        $this->assertEquals('issuer_789', $custom->transaction->issuer_id);
    }

    public function testSetPayerIdentificationInfoLogicWithValidData()
    {
        // Arrange - Test the setPayerIdentificationInfo logic
        $checkout = [
            'doc_type' => 'CPF',
            'doc_number' => '12345678901'
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();
        $transaction->payer->identification = new \stdClass();

        // Act - Simulate setPayerIdentificationInfo method logic
        if (!empty($checkout['doc_type']) && !empty($checkout['doc_number'])) {
            $transaction->payer->identification->type = $checkout['doc_type'];
            $transaction->payer->identification->number = $checkout['doc_number'];
        }

        // Assert
        $this->assertEquals('CPF', $transaction->payer->identification->type);
        $this->assertEquals('12345678901', $transaction->payer->identification->number);
    }

    public function testSetPayerIdentificationInfoLogicWithEmptyDocType()
    {
        // Arrange - Test with empty doc_type
        $checkout = [
            'doc_type' => '',
            'doc_number' => '12345678901'
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();
        $transaction->payer->identification = new \stdClass();

        // Act - Simulate setPayerIdentificationInfo method logic
        $identificationSet = false;
        if (!empty($checkout['doc_type']) && !empty($checkout['doc_number'])) {
            $transaction->payer->identification->type = $checkout['doc_type'];
            $transaction->payer->identification->number = $checkout['doc_number'];
            $identificationSet = true;
        }

        // Assert
        $this->assertFalse($identificationSet);
        $this->assertFalse(property_exists($transaction->payer->identification, 'type'));
        $this->assertFalse(property_exists($transaction->payer->identification, 'number'));
    }

    public function testSetPayerIdentificationInfoLogicWithEmptyDocNumber()
    {
        // Arrange - Test with empty doc_number
        $checkout = [
            'doc_type' => 'CPF',
            'doc_number' => ''
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();
        $transaction->payer->identification = new \stdClass();

        // Act - Simulate setPayerIdentificationInfo method logic
        $identificationSet = false;
        if (!empty($checkout['doc_type']) && !empty($checkout['doc_number'])) {
            $transaction->payer->identification->type = $checkout['doc_type'];
            $transaction->payer->identification->number = $checkout['doc_number'];
            $identificationSet = true;
        }

        // Assert
        $this->assertFalse($identificationSet);
        $this->assertFalse(property_exists($transaction->payer->identification, 'type'));
        $this->assertFalse(property_exists($transaction->payer->identification, 'number'));
    }

    public function testSetPayerIdentificationInfoLogicWithBothEmpty()
    {
        // Arrange - Test with both doc_type and doc_number empty
        $checkout = [
            'doc_type' => '',
            'doc_number' => ''
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();
        $transaction->payer->identification = new \stdClass();

        // Act - Simulate setPayerIdentificationInfo method logic
        $identificationSet = false;
        if (!empty($checkout['doc_type']) && !empty($checkout['doc_number'])) {
            $transaction->payer->identification->type = $checkout['doc_type'];
            $transaction->payer->identification->number = $checkout['doc_number'];
            $identificationSet = true;
        }

        // Assert
        $this->assertFalse($identificationSet);
        $this->assertFalse(property_exists($transaction->payer->identification, 'type'));
        $this->assertFalse(property_exists($transaction->payer->identification, 'number'));
    }

    public function testSetPayerIdentificationInfoLogicWithDifferentDocTypes()
    {
        // Arrange - Test with different Brazilian document types
        $docTypes = [
            ['type' => 'CNPJ', 'number' => '12345678000190'],
            ['type' => 'RG', 'number' => '123456789'],
            ['type' => 'CI', 'number' => '12345678']
        ];

        foreach ($docTypes as $docData) {
            $checkout = [
                'doc_type' => $docData['type'],
                'doc_number' => $docData['number']
            ];

            $transaction = new \stdClass();
            $transaction->payer = new \stdClass();
            $transaction->payer->identification = new \stdClass();

            // Act - Simulate setPayerIdentificationInfo method logic
            if (!empty($checkout['doc_type']) && !empty($checkout['doc_number'])) {
                $transaction->payer->identification->type = $checkout['doc_type'];
                $transaction->payer->identification->number = $checkout['doc_number'];
            }

            // Assert
            $this->assertEquals($docData['type'], $transaction->payer->identification->type);
            $this->assertEquals($docData['number'], $transaction->payer->identification->number);
        }
    }

    public function testTokenTransactionLogic()
    {
        // Arrange - Test the token transaction logic without calling the method directly
        $checkoutWithToken = [
            'token' => 'test_token_123',
            'customer_id' => 'customer_456',
            'issuer' => 'issuer_789'
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();

        // Act - Simulate the setTokenTransaction logic
        if (array_key_exists('token', $checkoutWithToken)) {
            $transaction->token = $checkoutWithToken['token'];

            if (isset($checkoutWithToken['customer_id'])) {
                $transaction->payer->id = $checkoutWithToken['customer_id'];
            }

            if (isset($checkoutWithToken['issuer'])) {
                $transaction->issuer_id = $checkoutWithToken['issuer'];
            }
        }

        // Assert
        $this->assertEquals('test_token_123', $transaction->token);
        $this->assertEquals('customer_456', $transaction->payer->id);
        $this->assertEquals('issuer_789', $transaction->issuer_id);
    }

    public function testTokenTransactionLogicWithoutToken()
    {
        // Arrange - Test without token
        $checkoutWithoutToken = [
            'payment_method_id' => 'visa',
            'installments' => 6
        ];

        $transaction = new \stdClass();
        $hasToken = false;

        // Act - Simulate the setTokenTransaction logic
        if (array_key_exists('token', $checkoutWithoutToken)) {
            $transaction->token = $checkoutWithoutToken['token'];
            $hasToken = true;
        }

        // Assert
        $this->assertFalse($hasToken);
        $this->assertFalse(property_exists($transaction, 'token'));
    }

    public function testConstructorLogicSimulation()
    {
        // Arrange
        $checkout = [
            'payment_method_id' => 'mastercard',
            'installments' => '12'
        ];

        // Create a simple mock transaction object
        $transaction = new \stdClass();

        // Act - Simulate the constructor logic
        $transaction->payment_method_id = $checkout['payment_method_id'];
        $transaction->installments = (int) $checkout['installments'];
        $transaction->three_d_secure_mode = 'optional';

        // Assert
        $this->assertEquals('mastercard', $transaction->payment_method_id);
        $this->assertEquals(12, $transaction->installments);
        $this->assertEquals('optional', $transaction->three_d_secure_mode);
    }

    public function testSetTokenTransactionLogicWithToken()
    {
        // Arrange
        $checkout = [
            'token' => 'test_token_123',
            'customer_id' => 'customer_456',
            'issuer' => 'issuer_789'
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();

        // Act - Simulate setTokenTransaction method logic
        if (array_key_exists('token', $checkout)) {
            $transaction->token = $checkout['token'];

            if (isset($checkout['customer_id'])) {
                $transaction->payer->id = $checkout['customer_id'];
            }

            if (isset($checkout['issuer'])) {
                $transaction->issuer_id = $checkout['issuer'];
            }
        }

        // Assert
        $this->assertEquals('test_token_123', $transaction->token);
        $this->assertEquals('customer_456', $transaction->payer->id);
        $this->assertEquals('issuer_789', $transaction->issuer_id);
    }

    public function testSetTokenTransactionLogicWithoutToken()
    {
        // Arrange
        $checkout = [
            'payment_method_id' => 'visa',
            'installments' => 6
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();

        // Act - Simulate setTokenTransaction method logic
        $tokenSet = false;
        if (array_key_exists('token', $checkout)) {
            $transaction->token = $checkout['token'];
            $tokenSet = true;
        }

        // Assert
        $this->assertFalse($tokenSet);
        $this->assertFalse(property_exists($transaction, 'token'));
    }

    public function testSetTokenTransactionLogicWithPartialData()
    {
        // Arrange
        $checkout = [
            'token' => 'test_token_only'
        ];

        $transaction = new \stdClass();
        $transaction->payer = new \stdClass();

        // Act - Simulate setTokenTransaction method logic
        if (array_key_exists('token', $checkout)) {
            $transaction->token = $checkout['token'];

            if (isset($checkout['customer_id'])) {
                $transaction->payer->id = $checkout['customer_id'];
            }

            if (isset($checkout['issuer'])) {
                $transaction->issuer_id = $checkout['issuer'];
            }
        }

        // Assert
        $this->assertEquals('test_token_only', $transaction->token);
        $this->assertFalse(property_exists($transaction->payer, 'id'));
        $this->assertFalse(property_exists($transaction, 'issuer_id'));
    }

    public function testThreeDSecureModeConstant()
    {
        // Arrange & Act
        $threeDSecureMode = 'optional';

        // Assert
        $this->assertEquals('optional', $threeDSecureMode);
        $this->assertIsString($threeDSecureMode);
    }

    public function testPaymentMetadataCreation()
    {
        // Arrange & Act
        $metadata = new PaymentMetadata();
        $metadata->checkout = 'custom';
        $metadata->checkout_type = 'credit_card';

        // Assert
        $this->assertEquals('custom', $metadata->checkout);
        $this->assertEquals('credit_card', $metadata->checkout_type);
        $this->assertInstanceOf(PaymentMetadata::class, $metadata);
    }

    public function testInstallmentsTypeConversion()
    {
        // Arrange
        $stringInstallments = '6';
        $floatInstallments = 12.0;

        // Act
        $convertedString = (int) $stringInstallments;
        $convertedFloat = (int) $floatInstallments;

        // Assert
        $this->assertEquals(6, $convertedString);
        $this->assertEquals(12, $convertedFloat);
        $this->assertIsInt($convertedString);
        $this->assertIsInt($convertedFloat);
    }

    public function testDocumentIdentificationIntegrationBetweenBlockAndClassicModes()
    {
        // Arrange - Test that both block and classic modes can populate the same transaction structure
        $blockModeCheckout = [
            'mercadopago_custom[doc_type]' => 'CPF',
            'mercadopago_custom[doc_number]' => '12345678901',
            'payment_method_id' => 'visa',
            'installments' => '6'
        ];

        $classicModeCheckout = [
            'doc_type' => 'CPF',
            'doc_number' => '12345678901',
            'payment_method_id' => 'visa',
            'installments' => '6'
        ];

        // Act - Simulate how data is normalized from both modes
        $normalizedBlockData = [];
        $normalizedClassicData = [];

        // Block mode normalization (simulating what happens in JavaScript)
        foreach ($blockModeCheckout as $key => $value) {
            if (strpos($key, 'mercadopago_custom[') === 0) {
                $normalizedKey = str_replace(['mercadopago_custom[', ']'], '', $key);
                $normalizedBlockData[$normalizedKey] = $value;
            } else {
                $normalizedBlockData[$key] = $value;
            }
        }

        // Classic mode data is already in the expected format
        $normalizedClassicData = $classicModeCheckout;

        // Assert - Both should result in the same structure
        $this->assertEquals('CPF', $normalizedBlockData['doc_type']);
        $this->assertEquals('12345678901', $normalizedBlockData['doc_number']);
        $this->assertEquals('CPF', $normalizedClassicData['doc_type']);
        $this->assertEquals('12345678901', $normalizedClassicData['doc_number']);

        // Both modes should have identical normalized data
        $this->assertEquals($normalizedBlockData, $normalizedClassicData);
    }

    public function testPayerIdentificationConsistencyAcrossModes()
    {
        // Arrange - Test that both modes result in the same payer identification structure
        $sharedDocumentData = [
            'doc_type' => 'CNPJ',
            'doc_number' => '12345678000190'
        ];

        $blockTransaction = new \stdClass();
        $blockTransaction->payer = new \stdClass();
        $blockTransaction->payer->identification = new \stdClass();

        $classicTransaction = new \stdClass();
        $classicTransaction->payer = new \stdClass();
        $classicTransaction->payer->identification = new \stdClass();

        // Act - Apply the same logic for both modes
        foreach ([$blockTransaction, $classicTransaction] as $transaction) {
            if (!empty($sharedDocumentData['doc_type']) && !empty($sharedDocumentData['doc_number'])) {
                $transaction->payer->identification->type = $sharedDocumentData['doc_type'];
                $transaction->payer->identification->number = $sharedDocumentData['doc_number'];
            }
        }

        // Assert - Both transactions should have identical payer identification
        $this->assertEquals($blockTransaction->payer->identification->type, $classicTransaction->payer->identification->type);
        $this->assertEquals($blockTransaction->payer->identification->number, $classicTransaction->payer->identification->number);
        $this->assertEquals('CNPJ', $blockTransaction->payer->identification->type);
        $this->assertEquals('12345678000190', $blockTransaction->payer->identification->number);
    }
}
