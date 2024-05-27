<?php

namespace MercadoPago\PP\Sdk\Entity\Identification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Platform
 *
 * @property string $id
 * @property bool $is_added_production_credential
 * @property bool $is_added_test_credential
 * @property string $product_id
 * @property string $cust_id
 * @property string $application_id
 * @property string $plugin_mode
 * @property bool $is_deleted
 * @property string[] $accepted_payments
 * @property string $cpp_token
 * @property bool $is_disabled
 *
 * @package MercadoPago\PP\Sdk\Entity\Identification
 */
class UpdateSellerFunnelBase extends AbstractEntity
{
    /**
     * @var string
     */
    protected $id;
    /**
     * @var bool
     */
    protected $is_added_production_credential;
    /**
     * @var bool
     */
    protected $is_added_test_credential;
    /**
     * @var string
     */
    protected $product_id;
    /**
     * @var string
     */
    protected $cust_id;
    /**
     * @var string
     */
    protected $application_id;
    /**
     * @var string
     */
    protected $plugin_mode;
    /**
     * @var bool
     */
    protected $is_deleted;
    /**
     * @var string[]
     */
    protected $accepted_payments;
    /**
     * @var string
     */
    protected $cpp_token;
    /**
     * @var bool
     */
    protected $is_disabled;
    /**
     * @var string
     */
    protected $site_id;


    /**
     * Identification constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
    }

    /**
     * Exclude properties from entity building.
     *
     * @return void
     */
    public function setExcludedProperties()
    {
        $this->excluded_properties = ['cpp_token'];
    }

    /**
     * Get and set custom headers for entity.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return [
            'update' => ['x-cpp-token: ' . $this->cpp_token],
        ];
    }

    /**
     * Get uris.
     *
     * @return array
     */
    public function getUris(): array
    {
        return array(
            'put' => '/v1/eplatforms/core/identification/seller-configuration/update-integration',
        );
    }

    /**
     * When invoking this method, a request is made to the ag-Identification API
     * using shop_url and platform_id the api will return a new integration id
     *
     * @return mixed return new id and field success in boolean.
     *
     * @throws \Exception Throws an exception if something goes wrong during the save operation.
     */
    public function update(array $params = [])
    {
        return parent::update();
    }
}
