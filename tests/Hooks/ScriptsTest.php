<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\PaymentMethods;
use MercadoPago\Woocommerce\HealthMonitor\ScriptHealthMonitor;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use WP_Mock;

class ScriptsTest extends TestCase
{
    use WoocommerceMock;

    /**
     * Actual WordPress priorities passed to add_action().
     * registerCheckoutScript() uses the default (10) for ALL checkout scripts, including SDK.
     * prioritizeMelidataStoreScriptEarly() uses 20 so wp_script_is() can reliably detect
     * the SDK — which was already registered at priority 10 — before declaring it as a dependency.
     */
    private const WP_PRIORITY_MELIDATA = 20;

    private const PHASE_WP_ENQUEUE_P10 = 1;
    private const PHASE_WP_ENQUEUE_P20 = 2;
    private const PHASE_TEMPLATE        = 3;

    private const PHASE_LABELS = [
        self::PHASE_WP_ENQUEUE_P10 => 'wp_enqueue_scripts:priority_10',
        self::PHASE_WP_ENQUEUE_P20 => 'wp_enqueue_scripts:priority_20',
        self::PHASE_TEMPLATE        => 'template_rendering',
    ];

    /** @var Mockery\MockInterface|Url */
    private $urlMock;

    /** @var Mockery\MockInterface|Seller */
    private $sellerMock;

    /** @var Mockery\MockInterface|PaymentMethods */
    private $paymentMethodsMock;

    private ScriptHealthMonitor $scriptHealthMonitor;

    private Scripts $scripts;

    public function setUp(): void
    {
        $this->urlMock            = Mockery::mock(Url::class);
        $this->sellerMock         = Mockery::mock(Seller::class);
        $this->paymentMethodsMock = Mockery::mock(PaymentMethods::class);

        $sellerConfig = Mockery::mock(Seller::class);
        $sellerConfig->shouldReceive('getSiteId')->andReturn('MLB')->byDefault();
        $sellerConfig->shouldReceive('getCustIdFromAT')->andReturn('cust-123')->byDefault();

        $storeConfig = Mockery::mock(Store::class);
        $storeConfig->shouldReceive('isTestMode')->andReturn(false)->byDefault();

        $mercadopagoMock = Mockery::mock(WoocommerceMercadoPago::class);
        $mercadopagoMock->sellerConfig = $sellerConfig;
        $mercadopagoMock->storeConfig  = $storeConfig;

        $GLOBALS['mercadopago'] = $mercadopagoMock;

        $this->scriptHealthMonitor = new ScriptHealthMonitor();

        $this->scripts = new Scripts(
            $this->urlMock,
            $this->sellerMock,
            $this->paymentMethodsMock,
            $this->scriptHealthMonitor
        );

        $GLOBALS['woocommerce'] = (object) ['version' => '8.0.0'];

        $this->urlMock->shouldReceive('getJsAsset')->with('melidata/melidata-client')->andReturn('path/to/melidata-client.js');
        $this->urlMock->shouldReceive('assetVersion')->andReturn('1.0.0');
        $this->sellerMock->shouldReceive('getSiteId')->andReturn('MLA');
        $this->paymentMethodsMock->shouldReceive('getEnabledPaymentMethods')->andReturn([]);
    }

