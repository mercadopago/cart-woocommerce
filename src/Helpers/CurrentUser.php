<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

final class CurrentUser
{
    /**
     * @var Logs
     */
    private $logs;

    /**
     * Store
     *
     * @var Store
     */
    private $store;

    /**
     * Is debug mode
     *
     * @var mixed|string
     */
    public $debugMode;

    /**
     * CurrentUser constructor
     *
     * @param Logs $logs
     * @param Store $store
     */
    public function __construct(Logs $logs, Store $store)
    {
        $this->logs      = $logs;
        $this->store     = $store;
        $this->debugMode = $this->store->getDebugMode();
    }

    /**
     * Get WP current user
     *
     * @return bool
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get WP current user
     *
     * @return \WP_User
     */
    public function getCurrentUser(): \WP_User
    {
        return wp_get_current_user();
    }

    /**
     * Get WP current user roles
     *
     * @return array
     */
    public function getCurrentUserRoles(): array
    {
        return $this->getCurrentUser()->roles;
    }

    /**
     * Retrieves current user info
     *
     * @return  \WP_User|false
     */
    public function getCurrentUserData()
    {
        return get_userdata($this->getCurrentUser()->ID);
    }

    /**
     * Get WP current user roles
     *
     * @param string $key
     * @param bool   $single
     *
     * @return array|string
     */
    public function getCurrentUserMeta(string $key, bool $single = false)
    {
        return get_user_meta($this->getCurrentUser()->ID, $key, $single);
    }

    /**
     * Verify if current_user has specifics roles
     *
     * @param array $roles
     *
     * @return bool
     */
    public function userHasRoles(array $roles): bool
    {
        return is_super_admin($this->getCurrentUser()) || !empty(array_intersect($roles, $this->getCurrentUserRoles()));
    }

    /**
     * Verify if current user has permission
     * @see https://wordpress.org/documentation/article/roles-and-capabilities/
     *
     * @param string $capability
     *
     * @return bool
     */
    public function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Validate if user has administrator or editor permissions
     *
     * @return void
     */
    public function validateUserNeededPermissions(): void
    {
        $neededRoles = ['administrator', 'manage_woocommerce'];

        if (!$this->userHasRoles($neededRoles)) {
            $this->logs->file->error('User does not have permissions', __CLASS__);
            wp_send_json_error('Forbidden', 403);
        }
    }
}
