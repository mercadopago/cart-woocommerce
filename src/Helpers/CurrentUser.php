<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class CurrentUser
{
    /**
     * @var Logs
     */
    private $logs;

    /**
     * CurrentUser constructor
     *
     * @param Logs $logs
     */
    public function __construct(Logs $logs)
    {
        $this->logs = $logs;
    }

    /**
     * Get WP current user
     *
     * @return \WP_User
     */
    public function getCurrentUser()
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
     * Verify if current_user has specifics roles
     *
     * @param array $roles 'administrator | editor | author | contributor | subscriber'
     *
     * @return bool
     */
    public function userHasRoles(array $roles): bool
    {
        return !empty(array_intersect($roles, $this->getCurrentUserRoles()));
    }

    /**
     * Validate if user has administrator or editor permissions
     *
     * @return void
     */
    public function validateUserNeededPermissions(): void
    {
        $needed_roles = ['administrator', 'editor'];

        if (!$this->userHasRoles($needed_roles)) {
            $this->logs->file->error('User does not have permission (need admin or editor)', __CLASS__);
            wp_send_json_error('Forbidden', 403);
        }
    }
}
