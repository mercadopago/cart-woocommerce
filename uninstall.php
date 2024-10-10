<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

delete_option('_mp_access_token_prod');
delete_option('_mp_public_key_prod');
delete_option('_mp_public_key_test');
delete_option('_mp_access_token_test');
