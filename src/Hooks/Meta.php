<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Meta
{
    /**
     * Get meta
     *
     * @param $object
     * @param string $metaKey
     * @param bool $single
     *
     * @return mixed|string
     */
    public function get($object, string $metaKey, bool $single = true)
    {
        return $object->get_meta($metaKey, $single);
    }

    /**
     * Get post meta
     *
     * @param int $postId
     * @param string $metaKey
     * @param bool $single
     *
     * @return mixed|string
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
     * @param $metaValue
     * @param string $prevValue
     *
     * @return bool|int
     */
    public function setPost(int $postId, string $metaKey, $metaValue, string $prevValue = '')
    {
        return update_post_meta($postId, $metaKey, $metaValue, $prevValue);
    }

    /**
     * Add metadata
     *
     * @param $object
     * @param string $metaKey
     * @param string|array $value
     * @param bool $unique
     */
    public function addData($object, string $metaKey, $value, bool $unique = false): void
    {
        $object->add_meta_data($metaKey, $value, $unique);
    }

    /**
     * Get metadata
     *
     * @var $object
     *
     * @return array
     */
    public function getData($object): array
    {
        return $object->get_meta_data();
    }

    /**
     * Set metadata
     *
     * @param $object
     * @param string $metaKey
     * @param string|array $value
     */
    public function setData($object, string $metaKey, $value): void
    {
        $object->update_meta_data($metaKey, $value);
    }
}
