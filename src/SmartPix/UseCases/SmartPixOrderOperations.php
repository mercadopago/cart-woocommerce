<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\UseCases;

use MercadoPago\Woocommerce\Order\OrderMetadata;
use RuntimeException;
use Throwable;
use WC_Order;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixRefundContext;

if (!defined('ABSPATH')) {
    exit;
}

/** WooCommerce integration for merchant-initiated operations, independent of gateway availability. */
class SmartPixOrderOperations
{
    /**
     * An unlinked creation may be cancelled locally only after the provider Order it might have
     * created would have expired (PT15M) plus a grace period for late provider processing.
     */
    public const LOCAL_CANCEL_DELAY_SECONDS = 15 * 60 + 2 * 60;

    private OrderMetadata $metadata;
    private SmartPixMutationProcessor $processor;
    private SmartPixRefundContext $refundContext;

    /** @var callable */
    private $loadOrder;

    private SmartPixNativeEffects $nativeEffects;

    /** @var callable(): int */
    private $clock;

    public function __construct(
        OrderMetadata $metadata,
        SmartPixMutationProcessor $processor,
        SmartPixRefundContext $refundContext,
        ?callable $loadOrder = null,
        ?SmartPixNativeEffects $nativeEffects = null,
        ?callable $clock = null
    ) {
        $this->metadata = $metadata;
        $this->processor = $processor;
        $this->refundContext = $refundContext;
        $this->nativeEffects = $nativeEffects ?? new SmartPixNativeEffects();
        $this->loadOrder = $loadOrder ?? static function (int $id): WC_Order {
            return new WC_Order($id);
        };
        $this->clock = $clock ?? static fn(): int => time();
    }

    public function usesOrders(WC_Order $order): bool
    {
        $order->read_meta_data(true);
        return $this->metadata->getPaymentContract($order) === OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1;
    }

    /** @return array<string, mixed> */
    public function refund(WC_Order $order, float $amount): array
    {
        $context = $this->refundContext->resolve($order, $amount);
        if ($context === null) {
            $this->processor->rejectRefundContext($order);
            throw new RuntimeException('unknown_error');
        }
        $result = $this->processor->refund($order, $context['identity'], $context['amount']);
        if (!$result['success']) {
            throw new RuntimeException('unknown_error');
        }
        return ['status' => 'approved', 'data' => []];
    }

    public function canCancel(WC_Order $order, bool $expiration): bool
    {
        try {
            return $this->processor->cancel($order, $expiration)['success'];
        } catch (Throwable $exception) {
            return false;
        }
    }

    /** Guard merchant status edits before WooCommerce persists cancellation or emits its effects. */
    public function beforeSave(WC_Order $candidate): void
    {
        if ($this->nativeEffects->isApplying($candidate)) {
            return;
        }
        if (
            $candidate->get_id() <= 0 || $candidate->get_status('edit') !== 'cancelled'
            || !array_key_exists('status', $candidate->get_changes())
        ) {
            return;
        }
        try {
            if (!$this->usesOrders($candidate)) {
                return;
            }
        } catch (Throwable $exception) {
            // The native save aborts on read failure but still emits any queued transition.
            $this->nativeEffects->clearTransition($candidate);
            throw $exception;
        }
        $this->nativeEffects->clearTransition($candidate);
        // WooCommerce authorizes its entrypoints before saving this native object. Background
        // cancellation has no logged-in actor; signed-in admin writes require the refund capability.
        $mayOperate = get_current_user_id() === 0 || current_user_can('manage_woocommerce');
        // A separate native object reads persisted facts, not the caller's proposed cancelled status.
        $stored = ($this->loadOrder)($candidate->get_id());
        $previous = $stored->get_status('edit');
        $sameFinancialContext = $candidate->get_total('edit') === $stored->get_total('edit')
            && $candidate->get_currency('edit') === $stored->get_currency('edit')
            && $candidate->get_payment_method('edit') === $stored->get_payment_method('edit');
        $confirmed = $mayOperate && $sameFinancialContext && in_array($previous, ['pending', 'on-hold'], true)
            && ($this->isExpiredUnlinkedCreation($stored) ? $this->cancelLocally($stored) : $this->canCancel($stored, false));
        // The processor owns native terminal effects while its durable reservation is active.
        // Always discard this stale object's queued transition, including on a rejected cancel.
        $this->nativeEffects->refresh($candidate);
        if (!$confirmed && class_exists('WC_Admin_Meta_Boxes')) {
            \WC_Admin_Meta_Boxes::add_error(__('Smart Pix cancellation could not be confirmed. The order status was preserved.', 'woocommerce-mercadopago'));
        }
    }

    public function getRefundContext(): SmartPixRefundContext
    {
        return $this->refundContext;
    }

    /**
     * A creation whose response never linked a PPORD cannot be cancelled in Orders, so the order
     * and its stock would stay blocked. Once any Order it may have created has expired, only a
     * signed-in merchant may cancel it locally; background cancellation keeps preserving it.
     */
    private function isExpiredUnlinkedCreation(WC_Order $stored): bool
    {
        // A background job running with a merchant session is not a merchant decision.
        if (get_current_user_id() === 0 || !current_user_can('manage_woocommerce') || wp_doing_cron()) {
            return false;
        }
        try {
            $reference = $this->metadata->getPpOrderId($stored);
            $startedAt = $this->creationStartedAt($stored);

            return ($reference === '' || $reference === null)
                && SmartPixCreationStore::hasStarted($stored)
                && $startedAt !== null
                && ($this->clock)() >= $startedAt + self::LOCAL_CANCEL_DELAY_SECONDS;
        } catch (Throwable $exception) {
            // An unreadable order keeps the Orders cancellation path, which preserves it on failure.
            return false;
        }
    }

    /** Older admissions have no marker; the latest native write cannot precede their start. */
    private function creationStartedAt(WC_Order $order): ?int
    {
        $startedAt = SmartPixCreationStore::startedAt($order);
        if ($startedAt !== null) {
            return $startedAt;
        }
        $created  = $order->get_date_created('edit');
        $modified = $order->get_date_modified('edit');
        if ($created === null) {
            return null;
        }

        return max($created->getTimestamp(), $modified === null ? 0 : $modified->getTimestamp());
    }

    /**
     * Cancel on a fresh object inside the native scope, so this guard does not run for it again.
     * WooCommerce has no conditional status write, so the facts are read again right before it.
     */
    private function cancelLocally(WC_Order $stored): bool
    {
        try {
            $current = ($this->loadOrder)($stored->get_id());
            $current->read_meta_data(true);
            if (
                !in_array($current->get_status('edit'), ['pending', 'on-hold'], true)
                || $current->is_paid()
                || !$this->isExpiredUnlinkedCreation($current)
            ) {
                return false;
            }

            return $this->nativeEffects->cancel($current, static function () use ($current): bool {
                return $current->update_status('cancelled', __(
                    'Mercado Pago: the merchant cancelled this order locally. The Smart Pix creation had no confirmed Order, so no cancellation was sent to Mercado Pago.',
                    'woocommerce-mercadopago'
                ));
            });
        } catch (Throwable $exception) {
            return false;
        }
    }
}
