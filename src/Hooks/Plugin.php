<?php

namespace MercadoPago\Woocommerce\Hooks;

class Plugin
{
    /**
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Get Plugin Hooks instance
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register more links on WordPress plugins page
     *
     * @param string $pluginName
     * @param array  $pluginLinks
     * @return void
     */
    public function registerPluginActionLinks(string $pluginName, array $pluginLinks): void
    {
        add_filter('plugin_action_links_' . $pluginName, function (array $links) use ($pluginLinks) {
            $newLinks = [];

            foreach ($pluginLinks as $link) {
                $newLinks[] = '<a href="'. $link['href'] .'" target="'. $link['target'] .'">' . $link['text'] . '</a>';
            }

            return array_merge($newLinks, $links);
        });
    }
}
