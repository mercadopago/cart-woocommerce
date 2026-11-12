<?php

namespace MercadoPago\Woocommerce\Tests\Admin;

use MercadoPago\Woocommerce\Admin\OnboardingNoticeMetrics;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class OnboardingNoticeMetricsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private array $originalPost;
    private array $originalServer;
    private $nonce;
    private $currentUser;
    private $datadog;
    private $callback;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->originalPost = $_POST;
        $this->originalServer = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $this->validInput();

        $this->nonce = Mockery::mock(Nonce::class);
        $this->currentUser = Mockery::mock(CurrentUser::class);
        $this->datadog = Mockery::mock(Datadog::class);
        $endpoints = Mockery::mock(Endpoints::class);
        $endpoints->shouldReceive('registerAjaxEndpoint')->once()
            ->with(OnboardingNoticeMetrics::AJAX_ACTION, Mockery::on(function ($callback) {
                $this->callback = $callback;
                return is_callable($callback);
            }));

        new OnboardingNoticeMetrics($this->nonce, $this->currentUser, $endpoints, $this->datadog);
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;
        $_SERVER = $this->originalServer;
        WP_Mock::tearDown();
    }

    /**
     * @dataProvider eventInputs
     */
    public function testRegisteredCallbackSendsTheAllowedEventsWithServerStoreContext(string $event, ?string $reason): void
    {
        $_POST['event'] = $event;
        if ($reason !== null) {
            $_POST['reason'] = $reason;
        }
        $this->authorizeInput();
        $this->serverContext('https://store.example.test/wordpress', 'BR:SP');
        $details = [
            'team'    => 'big',
            'device'  => 'desktop',
            'site_id' => 'MLB',
        ];
        if ($reason !== null) {
            $details['reason'] = $reason;
        }
        $this->datadog->shouldReceive('sendEvent')->once()->with(
            'mp_woo_onboarding_notice_' . $event,
            Mockery::on(function ($value) {
                return $value === '1';
            }),
            null,
            null,
            $details,
            'https://store.example.test/wordpress',
            '/wordpress/wp-admin/index.php'
        );

        $this->assertJsonResponse(true, ['accepted' => true], 200);
    }

    public static function eventInputs(): array
    {
        return [
            'visible message' => ['view', null],
            'CTA activation' => ['click', null],
            'bootstrap failed' => ['error', 'bootstrap_failed'],
            'native slot missing' => ['error', 'native_slot_missing'],
            'CTA missing' => ['error', 'cta_missing'],
        ];
    }

    public function testStoreUrlDiscardsCredentialsQueryAndFragmentButPreservesPortAndSubdirectory(): void
    {
        $this->authorizeInput();
        $this->serverContext('https://synthetic-user:synthetic-secret@store.example.test:8443/wordpress?temporary=synthetic#settings');
        $this->datadog->shouldReceive('sendEvent')->once()->with(
            'mp_woo_onboarding_notice_view',
            Mockery::on(function ($value) {
                return $value === '1';
            }),
            null,
            null,
            Mockery::type('array'),
            'https://store.example.test:8443/wordpress',
            '/wordpress/wp-admin/index.php'
        );

        $this->assertJsonResponse(true, ['accepted' => true], 200);
    }

    /**
     * @dataProvider countryInputs
     */
    public function testSiteUsesOnlyLocalCountryWithoutCredentialResolution(string $country, string $siteId): void
    {
        $_POST['viewport'] = 'mobile';
        $this->authorizeInput();
        $this->serverContext('https://store.example.test', $country);
        $this->datadog->shouldReceive('sendEvent')->once()->with(
            'mp_woo_onboarding_notice_view',
            Mockery::on(function ($value) {
                return $value === '1';
            }),
            null,
            null,
            [
                'team' => 'big',
                'device' => 'mobile',
                'site_id' => $siteId,
            ],
            'https://store.example.test',
            '/wordpress/wp-admin/index.php'
        );

        $this->assertJsonResponse(true, ['accepted' => true], 200);
    }

    public static function countryInputs(): array
    {
        return [
            'Argentina' => ['AR', 'MLA'],
            'Brazil' => ['BR', 'MLB'],
            'Chile' => ['CL', 'MLC'],
            'Colombia' => ['CO', 'MCO'],
            'Mexico' => ['MX', 'MLM'],
            'Peru' => ['PE', 'MPE'],
            'Uruguay' => ['UY', 'MLU'],
            'not configured' => ['', 'unknown'],
            'unsupported country' => ['US', 'unknown'],
        ];
    }

    /**
     * @dataProvider screenUris
     */
    public function testScreenUsesOnlyCanonicalAdminUriAndPreservesTheInstallationPath(
        string $screen,
        string $suffix,
        string $adminBase,
        string $expectedUri
    ): void {
        $_POST['screen'] = $screen;
        $_SERVER['REQUEST_URI'] = '/unexpected.php?access_token=synthetic-secret#untrusted';
        $this->authorizeInput();
        $this->serverContext('https://store.example.test/wordpress', 'BR', $suffix, $adminBase);
        $this->datadog->shouldReceive('sendEvent')->once()->with(
            'mp_woo_onboarding_notice_view',
            Mockery::on(function ($value) {
                return $value === '1';
            }),
            null,
            null,
            ['team' => 'big', 'device' => 'desktop', 'site_id' => 'MLB'],
            'https://store.example.test/wordpress',
            $expectedUri
        );

        $this->assertJsonResponse(true, ['accepted' => true], 200);
    }

    public static function screenUris(): array
    {
        return [
            'dashboard in WordPress subdirectory' => ['dashboard', 'index.php', 'https://store.example.test/wordpress/wp-admin/', '/wordpress/wp-admin/index.php'],
            'plugins' => ['plugins', 'plugins.php', 'https://store.example.test/wordpress/wp-admin/', '/wordpress/wp-admin/plugins.php'],
            'WooCommerce home' => ['wc_home', 'admin.php?page=wc-admin', 'https://store.example.test/wordpress/wp-admin/', '/wordpress/wp-admin/admin.php?page=wc-admin'],
            'WooCommerce settings' => ['wc_settings', 'admin.php?page=wc-settings', 'https://store.example.test/wordpress/wp-admin/', '/wordpress/wp-admin/admin.php?page=wc-settings'],
            'Mercado Pago settings' => ['mp_settings', 'admin.php?page=mercadopago-settings', 'https://store.example.test/wordpress/wp-admin/', '/wordpress/wp-admin/admin.php?page=mercadopago-settings'],
            'dashboard in root installation' => ['dashboard', 'index.php', 'https://store.example.test/wp-admin/', '/wp-admin/index.php'],
        ];
    }

    /**
     * @dataProvider requestMethods
     */
    public function testNonPostDoesNotAuthorizeReadBodyOrEmit($method): void
    {
        if ($method === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $method;
        }
        $this->currentUser->shouldNotReceive('currentUserCan');
        $this->nonce->shouldNotReceive('isValidNonce');
        $this->datadog->shouldNotReceive('sendEvent');
        WP_Mock::userFunction('sanitize_post')->never();

        $this->assertJsonResponse(false, 'Method not allowed', 405);
    }

    public static function requestMethods(): array
    {
        return [['GET'], ['PUT'], [null], [['POST']]];
    }

    public function testMissingCapabilityDoesNotReadNonceOrEmit(): void
    {
        $this->currentUser->shouldReceive('currentUserCan')->once()->with('manage_woocommerce')->andReturn(false);
        $this->nonce->shouldNotReceive('isValidNonce');
        $this->datadog->shouldNotReceive('sendEvent');
        WP_Mock::userFunction('sanitize_post')->never();

        $this->assertJsonResponse(false, 'Forbidden', 403);
    }

    public function testInvalidNonceCannotEmitAndDoesNotUseTheLoggingValidator(): void
    {
        $this->authorizeInput(false);
        $this->nonce->shouldNotReceive('validateNonce');
        $this->datadog->shouldNotReceive('sendEvent');

        $this->assertJsonResponse(false, 'Forbidden', 403);
    }

    /**
     * @dataProvider invalidInputs
     */
    public function testInvalidAndUnknownFieldsCannotReachTheEmitter(array $changes, ?string $remove): void
    {
        $_POST = array_merge($_POST, $changes);
        if ($remove !== null) {
            unset($_POST[$remove]);
        }
        $this->currentUser->shouldReceive('currentUserCan')->once()->with('manage_woocommerce')->andReturn(true);
        $this->mockWordPressPostBoundary();
        $this->nonce->shouldNotReceive('isValidNonce');
        $this->datadog->shouldNotReceive('sendEvent');

        $this->assertJsonResponse(false, 'Invalid notice event', 400);
    }

    public static function invalidInputs(): array
    {
        return [
            'client URL' => [['url' => 'https://untrusted.example.test'], null],
            'client URI' => [['uri' => '/wp-admin/untrusted.php'], null],
            'client WordPress post ID' => [['ID' => '0'], null],
            'client WordPress post filter' => [['filter' => 'display'], null],
            'client locale' => [['locale' => 'pt_BR'], null],
            'raw exception' => [['message' => 'synthetic upstream exception'], null],
            'other action' => [['action' => 'other_action'], null],
            'unknown event' => [['event' => 'purchase'], null],
            'unknown screen' => [['screen' => 'checkout'], null],
            'unknown viewport' => [['viewport' => 'tablet'], null],
            'unexpected reason for view' => [['reason' => 'bootstrap_failed'], null],
            'missing error reason' => [['event' => 'error'], null],
            'unknown error reason' => [['event' => 'error', 'reason' => 'upstream exception'], null],
            'nested error reason' => [['event' => 'error', 'reason' => ['bootstrap_failed']], null],
            'nested event' => [['event' => ['view']], null],
            'empty nonce' => [['nonce' => ''], null],
            'oversized nonce' => [['nonce' => str_repeat('n', 257)], null],
            'missing event' => [[], 'event'],
            'missing nonce' => [[], 'nonce'],
            'missing screen' => [[], 'screen'],
            'missing viewport' => [[], 'viewport'],
            'missing action' => [[], 'action'],
        ];
    }

    /**
     * @dataProvider invalidStoreUrls
     */
    public function testInvalidStoreUrlReturnsGenericFailureWithoutSending(string $url): void
    {
        $this->authorizeInput();
        $this->mockStoreUrl($url);
        $this->datadog->shouldNotReceive('sendEvent');

        $this->assertJsonResponse(false, 'Notice metric unavailable', 503);
    }

    public static function invalidStoreUrls(): array
    {
        return [['/relative/path'], ['mailto:synthetic@example.test'], ['https:///missing-host']];
    }

    /**
     * @dataProvider transportFailures
     */
    public function testEmitterFailureIsContainedWithoutRecursiveMetrics(string $failureClass): void
    {
        $this->authorizeInput();
        $this->serverContext('https://store.example.test');
        $this->datadog->shouldReceive('sendEvent')->once()->andThrow(new $failureClass('Synthetic transport failure'));

        $this->assertJsonResponse(false, 'Notice metric unavailable', 503);
    }

    public static function transportFailures(): array
    {
        return [
            'exception' => [\RuntimeException::class],
            'PHP error' => [\Error::class],
        ];
    }

    private function validInput(): array
    {
        return [
            'action' => OnboardingNoticeMetrics::AJAX_ACTION,
            'nonce' => 'synthetic-notice-nonce',
            'event' => 'view',
            'screen' => 'dashboard',
            'viewport' => 'desktop',
        ];
    }

    private function authorizeInput(bool $validNonce = true): void
    {
        $this->currentUser->shouldReceive('currentUserCan')->once()->with('manage_woocommerce')->andReturn(true);
        $this->mockWordPressPostBoundary();
        $this->nonce->shouldReceive('isValidNonce')->once()
            ->with(OnboardingNoticeMetrics::NONCE_ACTION, 'synthetic-notice-nonce')->andReturn($validNonce);
    }

    private function mockWordPressPostBoundary(): void
    {
        WP_Mock::userFunction('sanitize_post')->never();
        WP_Mock::userFunction('map_deep')->never();
        $stringCount = count(array_filter($_POST, 'is_string'));
        WP_Mock::userFunction('wp_unslash')->times($stringCount)->andReturnUsing(function (string $value): string {
            return $value;
        });
        WP_Mock::userFunction('sanitize_text_field')->times($stringCount)->andReturnUsing(function (string $value): string {
            return $value;
        });
    }

    private function serverContext(
        string $url,
        string $country = 'BR',
        string $adminSuffix = 'index.php',
        string $adminBase = 'https://store.example.test/wordpress/wp-admin/'
    ): void {
        $this->mockStoreUrl($url);
        WP_Mock::userFunction('get_locale')->never();
        WP_Mock::userFunction('get_option')->once()->with('woocommerce_default_country', '')->andReturn($country);
        $adminUrl = $adminBase . $adminSuffix;
        WP_Mock::userFunction('admin_url')->once()->with($adminSuffix)->andReturn($adminUrl);
        WP_Mock::userFunction('wp_parse_url')->once()->with($adminUrl, PHP_URL_PATH)
            ->andReturn(parse_url($adminUrl, PHP_URL_PATH));
    }

    private function mockStoreUrl(string $url): void
    {
        WP_Mock::userFunction('site_url')->once()->andReturn($url);
        WP_Mock::userFunction('wp_parse_url')->once()->with($url)->andReturn(parse_url($url));
    }

    private function assertJsonResponse(bool $success, $data, int $status): void
    {
        $expected = $success ? 'wp_send_json_success' : 'wp_send_json_error';
        $unexpected = $success ? 'wp_send_json_error' : 'wp_send_json_success';
        WP_Mock::userFunction($unexpected)->never();
        $response = WP_Mock::userFunction($expected)->once();
        if ($success) {
            $response->with($data);
        } else {
            $response->with($data, $status);
        }
        $response->andReturnUsing(function () {
            throw new OnboardingNoticeJsonResponse();
        });

        try {
            call_user_func($this->callback);
            $this->fail('WordPress JSON response must terminate the request.');
        } catch (OnboardingNoticeJsonResponse $response) {
            $this->assertTrue(true, 'Registered callback reached its expected terminal WordPress response.');
        }
    }
}

class OnboardingNoticeJsonResponse extends \RuntimeException
{
}
