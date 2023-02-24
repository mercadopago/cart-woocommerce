<?php

namespace MercadoPago\Woocommerce\Configs;

use MercadoPago\Woocommerce\Hooks\Meta;

if (!defined('ABSPATH')) {
    exit;
}

class MetaData
{
    /**
     * @const
     */
    private const IS_PRODUCTION_MODE = 'is_production_mode';

    /**
     * @const
     */
    private const USED_GATEWAY = '_used_gateway';

    /**
     * @const
     */
    private const DISCOUNT = 'Mercado Pago: discount';

    /**
     * @const
     */
    private const COMMISSION = 'Mercado Pago: comission';

    /**
     * @const
     */
    private const MP_INSTALLMENTS = 'mp_installments';

    /**
     * @const
     */
    private const MP_TRANSACTION_DETAILS = 'mp_transaction_details';

    /**
     * @const
     */
    private const MP_TRANSACTION_AMOUNT = 'mp_transaction_amount';

    /**
     * @const
     */
    private const MP_TOTAL_PAID_AMOUNT = 'mp_total_paid_amount';

    /**
     * @const
     */
    private const PAYMENTS_IDS = '_Mercado_Pago_Payment_IDs';

    /**
     * @const
     */
    private const TICKET_TRANSACTION_DETAILS = '_transaction_details_ticket';

    /**
     * @const
     */
    private const MP_PIX_QR_BASE_64 = 'mp_pix_qr_base64';

    /**
     * @const
     */
    private const MP_PIX_QR_CODE = 'mp_pix_qr_code';

    /**
     * @const
     */
    private const PIX_EXPIRATION_DATE = 'checkout_pix_date_expiration';

    /**
     * @const
     */
    private const PIX_ON = 'pix_on';

    /**
     * @var Meta
     */
    private $meta;


    /**
     * Metadata constructor
     */
    public function __construct(Meta $meta)
    {
        $this->meta = $meta;
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function getIsProductionMode($object, string $default): string
    {
        return $this->meta->getData($object, self::IS_PRODUCTION_MODE, $default);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setIsProductionMode($object, $value): void
    {
        $this->meta->setData($object, self::IS_PRODUCTION_MODE, $value);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function getUsedGateway($object, string $default): string
    {
        return $this->meta->getData($object, self::USED_GATEWAY, $default);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setUsedGateway($object, $value): void
    {
        $this->meta->setData($object, self::USED_GATEWAY, $value);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function getPostUsedGateway($object, string $default): string
    {
        return $this->meta->getPost($object, self::USED_GATEWAY, $default);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPostUsedGateway($object, $value): void
    {
        $this->meta->setPost($object, self::USED_GATEWAY, $value);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function getDiscount($object, string $default): string
    {
        return $this->meta->getData($object, self::DISCOUNT, $default);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setDiscount($object, $value): void
    {
        $this->meta->setData($object, self::DISCOUNT, $value);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function getPostDiscount($object, string $default): string
    {
        return $this->meta->getPost($object, self::DISCOUNT, $default);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPostDiscount($object, $value): void
    {
        $this->meta->setPost($object, self::DISCOUNT, $value);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function getCommission($object, string $default): string
    {
        return $this->meta->getData($object, self::COMMISSION, $default);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setCommission($object, $value): void
    {
        $this->meta->setData($object, self::COMMISSION, $value);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function getPostCommission($object, string $default): string
    {
        return $this->meta->getPost($object, self::COMMISSION, $default);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPostCommission($object, $value): void
    {
        $this->meta->setPost($object, self::COMMISSION, $value);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function addInstallments($object, string $default): string
    {
        return $this->meta->addData($object, self::MP_INSTALLMENTS, $default);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function addTransactionDetails($object, string $default): string
    {
        return $this->meta->addData($object, self::MP_TRANSACTION_DETAILS, $default);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function addTransactionAmount($object, string $default): string
    {
        return $this->meta->addData($object, self::MP_TRANSACTION_AMOUNT, $default);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function setTransactionAmount($object, string $default): string
    {
        return $this->meta->setData($object, self::MP_TRANSACTION_AMOUNT, $default);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function setPostTransactionAmount($object, string $default): string
    {
        return $this->meta->setPost($object, self::MP_TRANSACTION_AMOUNT, $default);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function addTotalPaidAmount($object, string $default): string
    {
        return $this->meta->addData($object, self::MP_TOTAL_PAID_AMOUNT, $default);
    }

    /**
     * @param $object
     * @param string $default
     *
     * @return string
     */
    public function getPostPaymentIds($object, string $default): string
    {
        return $this->meta->getPost($object, self::PAYMENTS_IDS, $default);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPostPaymentIds($object, $value): void
    {
        $this->meta->setPost($object, self::PAYMENTS_IDS, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPostTicketTransactionDetails($object, $value): void
    {
        $this->meta->setPost($object, self::TICKET_TRANSACTION_DETAILS, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setTicketTransactionDetails($object, $value): void
    {
        $this->meta->setData($object, self::TICKET_TRANSACTION_DETAILS, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPostPixQrBase64($object, $value): void
    {
        $this->meta->setPost($object, self::MP_PIX_QR_BASE_64, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPixQrBase64($object, $value): void
    {
        $this->meta->setData($object, self::MP_PIX_QR_BASE_64, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPostPixQrCode($object, $value): void
    {
        $this->meta->setPost($object, self::MP_PIX_QR_CODE, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPixQrCode($object, $value): void
    {
        $this->meta->setData($object, self::MP_PIX_QR_CODE, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPostPixExpirationDate($object, $value): void
    {
        $this->meta->setPost($object, self::PIX_EXPIRATION_DATE, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPixExpirationDate($object, $value): void
    {
        $this->meta->setData($object, self::PIX_EXPIRATION_DATE, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPostPixOn($object, $value): void
    {
        $this->meta->setPost($object, self::PIX_ON, $value);
    }

    /**
     * @param $object
     * @param $value
     */
    public function setPixOn($object, $value): void
    {
        $this->meta->setData($object, self::PIX_ON, $value);
    }

    /**
     * Update an order's payments metadata
     *
     * @param string $orderId
     * @param array $paymentsId
     *
     * @return void
     */
    public function updatePaymentsOrderMetadata(string $orderId, array $paymentsId)
    {
        $paymentIdMetadata = count($this->getPostPaymentIds($orderId));

        if (count($paymentsId) > 0) {
            if (0 === $paymentIdMetadata) {
                $this->setPostPaymentIds($orderId, implode(', ', $paymentsId));
            }

            foreach ($paymentsId as $paymentId) {
                $paymentDetailKey = 'Mercado Pago - Payment ' . $paymentId;
                $paymentDetailMetadata = count($this->meta->getPost($orderId, $paymentDetailKey));

                if (0 === $paymentDetailMetadata) {
                    $this->meta->setPost(
                        orderId,
                        $paymentDetailKey,
                        '[Date ' . gmdate('Y-m-d H:i:s') . ']'
                    );
                }
            }
        }
    }
}
