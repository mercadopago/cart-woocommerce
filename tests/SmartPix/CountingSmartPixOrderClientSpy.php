<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use LogicException;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;
use RuntimeException;

final class CountingSmartPixOrderClientSpy implements SmartPixOrderClient
{
    private string $role;

    private string $evidenceDirectory;

    private int $createCalls = 0;

    public function __construct(string $role, string $evidenceDirectory)
    {
        if (!in_array($role, ['A', 'B'], true) || !is_dir($evidenceDirectory)) {
            throw new RuntimeException('The Smart Pix Orders spy configuration is invalid.');
        }

        $this->role              = $role;
        $this->evidenceDirectory = rtrim($evidenceDirectory, DIRECTORY_SEPARATOR);
    }

    public function create(array $request, ?SmartPixAuthorizedPayment $payment = null): array
    {
        ++$this->createCalls;

        $marker = sprintf(
            '%s/orders-create-call-%s-%d',
            $this->evidenceDirectory,
            $this->role,
            $this->createCalls
        );
        $evidence = wp_json_encode(
            [
                'role'          => $this->role,
                'call'          => $this->createCalls,
                'operation'     => 'create',
                'request_keys'  => array_keys($request),
            ],
            JSON_UNESCAPED_SLASHES
        );

        if (!is_string($evidence) || file_put_contents($marker, $evidence, LOCK_EX) === false) {
            throw new RuntimeException('The Smart Pix Orders spy could not persist create evidence.');
        }

        return [
            'outcome'        => 'success',
            'state'          => 'processing',
            'order_reference' => 'spy_order_' . strtolower($this->role),
            'eligibility'    => null,
            'authorization'  => null,
            'payments'       => [],
            'error'          => null,
        ];
    }

    public function query(array $request): array
    {
        throw new LogicException('The Smart Pix concurrency spy does not support query.');
    }

    public function cancel(array $request): array
    {
        throw new LogicException('The Smart Pix concurrency spy does not support cancel.');
    }

    public function refund(array $request): array
    {
        throw new LogicException('The Smart Pix concurrency spy does not support refund.');
    }

    public function getCreateCalls(): int
    {
        return $this->createCalls;
    }
}
