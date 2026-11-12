<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Persistence;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use wpdb;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reserves native effects in the current site's options table without executing them.
 * Callers own authorization, trusted attempt linkage, monetary checks and native verification.
 * The applicator uses this port; productive checkout/query/notification wiring is separate.
 */
final class SmartPixEffectOwnership implements SmartPixEffectOwnershipStore
{
    /**
     * A reservation only covers local reads before start(), so an owner that crashed or could not
     * release it left no native effect behind. Once expired, the same attempt and effect may take it over.
     */
    private const RESERVATION_LEASE_SECONDS = 300;

    private wpdb $database;

    private string $table;

    /** @var callable(): int */
    private $clock;

    /** @param (callable(): int)|null $clock Current Unix time; injectable for tests. */
    public function __construct(wpdb $database, ?callable $clock = null)
    {
        $this->database = $database;
        // Bind the site at construction; never reuse this instance after switch_to_blog().
        $this->table = $this->tableName($database->options);
        $this->clock = $clock ?? static fn(): int => time();
    }

    /**
     * The owner is internal coordination material, never browser input or log context.
     * The standard unique option_name index is required. Only an expired reservation of the same
     * attempt and effect can be taken over; executing, completed and uncertain records never are,
     * except that a completed approval may advance to the same attempt's full refund.
     * @return array{outcome: string, owner: string|null}
     */
    public function acquire(int $orderId, string $attemptKey, string $effect): array
    {
        $name = $this->name($orderId, $attemptKey, $effect);
        $owner = bin2hex(random_bytes(32));
        $record = [
            'version' => 2,
            'attempt_hash' => hash('sha256', $attemptKey),
            'effect' => $effect,
            'owner' => $owner,
            'state' => 'reserved',
            'reserved_until' => $this->now() + self::RESERVATION_LEASE_SECONDS,
        ];
        $raw = $this->encode($record);
        // A duplicate is a no-op, never an owner replacement (including CLIENT_FOUND_ROWS).
        $sql = $this->database->prepare(
            "INSERT INTO `{$this->table}` (option_name, option_value, autoload) VALUES (%s, %s, %s)"
            . ' ON DUPLICATE KEY UPDATE option_name = option_name',
            $name,
            $raw,
            'no'
        );
        $written = $this->query($sql);
        if ($written === null) {
            return $this->result('unavailable');
        }
        $stored = $this->read($name);
        if ($stored === null) {
            return $this->result('unavailable');
        }
        if ($written === 1 && hash_equals($raw, $stored['raw'])) {
            $this->invalidateCache($name);
            return $this->result('acquired', $owner);
        }
        $existing = $stored['record'];
        if ($effect === 'record_full_refund' && $existing['effect'] === 'complete_payment'
            && $existing['state'] === 'completed' && $existing['attempt_hash'] === $record['attempt_hash']) {
            // A verified refund follows approval; never replace an executing/uncertain effect.
            $updated = $this->query($this->database->prepare(
                "UPDATE `{$this->table}` SET option_value = %s, autoload = %s"
                . ' WHERE option_name = %s AND BINARY option_value = BINARY %s',
                $raw, 'no', $name, $stored['raw']
            ));
            $verified = $this->read($name);
            if ($updated === 1 && $verified !== null && hash_equals($raw, $verified['raw'])) {
                $this->invalidateCache($name);
                return $this->result('acquired', $owner);
            }
            return $this->result('busy');
        }
        if ($existing['attempt_hash'] !== $record['attempt_hash'] || $existing['effect'] !== $effect) {
            return $this->result('conflict');
        }
        if ($existing['state'] === 'completed' || $existing['state'] === 'uncertain') {
            return $this->result($existing['state']);
        }
        if ($existing['state'] === 'reserved' && $this->isExpired($existing)) {
            return $this->takeOver($name, $stored['raw'], $raw, $owner);
        }
        return $this->result('busy');
    }

    /** Persist execution admission before invoking any native method. */
    public function start(int $orderId, string $attemptKey, string $effect, string $owner): bool
    {
        return $this->transition($orderId, $attemptKey, $effect, $owner, 'reserved', 'executing');
    }

    /** The caller must verify native persistence before recording completion. */
    public function complete(int $orderId, string $attemptKey, string $effect, string $owner): bool
    {
        return $this->transition($orderId, $attemptKey, $effect, $owner, 'executing', 'completed');
    }

    /** A failed native effect or verification must never authorize automatic repetition. */
    public function markUncertain(int $orderId, string $attemptKey, string $effect, string $owner): bool
    {
        return $this->transition($orderId, $attemptKey, $effect, $owner, 'executing', 'uncertain');
    }

    /** Only the matching owner can release a reservation whose execution has not begun. */
    public function releaseReserved(int $orderId, string $attemptKey, string $effect, string $owner): bool
    {
        $name = $this->name($orderId, $attemptKey, $effect);
        $stored = $this->ownedRecord($name, $attemptKey, $effect, $owner, 'reserved');
        if ($stored === null) {
            return false;
        }
        $sql = $this->database->prepare(
            "DELETE FROM `{$this->table}` WHERE option_name = %s AND BINARY option_value = BINARY %s",
            $name,
            $stored['raw']
        );
        if ($this->query($sql) !== 1) {
            return false;
        }
        $this->invalidateCache($name);
        return true;
    }

    private function transition(
        int $orderId,
        string $attemptKey,
        string $effect,
        string $owner,
        string $from,
        string $to
    ): bool {
        $name = $this->name($orderId, $attemptKey, $effect);
        $stored = $this->ownedRecord($name, $attemptKey, $effect, $owner, $from);
        if ($stored === null) {
            return false;
        }
        $record = $stored['record'];
        $record['state'] = $to;
        $raw = $this->encode($record);
        if ($this->query($this->replaceSql($name, $stored['raw'], $raw)) !== 1) {
            return false;
        }
        $this->invalidateCache($name);
        $verified = $this->read($name);
        return $verified !== null && hash_equals($raw, $verified['raw']);
    }

