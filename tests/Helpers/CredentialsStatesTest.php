<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\I18n;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Helpers\CredentialsStates;

class CredentialsStatesTest extends TestCase
{
    private $credentialsStates;

    private Seller $sellerConfigMock;

    public function setUp(): void
    {
        $this->sellerConfigMock = Mockery::mock(Seller::class);

        $adminTranslationsMock = Mockery::mock(AdminTranslations::class);
        $adminTranslationsMock->credentialsLinkComponents = [
            'initial_title'                         => '',
            'initial_description'                   => '',
            'initial_button'                        => '',
            'linked_title'                          => '',
            'linked_description'                    => '',
            'linked_store_name'                     => '',
            'linked_store_contact'                  => '',
            'linked_account'                        => '',
            'linked_button'                         => '',
            'linked_more_info'                      => '',
            'linked_data'                           => '',
            'failed_title'                          => '',
            'failed_description'                    => '',
            'failed_button'                         => '',
            'update_title'                          => '',
            'update_description'                    => '',
            'update_button'                         => '',
            'link_updated_title'                    => '',
            'link_updated_description'              => '',
            'previously_linked_title'               => '',
            'previously_linked_description'         => '',
            'linked_failed_to_load_store_name'      => '',
            'linked_failed_to_load_store_contact'   => '',
            'could_not_validate_link_title'         => '',
            'could_not_validate_link_description'   => ''
        ];

        $this->sellerConfigMock->shouldReceive('getSiteId')->andReturn('MLB');

        $this->credentialsStates = new CredentialsStates($adminTranslationsMock, $this->sellerConfigMock);
    }

    public function testGetCredentialsLinkedFailedToLoadTemplate()
    {
        $this->sellerConfigMock->shouldReceive('getSellerData')->andReturn([
            'status' => 'error',
            'data' => []
        ]);

        $linkState = 'recently_linked';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals('linked', $response['credentials_state']);
        $this->assertEquals('linked_failed_to_load', $response['type']);
    }

    public function testGetCredentialsRecentlyLinkedTemplate()
    {
        $this->sellerConfigMock->shouldReceive('getSellerData')->andReturn([
            'status' => 'success',
            'data' => [
                'nickname' => 'test_nickname',
                'app_name' => 'test_app_name',
                'email' => 'test_email@example.com'
            ]
        ]);

        $linkState = 'recently_linked';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals([
            "credentials_state" => "linked",
            "type" => "recently_linked",
            "title" => "",
            "description" => "",
            "store_name" => "test_nickname",
            "app_name" => "test_app_name",
            "store_contact" => "test_email@example.com",
            "linked_account" => "",
            "button" => "",
            "more_info" => "",
            "linked_data" => "",
            "current_site_id" => "MLB",
            'period' => '.'
        ], $response);
    }

    public function testGetCredentialsLinkUpdatedTemplate()
    {
        $this->sellerConfigMock->shouldReceive('getSellerData')->andReturn([
            'status' => 'success',
            'data' => [
                'nickname' => 'test_nickname',
                'app_name' => 'test_app_name',
                'email' => 'test_email@example.com'
            ]
        ]);

        $linkState = 'link_updated';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals('linked', $response['credentials_state']);
        $this->assertEquals('link_updated', $response['type']);
    }

    public function testGetCredentialsPreviouslyLinkedTemplate()
    {
        $this->sellerConfigMock->shouldReceive('getSellerData')->andReturn([
            'status' => 'success',
            'data' => [
                'nickname' => 'test_nickname',
                'app_name' => 'test_app_name',
                'email' => 'test_email@example.com'
            ]
        ]);

        $linkState = 'previously_linked';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals('linked', $response['credentials_state']);
        $this->assertEquals('previously_linked', $response['type']);
    }

    public function testGetCredentialsLinkedNoTestCredentialsTemplate()
    {
        $this->sellerConfigMock->shouldReceive('getSellerData')->andReturn([
            'status' => 'success',
            'data' => [
                'nickname' => 'test_nickname',
                'app_name' => 'test_app_name',
                'email' => 'test_email@example.com'
            ]
        ]);

        $linkState = 'linked_no_test_credentials';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals('linked', $response['credentials_state']);
        $this->assertEquals('linked_no_test_credentials', $response['type']);
    }

    public function testGetCredentialsNotLinkedFailedTemplate()
    {
        $linkState = 'not_linked_failed';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals('not_linked', $response['credentials_state']);
        $this->assertEquals('failed', $response['type']);
    }

    public function testGetCredentialsExpiredTemplate()
    {
        $linkState = 'expired';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals('not_linked', $response['credentials_state']);
        $this->assertEquals('update', $response['type']);
    }

    public function testGetCredentialsCouldNotValidateLinkTemplate()
    {
        $linkState = 'could_not_validate_link';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals('not_linked', $response['credentials_state']);
        $this->assertEquals('failed', $response['type']);
    }

    public function testGetCredentialsDefaultTemplate()
    {
        $linkState = 'not_linked';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals('not_linked', $response['credentials_state']);
        $this->assertEquals('initial', $response['type']);
    }

    public function testGetCredentialsInvalidStateTemplate()
    {
        $linkState = 'invalid_state';
        $response = $this->credentialsStates->getCredentialsTemplate($linkState);
        $this->assertEquals('not_linked', $response['credentials_state']);
        $this->assertEquals('initial', $response['type']);
    }
}