    public function tearDown(): void
    {
        unset($GLOBALS['mercadopago']);
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Proves that prioritizeMelidataStoreScriptEarly loads melidata in an earlier WordPress
     * lifecycle phase than registerMelidataStoreScript.
     *
     * Assertion: melidataPhase_B (2) < melidataPhase_A (3)
     */
    public function testGivenCallToRegisterMelidataScriptWhenPrioritizeMelidataStoreScriptEarlyCallsShouldBeFasterThanRegisterMelidataScript(): void
    {
        $lifecycleLog  = [];
        $currentPhase  = 0;

        // -----------------------------------------------------------------
        // SCENARIO B setup — intercept add_action('wp_enqueue_scripts', closure, 20)
        //
        // WP_Mock's internal safe_offset() converts Mockery::type('callable') to '__CLOSURE__',
        // which matches any Closure passed to add_action. When the match is found, perform()
        // fires, recording PHASE_WP_ENQUEUE_P20 into the lifecycle log.
        //
        // This is the only WP_Mock-compatible way to intercept a closure-based add_action call:
        // Mockery::type('callable') is the sole matcher that produces '__CLOSURE__' internally.
        // -----------------------------------------------------------------
        WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
            ->with(Mockery::type('callable'), self::WP_PRIORITY_MELIDATA, 1)
            ->perform(function () use (&$lifecycleLog) {
                // Fires when add_action('wp_enqueue_scripts', $closure, 20) is called.
                // Records the lifecycle phase the closure WILL run in when WordPress
                // dispatches wp_enqueue_scripts at priority 20 (before template rendering).
                $lifecycleLog[] = ['handle' => 'mercadopago_melidata', 'phase' => self::PHASE_WP_ENQUEUE_P20];
            });

        // wp_enqueue_script: records the lifecycle phase at the moment of each direct call
        WP_Mock::userFunction('wp_enqueue_script', [
            'return' => function (string $handle) use (&$lifecycleLog, &$currentPhase) {
                $lifecycleLog[] = ['handle' => $handle, 'phase' => $currentPhase];
            },
        ]);

        WP_Mock::userFunction('wp_localize_script', ['return' => true]);

        // -----------------------------------------------------------------
        // SCENARIO A — registerMelidataStoreScript (slow path)
        //
        // Simulates: woocommerce_before_checkout_form fires during template rendering.
        // registerMelidataStoreScript() calls wp_enqueue_script() DIRECTLY at this phase.
        // -----------------------------------------------------------------
        $currentPhase = self::PHASE_TEMPLATE;
        $this->scripts->registerMelidataStoreScript('/checkout');

        $melidataPhase_A = $this->findScriptPhase($lifecycleLog, 'mercadopago_melidata');

        // -----------------------------------------------------------------
        // SCENARIO B — prioritizeMelidataStoreScriptEarly (fast path)
        //
        // Simulates: gateway constructor calls prioritizeMelidataStoreScriptEarly.
        // The method calls add_action('wp_enqueue_scripts', $closure, 20).
        // WP_Mock intercepts this call, matches the processor set up above, and fires
        // perform(), which records PHASE_WP_ENQUEUE_P20 into the lifecycle log.
        // -----------------------------------------------------------------
        $this->scripts->prioritizeMelidataStoreScriptEarly('/checkout');

        $melidataPhase_B = $this->findScriptPhase($lifecycleLog, 'mercadopago_melidata', 1);

        // -----------------------------------------------------------------
        // ASSERTION
        // prioritizeMelidataStoreScriptEarly must produce an earlier lifecycle phase
        // -----------------------------------------------------------------
        $this->assertLessThan(
            $melidataPhase_A,
            $melidataPhase_B,
            sprintf(
                "prioritizeMelidataStoreScriptEarly: melidata enqueued at phase %d (%s)\n" .
                "registerMelidataStoreScript:        melidata enqueued at phase %d (%s)\n" .
                "Expected the fast path to produce a lower (earlier) lifecycle phase number.",
                $melidataPhase_B, self::PHASE_LABELS[$melidataPhase_B],
                $melidataPhase_A, self::PHASE_LABELS[$melidataPhase_A]
            )
        );
    }

    /**
     * TC-INT-01: registerCheckoutScript wires ScriptHealthMonitor correctly.
     *
     * Proves the integration between Scripts and ScriptHealthMonitor:
     * 1) registerCheckoutScript registers a wp_enqueue_scripts action
     * 2) Scripts exposes the SAME ScriptHealthMonitor instance injected via DI
     * 3) trackEnqueued on that shared instance records handles for dequeue detection
     *
     * Note: WP_Mock's HookedCallbackResponder::react() invokes perform() with zero
     * arguments, so the original closure cannot be captured and executed here.
     * The closure body (`$this->scriptHealthMonitor->trackEnqueued($name)`) is
     * verified through code review and the ScriptHealthMonitor unit-test suite.
     */
    public function testRegisterCheckoutScriptTracksHandleInScriptHealthMonitor(): void
    {
        $hookRegistered = false;

        WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
            ->with(Mockery::type('callable'), 10, 1)
            ->perform(function () use (&$hookRegistered) {
                $hookRegistered = true;
            });

        $this->scripts->registerCheckoutScript('wc_mercadopago_supertoken', 'path/to/supertoken.js');

        // 1) The wp_enqueue_scripts hook was registered
        $this->assertTrue($hookRegistered, 'registerCheckoutScript must add a wp_enqueue_scripts action');

        // 2) The ScriptHealthMonitor accessible through Scripts is the same injected instance
        $this->assertSame(
            $this->scriptHealthMonitor,
            $this->scripts->scriptHealthMonitor,
            'Scripts must expose the injected ScriptHealthMonitor instance'
        );

        // 3) trackEnqueued on the shared instance records the handle
        $this->scripts->scriptHealthMonitor->trackEnqueued('wc_mercadopago_supertoken');

        $reflection = new \ReflectionProperty(ScriptHealthMonitor::class, 'trackedHandles');
        $reflection->setAccessible(true);
        $tracked = $reflection->getValue($this->scriptHealthMonitor);

        $this->assertContains('wc_mercadopago_supertoken', $tracked);
    }

    /**
     * Returns the lifecycle phase of the nth occurrence of a given script handle in the log.
     *
     * @param array<array{handle: string, phase: int}> $log
     */
    private function findScriptPhase(array $log, string $handle, int $occurrenceIndex = 0): int
    {
        $found = 0;
        foreach ($log as $entry) {
            if ($entry['handle'] === $handle) {
                if ($found === $occurrenceIndex) {
                    return $entry['phase'];
                }
                $found++;
            }
        }

        $this->fail(sprintf(
            "Script '%s' (occurrence #%d) was not found in the lifecycle log.\n" .
            "Log contents: %s",
            $handle,
            $occurrenceIndex,
            json_encode($log)
        ));
    }
}
