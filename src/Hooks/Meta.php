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
     * @param string $metaName
     * @return mixed|string
     */
    public function get($object, string $metaName)
    {
        return $object->get_meta($metaName);
    }

    /**
     * Get post meta
     *
     * @param $object
     * @param string $metaName
     * @param string $default
     *
     * @return mixed|string
     */
    public function getPost($object, string $metaName, string $default = '')
    {
        return get_post_meta($object, $metaName, $default);
    }

    /**
     * Set post meta
     *
     * @param $object
     * @param string $metaName
     * @param mixed $value
     *
     * @return bool
     */
    public function setPost($object, string $metaName, $value): bool
    {
        return update_post_meta($object, $metaName, $value);
    }

    /**
     * Add metadata
     *
     * @param $object
     * @param string $metadataName
     * @param mixed|string $default
     *
     * @return mixed|string
     */
    public function addData($object, string $metadataName, string $default = '')
    {
        return $object->add_meta_data($metadataName, $default);
    }

    /**
     * Get metadata
     *
     * @param $object
     * @param string $metadataName
     * @param mixed|string $default
     *
     * @return mixed|string
     */
    public function getData($object, string $metadataName, string $default = '')
    {
        return $object->get_meta_data($metadataName, $default);
    }

    /**
     * Set metadata
     *
     * @param $object
     * @param string $metadataName
     * @param mixed $value
     *
     * @return bool
     */
    public function setData($object, string $metadataName, $value): bool
    {
        return $object->update_meta_data($metadataName, $value);
    }
}
