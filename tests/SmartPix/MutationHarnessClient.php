<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use RuntimeException;
use WC_Order;

if (!defined('WP_CLI') || !WP_CLI || getenv('SMART_PIX_MUTATION_HARNESS') !== '1') {
    throw new RuntimeException('Synthetic mutation fixtures require explicit WP-CLI authorization.');
}

/** Synthetic port only. All WooCommerce persistence/refund/status methods remain real. */
class MutationHarnessClient implements SmartPixOrderClient
{
    public array $calls = [];
    public bool $timeout = false;
    public bool $failQuery = false;
    public bool $cancelTimeout = false;
    public int $queries = 0;
    public ?string $queryState = null;
    /** @var callable|null */
    public $onCancel = null;

    public function create(array $request, ?SmartPixAuthorizedPayment $payment = null): array
    {
        throw new RuntimeException('Creation is outside this harness.');
    }

    public function query(array $request): array
    {
        ++$this->queries;
        if ($this->failQuery) {
            $this->failQuery = false;
            throw new RuntimeException('unavailable');
        }
        $order = $this->order($request);
        return $this->response($order, $request, $this->queryState ?? ($order->is_paid() ? 'approved' : 'processing'));
    }

    public function cancel(array $request): array
    {
        $this->calls[] = ['operation' => 'cancel', 'key' => $request['operation_context']['idempotency_key']];
        if ($this->cancelTimeout) {
            throw new RuntimeException('timeout');
        }
        if ($this->onCancel !== null) {
            $callback = $this->onCancel;
            $this->onCancel = null;
            $callback();
        }
        return $this->response($this->order($request), $request, 'cancelled');
    }

    public function refund(array $request): array
    {
        $this->calls[] = ['operation' => 'refund', 'key' => $request['operation_context']['idempotency_key']];
        $order = $this->order($request);
        if ($this->timeout) {
            $response = $this->response($order, $request, 'processing');
            $response['outcome'] = 'failure';
            $response['error'] = ['category' => 'timeout', 'code' => 'SMART_PIX_TIMEOUT', 'retryable' => true];
            return $response;
        }
        $total = SmartPixMoney::normalize($order->get_total('edit'));
        $before = $order->get_meta('_psw_4772_synthetic_refunded') ?: '0.00';
        $remaining = SmartPixMoney::subtract($total, $before);
        $after = SmartPixMoney::subtract($total, SmartPixMoney::subtract($remaining, $request['refund_amount']['value']));
        $order->update_meta_data('_psw_4772_synthetic_refunded', $after);
        $order->save_meta_data();
        return $this->response($order, $request, 'refunded');
    }

    private function order(array $request): WC_Order
    {
        if (preg_match('/^WC-([1-9][0-9]*)$/D', $request['attempt_reference'] ?? '', $match) !== 1) {
            throw new RuntimeException('Invalid synthetic order correlation.');
        }
        $order = new WC_Order((int) $match[1]);
        if ($order->get_meta('_psw_4772_synthetic_order') !== 'yes') {
            throw new RuntimeException('The harness cannot operate a real merchant order.');
        }
        return $order;
    }

    private function response(WC_Order $order, array $request, string $state): array
    {
        $total = SmartPixMoney::normalize($order->get_total('edit'));
        return [
            'outcome' => 'success',
            'state' => $state === 'processing' ? 'processing' : ($state === 'cancelled' ? 'terminal_failure' : 'terminal_success'),
            'order_reference' => $request['order_reference'], 'eligibility' => null, 'authorization' => null,
            'payments' => [['reference' => 'PAYMENT_SYNTHETIC_001', 'state' => $state,
                'amount' => ['value' => $state === 'refunded' ? $request['refund_amount']['value'] : $total, 'currency' => 'BRL']]],
            'error' => null,
            'order_context' => ['contract_version' => 'smart-pix-mutations/v1', 'seller_reference' => '12345',
                'attempt_reference' => $request['attempt_reference'], 'amount' => ['value' => $total, 'currency' => 'BRL'],
                'refunded_amount' => $order->get_meta('_psw_4772_synthetic_refunded') ?: '0.00'],
        ];
    }
}
