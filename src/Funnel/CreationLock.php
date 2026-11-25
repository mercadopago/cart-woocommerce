<?php

namespace MercadoPago\Woocommerce\Funnel;

if (!defined('ABSPATH')) {
    exit;
}

class CreationLock
{
    public const OPTION_NAME = '_mp_funnel_creation_lock';

    public const LEASE_SECONDS = 120;

    /** @var \wpdb|null */
    private $database;

    public function __construct($database = null)
    {
        $this->database = $database;
    }

    public function acquire(): ?string
    {
        $database = $this->database();
        $owner = (time() + self::LEASE_SECONDS) . ':' . bin2hex(random_bytes(16));
        $previous = $database->suppress_errors(true);
        try {
            // A plain INSERT relies on option_name's unique index, without upserting another owner.
            $inserted = $database->insert(
                $database->options,
                ['option_name' => self::OPTION_NAME, 'option_value' => $owner, 'autoload' => 'no'],
                ['%s', '%s', '%s']
            );
            if ($inserted === 1) {
                return $owner;
            }

            $existing = $database->get_var($database->prepare(
                "SELECT option_value FROM {$database->options} WHERE option_name = %s",
                self::OPTION_NAME
            ));
            if (!is_string($existing)) {
                throw new \RuntimeException('Unable to acquire onboarding creation lease.');
            }
            if ((int) strtok($existing, ':') > time()) {
                return null;
            }

            $replaced = $database->update(
                $database->options,
                ['option_value' => $owner],
                ['option_name' => self::OPTION_NAME, 'option_value' => $existing],
                ['%s'],
                ['%s', '%s']
            );
            if ($replaced === false) {
                throw new \RuntimeException('Unable to recover onboarding creation lease.');
            }

            return $replaced === 1 ? $owner : null;
        } finally {
            $database->suppress_errors($previous);
        }
    }

    public function owns(string $owner): bool
    {
        $database = $this->database();

        // Compare with the PHP clock that wrote the expiry; the database clock may drift from it.
        return $database->get_var($database->prepare(
            "SELECT option_value FROM {$database->options} WHERE option_name = %s
                AND option_value = %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) > %d",
            self::OPTION_NAME,
            $owner,
            time()
        )) === $owner;
    }

    public function release(string $owner): void
    {
        $database = $this->database();
        if ($database->delete(
            $database->options,
            ['option_name' => self::OPTION_NAME, 'option_value' => $owner],
            ['%s', '%s']
        ) === false) {
            throw new \RuntimeException('Unable to release onboarding creation lease.');
        }
    }

    /** @return \wpdb */
    private function database()
    {
        return $this->database ?? $GLOBALS['wpdb'];
    }
}
