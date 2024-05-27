<?php

namespace MercadoPago\PP\Sdk\Entity\Identification;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\HttpClient\Response;

/**
 * Class Platform
 *
 * @property string $platform_id
 * @property string $shop_url
 * @property string $platform_version
 * @property string $plugin_version
 * @property string $site_id
 *
 * @package MercadoPago\PP\Sdk\Entity\Identification
 */
class CreateSellerFunnelBase extends AbstractEntity
{
    /**
     * @var string
     */
    protected $platform_id;

    /**
     * @var string
     */
    protected $shop_url;

    /**
     * @var string
     */
    protected $platform_version;

    /**
     * @var string
     */
    protected $plugin_version;

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
     * Get and set custom headers for entity.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return [
            'save' => []
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
            'post' => '/v1/eplatforms/core/identification/seller-configuration/start-integration',
        );
    }

    /**
     * When invoking this method, a request is made to the ag-Identification API
     * using shop_url and platform_id the api will return a new integration id
     *
     * @return ResponseCreateSellerFunnelBase return new id and x-cpp-token in header of response.
     *
     * @throws \Exception Throws an exception if something goes wrong during the save operation.
     */
    public function save(array $params = [])
    {
        $response = parent::saveWithResponseHeaders();

        return $this->buildResponseSellerFunnelBase($response);
    }

    /**
     * responsible for parsing the response made by the request and standardizing it into a response class
     *
     * @param Response $response
     */
    private function buildResponseSellerFunnelBase(Response $response)
    {
        if ($response->getStatus() ==! 201) {
            return $response;
        }
        $responseBody = json_decode(json_encode($response->getData()));
        $responseHeader = $response->getHeaders();

        return new ResponseCreateSellerFunnelBase($responseBody->id, $responseHeader["x-cpp-token"]);
    }
}
