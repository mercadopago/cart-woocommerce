<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use Closure;
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
use MercadoPago\Woocommerce\Helpers\Paths;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Scripts;

class NoticesTest extends TestCase
{
    private Notices $notices;
    /** @var LegacyMockInterface|MockInterface */
    private $isAdmin;
    /** @var LegacyMockInterface|MockInterface */
    private $isNetworkAdmin;
    /** @var LegacyMockInterface|MockInterface */
    private $isUserAdmin;
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

        $this->isAdmin = WP_Mock::userFunction('is_admin')->andReturn(false);
        $this->isNetworkAdmin = WP_Mock::userFunction('is_network_admin')->andReturn(false);
        $this->isUserAdmin = WP_Mock::userFunction('is_user_admin')->andReturn(false);
        $this->addAction = WP_Mock::userFunction('add_action');
        $this->scripts = Mockery::mock(Scripts::class);
        $this->adminTranslations = Mockery::mock(AdminTranslations::class);
        $this->url = Mockery::mock(Url::class);
        $this->links = Mockery::mock(Links::class);
        $this->links->shouldReceive('getLinks')->andReturn(['admin_settings_page' => 'https://shop.example.test/wp-admin/admin.php?page=mercadopago-settings']);
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

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    private function useScreen(string $path, string $page = '', ?string $adminScript = null): void
    {
        $this->url->shouldReceive('getCurrentAdminScript')->andReturn($adminScript ?? $path . '.php');
        $this->url->shouldReceive('validateUrl')->andReturnUsing(static function (string $expected) use ($path): bool {
            return $expected === $path;
        });
        $this->url->shouldReceive('validatePage')->andReturnUsing(static function (string $expected) use ($page): bool {
            return $expected === $page;
        });
    }

    /** @dataProvider legacyOnlyAdminContexts */
    public function testNetworkAndUserAdminRetainLegacyClassificationWithoutNativeAssetsOrMarkup(bool $networkAdmin, bool $userAdmin, string $publicKey, string $accessToken): void
    {
        $this->isAdmin->andReturn(true);
        $this->isNetworkAdmin->andReturn($networkAdmin);
        $this->isUserAdmin->andReturn($userAdmin);
        $this->useScreen('plugins');
        $this->currentUser->shouldReceive('currentUserCan')->with('manage_woocommerce')->andReturn(true);
        $this->seller->shouldReceive('getCredentialsPublicKeyProd')->andReturn($publicKey);
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn($accessToken);
        $this->scripts->shouldNotReceive('registerNativeNoticesAdminScript');
        $this->nonce->shouldNotReceive('generateNonce');

        $this->assertFalse($this->notices->shouldShowNativeOnboardingNotice());
        $this->assertFalse($this->notices->registerOnboardingNotice());
        $this->notices->renderNoticesRegion();

        $this->assertSame('plugins', Notices::getNoticeScreen($this->url));
    }

    public function legacyOnlyAdminContexts(): array
    {
        return [
            'network without credentials' => [true, false, '', ''],
            'network with partial credentials' => [true, false, 'synthetic-public-key', ''],
            'network with complete credentials' => [true, false, 'synthetic-public-key', 'synthetic-access-token'],
            'user admin without credentials' => [false, true, '', ''],
            'user admin with partial credentials' => [false, true, 'synthetic-public-key', ''],
            'user admin with complete credentials' => [false, true, 'synthetic-public-key', 'synthetic-access-token'],
        ];
    }

    public function testShouldShowNoticesForSettingsSectionReturnTrue()
    {
        $this->isAdmin->andReturn(true);
        $this->url->shouldReceive('validatePage')->andReturn(true);
        $this->assertTrue($this->notices->shouldShowNoticesForSettingsSection());
    }

    public function testAdminNoticeError()
    {
        WP_Mock::expectActionAdded('admin_notices', Closure::class);

        $this->assertNull($this->notices->adminNoticeError(random()->word()));
    }

