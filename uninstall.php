<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;

$wpdb->query("DELETE FROM wp_options WHERE option_name LIKE '_mp_public_key%' ");
$wpdb->query("DELETE FROM wp_options WHERE option_name LIKE '_mp_access_token%' ");
