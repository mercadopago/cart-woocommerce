<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Persistence;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Per-order lease that serializes the status endpoint's Orders query and terminal application,
 * and, under its own prefix, the first report of a terminal observation.
 * `add_option()` is not used because its INSERT ... ON DUPLICATE KEY UPDATE lets two racing
 * callers both report success; a plain insert relies on the unique `option_name` instead.
 */
class SmartPixStatusLock
{
    public const LEASE_SECONDS = 30;

    private const NAME_PREFIX = 'mp_smart_pix_status_lock_';

    /** Distinct from the query lease, which the same request may already hold. */
    public const TERMINAL_OBSERVATION_NAME_PREFIX = 'mp_smart_pix_terminal_lock_';

    /** @var \wpdb */
    private $database;

    private string $namePrefix;

    public function __construct($database = null, string $namePrefix = self::NAME_PREFIX)
    {
        if ($database === null) {
            global $wpdb;
            $database = $wpdb;
        }
        $this->database   = $database;
        $this->namePrefix = $namePrefix;
    }

    /**
     * @return array{name: string, value: string}|null Null means another request owns the lease or the database failed.
     */
    public function acquire(int $orderId): ?array
    {
        $name = $this->namePrefix . $orderId;
        $value = (time() + self::LEASE_SECONDS) . ':' . bin2hex(random_bytes(16));
        $lock = ['name' => $name, 'value' => $value];

        // A duplicate key is the normal outcome when polls overlap: keep wpdb from printing it
        // (WP_DEBUG_DISPLAY) into the AJAX response, as SmartPixCreationStore does.
        $previous = $this->database->suppress_errors(true);
        try {
            $inserted = $this->database->insert(
                $this->database->options,
                ['option_name' => $name, 'option_value' => $value, 'autoload' => 'no'],
                ['%s', '%s', '%s']
            );
        } finally {
            $this->database->suppress_errors($previous);
        }
        if ($inserted === 1) {
            return $lock;
        }

        $existing = $this->database->get_var($this->database->prepare(
            "SELECT option_value FROM {$this->database->options} WHERE option_name = %s",
            $name
        ));
        if (!is_string($existing) || (int) strtok($existing, ':') > time()) {
            return null;
        }

        // Replace only the exact expired value so a contender that already took it over keeps it.
        $replaced = $this->database->update(
            $this->database->options,
            ['option_value' => $value],
            ['option_name' => $name, 'option_value' => $existing],
            ['%s'],
            ['%s', '%s']
        );

        return $replaced === 1 ? $lock : null;
    }

    /** @param array{name: string, value: string} $lock */
    public function release(array $lock): bool
    {
        return $this->database->delete(
            $this->database->options,
            ['option_name' => $lock['name'], 'option_value' => $lock['value']],
            ['%s', '%s']
        ) === 1;
    }
}
