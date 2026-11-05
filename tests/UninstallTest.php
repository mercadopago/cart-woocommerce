<?php

namespace MercadoPago\Woocommerce\Tests;

use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    private const CREDENTIAL_OPTIONS = [
        '_mp_access_token_prod',
        '_mp_public_key_prod',
        '_mp_public_key_test',
        '_mp_access_token_test',
    ];

    public function testGivenNoPluginBootstrapWhenUninstallRunsThenTheRealClientsSendAndCredentialsAreRemoved(): void
    {
        $state = $this->runUninstall();

        $this->assertCount(1, $state['requests']);
        $request = $state['requests'][0];
        $this->assertSame(
            'https://api.mercadopago.com/v1/eplatforms/core/identification/seller-configuration/update-integration',
            $request['url']
        );
        $this->assertSame('PUT', $request['args']['method']);
        $this->assertSame(['id' => 'synthetic-installation', 'is_deleted' => true], json_decode($request['args']['body'], true));
        $this->assertSame('synthetic-cpp-token', $request['args']['headers']['x-cpp-token']);
        $this->assertSame(30, $request['args']['timeout']);
        $this->assertSame(0, $request['args']['redirection']);
        $this->assertFalse($state['plugin_loaded']);
        $this->assertFalse($state['woocommerce_loaded']);
        $this->assertFalse($state['plugin_version_defined']);
        $this->assertSame([], $state['logs']);
        $this->assertCredentialsRemoved($state);
    }

    /** @dataProvider failureProvider */
    public function testGivenAnUpstreamFailureWhenUninstallRunsThenCleanupContinuesAndTheLogIsSanitized(string $failure): void
    {
        $state = $this->runUninstall(['failure' => $failure]);

        $this->assertCount(1, $state['requests']);
        $this->assertCount(1, $state['logs']);
        $this->assertSame('Unable to report plugin uninstallation to the integration funnel.', $state['logs'][0]['message']);
        $this->assertSame(['source' => 'woocommerce-mercadopago'], $state['logs'][0]['context']);
        $this->assertCredentialsRemoved($state);
    }

    public static function failureProvider(): array
    {
        return [['http_error'], ['wp_error'], ['exception'], ['error']];
    }

    public function testGivenNoWooCommerceLoggerWhenSendingFailsThenCleanupStillCompletes(): void
    {
        $state = $this->runUninstall(['failure' => 'error', 'logger_available' => false]);

        $this->assertSame([], $state['logs']);
        $this->assertCredentialsRemoved($state);
    }

    public function testGivenTheLoggerAlsoThrowsWhenSendingFailsThenCleanupStillCompletes(): void
    {
        $state = $this->runUninstall(['failure' => 'error', 'logger_fails' => true]);

        $this->assertSame([], $state['logs']);
        $this->assertCredentialsRemoved($state);
    }

    public function testGivenIncompleteInstallationIdentityWhenUninstallRunsThenItOnlyCleansCredentials(): void
    {
        $state = $this->runUninstall(['options' => ['_mp_installation_key' => '']]);

        $this->assertSame([], $state['requests']);
        $this->assertSame(self::CREDENTIAL_OPTIONS, $state['deleted']);
        $this->assertSame([], $state['logs']);
    }

    public function testGivenDirectAccessWhenUninstallRunsThenItDoesNotLoadClientsOrChangeOptions(): void
    {
        $state = $this->runUninstall(['authorized' => false]);

        $this->assertSame([], $state['requests']);
        $this->assertSame([], $state['deleted']);
        $this->assertFalse($state['client_loaded']);
    }

    public function testGivenAMissingAutoloaderWhenUninstallRunsThenCleanupStillCompletes(): void
    {
        $directory = sys_get_temp_dir() . '/mp-uninstall-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory . '/uninstall.php';
        copy(dirname(__DIR__) . '/uninstall.php', $path);

        try {
            $state = $this->runUninstall(['uninstall_path' => $path]);
            $this->assertSame([], $state['requests']);
            $this->assertFalse($state['client_loaded']);
            $this->assertCredentialsRemoved($state);
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    private function assertCredentialsRemoved(array $state): void
    {
        $this->assertSame(self::CREDENTIAL_OPTIONS, $state['deleted']);
        $this->assertSame([
            '_mp_installation_id' => 'synthetic-installation',
            '_mp_installation_key' => 'synthetic-cpp-token',
            'unrelated_option' => 'preserved',
        ], $state['options']);
    }

    private function runUninstall(array $overrides = []): array
    {
        $scenario = array_replace([
            'authorized' => true,
            'failure' => '',
            'logger_available' => true,
            'logger_fails' => false,
            'uninstall_path' => dirname(__DIR__) . '/uninstall.php',
        ], $overrides);
        $scenario['options'] = array_replace([
            '_mp_installation_id' => 'synthetic-installation',
            '_mp_installation_key' => 'synthetic-cpp-token',
            '_mp_access_token_prod' => 'synthetic-payment-credential',
            '_mp_public_key_prod' => 'synthetic-public-key',
            '_mp_public_key_test' => 'synthetic-public-key',
            '_mp_access_token_test' => 'synthetic-payment-credential',
            'unrelated_option' => 'preserved',
        ], $overrides['options'] ?? []);

        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/Mocks/UninstallRuntime.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors);
        $this->assertSame('', $errors);
        $state = json_decode($output, true);
        $this->assertIsArray($state, $output);

        return $state;
    }
}