    /** @dataProvider noticeScreens */
    public function testMapsOnlySupportedAdminNoticeScreens(string $path, string $page, string $screen): void
    {
        $this->useScreen($path, $page);

        $this->assertSame($screen, Notices::getNoticeScreen($this->url));
    }

    public function noticeScreens(): array
    {
        return [
            'dashboard'   => ['index', '', 'dashboard'],
            'plugins'     => ['plugins', '', 'plugins'],
            'wc home'     => ['admin', 'wc-admin', 'wc_home'],
            'wc settings' => ['admin', 'wc-settings', 'wc_settings'],
            'mp settings' => ['admin', 'mercadopago-settings', 'mp_settings'],
            'other admin' => ['edit', '', ''],
        ];
    }

    /** @dataProvider strictNativeScreens */
    public function testNativeNoticeUsesExactAdminScriptsAndPages(string $script, string $page, string $expected): void
    {
        $this->useScreen('admin', $page, $script);

        $this->assertSame($expected, Notices::getNativeNoticeScreen($this->url));
    }

    public function strictNativeScreens(): array
    {
        return [
            'dashboard script' => ['index.php', '', 'dashboard'],
            'plugins script' => ['plugins.php', '', 'plugins'],
            'wc home page' => ['admin.php', 'wc-admin', 'wc_home'],
            'wc settings page' => ['admin.php', 'wc-settings', 'wc_settings'],
            'mp settings page' => ['admin.php', 'mercadopago-settings', 'mp_settings'],
            'different page' => ['admin.php', 'wc-settings-extra', ''],
            'allowed page on unrelated script' => ['edit.php', 'wc-settings', ''],
            'ajax script' => ['admin-ajax.php', 'mercadopago-settings', ''],
        ];
    }

    /** @dataProvider unrelatedAdminQueryMatches */
    public function testLegacyRemoteUrlMatchesDoNotEnableNativeNotice(string $uri, string $remoteScreen): void
    {
        $previousServer = $_SERVER;
        $previousGet = $_GET;
        $hadPageNow = array_key_exists('pagenow', $GLOBALS);
        $previousPageNow = $GLOBALS['pagenow'] ?? null;
        $_SERVER['REQUEST_URI'] = $uri;
        $_GET = [];
        $GLOBALS['pagenow'] = 'edit.php';
        WP_Mock::userFunction('wp_unslash')->andReturnUsing(static function ($value) {
            return $value;
        });
        WP_Mock::userFunction('sanitize_text_field')->andReturnUsing(static function ($value) {
            return $value;
        });

        try {
            $url = new Url(new Strings());
            $this->assertSame($remoteScreen, Notices::getNoticeScreen($url));
            $this->assertSame('', Notices::getNativeNoticeScreen($url));
        } finally {
            $_SERVER = $previousServer;
            $_GET = $previousGet;
            if ($hadPageNow) {
                $GLOBALS['pagenow'] = $previousPageNow;
            } else {
                unset($GLOBALS['pagenow']);
            }
        }
    }

    public function unrelatedAdminQueryMatches(): array
    {
        return [
            'index in search query' => ['/wp-admin/edit.php?s=index', 'dashboard'],
            'plugins in search query' => ['/wp-admin/edit.php?s=plugins', 'plugins'],
        ];
    }

    /** @dataProvider productionCredentials */
    public function testNativeEligibilityUsesOnlyProductionCredentials(string $publicKey, string $accessToken, bool $eligible): void
    {
        $this->isAdmin->andReturn(true);
        $this->useScreen('index');
        $this->currentUser->shouldReceive('currentUserCan')->with('manage_woocommerce')->andReturn(true);
        $this->seller->shouldReceive('getCredentialsPublicKeyProd')->andReturn($publicKey);
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn($accessToken);
        $this->seller->shouldNotReceive('getCredentialsPublicKey');
        $this->seller->shouldNotReceive('getCredentialsAccessToken');
        $this->seller->shouldNotReceive('getCredentialsPublicKeyTest');
        $this->seller->shouldNotReceive('getCredentialsAccessTokenTest');
        $this->seller->shouldNotReceive('getSiteId');

        $this->assertSame($eligible, $this->notices->shouldShowNativeOnboardingNotice());
    }

