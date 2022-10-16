<?php

/**
 * Plugin Name: Notch Pay for WooCommerce
 * Plugin URI:  https://notchpay.co
 * Author:      Chapdel KAMGA
 * Author URI:  http://chapdel.me
 * Description: Accept local and international payments.
 * Version:     1.0.1
 * License:     GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: woo-notchpay
 */



if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;


add_action('plugins_loaded', 'notchpay_payment_init', 11);

add_filter('woocommerce_payment_gateways', 'add_to_woo_notchpay_payment_gateway');

function notchpay_payment_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        require_once plugin_dir_path(__FILE__) . '/includes/class-wc-gateway-notchpay.php';
        require_once plugin_dir_path(__FILE__) . '/includes/notchpay-order-statuses.php';
    }
}
function add_to_woo_notchpay_payment_gateway($gateways)
{
    $gateways[] = 'WC_Gateway_NotchPay';
    return $gateways;
}
