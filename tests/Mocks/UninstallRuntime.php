<?php

// A fresh CLI process substitutes only the WordPress options, HTTP and logging boundaries.
if (PHP_SAPI !== 'cli') {
    exit;
}

$scenario = json_decode(stream_get_contents(STDIN), true);
$state = ['deleted' => [], 'requests' => [], 'logs' => []];
$options = $scenario['options'];

define('ABSPATH', sys_get_temp_dir() . '/mp-uninstall-wordpress/');
if ($scenario['authorized']) {
    define('WP_UNINSTALL_PLUGIN', 'woocommerce-mercadopago/woocommerce-mercadopago.php');
}

register_shutdown_function(function () use (&$state, &$options) {
    $state['options'] = $options;
    $state['plugin_loaded'] = isset($GLOBALS['mercadopago']);
    $state['woocommerce_loaded'] = class_exists('WooCommerce', false);
    $state['plugin_version_defined'] = defined('MP_VERSION');
    $state['client_loaded'] = class_exists('MercadoPago\\Woocommerce\\Api\\Core\\FunnelClient', false);
    echo json_encode($state);
});

function get_option($name, $default = false)
{
    global $options;
    return $options[$name] ?? $default;
}

function delete_option($name)
{
    global $state, $options;
    $state['deleted'][] = $name;
    unset($options[$name]);
    return true;
}

function wp_json_encode($value)
{
    return json_encode($value);
}

class UninstallWordPressError
{
    public function get_error_code()
    {
        return 'http_request_failed';
    }
}

function wp_remote_request($url, $args)
{
    global $state, $scenario;
    $state['requests'][] = ['url' => $url, 'args' => $args];
    if ($scenario['failure'] === 'exception') {
        throw new RuntimeException('private-upstream-diagnostic');
    }
    if ($scenario['failure'] === 'error') {
        throw new Error('private-upstream-diagnostic');
    }
    if ($scenario['failure'] === 'wp_error') {
        return new UninstallWordPressError();
    }
    return [
        'status' => $scenario['failure'] === 'http_error' ? 500 : 204,
        'body' => $scenario['failure'] === 'http_error' ? '{"message":"private-upstream-diagnostic"}' : '',
    ];
}

function is_wp_error($response)
{
    return $response instanceof UninstallWordPressError;
}

function wp_remote_retrieve_body($response)
{
    return $response['body'];
}

function wp_remote_retrieve_response_code($response)
{
    return $response['status'];
}

function wp_remote_retrieve_headers($response)
{
    return [];
}

if ($scenario['logger_available']) {
    function wc_get_logger()
    {
        return new class {
            public function error($message, $context)
            {
                global $state, $scenario;
                if ($scenario['logger_fails']) {
                    throw new Error('private-logger-diagnostic');
                }
                $state['logs'][] = ['message' => $message, 'context' => $context];
            }
        };
    }
}

require $scenario['uninstall_path'];
