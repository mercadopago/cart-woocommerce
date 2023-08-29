<?php

namespace MercadoPago\Woocommerce\Configs;

use MercadoPago\Woocommerce\Hooks\Options;

if (!defined('ABSPATH')) {
    exit;
}

class Metadata
{
    /**
     * @var Options
     */
    private $options;

    /**
     * MetadataSettings constructor
     *
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * Get settings by gateway id
     *
     * @param string $gatewayId
     *
     * @return array
     */
    public function getGatewaySettings(string $gatewayId): array
    {
        return $this->getSettings("woocommerce_woo-mercado-pago-{$gatewayId}_settings");
    }

    /**
     * Get settings by gateway id
     *
     * @param string $option
     *
     * @return array
     */
    public function getSettings(string $option): array
    {
        $options = $this->options->get($option, []);
        $ignoredOptions = $this->getIgnoredOptions();

        $validValues = array_filter($options, function ($value, $key) use ($ignoredOptions) {
            return !empty($value) && !in_array($key, $ignoredOptions, true);
        }, ARRAY_FILTER_USE_BOTH);

        return $validValues;
    }

    /**
     * Get ignored options
     *
     * @return array
     */
    public function getIgnoredOptions(): array
    {
        return [
            'title',
            'description',
            '_mp_public_key_prod',
            '_mp_public_key_test',
            '_mp_access_token_prod',
            '_mp_access_token_test'
        ];
    }
}
