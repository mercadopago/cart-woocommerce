<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class OrderMeta
{
    /**
     * Get post meta
     *
     * @param int $postId
     * @param string $metaKey
     * @param bool $single
     *
     * @return mixed
     */
    public function getPost(int $postId, string $metaKey, bool $single = false)
    {
        return get_post_meta($postId, $metaKey, $single);
    }

    /**
     * Update post meta
     *
     * @param int $postId
     * @param string $metaKey
     * @param mixed $value
     * @param string $prevValue
     *
     * @return bool|int
     */
    public function setPost(int $postId, string $metaKey, $value, string $prevValue = '')
    {
        return update_post_meta($postId, $metaKey, $value, $prevValue);
    }

    /**
     * Get meta
     *
     * @param \WC_Order $order
     * @param string $metaKey
     * @param bool $single
     *
     * @return mixed
     */
    public function get(\WC_Order $order, string $metaKey, bool $single = true)
    {
        return $order->get_meta($metaKey, $single);
    }

    /**
     * Get all metadata
     *
     * @param \WC_Order $order
     *
     * @return array
     */
    public function getAll(\WC_Order $order): array
    {
        return $order->get_meta_data();
    }

    /**
     * Add metadata
     *
     * @param \WC_Order $order
     * @param string $metaKey
     * @param mixed $value
     * @param bool $unique
     *
     * @return void
     */
    public function add(\WC_Order $order, string $metaKey, $value, bool $unique = false): void
    {
        $order->add_meta_data($metaKey, $value, $unique);
    }

    /**
     * Set metadata
     *
     * @param \WC_Order $order
     * @param string $metaKey
     * @param string|array $value
     *
     * @return void
     */
    public function update(\WC_Order $order, string $metaKey, $value): void
    {
        $order->update_meta_data($metaKey, $value);
        $order->save();
    }
}