    public function productionCredentials(): array
    {
        return [
            'none'           => ['', '', true],
            'only pk'        => ['synthetic-production-pk', '', true],
            'only token'     => ['', 'synthetic-production-token', true],
            'complete pair'  => ['synthetic-production-pk', 'synthetic-production-token', false],
        ];
    }

    public function testNativeNoticeIsNotEligibleWithoutCapability(): void
    {
        $this->isAdmin->andReturn(true);
        $this->useScreen('index');
        $this->currentUser->shouldReceive('currentUserCan')->with('manage_woocommerce')->andReturn(false);
        $this->seller->shouldNotReceive('getCredentialsPublicKeyProd');
        $this->seller->shouldNotReceive('getCredentialsAccessTokenProd');

        $this->assertFalse($this->notices->shouldShowNativeOnboardingNotice());
    }

    public function testNativeNoticeIsNotEligibleOutsideSupportedScreens(): void
    {
        $this->isAdmin->andReturn(true);
        $this->useScreen('edit');
        $this->currentUser->shouldNotReceive('currentUserCan');

        $this->assertFalse($this->notices->shouldShowNativeOnboardingNotice());
    }

    public function testFrontendNeitherDisplaysNorRegistersTheNoticeRegion(): void
    {
        $this->scripts->shouldNotReceive('registerNativeNoticesAdminScript');
        $this->currentUser->shouldNotReceive('currentUserCan');
        WP_Mock::userFunction('wc_get_template')->never();

        $this->assertFalse($this->notices->shouldShowNativeOnboardingNotice());
        $this->assertFalse($this->notices->registerOnboardingNotice());
        $this->notices->renderNoticesRegion();
    }

    public function testRegionRegistrationSucceedsEvenWhenNativeIsNotEligible(): void
    {
        $this->isAdmin->andReturn(true);
        $this->useScreen('index');
        $this->currentUser->shouldReceive('currentUserCan')->with('manage_woocommerce')->andReturn(false);
        $this->nonce->shouldNotReceive('generateNonce');
        $this->scripts->shouldReceive('registerNativeNoticesAdminScript')->once()->with([
            'enabled' => false,
            'ajax_url' => '',
            'nonce' => '',
            'screen' => 'dashboard',
        ]);
        WP_Mock::expectActionAdded('admin_notices', [$this->notices, 'renderNoticesRegion'], 10, 0);

        $this->assertTrue($this->notices->registerOnboardingNotice());
    }

    public function testUnsupportedAdminScreenDoesNotRegisterOrRenderARegion(): void
    {
        $this->isAdmin->andReturn(true);
        $this->useScreen('edit');
        $this->scripts->shouldNotReceive('registerNativeNoticesAdminScript');
        $this->currentUser->shouldNotReceive('currentUserCan');
        WP_Mock::userFunction('wc_get_template')->never();

        $this->assertFalse($this->notices->registerOnboardingNotice());
        $this->notices->renderNoticesRegion();

        $this->addToAssertionCount(1);
    }

    public function testLegacyRemoteOnlyScreenKeepsRegionWithoutEnablingNativeMetrics(): void
    {
        $this->isAdmin->andReturn(true);
        $this->useScreen('index', '', 'edit.php');
        $this->currentUser->shouldNotReceive('currentUserCan');
        $this->nonce->shouldNotReceive('generateNonce');
        $this->scripts->shouldReceive('registerNativeNoticesAdminScript')->once()->with([
            'enabled' => false,
            'ajax_url' => '',
            'nonce' => '',
            'screen' => 'dashboard',
        ]);
        WP_Mock::expectActionAdded('admin_notices', [$this->notices, 'renderNoticesRegion'], 10, 0);

        $this->assertTrue($this->notices->registerOnboardingNotice());
    }

