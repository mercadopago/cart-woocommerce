<?php

namespace MercadoPago\Woocommerce\Funnel;

use MercadoPago\Woocommerce\Api\Core\Models\FunnelRegistration;

if (!defined('ABSPATH')) {
    exit;
}

class InstallationStore
{
    public const OPTION_NAME = '_mp_funnel_identity';

    private const LEGACY_ID = '_mp_installation_id';

    private const LEGACY_KEY = '_mp_installation_key';

    /** @var \wpdb|null */
    private $database;

    public function __construct($database = null)
    {
        $this->database = $database;
    }

    /** @return array{id: string, key: string} */
    public function read(): array
    {
        $options = $this->readOptions();
        if (array_key_exists(self::OPTION_NAME, $options)) {
            $identity = json_decode($options[self::OPTION_NAME], true);
            if (!is_array($identity) || !$this->isComplete($identity)) {
                throw new \RuntimeException('Invalid onboarding installation identity.');
            }

            return ['id' => $identity['id'], 'key' => $identity['key']];
        }

        return [
            'id' => $options[self::LEGACY_ID] ?? '',
            'key' => $options[self::LEGACY_KEY] ?? '',
        ];
    }

    public function save(FunnelRegistration $registration, string $owner): void
    {
        $identity = ['id' => $registration->getId(), 'key' => $registration->getCppToken()];
        $encoded = wp_json_encode($identity);
        if (!is_string($encoded)) {
            throw new \RuntimeException('Unable to encode onboarding installation identity.');
        }
        $database = $this->database();
        $previous = $database->suppress_errors(true);
        try {
            // Publish once, only while the same unexpired owner still holds the lease. The expiry
            // was written with the PHP clock, so compare with it rather than the database clock.
            $inserted = $database->query($database->prepare(
                "INSERT INTO {$database->options} (option_name, option_value, autoload)
                    SELECT %s, %s, 'no' FROM {$database->options}
                    WHERE option_name = %s AND option_value = %s
                        AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) > %d",
                self::OPTION_NAME,
                $encoded,
                CreationLock::OPTION_NAME,
                $owner,
                time()
            ));
            if ($inserted !== 1) {
                throw new \RuntimeException('Unable to persist onboarding installation identity.');
            }
        } finally {
            $database->suppress_errors($previous);
        }
        $this->invalidateCache(self::OPTION_NAME);
        $this->synchronizeLegacy($identity);
    }

    /** @param array{id: string, key: string} $identity */
    public function synchronizeLegacy(array $identity): void
    {
        $database = $this->database();
        $previous = $database->suppress_errors(true);
        try {
            $this->writeLegacy($identity);
        } finally {
            // update_option() errors can contain the secret-bearing SQL statement.
            $database->suppress_errors($previous);
        }
    }

    /** @param array{id: string, key: string} $identity */
    private function writeLegacy(array $identity): void
    {
        $options = $this->readOptions();
        // A legacy-only installation needs no migration or writes during reactivation.
        if (!array_key_exists(self::OPTION_NAME, $options)) {
            return;
        }
        if (!$this->isComplete($identity) || $this->read() !== $identity) {
            throw new \RuntimeException('Unable to synchronize onboarding installation identity.');
        }
        foreach ([self::LEGACY_ID => $identity['id'], self::LEGACY_KEY => $identity['key']] as $name => $value) {
            if (($options[$name] ?? null) !== $value) {
                $this->invalidateCache($name);
                update_option($name, $value, false);
            }
        }
        $saved = $this->readOptions();
        if (($saved[self::LEGACY_ID] ?? '') !== $identity['id'] || ($saved[self::LEGACY_KEY] ?? '') !== $identity['key']) {
            throw new \RuntimeException('Unable to synchronize onboarding installation identity.');
        }
    }

    private function isComplete(array $identity): bool
    {
        return isset($identity['id'], $identity['key'])
            && is_string($identity['id']) && $identity['id'] !== ''
            && is_string($identity['key']) && $identity['key'] !== '';
    }

    /** @return array<string, string> */
    private function readOptions(): array
    {
        $database = $this->database();
        // One database snapshot bypasses stale alloptions/notoptions and persistent object caches.
        $rows = $database->get_results($database->prepare(
            "SELECT option_name, option_value FROM {$database->options} WHERE option_name IN (%s, %s, %s)",
            self::OPTION_NAME,
            self::LEGACY_ID,
            self::LEGACY_KEY
        ));
        if (!is_array($rows) || $database->last_error !== '') {
            throw new \RuntimeException('Unable to read onboarding installation identity.');
        }
        $options = [];
        foreach ($rows as $row) {
            $options[$row->option_name] = $row->option_value;
        }

        return $options;
    }

    private function invalidateCache(string $name): void
    {
        wp_cache_delete($name, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }

    /** @return \wpdb */
    private function database()
    {
        return $this->database ?? $GLOBALS['wpdb'];
    }
}
