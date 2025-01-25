<?php
/*
Plugin Name: WooCommerce Jaib Payment Gateway
Description: Extends WooCommerce with Jaib Payment Gateway.
Version: 1.0.0
Author: mERO
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Create necessary database tables
function jaib_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $settings_table = $wpdb->prefix . 'jaib_settings';
    $settings_sql = "CREATE TABLE $settings_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        status VARCHAR(10) NOT NULL,
        username VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        agent_code VARCHAR(255) NOT NULL,
        request_id BIGINT(12) NOT NULL,
        login_url TEXT NOT NULL,
        exe_buy_url TEXT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $connection_history_table = $wpdb->prefix . 'jaib_connection_history';
    $connection_history_sql = "CREATE TABLE $connection_history_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        try_time DATETIME NOT NULL,
        result VARCHAR(10) NOT NULL,
        payload TEXT NOT NULL,
        response TEXT NOT NULL,
        pin_api VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $payment_history_table = $wpdb->prefix . 'jaib_payment_history';
    $payment_history_sql = "CREATE TABLE $payment_history_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        try_time DATETIME NOT NULL,
        result VARCHAR(10) NOT NULL,
        payload TEXT NOT NULL,
        response TEXT NOT NULL,
        pin_api VARCHAR(255) NOT NULL,
        request_id BIGINT(12) NOT NULL,
        payment_datetime DATETIME NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        mobile VARCHAR(15) NOT NULL,
        order_id BIGINT(20) NOT NULL,
        reference_id INT(24),
        PRIMARY KEY (id)
    ) $charset_collate;";

    $payment_table = $wpdb->prefix . 'jaib_payment';
    $payment_sql = "CREATE TABLE $payment_table (
        request_id BIGINT(12) NOT NULL,
        payment_datetime DATETIME NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        mobile VARCHAR(15) NOT NULL,
        order_id BIGINT(20) NOT NULL,
        PRIMARY KEY (request_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    dbDelta($settings_sql);
    dbDelta($connection_history_sql);
    dbDelta($payment_history_sql);
    dbDelta($payment_sql);
}

// Delete the settings table if it exists
function jaib_delete_settings_table() {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'jaib_settings';
    $wpdb->query("DROP TABLE IF EXISTS $settings_table");
}

register_activation_hook(__FILE__, 'jaib_delete_settings_table');
register_activation_hook(__FILE__, 'jaib_create_tables');

// Include the main class for the gateway
function jaib_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) return;

    // Include the gateway class and AJAX handler for Settings & connection 
    include_once 'includes/class-wc-gateway-jaib.php';

    // Add the gateway to WooCommerce
    if (!function_exists('add_jaib_gateway')) {
        function add_jaib_gateway($methods) {
            $methods[] = 'WC_Gateway_Jaib';
            return $methods;
        }
    }
    add_filter('woocommerce_payment_gateways', 'add_jaib_gateway');
}

add_action('plugins_loaded', 'jaib_gateway_init', 11);

// Include the AJAX handler for executing the buy
include_once 'includes/execute-buy.php';

// Enqueue the Jaib scripts
function enqueue_jaib_scripts() {
    wp_enqueue_script('jaib-scripts', plugins_url('includes/jaib-scripts.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('jaib-scripts', 'jaib_script_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_jaib_scripts');
?>