    public function testEligibleNoticeRegistrationPassesOnlyPublicBootstrapParams(): void
    {
        $this->isAdmin->andReturn(true);
        $this->useScreen('admin', 'mercadopago-settings');
        $this->currentUser->shouldReceive('currentUserCan')->with('manage_woocommerce')->andReturn(true);
        $this->seller->shouldReceive('getCredentialsPublicKeyProd')->andReturn('');
        $this->nonce->shouldReceive('generateNonce')->once()->with('mp_native_onboarding_notice_metrics')->andReturn('synthetic-metrics-nonce');
        WP_Mock::userFunction('admin_url')->once()->with('admin-ajax.php')->andReturn('https://shop.example.test/wp-admin/admin-ajax.php');
        $this->scripts->shouldReceive('registerNativeNoticesAdminScript')->once()->with([
            'enabled' => true,
            'ajax_url' => 'https://shop.example.test/wp-admin/admin-ajax.php',
            'nonce' => 'synthetic-metrics-nonce',
            'screen' => 'mp_settings',
        ]);
        WP_Mock::expectActionAdded('admin_notices', [$this->notices, 'renderNoticesRegion'], 10, 0);

        $this->assertTrue($this->notices->registerOnboardingNotice());
    }

    /** @dataProvider dashboardAliases */
    public function testDashboardAliasesRegisterAndRenderTheNativeNoticeWithTheRealUrlHelper(string $uri): void
    {
        if (!defined('MP_PLUGIN_FILE')) {
            define('MP_PLUGIN_FILE', dirname(__DIR__, 2) . '/woocommerce-mercadopago.php');
        }
        $previousServer = $_SERVER;
        $previousGet = $_GET;
        $hadPageNow = array_key_exists('pagenow', $GLOBALS);
        $previousPageNow = $GLOBALS['pagenow'] ?? null;
        $_SERVER['REQUEST_URI'] = $uri;
        $_GET = [];
        $GLOBALS['pagenow'] = 'index.php';
        WP_Mock::userFunction('wp_unslash')->andReturnUsing(static function ($value) {
            return $value;
        });
        WP_Mock::userFunction('sanitize_text_field')->andReturnUsing(static function ($value) {
            return $value;
        });
        WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
        WP_Mock::userFunction('plugins_url')->andReturn('https://shop.example.test/plugin/assets/images/icons/icon-mp.png');

        try {
            $url = new Url(new Strings());
            $notices = new Notices(
                $this->scripts,
                $this->adminTranslations,
                $url,
                $this->links,
                $this->currentUser,
                $this->store,
                $this->nonce,
                $this->endpoints,
                $this->seller
            );
            $this->isAdmin->andReturn(true);
            $this->currentUser->shouldReceive('currentUserCan')->with('manage_woocommerce')->andReturn(true);
            $this->seller->shouldReceive('getCredentialsPublicKeyProd')->andReturn('');
            $this->nonce->shouldReceive('generateNonce')->once()->with('mp_native_onboarding_notice_metrics')->andReturn('synthetic-metrics-nonce');
            WP_Mock::userFunction('admin_url')->once()->with('admin-ajax.php')->andReturn('https://shop.example.test/wp-admin/admin-ajax.php');
            $bootstrap = null;
            $this->scripts->shouldReceive('registerNativeNoticesAdminScript')->once()->andReturnUsing(static function (array $params) use (&$bootstrap): void {
                $bootstrap = $params;
            });
            WP_Mock::expectActionAdded('admin_notices', [$notices, 'renderNoticesRegion'], 10, 0);
            $this->adminTranslations->notices = [
                'native_onboarding_title' => 'Complete your Mercado Pago setup',
                'native_onboarding_message' => 'Link your account to start receiving payments in your store.',
                'native_onboarding_button' => 'Link my account',
            ];
            $arguments = null;
            WP_Mock::userFunction('wc_get_template')->once()->with('/admin/notices/notices-region.php', Mockery::type('array'), '', Paths::templatesPath())->andReturnUsing(static function (string $template, array $args) use (&$arguments): void {
                $arguments = $args;
            });

            $this->assertTrue($notices->registerOnboardingNotice());
            $notices->renderNoticesRegion();

            $this->assertSame([
                'enabled' => true,
                'ajax_url' => 'https://shop.example.test/wp-admin/admin-ajax.php',
                'nonce' => 'synthetic-metrics-nonce',
                'screen' => 'dashboard',
            ], $bootstrap);
            $this->assertTrue($arguments['showNativeNotice']);
            $this->assertSame('Link my account', $arguments['buttonText']);
            $this->assertSame('https://shop.example.test/wp-admin/admin.php?page=mercadopago-settings', $arguments['buttonLink']);
            $this->assertSame($uri === '/wp-admin/' ? '' : 'dashboard', Notices::getNoticeScreen($url));
        } finally {
            $_SERVER = $previousServer;
            $_GET = $previousGet;
            if ($hadPageNow) {
                $GLOBALS['pagenow'] = $previousPageNow;
            } else {
                unset($GLOBALS['pagenow']);
            }
        }
    }

