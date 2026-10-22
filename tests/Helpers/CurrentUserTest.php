<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use WP_Mock;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;

class CurrentUserTest extends TestCase
{
    private CurrentUser $currentUser;

    /** @var LegacyMockInterface|MockInterface */
    private $logs;

    /** @var LegacyMockInterface|MockInterface */
    private $file;

    /** @var LegacyMockInterface|MockInterface */
    private $store;

    public function setUp(): void
    {
        WP_Mock::setUp();

        $this->file  = Mockery::mock(File::class);
        $this->logs  = Mockery::mock(Logs::class);
        $this->logs->file = $this->file;

        $this->store = Mockery::mock(Store::class);
        $this->store->shouldReceive('getDebugMode')->andReturn('yes');

        $this->currentUser = new CurrentUser($this->logs, $this->store);
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /**
     * A user holding the manage_woocommerce capability (administrator, shop_manager
     * or any custom role granted the capability) passes the guard without a 403.
     * All of them resolve to the same code path: current_user_can('manage_woocommerce') === true.
     */
    public function testValidateUserNeededPermissionsAllowsUserWithManageWoocommerceCapability(): void
    {
        WP_Mock::userFunction('current_user_can')
            ->once()
            ->with('manage_woocommerce')
            ->andReturn(true);

        $this->file->shouldNotReceive('error');
        WP_Mock::userFunction('wp_send_json_error')->never();

        $this->currentUser->validateUserNeededPermissions();

        $this->addToAssertionCount(1);
    }

    /**
     * A user without the manage_woocommerce capability (e.g. editor) is blocked with
     * a 403 Forbidden and the denial is logged.
     */
    public function testValidateUserNeededPermissionsBlocksUserWithoutManageWoocommerceCapability(): void
    {
        WP_Mock::userFunction('current_user_can')
            ->once()
            ->with('manage_woocommerce')
            ->andReturn(false);

        $this->file->shouldReceive('error')
            ->once()
            ->with('User does not have permissions', CurrentUser::class);

        WP_Mock::userFunction('wp_send_json_error')
            ->once()
            ->with('Forbidden', 403);

        $this->currentUser->validateUserNeededPermissions();

        $this->addToAssertionCount(1);
    }

    /** @dataProvider userMetadata */
    public function testMetadataUsesTheCurrentUserIdAndSingleFlag(bool $single, $value): void
    {
        $user = Mockery::mock('WP_User');
        $user->ID = 42;
        WP_Mock::userFunction('wp_get_current_user')->once()->andReturn($user);
        WP_Mock::userFunction('get_user_meta')->once()->with(42, 'billing_document', $single)->andReturn($value);

        $result = $single
            ? $this->currentUser->getCurrentUserMeta('billing_document', true)
            : $this->currentUser->getCurrentUserMeta('billing_document');

        $this->assertSame($value, $result);
    }

    public function userMetadata(): array
    {
        return ['default returns collection' => [false, ['document']], 'single returns scalar' => [true, 'document']];
    }

    public function testCurrentUserDataPreservesTheWordPressUserObject(): void
    {
        $user = Mockery::mock('WP_User');
        $user->ID = 42;
        $data = Mockery::mock('WP_User');
        WP_Mock::userFunction('wp_get_current_user')->once()->andReturn($user);
        WP_Mock::userFunction('get_userdata')->once()->with(42)->andReturn($data);

        $this->assertSame($data, $this->currentUser->getCurrentUserData());
    }

    public function testCurrentUserDataPreservesMissingUserResult(): void
    {
        $user = Mockery::mock('WP_User');
        $user->ID = 0;
        WP_Mock::userFunction('wp_get_current_user')->once()->andReturn($user);
        WP_Mock::userFunction('get_userdata')->once()->with(0)->andReturn(false);

        $this->assertFalse($this->currentUser->getCurrentUserData());
    }

    /** @dataProvider loggedInStates */
    public function testLoginStateIsReadFromWordPress(bool $loggedIn): void
    {
        WP_Mock::userFunction('is_user_logged_in')->once()->andReturn($loggedIn);

        $this->assertSame($loggedIn, $this->currentUser->isUserLoggedIn());
    }

    public function loggedInStates(): array
    {
        return ['logged in' => [true], 'guest' => [false]];
    }
}
