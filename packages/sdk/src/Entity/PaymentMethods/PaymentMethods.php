<?php

namespace MercadoPago\PP\Sdk\Entity\PaymentMethods;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Interfaces\RequesterEntityInterface;

/**
 * Handles the integration with the Payment Methods service.
 *
 * The Asgard Payment Methods acts as a middleware that processes available payment methods
 * based on the platform and its country of origin.
 *
 * This class handles specific cases of payment methods. For instance, in Mexico, the
 * `Paycash` payment method, which is shown to the end user, is internally understood
 * and processed as multiple forms of payment. However, in MercadoPago's API, it is described
 * as a single method.
 *
 * This functionality makes the class a crucial piece for the flexibility and effectiveness
 * of the payment system, adapting to the specificities of different markets and their respective
 * payment methods.
 *
 * @property PaymentMethodsList $payment_methods
 * @property array $grouped_payment_methods
 *
 * @package MercadoPago\PP\Sdk\Entity\PaymentMethods
 */
class PaymentMethods extends AbstractEntity implements RequesterEntityInterface
{
    /**
     * @var PaymentMethodsList
     */
    protected $payment_methods;

    /**
     * @var array
     */
    protected $grouped_payment_methods;

    /**
     * @var array
     */
    private $customHeader;


    /**
     * PaymentMethods constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->payment_methods = new PaymentMethodsList($manager);
    }

    /**
     * Exclude properties from entity building.
     *
     * @return void
     */
    public function setExcludedProperties()
    {
        $this->excluded_properties = [];
    }

    /**
     * Get and set custom headers for entity.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return [
            'read' => isset($this->customHeader) ? $this->customHeader : [],
            'save' => [],
        ];
    }

    /**
     * Set custom headers for entity.
     *
     * @return array
     */
    public function setCustomHeaders(array $customHeader = [])
    {
        return $this->customHeader = $customHeader;
    }

    /**
     * Get uris.
     *
     * @return array
     */
    public function getUris(): array
    {
        return array(
            'get' => '/ppcore/prod/payment-methods/v1/payment-methods',
        );
    }

    /**
     * Returns all available {@link PaymentMethod} for a given seller.
     *
     * The available payment methods can include options such as credit card, boleto, bank transfer, among others.
     * When making a request to fetch the payment methods, the returned object will include a list of methods
     * containing detailed information about each one.
     *
     * Some of the included information fields are: {`name`}, {`id`}, {`payment_type_id`},
     * {`thumbnail`}, {`status`}, among others.
     *
     * it is necessary to send the `Authorization` header (via setCustomHeaders),
     * with the public key of seller used on the app
     *  Example: $paymentMethods -> setCustomHeaders(array('Authorization' => $publicKey));
     *
     * @return PaymentMethodsList the list with the payment methods
     */
    public function getPaymentMethods() : PaymentMethodsList
    {
        $publicKey = $this->manager->getConfig()->__get('public_key');
        $this->setCustomHeaders(['Authorization' => $publicKey]);
        $response = parent::read([], [], false);
        foreach ($response as $value) {
            $this->payment_methods->add($value);
        }
        return $this->payment_methods;
    }

    /**
     * Returns all available payment methods for a given seller grouped by
     * one of the parameters of the response object {@link PaymentMethod}.
     *
     * The available payment methods can include options such as credit card, boleto, bank transfer, among others.
     * When making a request to fetch the payment methods, the returned object will include a list of methods
     * containing detailed information about each one.
     *
     * Some of the included information fields are: {`name`}, {`id`}, {`payment_type_id`},
     * {`thumbnail`}, {`status`}, among others.
     *
     * it is necessary to send the `Authorization` header (via setCustomHeaders),
     * with the public key of seller used on the app
     *  Example: $paymentMethods -> setCustomHeaders(array('Authorization' => $publicKey));
     *
     * @param string $groupBy the field name used to group the response result
     *
     * @return array: an object having as key the attribute chosen in group_by
     * and as value a list of payment_method related to the grouping
     */
    public function getPaymentMethodsByGroupBy(string $groupBy)
    {
        $publicKey = $this->manager->getConfig()->__get('public_key');
        $this->setCustomHeaders(['Authorization' => $publicKey]);
        $queryStrings = array('group_by' => $groupBy);
        $response = parent::read([], $queryStrings, false);
        $this->grouped_payment_methods = $response;
        foreach ($response as $key => $value) {
            $this->grouped_payment_methods[$key] = $value;
        }
        return $this->grouped_payment_methods;
    }
}
