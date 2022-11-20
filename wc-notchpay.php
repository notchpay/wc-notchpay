<?php

/**
 * Plugin Name: Notch Pay for WooCommerce
 * Plugin URI:  https://notchpay.co
 * Author:      Chapdel KAMGA
 * Author URI:  http://chapdel.me
 * Description: Accept local and international payments with Notch Pay.
 * Version:     2.0.2
 * License:     GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 6.1
 * WC tested up to: 6.9
 * text-domain: wc-notchpay
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

define('WC_NOTCHPAY_MAIN_FILE', __FILE__);
define('WC_NOTCHPAY_URL', untrailingslashit(plugins_url('/', __FILE__)));

define('WC_NOTCHPAY_VERSION', '2.0');

/**
 * Initialize Notch Pay WooCommerce payment gateway.
 */
function wc_notchpay_init()
{

	load_plugin_textdomain('wc-notchpay', false, plugin_basename(dirname(__FILE__)) . '/languages');

	if (!class_exists('WC_Payment_Gateway')) {
		add_action('admin_notices', 'wc_notchpay_wc_missing_notice');
		return;
	}

	add_action('admin_notices', 'wc_notchpay_testmode_notice');

	require_once dirname(__FILE__) . '/includes/class-wc-gateway-notchpay.php';

	add_filter('woocommerce_payment_gateways', 'tbz_wc_add_notchpay_gateway', 99);

	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'tbz_woo_notchpay_plugin_action_links');
}
add_action('plugins_loaded', 'wc_notchpay_init', 99);

/**
 * Add Settings link to the plugin entry in the plugins menu.
 *
 * @param array $links Plugin action links.
 *
 * @return array
 **/
function tbz_woo_notchpay_plugin_action_links($links)
{

	$settings_link = array(
		'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=notchpay') . '" title="' . __('View Notch Pay WooCommerce Settings', 'wc-notchpay') . '">' . __('Settings', 'wc-notchpay') . '</a>',
	);

	return array_merge($settings_link, $links);
}

/**
 * Add Notch Pay Gateway to WooCommerce.
 *
 * @param array $methods WooCommerce payment gateways methods.
 *
 * @return array
 */
function tbz_wc_add_notchpay_gateway($methods)
{

	$methods[] = 'WC_Gateway_NotchPay';

	return $methods;
}

/**
 * Display a notice if WooCommerce is not installed
 */
function wc_notchpay_wc_missing_notice()
{
	echo '<div class="error"><p><strong>' . sprintf(__('Notch Pay requires WooCommerce to be installed and active. Click %s to install WooCommerce.', 'wc-notchpay'), '<a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce&TB_iframe=true&width=772&height=539') . '" class="thickbox open-plugin-details-modal">here</a>') . '</strong></p></div>';
}

/**
 * Display the test mode notice.
 **/
function wc_notchpay_testmode_notice()
{

	if (!current_user_can('manage_options')) {
		return;
	}

	$notchpay_settings = get_option('woocommerce_notchpay_settings');
	$test_mode         = isset($notchpay_settings['testmode']) ? $notchpay_settings['testmode'] : '';

	if ('yes' === $test_mode) {
		/* translators: 1. Notch Pay settings page URL link. */
		echo '<div class="error"><p>' . sprintf(__(' Notch Pay test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.', 'wc-notchpay'), esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=notchpay'))) . '</p></div>';
	}
}
