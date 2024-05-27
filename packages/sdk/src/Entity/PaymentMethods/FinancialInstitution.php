<?php

namespace MercadoPago\PP\Sdk\Entity\PaymentMethods;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class FinancialInstitution
 *
 * @property string $id
 * @property string $description

 * @package MercadoPago\PP\Sdk\Entity\PaymentMethods
 */
class FinancialInstitution extends AbstractEntity
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $description;
}
