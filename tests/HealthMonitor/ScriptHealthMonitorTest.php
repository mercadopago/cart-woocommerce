<?php

namespace MercadoPago\Woocommerce\Tests\HealthMonitor;

use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\HealthMonitor\ScriptHealthMonitor;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Libraries\Singleton\Singleton;
use WP_Mock;

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

class ScriptHealthMonitorTest extends TestCase
{
    private ScriptHealthMonitor $monitor;

    private \Mockery\MockInterface $datadogMock;

    public function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->setupMercadoPagoGlobal();
        $this->datadogMock = $this->mockDatadog();
        $this->datadogMock->shouldReceive('sendEvent')->byDefault();
        $this->monitor = new ScriptHealthMonitor();
    }

    public function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        $this->clearDatadogMock();
        unset($GLOBALS['mercadopago']);
        WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // TC-SM-01 — sem handles rastreados: check é ignorado silenciosamente
    // -------------------------------------------------------------------------

    public function testCheckIsSkippedWhenNoHandlesAreTracked(): void
    {
        // WP_Mock strict mode: nenhuma função WP deve ser chamada
        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->invokeCheck();
    }

    // -------------------------------------------------------------------------
    // TC-SM-02 — handle enfileirado e presente: sem métrica
    // -------------------------------------------------------------------------

    public function testNoMetricWhenTrackedHandleIsStillEnqueued(): void
    {
        $this->monitor->trackEnqueued('wc_mercadopago_session_data_register');

        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_script_checked'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('wp_script_is', [
            'args'   => ['wc_mercadopago_session_data_register', 'enqueued'],
            'return' => true,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_script_checked', true, HOUR_IN_SECONDS],
            'times' => 1,
        ]);

        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->invokeCheck();
    }

    // -------------------------------------------------------------------------
    // TC-SM-03 — handle removido: métrica enviada com payload correto
    // -------------------------------------------------------------------------

    public function testMetricSentWhenTrackedHandleWasDequeued(): void
    {
        $handle = 'wc_mercadopago_session_data_register';
        $this->monitor->trackEnqueued($handle);

        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_script_checked'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('wp_script_is', [
            'args'   => [$handle, 'enqueued'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_script_checked', true, HOUR_IN_SECONDS],
            'times' => 1,
        ]);
        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_script_metric_sent'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_script_metric_sent', true, HOUR_IN_SECONDS],
            'times' => 1,
        ]);

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with(
                'mp_script_dequeued_detected',
                'true',
                $handle,
                null,
                [
                    'site_id'     => 'MLB',
                    'environment' => 'homol',
                    'cust_id'     => 'cust-123',
                    'team'        => 'big',
                ]
            );

        $this->invokeCheck();
    }

    // -------------------------------------------------------------------------
    // TC-SM-04 — mix: só o handle removido aparece no payload
    // -------------------------------------------------------------------------

    public function testPayloadContainsOnlyRemovedHandles(): void
    {
        $handleOk     = 'wc_mercadopago_basic';
        $handleOk2    = 'wc_mercadopago_credits';
        $handleRemoved = 'wc_mercadopago_session_data_register';

        $this->monitor->trackEnqueued($handleOk);
        $this->monitor->trackEnqueued($handleOk2);
        $this->monitor->trackEnqueued($handleRemoved);

        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_script_checked'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('wp_script_is', [
            'args'   => [$handleOk, 'enqueued'],
            'return' => true,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('wp_script_is', [
            'args'   => [$handleOk2, 'enqueued'],
            'return' => true,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('wp_script_is', [
            'args'   => [$handleRemoved, 'enqueued'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_script_checked', true, HOUR_IN_SECONDS],
            'times' => 1,
        ]);
        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_script_metric_sent'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_script_metric_sent', true, HOUR_IN_SECONDS],
            'times' => 1,
        ]);

        $capturedDetails = null;
        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with(
                'mp_script_dequeued_detected',
                'true',
                $handleRemoved,
                null,
                Mockery::capture($capturedDetails)
            );

        $this->invokeCheck();

        $this->assertSame('MLB', $capturedDetails['site_id']);
        $this->assertSame('homol', $capturedDetails['environment']);
        $this->assertSame('cust-123', $capturedDetails['cust_id']);
        $this->assertSame('big', $capturedDetails['team']);
    }

    // -------------------------------------------------------------------------
    // TC-SM-05 — rate limit: segundo check dentro da hora não dispara métrica
    // -------------------------------------------------------------------------

    public function testCheckIsSkippedWhenRateLimitTransientIsSet(): void
    {
        $this->monitor->trackEnqueued('wc_mercadopago_session_data_register');

        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_script_checked'],
            'return' => true,
            'times'  => 1,
        ]);

        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->invokeCheck();
    }

    // -------------------------------------------------------------------------
    // TC-SM-06 — idempotência: métrica enviada apenas uma vez
    // -------------------------------------------------------------------------

    public function testMetricNotSentAgainWhenMetricTransientIsAlreadySet(): void
    {
        $handle = 'wc_mercadopago_session_data_register';
        $this->monitor->trackEnqueued($handle);

        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_script_checked'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('wp_script_is', [
            'args'   => [$handle, 'enqueued'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_script_checked', true, HOUR_IN_SECONDS],
            'times' => 1,
        ]);
        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_script_metric_sent'],
            'return' => true,  // already sent
            'times'  => 1,
        ]);

        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->invokeCheck();
    }

    // -------------------------------------------------------------------------
    // TC-SM-07 — trackEnqueued acumula handles corretamente
    // -------------------------------------------------------------------------

    public function testTrackEnqueuedAccumulatesMultipleHandles(): void
    {
        $this->monitor->trackEnqueued('handle-a');
        $this->monitor->trackEnqueued('handle-b');
        $this->monitor->trackEnqueued('handle-c');

        $reflection = new \ReflectionProperty(ScriptHealthMonitor::class, 'trackedHandles');
        $reflection->setAccessible(true);
        $tracked = $reflection->getValue($this->monitor);

        $this->assertSame(['handle-a', 'handle-b', 'handle-c'], $tracked);
    }

    // -------------------------------------------------------------------------
    // TC-SM-08 — resiliência: exceção interna não propaga
    // -------------------------------------------------------------------------

    public function testCheckDoesNotPropagateExceptions(): void
    {
        $this->monitor->trackEnqueued('wc_mercadopago_supertoken');

        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_script_checked'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('wp_script_is', [
            'return' => function () {
                throw new \RuntimeException('Simulated wp_script_is failure');
            },
        ]);

        $this->invokeCheck();
        $this->assertTrue(true, 'check() must silently handle exceptions');
    }

    // -------------------------------------------------------------------------
    // TC-SM-09 — register() adiciona hook wp_enqueue_scripts com prioridade 9999
    // -------------------------------------------------------------------------

    public function testRegisterAddsWpEnqueueScriptsHookWithLatePriority(): void
    {
        WP_Mock::expectActionAdded('wp_enqueue_scripts', WP_Mock\Functions::type('Closure'), 9999);

        $this->monitor->register();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function setupMercadoPagoGlobal(): void
    {
        $sellerConfig = Mockery::mock(\MercadoPago\Woocommerce\Configs\Seller::class);
        $sellerConfig->shouldReceive('getSiteId')->andReturn('MLB')->byDefault();
        $sellerConfig->shouldReceive('getCustIdFromAT')->andReturn('cust-123')->byDefault();

        $storeConfig = Mockery::mock(\MercadoPago\Woocommerce\Configs\Store::class);
        $storeConfig->shouldReceive('isTestMode')->andReturn(true)->byDefault();

        $mock = Mockery::mock(\MercadoPago\Woocommerce\WoocommerceMercadoPago::class);
        $mock->sellerConfig = $sellerConfig;
        $mock->storeConfig  = $storeConfig;

        $GLOBALS['mercadopago'] = $mock;
    }

    private function invokeCheck()
    {
        $reflection = new \ReflectionMethod(ScriptHealthMonitor::class, 'check');
        $reflection->setAccessible(true);
        $reflection->invoke($this->monitor);
    }

    private function mockDatadog(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(Datadog::class);

        $reflection = new \ReflectionProperty(Singleton::class, 'instances');
        $reflection->setAccessible(true);
        $current = $reflection->getValue(null);
        $current[Datadog::class] = $mock;
        $reflection->setValue(null, $current);

        return $mock;
    }

    private function clearDatadogMock(): void
    {
        $reflection = new \ReflectionProperty(Singleton::class, 'instances');
        $reflection->setAccessible(true);
        $current = $reflection->getValue(null);
        unset($current[Datadog::class]);
        $reflection->setValue(null, $current);
    }
}
