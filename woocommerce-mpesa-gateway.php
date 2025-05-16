<?php
/*
Plugin Name: WooCommerce M-Pesa Gateway
Description: Adds M-Pesa payment gateway to WooCommerce, including Checkout Block support
Version: 1.1.9
Author: Josephat BlackenedSeed Nyakundi
Requires Plugins: woocommerce
*/

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', function() {
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        require_once plugin_dir_path(__FILE__) . 'class-wc-gateway-mpesa.php';
        if (class_exists('WC_Gateway_Mpesa')) {
            add_filter('woocommerce_payment_gateways', 'add_mpesa_gateway', 10, 1);
            add_action('wp_enqueue_scripts', 'enqueue_mpesa_block_scripts');
            error_log('M-Pesa Gateway registered');
        } else {
            error_log('M-Pesa Gateway class not found');
        }
    } else {
        error_log('WooCommerce not active');
    }
});

function add_mpesa_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Mpesa';
    error_log('M-Pesa Gateway added to list: ' . print_r($gateways, true));
    return $gateways;
}

function enqueue_mpesa_block_scripts() {
    if (is_checkout()) {
        $script_path = plugin_dir_path(__FILE__) . 'assets/js/mpesa-checkout-block.min.js';
        $script_url = plugin_dir_url(__FILE__) . 'assets/js/mpesa-checkout-block.min.js';
        $version = file_exists($script_path) ? filemtime($script_path) : '1.0.0';

        wp_enqueue_script(
            'mpesa-checkout-block',
            $script_url,
            array('wc-blocks-checkout', 'wp-element', 'wp-i18n'),
            $version,
            true
        );
        wp_set_script_translations('mpesa-checkout-block', 'woocommerce-mpesa-gateway');
        error_log('M-Pesa block script enqueued');
    }
}