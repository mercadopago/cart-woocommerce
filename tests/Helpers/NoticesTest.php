<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use WP_Mock;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Helpers\Notices;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Scripts;

class NoticesTest extends TestCase
{
    private Notices $notices;
    /** @var LegacyMockInterface|MockInterface */
    private $isAdmin;
    /** @var LegacyMockInterface|MockInterface */
    private $addAction;
    /** @var LegacyMockInterface|MockInterface */
    private $scripts;
    /** @var LegacyMockInterface|MockInterface */
    private $adminTranslations;
    /** @var LegacyMockInterface|MockInterface */
    private $url;
    /** @var LegacyMockInterface|MockInterface */
    private $links;
    /** @var LegacyMockInterface|MockInterface */
    private $currentUser;
    /** @var LegacyMockInterface|MockInterface */
    private $store;
    /** @var LegacyMockInterface|MockInterface */
    private $nonce;
    /** @var LegacyMockInterface|MockInterface */
    private $endpoints;
    /** @var LegacyMockInterface|MockInterface */
    private $seller;

    public function setUp(): void
    {
        WP_Mock::setUp();

        $this->isAdmin = WP_Mock::userFunction('is_admin');
        $this->addAction = WP_Mock::userFunction('add_action');
        $this->scripts = Mockery::mock(Scripts::class);
        $this->adminTranslations = Mockery::mock(AdminTranslations::class);
        $this->url = Mockery::mock(Url::class);
        $this->links = Mockery::mock(Links::class);
        $this->links->shouldReceive('getLinks');
        $this->currentUser = Mockery::mock(CurrentUser::class);
        $this->store = Mockery::mock(Store::class);
        $this->nonce = Mockery::mock(Nonce::class);
        $this->endpoints = Mockery::mock(Endpoints::class);
        $this->endpoints->shouldReceive('registerAjaxEndpoint');
        $this->seller = Mockery::mock(Seller::class);

        WP_Mock::expectActionAdded('woocommerce_order_status_processing', [new \WP_Mock\Matcher\AnyInstance(Notices::class), 'checkOrderCompleted']);

        $this->notices = new Notices(
            $this->scripts,
            $this->adminTranslations,
            $this->url,
            $this->links,
            $this->currentUser,
            $this->store,
            $this->nonce,
            $this->endpoints,
            $this->seller
        );
    }

    public function testShouldShowNoticesForSettingsSectionReturnTrue()
    {
        $this->isAdmin->andReturn(true);
        $this->url->shouldReceive('validatePage')->andReturn(true);
        $this->assertTrue($this->notices->shouldShowNoticesForSettingsSection());
    }
}
