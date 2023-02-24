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
        $this->logs = $logs;
        $this->store = $store;
        $this->debugMode = $this->store->getDebugMode();
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
     * @param array $roles 'administrator | editor | author | contributor | subscriber'
     *
     * @return bool
     */
    public function userHasRoles(array $roles): bool
    {
        return is_super_admin($this->getCurrentUser()) || !empty(array_intersect($roles, $this->getCurrentUserRoles()));
    }

    /**
     * Validate if user has administrator or editor permissions
     *
     * @return void
     */
    public function validateUserNeededPermissions(): void
    {
        $neededRoles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];

        if (!$this->userHasRoles($neededRoles)) {
            $this->logs->file->error('User does not have permission (need admin or editor)', __CLASS__);
            wp_send_json_error('Forbidden', 403);
        }
    }
}