    /**
     * Replace only the exact expired value; a contender that replaced it first keeps ownership.
     * @return array{outcome: string, owner: string|null}
     */
    private function takeOver(string $name, string $expiredRaw, string $raw, string $owner): array
    {
        $written = $this->query($this->replaceSql($name, $expiredRaw, $raw));
        if ($written === null) {
            return $this->result('unavailable');
        }
        if ($written === 1) {
            $this->invalidateCache($name);
        }
        $verified = $this->read($name);
        if ($verified === null) {
            return $this->result('unavailable');
        }
        return $written === 1 && hash_equals($raw, $verified['raw'])
            ? $this->result('acquired', $owner)
            : $this->result('busy');
    }

    private function replaceSql(string $name, string $from, string $to): string
    {
        return $this->database->prepare(
            "UPDATE `{$this->table}` SET option_value = %s, autoload = %s"
            . ' WHERE option_name = %s AND BINARY option_value = BINARY %s',
            $to,
            'no',
            $name,
            $from
        );
    }

    /**
     * Version 1 records predate the lease and never expire.
     * @param array<string, mixed> $record
     */
    private function isExpired(array $record): bool
    {
        return $record['version'] === 2 && $record['reserved_until'] <= $this->now();
    }

    private function now(): int
    {
        return ($this->clock)();
    }

    /** @return array{raw: string, record: array<string, mixed>}|null */
    private function ownedRecord(string $name, string $attemptKey, string $effect, string $owner, string $state): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $owner) !== 1) {
            return null;
        }
        $stored = $this->read($name);
        if ($stored === null) {
            return null;
        }
        $record = $stored['record'];
        return $record['attempt_hash'] === hash('sha256', $attemptKey)
            && $record['effect'] === $effect && $record['state'] === $state
            && hash_equals($record['owner'], $owner) ? $stored : null;
    }

    /** Reads bypass the options cache; malformed or unavailable storage denies admission. */
    private function read(string $name): ?array
    {
        if ($this->database->options !== $this->table) {
            return null;
        }
        $sql = $this->database->prepare("SELECT option_value FROM `{$this->table}` WHERE option_name = %s", $name);
        $previous = $this->database->suppress_errors(true);
        try {
            $raw = $this->database->get_var($sql);
            if ($this->database->last_error !== '' || !is_string($raw) || strlen($raw) > 1024) {
                return null;
            }
        } catch (RuntimeException $exception) {
            return null;
        } finally {
            $this->database->suppress_errors($previous);
        }
        try {
            $record = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return null;
        }
        if (!is_array($record)) {
            return null;
        }
        $keys = array_keys($record);
        sort($keys);
        $legacy = $keys === ['attempt_hash', 'effect', 'owner', 'state', 'version'] && $record['version'] === 1;
        $leased = $keys === ['attempt_hash', 'effect', 'owner', 'reserved_until', 'state', 'version']
            && $record['version'] === 2 && is_int($record['reserved_until']) && $record['reserved_until'] > 0;
        if (
            (!$legacy && !$leased)
            || !is_string($record['attempt_hash']) || preg_match('/^[a-f0-9]{64}$/D', $record['attempt_hash']) !== 1
            || !is_string($record['owner']) || preg_match('/^[a-f0-9]{64}$/D', $record['owner']) !== 1
            || !in_array($record['effect'], ['complete_payment', 'mark_failed', 'mark_cancelled', 'record_full_refund'], true)
            || !in_array($record['state'], ['reserved', 'executing', 'completed', 'uncertain'], true)
        ) {
            return null;
        }
        return ['raw' => $raw, 'record' => $record];
    }

    /** Suppress raw wpdb diagnostics, which can expose the query and ownership material. */
    private function query(string $sql): ?int
    {
        if ($this->database->options !== $this->table) {
            return null;
        }
        $previous = $this->database->suppress_errors(true);
        try {
            $rows = $this->database->query($sql);
            return is_int($rows) && $rows >= 0 && $this->database->last_error === '' ? $rows : null;
        } catch (RuntimeException $exception) {
            return null;
        } finally {
            $this->database->suppress_errors($previous);
        }
    }

    private function name(int $orderId, string $attemptKey, string $effect): string
    {
        if (
            $orderId <= 0 || $attemptKey === '' || strlen($attemptKey) > 256
            || !in_array($effect, ['complete_payment', 'mark_failed', 'mark_cancelled', 'record_full_refund'], true)
        ) {
            throw new InvalidArgumentException('Invalid Smart Pix ownership context.');
        }
        // Order scope prevents a different attempt or intent from bypassing the reservation.
        return '_mp_smart_pix_effect_owner_' . $orderId;
    }

    /** @param mixed $table */
    private function tableName($table): string
    {
        if (!is_string($table) || preg_match('/^[A-Za-z0-9_]+$/D', $table) !== 1) {
            throw new InvalidArgumentException('Invalid Smart Pix ownership storage.');
        }
        return $table;
    }

    /** @param array<string, mixed> $record */
    private function encode(array $record): string
    {
        // Only validated ASCII values are encoded; no provider material is stored.
        return json_encode($record, JSON_THROW_ON_ERROR);
    }

    private function invalidateCache(string $name): void
    {
        wp_cache_delete($name, 'options');
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete('alloptions', 'options');
    }

    /** @return array{outcome: string, owner: string|null} */
    private function result(string $outcome, ?string $owner = null): array
    {
        return ['outcome' => $outcome, 'owner' => $owner];
    }
}
