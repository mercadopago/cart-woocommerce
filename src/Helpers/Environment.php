<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Environment
{
    public const WP_ENVIRONMENT_TYPE_LOCAL = 'local';
    public const WP_ENVIRONMENT_TYPE_DEVELOPMENT = 'development';
    public const WP_ENVIRONMENT_TYPE_STAGING = 'staging';
    public const WP_ENVIRONMENT_TYPE_PRODUCTION = 'production';

    /**
     * Checks whether the site is in the given environment type
     *
     * @param string $type Environment type to check for
     **/
    public static function isEnvironmentType(string $type): bool
    {
        return wp_get_environment_type() === $type;
    }

    /**
     * Checks whether the site is in a development environment
     **/
    public static function isDevelopmentEnvironment(): bool
    {
        return in_array(
            wp_get_environment_type(),
            [static::WP_ENVIRONMENT_TYPE_LOCAL, static::WP_ENVIRONMENT_TYPE_DEVELOPMENT],
            true
        );
    }
}
