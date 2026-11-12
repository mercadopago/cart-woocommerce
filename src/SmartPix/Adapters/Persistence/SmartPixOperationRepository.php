<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Persistence;

use RuntimeException;
use WC_Order;
use WC_Order_Refund;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixRefundReconciler;

if (!defined('ABSPATH')) {
    exit;
}

/** Durable operations survive WooCommerce deleting a failed native refund. */
class SmartPixOperationRepository
{
    public const META_KEY = '_mp_smart_pix_operations';

    private SmartPixStatusLock $lock;

    public function __construct(?SmartPixStatusLock $lock = null)
    {
        $this->lock = $lock ?? new SmartPixStatusLock(null, 'mp_smart_pix_operation_lock_');
    }

    /** @param array<string, string> $context @return array<string, string> */
    public function reserve(WC_Order $order, string $identity, array $context): array
    {
        return $this->underLock($order, function () use ($order, $identity, $context): array {
            $records = $this->read($order);
            $id = hash('sha256', $context['attempt_key'] . '|' . $identity);
            $readOnlyRecovery = false;
            if (isset($records[$id])) {
                $record = $records[$id];
                foreach ($context as $field => $value) {
                    if (($record[$field] ?? null) !== $value) {
                        throw new RuntimeException('operation_context_conflict');
                    }
                }
                // An old rejection does not prove whether a provider POST was sent.
                // Recovery may read a terminal state, but must never authorize a new POST.
                $readOnlyRecovery = $context['operation'] === 'cancel' && $record['status'] === 'rejected';
                if ($record['status'] !== 'read_failed' && !$readOnlyRecovery) {
                    return $record + ['dispatch' => 'no'];
                }
            }
            foreach ($records as $record) {
                if (!in_array($record['status'], ['succeeded', 'rejected', 'read_failed', 'reconciled'], true)) {
                    throw new RuntimeException('operation_unresolved');
                }
            }
            if (!isset($records[$id]) && count($records) >= 1000) {
                throw new RuntimeException('operation_limit');
            }
            $record = $context + [
                'id' => $id, 'key' => bin2hex(random_bytes(32)), 'status' => 'dispatching',
            ];
            $records[$id] = $record;
            $this->persist($order, $records);
            return $record + ['dispatch' => $readOnlyRecovery ? 'read_only' : 'yes'];
        });
    }

    /** Resolves only a financial uncertainty covered by the caller's validated full-refund read.
     * It never authorizes provider dispatch or resolves uncertain native effects.
     * @param array<string, string> $context
     */
    public function reconcileConfirmedFullRefund(WC_Order $order, array $context): void
    {
        $total = SmartPixMoney::normalize($context['total'] ?? null);
        if (
            $total === null || $total === '0.00' || ($context['amount'] ?? null) !== $total
            || ($context['operation'] ?? null) !== 'refund' || ($context['currency'] ?? null) !== 'BRL'
        ) {
            throw new RuntimeException('operation_context_conflict');
        }
        $this->underLock($order, function () use ($order, $context, $total): array {
            $records = $this->read($order);
            $unresolved = array_filter($records, static function (array $record): bool {
                return !in_array($record['status'], ['succeeded', 'rejected', 'read_failed', 'reconciled'], true);
            });
            if ($unresolved === []) {
                return [];
            }
            if (count($unresolved) !== 1) {
                throw new RuntimeException('operation_unresolved');
            }
            $record = reset($unresolved);
            $native = SmartPixMoney::normalize((string) $order->get_total_refunded());
            $remaining = $native === null ? null : SmartPixMoney::subtract($total, $native);
            if (
                $record['operation'] !== 'refund' || $record['status'] !== 'uncertain'
                || $record['id'] === hash('sha256', $context['attempt_key'] . '|provider_full_refund')
                // The full-refund read covers any earlier refund amount: an uncertain partial of 30
                // must not block importing a provider full refund of 100 forever.
                || $remaining === null || $remaining === '0.00'
            ) {
                throw new RuntimeException('operation_unresolved');
            }
            foreach (['attempt_key', 'order_reference', 'attempt_reference', 'seller', 'total', 'currency'] as $field) {
                if ($record[$field] !== $context[$field]) {
                    throw new RuntimeException('operation_context_conflict');
                }
            }
            foreach ($order->get_refunds() as $refund) {
                // The failed request may still be unwinding its provisional native refund.
                if (
                    !$refund instanceof WC_Order_Refund || $refund->get_id() <= 0
                    || $record['id'] === hash('sha256', $context['attempt_key'] . '|refund_' . $refund->get_id())
                ) {
                    throw new RuntimeException('operation_unresolved');
                }
            }
            $records[$record['id']]['status'] = 'reconciled';
            $this->persist($order, $records);
            return [];
        });
    }

