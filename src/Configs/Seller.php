<?php

namespace MercadoPago\Woocommerce\Configs;

use MercadoPago\Woocommerce\Helpers\Requester;

if (!defined('ABSPATH')) {
    exit;
}

class Seller
{
    /**
     * @const
     */
    private const SITE_ID = '_site_id_v1';

    /**
     * @var Requester
     */
    protected $requester;

    /**
     * @var Store
     */
    private static $instance = '';

    /**
     * Seller constructor
     */
    private function __construct()
    {
        $this->requester = Requester::getInstance();
    }

    /**
     * Get Seller Configs instance
     *
     * @return Seller
     */
    public static function getInstance(): Seller
    {
        if ('' === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return string
     */
    public function getSiteId(): string
    {
        return get_option(self::SITE_ID, '');
    }

    /**
     * @param string $siteId
     */
    public function setSiteId(string $siteId): void
    {
        update_option(self::SITE_ID, $siteId);
    }

    /**
     * Get seller info with users credentials
     *
     * @param string $accessToken
     *
     * @return array
     */
    public function getSellerInfo(string $accessToken): array
    {
        try {
            $uri     = '/users/me';
            $headers = ['Authorization: Bearer ' . $accessToken];

            $response = $this->requester->get($uri, $headers);

            return [
                'data'   => $response->getData(),
                'status' => $response->getStatus(),
            ];
        } catch (\Exception $e) {
            return [
                'data'   => null,
                'status' => 500,
            ];
        }
    }
}
