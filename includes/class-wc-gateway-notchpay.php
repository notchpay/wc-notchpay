<?php

/**
 * Notch Pay for WooCommerce.
 *
 * Provides a Notch Pay Payment Gateway.
 *
 * @class       WC_Gateway_NotchPay
 * @extends     WC_Payment_Gateway
 * @version     1.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_Gateway_NotchPay extends WC_Payment_Gateway
{


	public $public_key;
	public $sandbox_key;

	private $endpoint       = "https://api.notchpay.co";
	private $callback_url;
	private $query_vars     = [];
	private $is_callback    = false;
	private $sandbox    = false;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		$this->get_callback_query_vars();

		// Get settings.
		$this->title              = $this->get_option('title');
		$this->description        = $this->get_option('description');
		$this->public_key         = $this->get_option('public_key');
		$this->sandbox_key         = $this->get_option('sandbox_key');
		$this->instructions       = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());
		$this->sandbox           = $this->get_option('sandbox') === 'yes' ? true : false;

		$this->callback_url       = wc_get_checkout_url();

		if ($this->is_callback) {
			return $this->callback_handler();
		}
		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	}

	public function get_public_key()
	{
		return $this->sandbox === "yes" ? $this->sandbox_key : $this->public_key;
	}

	public function get_callback_query_vars()
	{
		if (isset($_GET) && isset($_GET['np-callback']) && isset($_GET['reference'])) {
			$this->is_callback = true;
			$this->query_vars = $_GET;
		}
	}

	/**
	 * Check if Paystack merchant details is filled.
	 */
	public function admin_notices()
	{

		if ($this->enabled == 'no') {
			return;
		}

		// Check required fields.
		if (!$this->public_key) {
			echo '<div class="error"><p>' . sprintf(__('Please enter your Notch Pay Business details <a href="%s">here</a> to be able to use the Notch Pay WooCommerce plugin.', 'wc-notchpay'), admin_url('admin.php?page=wc-settings&tab=checkout&section=notchpay')) . '</p></div>';
			return;
		}
	}

	public function get_callback()
	{
		return $this->callback_url;
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties()
	{
		$this->id                 = 'notchpay';
		$this->icon               =
			apply_filters('wc_notchpay_icon', plugins_url('/assets/channels.png', dirname(__FILE__)));
		$this->method_title = __('Notch Pay', 'wc-notchpay');

		$this->method_description =
			sprintf(__('Notch Pay provides merchants with the tools and services to accept online payments from local and international customers using Mobile Money, Mastercard, Visa and bank accounts. <a href="%1$s" target="_blank">Sign up</a> for a Notch Pay Business account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'wc-notchpay'), 'https://business.notchpay.co', 'https://business.notchpay.co/settings/developer');
		$this->public_key = __('Public Key', 'wc-notchpay');
		$this->public_sandbox_key = __('Sandbox Key', 'wc-notchpay');
		$this->has_fields         = false;
		add_action('admin_notices', array($this, 'admin_notices'));
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{

		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'wc-notchpay'),
				'type' => 'checkbox',
				'label' => __('Enable or Disable Notch Pay', 'wc-notchpay'),
				'default' => 'no'
			),
			'sandbox' => array(
				'title' => __('Sandbox', 'wc-notchpay'),
				'type' => 'checkbox',
				'label' => __('Enable or Disable Sandbox Mode', 'wc-notchpay'),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __('Title', 'wc-notchpay'),
				'type' => 'text',
				'default' => __('Notch Pay', 'wc-notchpay'),
				'desc_tip' => true,
				'description' => __('This controls the payment method title which the user sees during checkout.', 'wc-notchpay')
			),
			'public_key' => array(
				'title' => __('Public key', 'wc-notchpay'),
				'type' => 'text',
				'desc_tip' => true,
				'description' => __('Enter your Public Key here.', 'wc-notchpay')
			),
			'sandbox_key' => array(
				'title' => __('Sandbox key', 'wc-notchpay'),
				'type' => 'text',
				'desc_tip' => true,
				'description' => __('Enter your Sandbox Key here.', 'wc-notchpay')
			),
			'description' => array(
				'title' => __('Description', 'wc-notchpay'),
				'type' => 'textarea',
				'default' => __('Make a payment using local and international payment methods.', 'wc-notchpay'),
				'desc_tip' => true,
				'description' => __('This controls the payment method description which the user sees during checkout.', 'wc-notchpay')
			),
			'instructions'       => array(
				'title'       => __('Instructions', 'woocommerce'),
				'type'        => 'textarea',
				'description' => __('Instructions that will be added to the thank you page.', 'woocommerce'),
				'default'     => __('Pay with with local or international methods.', 'wc-notchpay'),
				'desc_tip'    => true,
			),
			'autocomplete_orders' => array(
				'title' => __('Autocomplete orders', "wc-notchpay"),
				'label' => __('Autocomplete orders on payment success', "wc-notchpay"),
				'type' => 'checkbox',
				'description' => __('If enabled, orders statuses will go directly to complete after successful payment', "wc-notchpay"),
				'default' => 'no',
				'desc_tip' => true,
			),
		);
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings()
	{
		if (is_admin()) {
			// phpcs:disable WordPress.Security.NonceVerification
			if (!isset($_REQUEST['page']) || 'wc-settings' !== $_REQUEST['page']) {
				return false;
			}
			if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
				return false;
			}
			if (!isset($_REQUEST['section']) || 'notchpay' !== $_REQUEST['section']) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	public function callback_handler()
	{
		return $this->verify_transaction($this->query_vars['reference']);
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options()
	{
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if (!$this->is_accessing_settings()) {
			return array();
		}

		$data_store = WC_Data_Store::load('shipping-zone');
		$raw_zones  = $data_store->get_zones();

		foreach ($raw_zones as $raw_zone) {
			$zones[] = new WC_Shipping_Zone($raw_zone);
		}

		$zones[] = new WC_Shipping_Zone(0);

		$options = array();
		foreach (WC()->shipping()->load_shipping_methods() as $method) {

			$options[$method->get_method_title()] = array();

			// Translators: %1$s shipping method name.
			$options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'woocommerce'), $method->get_method_title());

			foreach ($zones as $zone) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance) {

					if ($shipping_method_instance->id !== $method->id) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf(__('%1$s (#%2$s)', 'woocommerce'), $shipping_method_instance->get_title(), $shipping_method_instance_id);

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf(__('%1$s &ndash; %2$s', 'woocommerce'), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'woocommerce'), $option_instance_title);

					$options[$method->get_method_title()][$option_id] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		if ($order->get_total() > 0) {
			// Mark as processing or on-hold (payment won't be taken until delivery).
			return $this->processing_with_notchpay($order);
		} else {
			$order->payment_complete();
			// Remove cart.
			WC()->cart->empty_cart();
		}
	}

	private function processing_with_notchpay($order)
	{

		$order_desc = implode(
			', ',
			array_map(
				function (WC_Order_Item $item) {
					return $item->get_name();
				},
				$order->get_items()
			)
		);
		$transaction = [
			"amount" => $order->get_total(),
			"currency" => $order->get_currency(),
			"description" => $order_desc,
			"reference" => $order->get_id() . '_' . time(),
			"callback" => $this->callback_url,
			"name" => $order->get_formatted_billing_full_name(),
			"email" => $order->get_billing_email(),
			"phone" => $order->get_billing_phone(),
		];

		$headers = array(
			'Authorization' => $this->get_public_key(),
			'Content-Type'  => 'application/json',
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 60,
			"sslverify" => false,
			'body'    => json_encode($transaction),
		);



		update_post_meta($order->get_id(), '_notchpay_txn_ref', $transaction['reference']);



		try {
			$response = wp_remote_post($this->endpoint . '/transactions/initialize', $args);

			$status = wp_remote_retrieve_response_code($response);

			if ($status == 201) {
				$data = json_decode(wp_remote_retrieve_body($response), true);

				$order->set_transaction_id($data['transaction']['reference']);
				$order->save();
				wc_clear_notices();
				wc_add_notice("Transaction initiated, Redirecting To Notch Pay to confirm payment");
				return [
					'result' => 'success',
					'redirect' => $data['authorization_url']
				];
			} else {
				wc_clear_notices();
				wc_add_notice(__('Unable to process payment try again', 'wc-notchpay'), 'error');
			}
		} catch (Exception $th) {
			$order->add_order_note("Payment init failed with message: " . $th->getMessage());

			if (isset($response)) {
				wc_notchpay_log_data('Request <-----');
				wc_notchpay_log_data($response);
			}

			if (isset($status)) {
				wc_notchpay_log_data('Response Status <-----');
				wc_notchpay_log_data($status);
			}

			if (isset($data)) {
				wc_notchpay_log_data('Response Data <-----');
				wc_notchpay_log_data($data);
			}
		}
	}

	/**
	 * Verify Transaction
	 */

	public function verify_transaction($reference)
	{
		$headers = array(
			'Authorization' => $this->get_public_key(),
			'Content-Type'  => 'application/json',
		);

		$response = wp_remote_get($this->endpoint . '/transactions/' . $reference, array(
			'headers' => $headers,
			'timeout' => 180,
			"sslverify" => false,
		));


		if (is_array($response) && !is_wp_error($response)) {
			$status = wp_remote_retrieve_response_code($response);

			if ($status == 200) {
				$data = json_decode(wp_remote_retrieve_body($response), true);
				$_trx = $data['merchant_reference'];
				$order_id = explode('_', $_trx)[0];
				$order = wc_get_order($order_id);
				if ($order = wc_get_order($order_id)) {
					if ($data['status'] == 'complete') {
						$order->update_status('completed');
					}

					if ($data['status'] == 'canceled') {
						$order->update_status('canceled');
					}

					if ($data['status'] == 'failed') {
						$order->update_status('failed');
					}

					if (in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {
						wc_clear_notices();
						wp_redirect($this->get_return_url($order));
						exit;
					}
				} else {
					wc_clear_notices();
					$notice      = sprintf(__('Order Not Found', 'wc-notchpay'), '<br />', '<br />', '<br />');
					$notice_type = 'error';

					wc_add_notice($notice, $notice_type);
				}
			} elseif ($status == 404) {
				wc_clear_notices();
				$notice      = sprintf(__('Transaction Not Found on Notch Pay Server. Retry checkout', 'wc-notchpay'), '<br />', '<br />', '<br />');
				$notice_type = 'error';
				wc_add_notice($notice, $notice_type);
			}
		}
		if (is_wp_error($response)) {
			wc_clear_notices();
			$notice      = sprintf(__('Unable to refresh handle your Transaction on Notch Pay, please refresh the page', 'wc-notchpay'), '<br />', '<br />', '<br />');
			$notice_type = 'error';
			wc_add_notice($notice, $notice_type);
		}
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page()
	{
		if ($this->instructions) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)));
		}
	}

	/**
	 * Change payment complete order status to completed for NotchPay orders.
	 *
	 * @since  3.1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
	{
		if ($order && 'notchpay' === $order->get_payment_method()) {
			$status = 'processing';
		}
		return $status;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions($order, $sent_to_admin, $plain_text = false)
	{
		if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
		}
	}
}
