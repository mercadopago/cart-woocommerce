<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\TicketGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use Mockery;
use WP_Mock;

class TicketGatewayTest extends TestCase
{
    public function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetMLBStatesForAddressFields()
    {
        $gateway = Mockery::mock(TicketGateway::class)->makePartial();

        $result = $gateway->getMLBStatesForAddressFields();
        $this->assertEquals([
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espirito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MS' => 'Mato Grosso do Sul',
            'MT' => 'Mato Grosso',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins',
        ], $result);
    }

    public function testGetPaymentFieldsErrorMessages()
    {
        $gateway = Mockery::mock(TicketGateway::class)->makePartial();
        $expectedMessages = [
            'postalcode_error_empty'     => '1',
            'postalcode_error_partial'   => '2',
            'postalcode_error_invalid'   => '3',
            'state_error_unselected'     => '4',
            'city_error_empty'           => '5',
            'city_error_invalid'         => '6',
            'neighborhood_error_empty'   => '7',
            'neighborhood_error_invalid' => '8',
            'address_error_empty'        => '9',
            'address_error_invalid'      => '10',
            'number_error_empty'         => '11',
            'number_error_invalid'       => '12',
        ];

        $gateway->storeTranslations = [
            'billing_data_postalcode_error_empty'     => '1',
            'billing_data_postalcode_error_partial'   => '2',
            'billing_data_postalcode_error_invalid'   => '3',
            'billing_data_state_error_unselected'     => '4',
            'billing_data_city_error_empty'           => '5',
            'billing_data_city_error_invalid'         => '6',
            'billing_data_neighborhood_error_empty'   => '7',
            'billing_data_neighborhood_error_invalid' => '8',
            'billing_data_address_error_empty'        => '9',
            'billing_data_address_error_invalid'      => '10',
            'billing_data_number_error_empty'         => '11',
            'billing_data_number_error_invalid'       => '12',
        ];

        $result = $gateway->getPaymentFieldsErrorMessages();
        $this->assertEquals($expectedMessages, $result);
    }
}