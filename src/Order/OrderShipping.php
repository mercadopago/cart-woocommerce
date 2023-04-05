<?php

namespace MercadoPago\Woocommerce\Order;

if (!defined('ABSPATH')) {
    exit;
}

class OrderShipping
{
    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getFirstName(\WC_Order $order): string
    {
        return $order->get_shipping_first_name() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getLastName(\WC_Order $order): string
    {
        return $order->get_shipping_last_name() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getPhone(\WC_Order $order): string
    {
        return $order->get_shipping_phone() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getZipcode(\WC_Order $order): string
    {
        return $order->get_shipping_postcode() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getAddress1(\WC_Order $order): string
    {
        return $order->get_shipping_address_1() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getAddress2(\WC_Order $order): string
    {
        return $order->get_shipping_address_2() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getCity(\WC_Order $order): string
    {
        return $order->get_shipping_city() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getState(\WC_Order $order): string
    {
        return $order->get_shipping_state() ?? '';
    }

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getCountry(\WC_Order $order): string
    {
        return $order->get_shipping_country() ?? '';
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

    /**
     * @param \WC_Order $order
     *
     * @return string
     */
    public function getShippingMethod(\WC_Order $order): string
    {
        return $order->get_shipping_method();
    }

    /**
     * @param \WC_Order $order
     *
     * @return float
     */
    public function getTotal(\WC_Order $order): float
    {
        return (float) $order->get_shipping_total();
    }
}
