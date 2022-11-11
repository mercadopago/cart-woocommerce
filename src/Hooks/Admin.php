<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{
    /**
     * @const
     */
    public const HREF_TARGET_DEFAULT = '_self';

    /**
     * @const
     */
    public const HREF_TARGET_BLANK = '_blank';

    /**
     * @var Admin
     */
    private static $instance = null;

    /**
     * Get Admin Hooks instance
     *
     * @return Admin
     */
    public static function getInstance(): Admin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Validate if the actual page belongs to the admin section
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return is_admin();
    }

    /**
     * Register on WordPress or Plugins menu
     *
     * @param int   $priority
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOnMenu(int $priority, $callback): void
    {
        add_action('admin_menu', $callback, $priority);
    }

    /**
     * Add plugin on another plugin submenu
     *
     * @param string $parentSlug
     * @param string $pageTitle
     * @param string $menuTitle
     * @param string $capability
     * @param string $menuSlug
     * @param mixed  $callback
     *
     * @return void
     */
    public function registerSubmenuPage(string $parentSlug, string $pageTitle, string $menuTitle, string $capability, string $menuSlug, $callback): void
    {
        add_submenu_page($parentSlug, $pageTitle, $menuTitle, $capability, $menuSlug, $callback);
    }

    /**
     * Register more links on WordPress plugins page
     *
     * @param string $pluginName
     * @param array  $pluginLinks
     *
     * @return void
     */
    public function registerPluginActionLinks(string $pluginName, array $pluginLinks): void
    {
        add_filter('plugin_action_links_' . $pluginName, function (array $links) use ($pluginLinks) {
            $newLinks = [];

            foreach ($pluginLinks as $link) {
                $newLinks[] = '<a href="' . $link['href'] . '" target="' . $link['target'] . '">' . $link['text'] . '</a>';
            }

            return array_merge($newLinks, $links);
        });
    }
}
