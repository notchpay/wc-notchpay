<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_Gateway_NotchPay extends WC_Payment_Gateway_CC
{

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Should orders be marked as complete after payment?
	 * 
	 * @var bool
	 */
	public $autocomplete_order;

	/**
	 * Notch Pay payment page type.
	 *
	 * @var string
	 */
	public $payment_page;

	/**
	 * Notch Pay test public key.
	 *
	 * @var string
	 */
	public $test_public_key;

	/**
	 * Notch Pay test secret key.
	 *
	 * @var string
	 */
	public $test_secret_key;

	/**
	 * Notch Pay live public key.
	 *
	 * @var string
	 */
	public $live_public_key;

	/**
	 * Notch Pay live secret key.
	 *
	 * @var string
	 */
	public $live_secret_key;

	/**
	 * Should the order id be sent as a custom metadata to  Notch Pay ?
	 *
	 * @var bool
	 */
	public $meta_order_id;

	/**
	 * Should the customer name be sent as a custom metadata to  Notch Pay ?
	 *
	 * @var bool
	 */
	public $meta_name;

	/**
	 * Should the billing email be sent as a custom metadata to  Notch Pay ?
	 *
	 * @var bool
	 */
	public $meta_email;

	/**
	 * Should the billing phone be sent as a custom metadata to  Notch Pay ?
	 *
	 * @var bool
	 */
	public $meta_phone;

	/**
	 * Should the billing address be sent as a custom metadata to  Notch Pay ?
	 *
	 * @var bool
	 */
	public $meta_billing_address;

	/**
	 * Should the shipping address be sent as a custom metadata to  Notch Pay ?
	 *
	 * @var bool
	 */
	public $meta_shipping_address;


	/**
	 * API public key
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * API secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Gateway disabled message
	 *
	 * @var string
	 */
	public $msg;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->id                 = 'notchpay';
		$this->method_title       = __('Notch Pay ', 'wc-notchpay');
		$this->method_description = sprintf(__('Notch Pay provides merchants with the tools and services to accept online payments from local and international customers using Mobile Money, Mastercard, Visa and bank accounts. <a href="%1$s" target="_blank">Sign up</a> for a Notch Pay Business account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'wc-notchpay'), 'https://business.notchpay.co', 'https://business.notchpay.co/settings/developer');
		$this->has_fields         = true;

		$this->payment_page = $this->get_option('payment_page');

		$this->supports = array(
			'products',
		);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();

		// Get setting values

		$this->title              = $this->get_option('title');
		$this->description        = $this->get_option('description');
		$this->enabled            = $this->get_option('enabled');
		$this->testmode           = $this->get_option('testmode') === 'yes' ? true : false;

		$this->test_public_key = $this->get_option('test_public_key');
		$this->test_secret_key = $this->get_option('test_secret_key');

		$this->live_public_key = $this->get_option('live_public_key');
		$this->live_secret_key = $this->get_option('live_secret_key');




		$this->meta_order_id         = $this->get_option('meta_order_id') === 'yes' ? true : false;
		$this->meta_name             = $this->get_option('meta_name') === 'yes' ? true : false;
		$this->meta_email            = $this->get_option('meta_email') === 'yes' ? true : false;
		$this->meta_phone            = $this->get_option('meta_phone') === 'yes' ? true : false;
		$this->meta_billing_address  = $this->get_option('meta_billing_address') === 'yes' ? true : false;
		$this->meta_shipping_address = $this->get_option('meta_shipping_address') === 'yes' ? true : false;

		$this->public_key = $this->testmode ? $this->test_public_key : $this->live_public_key;
		$this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;

		// Hooks
		// add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

		add_action('admin_notices', array($this, 'admin_notices'));
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

		// Payment listener/API hook.
		add_action('woocommerce_api_wc_gateway_notchpay', array($this, 'verify_notchpay_transaction'));

		// Webhook listener/API hook.
		add_action('woocommerce_api_wc_notchpay_webhook', array($this, 'process_webhooks'));

		// Check if the gateway can be used.
		if (!$this->is_valid_for_use()) {
			$this->enabled = false;
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 */
	public function is_valid_for_use()
	{
		return true;
	}

	/**
	 * Display notchpay payment icon.
	 */
	public function get_icon()
	{

		$icon = '<img src="' . WC_HTTPS::force_https_url(plugins_url('assets/images/wc-notchpay.png', WC_NOTCHPAY_MAIN_FILE)) . '" alt=" Notch Pay Payment Options" />';

		return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
	}

	/**
	 * Check if Notch Pay merchant details is filled.
	 */
	public function admin_notices()
	{

		if ($this->enabled == 'no') {
			return;
		}

		// Check required fields.
		if (!($this->public_key)) {
			echo '<div class="error"><p>' . sprintf(__('Please enter your Notch Pay merchant details <a href="%s">here</a> to be able to use the Notch Pay WooCommerce plugin.', 'wc-notchpay'), admin_url('admin.php?page=wc-settings&tab=checkout&section=notchpay')) . '</p></div>';
			return;
		}
	}

	/**
	 * Check if Notch Pay gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available()
	{

		if ('yes' == $this->enabled) {

			if (!($this->public_key)) {

				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Admin Panel Options.
	 */
	public function admin_options()
	{

?>

		<h2><?php _e('Notch Pay ', 'wc-notchpay'); ?>
			<?php
			if (function_exists('wc_back_link')) {
				wc_back_link(__('Return to payments', 'wc-notchpay'), admin_url('admin.php?page=wc-settings&tab=checkout'));
			}
			?>
		</h2>

		<h4>
			<strong><?php printf(__('Optional: To avoid situations where bad network makes it impossible to verify transactions, set your webhook URL <a href="%1$s" target="_blank" rel="noopener noreferrer">here</a> to the URL below<span style="color: red"><pre><code>%2$s</code></pre></span>', 'wc-notchpay'), 'https://dashboard.notchpay.co/#/settings/developer', WC()->api_request_url('NP_WC_NotchPay_Webhook')); ?></strong>
		</h4>

		<?php

		if ($this->is_valid_for_use()) {

			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		} else {
		?>
			<div class="inline error">
				<p><strong><?php _e(' Notch Pay Payment Gateway Disabled', 'wc-notchpay'); ?></strong>: <?php echo $this->msg; ?></p>
			</div>

<?php
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{

		$form_fields = array(
			'enabled'                          => array(
				'title'       => __('Enable/Disable', 'wc-notchpay'),
				'label'       => __('Enable  Notch Pay ', 'wc-notchpay'),
				'type'        => 'checkbox',
				'description' => __('Enable Notch Pay as a payment option on the checkout page.', 'wc-notchpay'),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'title'                            => array(
				'title'       => __('Title', 'wc-notchpay'),
				'type'        => 'text',
				'description' => __('This controls the payment method title which the user sees during checkout.', 'wc-notchpay'),
				'default'     => __('Debit/Credit Cards', 'wc-notchpay'),
				'desc_tip'    => true,
			),
			'description'                      => array(
				'title'       => __('Description', 'wc-notchpay'),
				'type'        => 'textarea',
				'description' => __('This controls the payment method description which the user sees during checkout.', 'wc-notchpay'),
				'default'     => __('Make payment using your debit and credit cards', 'wc-notchpay'),
				'desc_tip'    => true,
			),
			'testmode'                         => array(
				'title'       => __('Test mode', 'wc-notchpay'),
				'label'       => __('Enable Test Mode', 'wc-notchpay'),
				'type'        => 'checkbox',
				'description' => __('Test mode enables you to test payments before going live. <br />Once the LIVE MODE is enabled on your Notch Pay account uncheck this.', 'wc-notchpay'),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'payment_page'                     => array(
				'title'       => __('Payment Option', 'wc-notchpay'),
				'type'        => 'select',
				'description' => __('Popup shows the payment popup on the page while Redirect will redirect the customer to Notch Pay to make payment.', 'wc-notchpay'),
				'default'     => 'redirect',
				'desc_tip'    => false,
				'options'     => array(
					// ''          => __('Select One', 'wc-notchpay'),
					//'inline'    => __('Popup', 'wc-notchpay'),
					'redirect'  => __('Redirect', 'wc-notchpay'),
				),
			),
			'test_public_key'                  => array(
				'title'       => __('Test Public Key', 'wc-notchpay'),
				'type'        => 'password',
				'description' => __('Enter your Test Public Key here.', 'wc-notchpay'),
				'default'     => '',
			),
			'live_public_key'                  => array(
				'title'       => __('Live Public Key', 'wc-notchpay'),
				'type'        => 'password',
				'description' => __('Enter your Live Public Key here.', 'wc-notchpay'),
				'default'     => '',
			),
			'autocomplete_order'               => array(
				'title'       => __('Autocomplete Order After Payment', 'wc-notchpay'),
				'label'       => __('Autocomplete Order', 'wc-notchpay'),
				'type'        => 'checkbox',
				'class'       => 'wc-notchpay-autocomplete-order',
				'description' => __('If enabled, the order will be marked as complete after successful payment', 'wc-notchpay'),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'meta_order_id'                    => array(
				'title'       => __('Order ID', 'wc-notchpay'),
				'label'       => __('Send Order ID', 'wc-notchpay'),
				'type'        => 'checkbox',
				'class'       => 'wc-notchpay-meta-order-id',
				'description' => __('If checked, the Order ID will be sent to  Notch Pay ', 'wc-notchpay'),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'meta_name'                        => array(
				'title'       => __('Customer Name', 'wc-notchpay'),
				'label'       => __('Send Customer Name', 'wc-notchpay'),
				'type'        => 'checkbox',
				'class'       => 'wc-notchpay-meta-name',
				'description' => __('If checked, the customer full name will be sent to  Notch Pay ', 'wc-notchpay'),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'meta_email'                       => array(
				'title'       => __('Customer Email', 'wc-notchpay'),
				'label'       => __('Send Customer Email', 'wc-notchpay'),
				'type'        => 'checkbox',
				'class'       => 'wc-notchpay-meta-email',
				'description' => __('If checked, the customer email address will be sent to  Notch Pay ', 'wc-notchpay'),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'meta_phone'                       => array(
				'title'       => __('Customer Phone', 'wc-notchpay'),
				'label'       => __('Send Customer Phone', 'wc-notchpay'),
				'type'        => 'checkbox',
				'class'       => 'wc-notchpay-meta-phone',
				'description' => __('If checked, the customer phone will be sent to  Notch Pay ', 'wc-notchpay'),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'meta_billing_address'             => array(
				'title'       => __('Order Billing Address', 'wc-notchpay'),
				'label'       => __('Send Order Billing Address', 'wc-notchpay'),
				'type'        => 'checkbox',
				'class'       => 'wc-notchpay-meta-billing-address',
				'description' => __('If checked, the order billing address will be sent to  Notch Pay ', 'wc-notchpay'),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'meta_shipping_address'            => array(
				'title'       => __('Order Shipping Address', 'wc-notchpay'),
				'label'       => __('Send Order Shipping Address', 'wc-notchpay'),
				'type'        => 'checkbox',
				'class'       => 'wc-notchpay-meta-shipping-address',
				'description' => __('If checked, the order shipping address will be sent to  Notch Pay ', 'wc-notchpay'),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);

		$this->form_fields = $form_fields;
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields()
	{

		if ($this->description) {
			echo wpautop(wptexturize($this->description));
		}

		/* if (!is_ssl()) {
			return;
		} */


		if ($this->supports('tokenization') && is_checkout() && $this->saved_cards && is_user_logged_in()) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
		}
	}

	/**
	 * Outputs scripts used for notchpay payment.
	 */
	public function payment_scripts()
	{

		if (isset($_GET['pay_for_order']) || !is_checkout_pay_page()) {
			return;
		}

		if ($this->enabled === 'no') {
			return;
		}

		$order_key = urldecode($_GET['key']);
		$order_id  = absint(get_query_var('order-pay'));

		$order = wc_get_order($order_id);

		if ($this->id !== $order->get_payment_method()) {
			return;
		}

		$suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';

		wp_enqueue_script('jquery');

		wp_enqueue_script('notchpay', 'https://js.notchpay.co/v1/inline.js', array('jquery'), WC_NOTCHPAY_VERSION, false);

		wp_enqueue_script('wc_notchpay', plugins_url('assets/js/notchpay' . $suffix . '.js', WC_NOTCHPAY_MAIN_FILE), array('jquery', 'notchpay'), WC_NOTCHPAY_VERSION, false);

		$notchpay_params = array(
			'key' => $this->public_key,
		);

		if (is_checkout_pay_page() && get_query_var('order-pay')) {

			$email         = $order->get_billing_email();
			$amount        = $order->get_total() * 100;
			$txnref        = $order_id . '_' . time();
			$the_order_id  = $order->get_id();
			$the_order_key = $order->get_order_key();
			$currency      = $order->get_currency();

			if ($the_order_id == $order_id && $the_order_key == $order_key) {

				$notchpay_params['email']    = $email;
				$notchpay_params['amount']   = $amount;
				$notchpay_params['txnref']   = $txnref;
				$notchpay_params['currency'] = $currency;
			}

			if ($this->split_payment) {

				$notchpay_params['subaccount_code'] = $this->subaccount_code;
				$notchpay_params['charges_account'] = $this->charges_account;

				if (empty($this->transaction_charges)) {
					$notchpay_params['transaction_charges'] = '';
				} else {
					$notchpay_params['transaction_charges'] = $this->transaction_charges * 100;
				}
			}

			if ($this->custom_metadata) {

				if ($this->meta_order_id) {

					$notchpay_params['meta_order_id'] = $order_id;
				}

				if ($this->meta_name) {

					$notchpay_params['meta_name'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
				}

				if ($this->meta_email) {

					$notchpay_params['meta_email'] = $email;
				}

				if ($this->meta_phone) {

					$notchpay_params['meta_phone'] = $order->get_billing_phone();
				}

				if ($this->meta_products) {

					$line_items = $order->get_items();

					$products = '';

					foreach ($line_items as $item_id => $item) {
						$name      = $item['name'];
						$quantity  = $item['qty'];
						$products .= $name . ' (Qty: ' . $quantity . ')';
						$products .= ' | ';
					}

					$products = rtrim($products, ' | ');

					$notchpay_params['meta_products'] = $products;
				}

				if ($this->meta_billing_address) {

					$billing_address = $order->get_formatted_billing_address();
					$billing_address = esc_html(preg_replace('#<br\s*/?>#i', ', ', $billing_address));

					$notchpay_params['meta_billing_address'] = $billing_address;
				}

				if ($this->meta_shipping_address) {

					$shipping_address = $order->get_formatted_shipping_address();
					$shipping_address = esc_html(preg_replace('#<br\s*/?>#i', ', ', $shipping_address));

					if (empty($shipping_address)) {

						$billing_address = $order->get_formatted_billing_address();
						$billing_address = esc_html(preg_replace('#<br\s*/?>#i', ', ', $billing_address));

						$shipping_address = $billing_address;
					}

					$notchpay_params['meta_shipping_address'] = $shipping_address;
				}
			}

			update_post_meta($order_id, '_notchpay_txn_ref', $txnref);
		}

		wp_localize_script('wc_notchpay', 'wc_notchpay_params', $notchpay_params);
	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts()
	{

		if ('woocommerce_page_wc-settings' !== get_current_screen()->id) {
			return;
		}


		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		$notchpay_admin_params = array(
			'plugin_url' => WC_NOTCHPAY_URL,
		);

		wp_enqueue_script('wc_notchpay_admin', plugins_url('assets/js/notchpay-admin' . $suffix . '.js', WC_NOTCHPAY_MAIN_FILE), array(), WC_NOTCHPAY_VERSION, true);

		wp_localize_script('wc_notchpay_admin', 'wc_notchpay_admin_params', $notchpay_admin_params);
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id
	 *
	 * @return array|void
	 */
	public function process_payment($order_id)
	{

		return $this->process_redirect_payment($order_id);
	}

	/**
	 * Process a redirect payment option payment.
	 *
	 * @since 5.7
	 * @param int $order_id
	 * @return array|void
	 */
	public function process_redirect_payment($order_id)
	{

		$order        = wc_get_order($order_id);
		$amount       = $order->get_total();
		$txnref       = $order_id . '_' . time();

		$callback_url = WC()->api_request_url('WC_Gateway_NotchPay');



		$notchpay_params = array(
			'amount'       => $amount,
			'email'        => $order->get_billing_email(),
			'currency'     => $order->get_currency(),
			'reference'    => $txnref,
			'callback' => $callback_url,
		);

		$custom_fields = array();


		if ($this->meta_name) {

			$custom_fields[] = array(
				'name'  =>
				$order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			);
		}

		if ($this->meta_email) {

			$custom_fields[] = array(
				'email'  =>
				$order->get_billing_email(),
			);
		}

		if ($this->meta_phone) {
			$custom_fields[] = array(
				'phone'  =>
				$order->get_billing_phone(),
			);
		}

		$notchpay_params['metadata']['cancel_action'] = wc_get_cart_url();



		$_data = array_merge($notchpay_params, $custom_fields);

		//$notchpay_params[] = $this->get_custom_fields($order_id);


		update_post_meta($order_id, '_notchpay_txn_ref', $txnref);

		$notchpay_url = 'https://api.notchpay.test/payments/initialize/';

		$headers = array(
			'Authorization' =>  $this->public_key,
			'Content-Type'  => 'application/json',
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 60,
			"sslverify" => false,
			'body'    => json_encode($_data),
		);


		$request = wp_remote_post($notchpay_url, $args);

		$status = wp_remote_retrieve_response_code($request);

		if ($status == 201) {
			$notchpay_response = json_decode(wp_remote_retrieve_body($request), true);

			return array(
				'result'   => 'success',
				'redirect' => $notchpay_response['authorization_url'],
			);
		} else {
			wc_add_notice(__('Unable to process payment try again', 'wc-notchpay'), 'error');

			return;
		}
	}

	/**
	 * Displays the payment page.
	 *
	 * @param $order_id
	 */
	public function receipt_page($order_id)
	{

		$order = wc_get_order($order_id);

		echo '<div id="wc-notchpay-form">';

		echo '<p>' . __('Thank you for your order, please click the button below to pay with  Notch Pay .', 'wc-notchpay') . '</p>';

		echo '<div id="notchpay_form"><form id="order_review" method="post" action="' . WC()->api_request_url('WC_Gateway_NotchPay') . '"></form><button class="button" id="notchpay-payment-button">' . __('Pay Now', 'wc-notchpay') . '</button>';

		if (!$this->remove_cancel_order_button) {
			echo '  <a class="button cancel" id="notchpay-cancel-payment-button" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'wc-notchpay') . '</a></div>';
		}

		echo '</div>';
	}

	/**
	 * Verify Notch Pay payment.
	 */
	public function verify_notchpay_transaction()
	{

		if (isset($_REQUEST['txnref'])) {
			$notchpay_txn_ref = sanitize_text_field($_REQUEST['txnref']);
		} elseif (isset($_REQUEST['notchpay_txnref'])) {
			$notchpay_txn_ref = sanitize_text_field($_REQUEST['notchpay_txnref']);
		} elseif (isset($_REQUEST['reference'])) {
			$notchpay_txn_ref = sanitize_text_field($_REQUEST['reference']);
		} else {
			$notchpay_txn_ref = false;
		}



		@ob_clean();

		if ($notchpay_txn_ref) {

			$notchpay_url = 'https://api.notchpay.co/payments/' . $notchpay_txn_ref;

			$headers = array(
				'Authorization' => $this->public_key,
			);

			$args = array(
				'headers' => $headers,
				'timeout' => 60,
				"sslverify" => false,
			);

			$request = wp_remote_get($notchpay_url, $args);



			if (200 == wp_remote_retrieve_response_code($request)) {

				$notchpay_response = json_decode(wp_remote_retrieve_body($request));



				if ('complete' == $notchpay_response->transaction->status) {

					$order_details = explode('_', $notchpay_response->transaction->merchant_reference);
					$order_id      = (int) $order_details[0];
					$order         = wc_get_order($order_id);

					if (in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {

						wp_redirect($this->get_return_url($order));

						exit;
					}

					$order_total      = $order->get_total();
					$order_currency   = $order->get_currency();
					$currency_symbol  = get_woocommerce_currency_symbol($order_currency);
					$amount_paid      = $notchpay_response->transaction->converted_amount;
					$notchpay_ref     = $notchpay_response->transaction->reference;
					$payment_currency = strtoupper($notchpay_response->transaction->currency);
					$gateway_symbol   = get_woocommerce_currency_symbol($payment_currency);

					// check if the amount paid is equal to the order amount.
					if ($amount_paid < $order_total) {

						$order->update_status('on-hold', '');

						add_post_meta($order_id, '_transaction_id', $notchpay_ref, true);

						$notice      = sprintf(__('Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'wc-notchpay'), '<br />', '<br />', '<br />');
						$notice_type = 'notice';

						// Add Customer Order Note
						$order->add_order_note($notice, 1);

						// Add Admin Order Note
						$admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong> Notch Pay Transaction Reference:</strong> %9$s', 'wc-notchpay'), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $notchpay_ref);
						$order->add_order_note($admin_order_note);

						function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

						wc_add_notice($notice, $notice_type);
					} else {

						if ($payment_currency !== $order_currency) {

							$order->update_status('on-hold', '');

							update_post_meta($order_id, '_transaction_id', $notchpay_ref);

							$notice      = sprintf(__('Thank you for shopping with us.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.', 'wc-notchpay'), '<br />', '<br />', '<br />');
							$notice_type = 'notice';

							// Add Customer Order Note
							$order->add_order_note($notice, 1);

							// Add Admin Order Note
							$admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong> Notch Pay Transaction Reference:</strong> %9$s', 'wc-notchpay'), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $notchpay_ref);
							$order->add_order_note($admin_order_note);

							function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

							wc_add_notice($notice, $notice_type);
						} else {

							$order->payment_complete($notchpay_ref);
							$order->add_order_note(sprintf(__('Payment via Notch Pay successful (Transaction Reference: %s)', 'wc-notchpay'), $notchpay_ref));

							if ($this->is_autocomplete_order_enabled($order)) {
								$order->update_status('completed');
							}
						}
					}


					WC()->cart->empty_cart();
				} else {

					$order_details = explode('_', $_REQUEST['notchpay_txnref']);

					$order_id = (int) $order_details[0];

					$order = wc_get_order($order_id);

					$order->update_status('failed', __('Payment was declined by  Notch Pay .', 'wc-notchpay'));
				}
			}

			wp_redirect($this->get_return_url($order));

			exit;
		}

		wp_redirect(wc_get_page_permalink('cart'));

		exit;
	}

	/**
	 * Process Webhook.
	 */
	public function process_webhooks()
	{

		if ((strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') || !array_key_exists('HTTP_X_NOTCHPAY_SIGNATURE', $_SERVER)) {
			exit;
		}

		$json = file_get_contents('php://input');

		// validate event do all at once to avoid timing attack.
		if ($_SERVER['HTTP_X_NOTCHPAY_SIGNATURE'] !== hash_hmac('sha512', $json, $this->secret_key)) {
			exit;
		}

		$event = json_decode($json);

		if ('transaction.complete' == $event->event) {

			sleep(10);

			$order_details = explode('_', $event->data->reference);

			$order_id = (int) $order_details[0];

			$order = wc_get_order($order_id);

			$notchpay_txn_ref = get_post_meta($order_id, '_notchpay_txn_ref', true);

			if ($event->data->reference != $notchpay_txn_ref) {
				exit;
			}

			http_response_code(200);

			if (in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {
				exit;
			}

			$order_currency = $order->get_currency();

			$currency_symbol = get_woocommerce_currency_symbol($order_currency);

			$order_total = $order->get_total();

			$amount_paid = $event->data->amount;

			$notchpay_ref = $event->data->reference;

			$payment_currency = strtoupper($event->data->currency);

			$gateway_symbol = get_woocommerce_currency_symbol($payment_currency);

			// check if the amount paid is equal to the order amount.
			if ($amount_paid < $order_total) {

				$order->update_status('on-hold', '');

				add_post_meta($order_id, '_transaction_id', $notchpay_ref, true);

				$notice      = sprintf(__('Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'wc-notchpay'), '<br />', '<br />', '<br />');
				$notice_type = 'notice';

				// Add Customer Order Note.
				$order->add_order_note($notice, 1);

				// Add Admin Order Note.
				$admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong> Notch Pay Transaction Reference:</strong> %9$s', 'wc-notchpay'), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $notchpay_ref);
				$order->add_order_note($admin_order_note);

				function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

				wc_add_notice($notice, $notice_type);

				WC()->cart->empty_cart();
			} else {

				if ($payment_currency !== $order_currency) {

					$order->update_status('on-hold', '');

					update_post_meta($order_id, '_transaction_id', $notchpay_ref);

					$notice      = sprintf(__('Thank you for shopping with us.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.', 'wc-notchpay'), '<br />', '<br />', '<br />');
					$notice_type = 'notice';

					// Add Customer Order Note.
					$order->add_order_note($notice, 1);

					// Add Admin Order Note.
					$admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong> Notch Pay Transaction Reference:</strong> %9$s', 'wc-notchpay'), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $notchpay_ref);
					$order->add_order_note($admin_order_note);

					function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

					wc_add_notice($notice, $notice_type);
				} else {

					$order->payment_complete($notchpay_ref);

					$order->add_order_note(sprintf(__('Payment via Notch Pay successful (Transaction Reference: %s)', 'wc-notchpay'), $notchpay_ref));

					WC()->cart->empty_cart();

					if ($this->is_autocomplete_order_enabled($order)) {
						$order->update_status('completed');
					}
				}
			}


			exit;
		}

		exit;
	}



	/**
	 * Checks if WC version is less than passed in version.
	 *
	 * @param string $version Version to check against.
	 *
	 * @return bool
	 */
	public function is_wc_lt($version)
	{
		return version_compare(WC_VERSION, $version, '<');
	}

	/**
	 * Checks if autocomplete order is enabled for the payment method.
	 *
	 * @since 5.7
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	protected function is_autocomplete_order_enabled($order)
	{
		$autocomplete_order = false;

		$payment_method = $order->get_payment_method();

		$notchpay_settings = get_option('woocommerce_' . $payment_method . '_settings');

		if (isset($notchpay_settings['autocomplete_order']) && 'yes' === $notchpay_settings['autocomplete_order']) {
			$autocomplete_order = true;
		}

		return $autocomplete_order;
	}

	/**
	 * Retrieve the payment channels configured for the gateway
	 *
	 * @since 5.7
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	protected function get_gateway_payment_channels($order)
	{

		$payment_method = $order->get_payment_method();

		if ('notchpay' === $payment_method) {
			return array();
		}

		$payment_channels = $this->payment_channels;

		if (empty($payment_channels)) {
			$payment_channels = array('card');
		}

		return $payment_channels;
	}
}
