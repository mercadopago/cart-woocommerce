<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use Exception;
use MercadoPago\Woocommerce\Helpers\Cron;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;
use WP_Mock\Tools\Constraints\ExpectationsMet;

/**
 * WP_Mock's global add_filter API does not expose the registered closure.
 * Isolate the namespaced WordPress spy so it cannot shadow add_filter in other tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CronTest extends TestCase
{
    private Cron $cron;
    private $file;
    private $intervalsCallback;

    public function setUp(): void
    {
        WP_Mock::setUp();
        $this->file = Mockery::mock(File::class);
        $logs = Mockery::mock(Logs::class);
        $logs->file = $this->file;
        WP_Mock::userFunction('MercadoPago\\Woocommerce\\Helpers\\add_filter')
            ->once()
            ->with('cron_schedules', Mockery::type('Closure'))
            ->andReturnUsing(function ($hook, $callback): void {
                $this->intervalsCallback = $callback;
            });

        $this->cron = new Cron($logs);
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testCustomIntervalsPreserveExistingSchedules(): void
    {
        WP_Mock::userFunction('__')->with('Every 5 minutes')->once()->andReturn('Five minutes');
        WP_Mock::userFunction('__')->with('Every 10 minutes')->once()->andReturn('Ten minutes');
        WP_Mock::userFunction('__')->with('Every 15 minutes')->once()->andReturn('Fifteen minutes');
        WP_Mock::userFunction('__')->with('Every 30 minutes')->once()->andReturn('Thirty minutes');
        $daily = ['interval' => 86400, 'display' => 'Once daily'];

        $schedules = ($this->intervalsCallback)(['daily' => $daily]);

        $this->assertSame(['daily', '5minutes', '10minutes', '15minutes', '30minutes'], array_keys($schedules));
        $this->assertSame($daily, $schedules['daily']);
        $expectedIntervals = [
            '5minutes' => ['interval' => 300, 'display' => 'Five minutes'],
            '10minutes' => ['interval' => 600, 'display' => 'Ten minutes'],
            '15minutes' => ['interval' => 900, 'display' => 'Fifteen minutes'],
            '30minutes' => ['interval' => 1800, 'display' => 'Thirty minutes'],
        ];
        foreach ($expectedIntervals as $name => $expected) {
            $this->assertSame($expected['interval'], $schedules[$name]['interval']);
            $this->assertSame($expected['display'], $schedules[$name]['display']);
        }
    }

    public function testRegisterDelegatesCurrentTimestampRecurrenceAndHook(): void
    {
        $received = null;
        WP_Mock::userFunction('wp_schedule_event')
            ->once()
            ->andReturnUsing(function ($timestamp, $recurrence, $hook) use (&$received): void {
                $received = [$timestamp, $recurrence, $hook];
            });
        $before = time();

        $this->cron->registerScheduledEvent('hourly', 'mp_sync');

        $this->assertNotNull($received);
        $this->assertIsInt($received[0]);
        $this->assertGreaterThanOrEqual($before, $received[0]);
        $this->assertLessThanOrEqual(time(), $received[0]);
        $this->assertSame(['hourly', 'mp_sync'], array_slice($received, 1));
    }

    public function testUnregisterDelegatesTheScheduledTimestamp(): void
    {
        WP_Mock::userFunction('wp_next_scheduled')->once()->with('mp_sync')->andReturn(1720000000);
        WP_Mock::userFunction('wp_unschedule_event')->once()->with(1720000000, 'mp_sync');

        $this->cron->unregisterScheduledEvent('mp_sync');

        $this->assertThat(null, new ExpectationsMet());
    }

    public function testUnregisterDoesNothingWhenHookIsNotScheduled(): void
    {
        WP_Mock::userFunction('wp_next_scheduled')->once()->with('mp_sync')->andReturn(false);
        WP_Mock::userFunction('wp_unschedule_event')->never();
        $this->file->shouldNotReceive('error');

        $this->cron->unregisterScheduledEvent('mp_sync');

        $this->assertThat(null, new ExpectationsMet());
    }

    public function testRegisterLogsSchedulerExceptionWithoutPropagatingIt(): void
    {
        WP_Mock::userFunction('wp_schedule_event')
            ->once()
            ->with(Mockery::type('int'), 'hourly', 'mp_sync')
            ->andThrow(new Exception('Scheduler unavailable'));
        $this->file->shouldReceive('error')->once()
            ->with('Unable to register event mp_sync, got error: Scheduler unavailable', Cron::class);

        $this->cron->registerScheduledEvent('hourly', 'mp_sync');

        $this->assertThat(null, new ExpectationsMet());
    }

    public function testUnregisterLogsWordPressExceptionWithoutPropagatingIt(): void
    {
        WP_Mock::userFunction('wp_next_scheduled')->once()->with('mp_sync')->andReturn(1720000000);
        WP_Mock::userFunction('wp_unschedule_event')->once()->with(1720000000, 'mp_sync')
            ->andThrow(new Exception('Scheduler unavailable'));
        $this->file->shouldReceive('error')->once()
            ->with('Unable to unregister event mp_sync, got error: Scheduler unavailable', Cron::class);

        $this->cron->unregisterScheduledEvent('mp_sync');

        $this->assertThat(null, new ExpectationsMet());
    }
}