    /**
     * Resolves an interrupted provider partial-refund import from the durable marker saved on its
     * native refund. Exactly one marked refund holding the whole target proves the import succeeded;
     * no marked refund and a native balance still below the target make it retryable. Any other
     * state stays blocked. It never creates a refund nor authorizes a provider dispatch.
     * The decision uses the record's own target, so a later full-refund read can also resolve it.
     * @param array<string, string> $context Validated partial-import or full-refund context of the current read.
     */
    public function reconcileUncertainPartialImport(WC_Order $order, array $context): void
    {
        if (
            !in_array($context['operation'] ?? null, ['native_partial_refund', 'refund'], true)
            || !isset($context['attempt_key'])
        ) {
            throw new RuntimeException('operation_context_conflict');
        }
        // Cheap pre-check on the caller's freshly loaded order: most deliveries have nothing to
        // recover and must not take the lock. The decision below re-reads under the lock.
        if ($this->uncertainPartialImport($this->read($order, false), $context) === null) {
            return;
        }
        $this->underLock($order, function () use ($order, $context): array {
            $records = $this->read($order);
            $record = $this->uncertainPartialImport($records, $context);
            if ($record === null) {
                return [];
            }
            $native = SmartPixMoney::normalize((string) $order->get_total_refunded());
            if ($native === null) {
                return [];
            }
            $marked = 0;
            foreach ($order->get_refunds() as $refund) {
                if (!$refund instanceof WC_Order_Refund) {
                    return [];
                }
                $marker = $refund->get_meta(SmartPixRefundReconciler::PARTIAL_IMPORT_META, true, 'edit');
                if (
                    is_array($marker) && ($marker['attempt'] ?? null) === hash('sha256', $record['attempt_key'])
                    && ($marker['target'] ?? null) === $record['amount']
                ) {
                    $marked++;
                }
            }
            if ($marked === 1 && $native === $record['amount']) {
                $status = 'succeeded';
            } elseif ($marked === 0 && SmartPixMoney::compare($native, $record['amount']) < 0) {
                // The native refund was rolled back or never persisted: importing again is safe.
                $status = 'read_failed';
            } else {
                return [];
            }
            $records[$record['id']]['status'] = $status;
            $this->persist($order, $records);
            return [];
        });
    }

    /**
     * The only unresolved record, when it is an uncertain partial import of the same attempt.
     * @param array<string, array<string, string>> $records
     * @param array<string, string> $context
     * @return array<string, string>|null
     */
    private function uncertainPartialImport(array $records, array $context): ?array
    {
        $unresolved = array_filter($records, static function (array $record): bool {
            return !in_array($record['status'], ['succeeded', 'rejected', 'read_failed', 'reconciled'], true);
        });
        $record = count($unresolved) === 1 ? reset($unresolved) : null;
        if (
            $record === null || $record['operation'] !== 'native_partial_refund' || $record['status'] !== 'uncertain'
            || SmartPixMoney::normalize($record['amount']) !== $record['amount']
        ) {
            return null;
        }
        foreach (['attempt_key', 'order_reference', 'attempt_reference', 'seller', 'total', 'currency'] as $field) {
            if ($record[$field] !== ($context[$field] ?? null)) {
                return null;
            }
        }
        return $record;
    }

