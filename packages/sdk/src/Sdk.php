<?php

namespace MercadoPago\PP\Sdk;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Config;
use MercadoPago\PP\Sdk\Common\Constants;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Notification\Notification;
use MercadoPago\PP\Sdk\Entity\Payment\Multipayment;
use MercadoPago\PP\Sdk\Entity\Payment\MultipaymentV2;
use MercadoPago\PP\Sdk\Entity\Payment\MultipaymentV21;
use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Payment\PaymentV2;
use MercadoPago\PP\Sdk\Entity\Payment\PaymentV21;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\PP\Sdk\HttpClient\HttpClient;
use MercadoPago\PP\Sdk\HttpClient\Requester\CurlRequester;
use MercadoPago\PP\Sdk\HttpClient\Requester\RequesterInterface;

/**
 * Class Sdk
 *
 * @package MercadoPago\PP\Sdk
 */
class Sdk
{
    public static $cache = [];
    
    /**
     * @var Config
     */
    private $config;

    /**
     * @var RequesterInterface
     */
    private $requester;

    /**
     * @param String $access_token
     * @param String $platform_id
     * @param String $product_id
     * @param String $integrator_id
     */
    public function __construct(
        string $access_token,
        string $platform_id,
        string $product_id,
        string $integrator_id
    ) {
        $this->requester = new CurlRequester();
        $this->config = new Config();
        $this->config->__set('access_token', $access_token);
        $this->config->__set('platform_id', $platform_id);
        $this->config->__set('product_id', $product_id);
        $this->config->__set('integrator_id', $integrator_id);
    }

    /**
     * @param string $entityName
     * @param string $baseUrl
     *
     * @return AbstractEntity
     */
    public function getEntityInstance(string $entityName, string $baseUrl)
    {
        $client  = new HttpClient($baseUrl, $this->requester);
        $manager = new Manager($client, $this->config);
        return new $entityName($manager);
    }

    /**
     * @return Preference
     */
    public function getPreferenceInstance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Preference\Preference', Constants::BASEURL_MP);
    }

    /**
     * @return Notification
     */
    public function getNotificationInstance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Notification\Notification', Constants::BASEURL_MP);
    }

    /**
     * @return Payment
     */
    public function getPaymentInstance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Payment\Payment', Constants::BASEURL_MP);
    }

    /**
     * @return PaymentV2
     */
    public function getPaymentV2Instance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Payment\PaymentV2', Constants::BASEURL_MP);
    }

     /**
     * @return PaymentV21
     */
    public function getPaymentV21Instance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Payment\PaymentV21', Constants::BASEURL_MP);
    }

    /**
     * @return Multipayment
     */
    public function getMultipaymentInstance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Payment\Multipayment', Constants::BASEURL_MP);
    }

    /**
     * @return MultipaymentV2
     */
    public function getMultipaymentV2Instance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Payment\MultipaymentV2', Constants::BASEURL_MP);
    }

     /**
     * @return MultipaymentV21
     */
    public function getMultipaymentV21Instance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Payment\MultipaymentV21', Constants::BASEURL_MP);
    }
}