    public function dashboardAliases(): array
    {
        return [
            'dashboard directory' => ['/wp-admin/'],
            'dashboard script' => ['/wp-admin/index.php'],
        ];
    }

    /** @dataProvider nativeRenderingStates */
    public function testRegionRenderPassesEligibilityAndLocalPresentationToTheRealTemplateBoundary(bool $canManage, string $publicKey, string $accessToken, bool $showNative): void
    {
        if (!defined('MP_PLUGIN_FILE')) {
            define('MP_PLUGIN_FILE', dirname(__DIR__, 2) . '/woocommerce-mercadopago.php');
        }
        $this->isAdmin->andReturn(true);
        $this->useScreen('index');
        $this->currentUser->shouldReceive('currentUserCan')->with('manage_woocommerce')->andReturn($canManage);
        $this->seller->shouldReceive('getCredentialsPublicKeyProd')->andReturn($publicKey);
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn($accessToken);
        $this->adminTranslations->notices = [
            'native_onboarding_title' => 'Complete your Mercado Pago setup',
            'native_onboarding_message' => 'Link your account to start receiving payments in your store.',
            'native_onboarding_button' => 'Link my account',
        ];
        $this->url->shouldReceive('getImageAsset')->with('icons/icon-mp')->andReturn('https://shop.example.test/plugin/assets/images/icons/icon-mp.png');
        $arguments = null;
        WP_Mock::userFunction('wc_get_template')->once()->with('/admin/notices/notices-region.php', Mockery::type('array'), '', Paths::templatesPath())->andReturnUsing(static function (string $template, array $args) use (&$arguments): void {
            $arguments = $args;
        });

        $this->notices->renderNoticesRegion();

        $this->assertSame([
            'showNativeNotice' => $showNative,
            'title' => 'Complete your Mercado Pago setup',
            'message' => 'Link your account to start receiving payments in your store.',
            'buttonText' => 'Link my account',
            'buttonLink' => 'https://shop.example.test/wp-admin/admin.php?page=mercadopago-settings',
            'thumbnail' => 'https://shop.example.test/plugin/assets/images/icons/icon-mp.png',
        ], $arguments);
    }

    public function nativeRenderingStates(): array
    {
        return [
            'native visible' => [true, '', '', true],
            'production complete retains remote slot' => [true, 'synthetic-production-pk', 'synthetic-production-token', false],
            'no capability retains remote slot' => [false, '', '', false],
        ];
    }
}