    /** @param array<string, string> $reservation */
    public function finish(WC_Order $order, array $reservation, string $status): void
    {
        if (
            !in_array($status, ['succeeded', 'rejected', 'uncertain', 'read_failed'], true)
            || ($status === 'read_failed' && !in_array($reservation['operation'] ?? null, ['cancel', 'refund', 'native_partial_refund'], true))
        ) {
            throw new RuntimeException('operation_status_invalid');
        }
        $this->underLock($order, function () use ($order, $reservation, $status): array {
            $records = $this->read($order);
            $id = $reservation['id'];
            if (
                !isset($records[$id]) || $records[$id]['key'] !== $reservation['key']
                || $records[$id]['status'] !== 'dispatching'
            ) {
                throw new RuntimeException('operation_ownership_lost');
            }
            $records[$id]['status'] = $status;
            $this->persist($order, $records);
            return [];
        });
    }

    /** @return array<string, array<string, string>> */
    private function read(WC_Order $order, bool $refresh = true): array
    {
        if ($refresh) {
            $order->read_meta_data(true);
        }
        $raw = $order->get_meta(self::META_KEY, true, 'edit');
        if ($raw === '') {
            return [];
        }
        if (!is_string($raw) || strlen($raw) > 1048576) {
            throw new RuntimeException('operation_journal_invalid');
        }
        $records = json_decode($raw, true);
        if (!is_array($records) || count($records) > 1000) {
            throw new RuntimeException('operation_journal_invalid');
        }
        foreach ($records as $id => $record) {
            $expectedFields = ['operation', 'attempt_key', 'order_reference', 'attempt_reference', 'seller', 'total', 'amount', 'currency', 'id', 'key', 'status'];
            $actualFields = is_array($record) ? array_keys($record) : [];
            sort($expectedFields);
            sort($actualFields);
            if (
                !is_string($id) || preg_match('/^[a-f0-9]{64}$/D', $id) !== 1 || !is_array($record)
                || $actualFields !== $expectedFields
                || ($record['id'] ?? null) !== $id || !is_string($record['key'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/D', $record['key']) !== 1
                || !in_array($record['status'] ?? null, ['dispatching', 'succeeded', 'rejected', 'uncertain', 'read_failed', 'reconciled'], true)
                || ($record['status'] === 'read_failed' && !in_array($record['operation'] ?? null, ['cancel', 'refund', 'native_partial_refund'], true))
                || ($record['status'] === 'reconciled' && $record['operation'] !== 'refund')
            ) {
                throw new RuntimeException('operation_journal_invalid');
            }
            foreach ($record as $value) {
                if (!is_string($value)) {
                    throw new RuntimeException('operation_journal_invalid');
                }
            }
        }
        return $records;
    }

    /** @param array<string, array<string, string>> $records */
    private function persist(WC_Order $order, array $records): void
    {
        $encoded = json_encode($records, JSON_THROW_ON_ERROR);
        $order->update_meta_data(self::META_KEY, $encoded);
        // Save only metadata, never a caller's pending status transition.
        $order->save_meta_data();
        $order->read_meta_data(true);
        if ($order->get_meta(self::META_KEY, true, 'edit') !== $encoded) {
            throw new RuntimeException('operation_persistence_failed');
        }
    }

    /** @param callable(): array<string, string> $action @return array<string, string> */
    private function underLock(WC_Order $order, callable $action): array
    {
        $lease = $this->lock->acquire($order->get_id());
        if ($lease === null) {
            throw new RuntimeException('operation_busy');
        }
        try {
            $result = $action();
        } catch (\Throwable $exception) {
            $this->lock->release($lease);
            throw $exception;
        }
        if (!$this->lock->release($lease)) {
            throw new RuntimeException('operation_ownership_lost');
        }
        return $result;
    }
}
