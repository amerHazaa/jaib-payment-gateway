<?php
/*
Plugin Name: WooCommerce Jaib Payment Gateway
Description: Extends WooCommerce with Jaib Payment Gateway.
Version: 1.0.0
Author: MeroOos
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the main class for the gateway
function jaib_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) return;

    // Include the gateway class
    include_once 'includes/class-wc-gateway-jaib.php';

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_jaib_gateway');

    function add_jaib_gateway($methods) {
        $methods[] = 'WC_Gateway_Jaib';
        return $methods;
    }
}

add_action('plugins_loaded', 'jaib_gateway_init', 11);

// Include the AJAX handler for testing the connection
include_once 'includes/jaib-connection.php';

// Include the AJAX handler for executing the buy
include_once 'includes/execute-buy.php';
?>