<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Persistence;

use InvalidArgumentException;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use RuntimeException;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class SmartPixAttemptRepository
{
    private OrderMetadata $orderMetadata;

    public function __construct(OrderMetadata $orderMetadata)
    {
        $this->orderMetadata = $orderMetadata;
    }

    /**
     * @param WC_Order $order Must not carry unsaved metadata changes when this is called.
     *     The forced `read_meta_data(true)` reload below discards any local, uncommitted
     *     `update_meta_data()`/`add_meta_data()` made on this same instance before the
     *     call, and the eventual `save()` this method triggers persists only the Smart
     *     Pix contract and idempotency key it manages — never the caller's pending
     *     unrelated changes. Save unrelated metadata before calling this method, or
     *     reload `$order` again afterward if more changes are still needed.
     * @return array{payment_contract: string, idempotency_key: string}
     */
    public function createOrRead(WC_Order $order, string $candidateIdempotencyKey): array
    {
        if ($candidateIdempotencyKey === '') {
            throw new InvalidArgumentException('The Smart Pix idempotency key must not be empty.');
        }

        $order->read_meta_data(true);

        [$paymentContract, $idempotencyKey] = $this->readAttemptMetadata(
            $order,
            'The Smart Pix attempt metadata is incomplete or incompatible.'
        );

        if ($paymentContract === '' && $idempotencyKey === '') {
            $this->orderMetadata->persistSmartPixAttempt($order, $candidateIdempotencyKey);

            $order->read_meta_data(true);

            [$paymentContract, $idempotencyKey] = $this->readAttemptMetadata(
                $order,
                'The Smart Pix attempt persistence could not be verified.'
            );

            if (
                $paymentContract !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
                || $idempotencyKey !== $candidateIdempotencyKey
            ) {
                throw new RuntimeException('The Smart Pix attempt persistence could not be verified.');
            }
        } elseif (
            $paymentContract !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1
            || $idempotencyKey === ''
        ) {
            throw new RuntimeException('The Smart Pix attempt metadata is incomplete or incompatible.');
        }

        return [
            'payment_contract' => $paymentContract,
            'idempotency_key'  => $idempotencyKey,
        ];
    }

    /**
     * Reads the payment contract and idempotency key as raw values and rejects any
     * type other than string (e.g. an array from a corrupted or tampered metadata
     * row) before the caller compares or persists them. A loose `(string)` cast
     * would otherwise turn an incompatible value into the literal string "Array",
     * which reads as non-empty and could be accepted as a valid, reusable attempt.
     *
     * @return array{0: string, 1: string}
     */
    private function readAttemptMetadata(WC_Order $order, string $typeErrorMessage): array
    {
        $paymentContract = $this->orderMetadata->getPaymentContract($order);
        $idempotencyKey  = $this->orderMetadata->getIdempotencyKey($order);

        if (!is_string($paymentContract) || !is_string($idempotencyKey)) {
            throw new RuntimeException($typeErrorMessage);
        }

        return [$paymentContract, $idempotencyKey];
    }
}
