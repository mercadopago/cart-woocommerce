<?php

namespace MercadoPago\Woocommerce\Order;

if (!defined('ABSPATH')) {
    exit;
}

class OrderBilling
{
    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getFirstName(\WC_Order $order): string
    {
        return $order->get_billing_first_name() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getLastName(\WC_Order $order): string
    {
        $lastName = $order->get_billing_last_name();

        if (empty($lastName)) {
            $firstName = $this->getFirstName($order);
            $names = explode(' ', $firstName);
            if (count($names) >= 2) {
                $lastName = $names[1];
            }
        }

        return $lastName ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getPhone(\WC_Order $order): string
    {
        return $order->get_billing_phone() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getEmail(\WC_Order $order): string
    {
        return $order->get_billing_email() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getZipcode(\WC_Order $order): string
    {
        return $order->get_billing_postcode() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getAddress1(\WC_Order $order): string
    {
        return $order->get_billing_address_1() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getAddress2(\WC_Order $order): string
    {
        return $order->get_billing_address_2() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getCity(\WC_Order $order): string
    {
        return $order->get_billing_city() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getState(\WC_Order $order): string
    {
        return $order->get_billing_state() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getCountry(\WC_Order $order): string
    {
        return $order->get_billing_country() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getFullAddress(\WC_Order $order): string
    {
        return "{$this->getAddress1($order)} / {$this->getAddress2($order)} - {$this->getCity($order)} - {$this->getState($order)} - {$this->getCountry($order)}";
    }
}
