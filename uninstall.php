<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option( 'woocommerce-mercadopago' );
delete_site_option( 'woocommerce-mercadopago' );
