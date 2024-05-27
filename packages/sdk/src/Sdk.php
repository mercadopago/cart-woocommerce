<?php

namespace MercadoPago\PP\Sdk;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Config;
use MercadoPago\PP\Sdk\Common\Constants;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Identification\CreateSellerFunnelBase;
use MercadoPago\PP\Sdk\Entity\Identification\UpdateSellerFunnelBase;
use MercadoPago\PP\Sdk\Entity\Monitoring\DatadogEvent;
use MercadoPago\PP\Sdk\Entity\Notification\Notification;
use MercadoPago\PP\Sdk\Entity\Payment\Multipayment;
use MercadoPago\PP\Sdk\Entity\Payment\MultipaymentV2;
use MercadoPago\PP\Sdk\Entity\Payment\MultipaymentV21;
use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Payment\PaymentV2;
use MercadoPago\PP\Sdk\Entity\Payment\PaymentV21;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\PP\Sdk\Entity\PaymentMethods\PaymentMethods;
use MercadoPago\PP\Sdk\Entity\MerchantOrder\MerchantOrder;
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
     * @param String $public_key
     */
    public function __construct(
        string $access_token = null,
        string $platform_id = null,
        string $product_id = null,
        string $integrator_id = null,
        string $public_key = null
    ) {
        $this->requester = new CurlRequester();
        $this->config = new Config();
        $parameters = [
            'access_token' => $access_token,
            'platform_id' => $platform_id,
            'product_id' => $product_id,
            'integrator_id' => $integrator_id,
            'public_key' => $public_key
        ];
        
        foreach ($parameters as $key => $value) {
            if (!empty($value) && isset($value)) {
                $this->config->__set($key, $value);
            }
        }
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

    /**
     * @return DatadogEvent
     */
    public function getDatadogEventInstance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Monitoring\DatadogEvent', Constants::BASEURL_MP);
    }

    /**
     * @return RegisterErrorLog
    */
    public function getRegisterErrorLogInstance()
    {
        return $this->getEntityInstance('MercadoPago\PP\Sdk\Entity\Monitoring\RegisterErrorLog', Constants::BASEURL_MP);
    }

    /**
     * @return PaymentMethods
    */
    public function getPaymentMethodsInstance()
    {
        return $this->getEntityInstance(
            'MercadoPago\PP\Sdk\Entity\PaymentMethods\PaymentMethods',
            Constants::BASEURL_MP
        );
    }

    /**
     * @return MerchantOrder
    */
    public function getMerchantOrderInstance()
    {
        return $this->getEntityInstance(
            'MercadoPago\PP\Sdk\Entity\MerchantOrder\MerchantOrder',
            Constants::BASEURL_MP
        );
    }

    /**
     * @return CreateSellerFunnelBase
     */
    public function getCreateSellerFunnelBaseInstance()
    {
        return $this->getEntityInstance(
            'MercadoPago\PP\Sdk\Entity\Identification\CreateSellerFunnelBase',
            Constants::BASEURL_MP
        );
    }

    /**
     * @return UpdateSellerFunnelBase
     */
    public function getUpdateSellerFunnelBaseInstance()
    {
        return $this->getEntityInstance(
            'MercadoPago\PP\Sdk\Entity\Identification\UpdateSellerFunnelBase',
            Constants::BASEURL_MP
        );
    }
}
