<?php
/**
Plugin Name: CITADEL Merchant for WooCommerce
Plugin URI: https://github.com/phelephant/woo-citadelmerchant
Author: Phelephant
Author URI: https://github.com/phelephant
Description: CITADEL Merchant Payment Gateway. Accept crypto payments with ease. Note: you will need a CTIADEL Merchant API key to use this plugin.
Version: 1.0
WC tested up to: 3.6.4
*/

defined( 'ABSPATH' ) || exit;

function add_citadel_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_Citadel_Merchant';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_citadel_gateway_class' );

class CMA_EX extends Exception {

}
class CMA {
	public function __construct($api_secret, $api_public)
	{
		$this->api_secret = $api_secret;
		$this->api_public = $api_public;
		$this->base_url = 'https://citadel.li/merchant_api/v1/';
	}

	public function _http_wp($url, $method, $data, $flags)
	{
		$content = '';
		$ctype = 'application/x-www-form-urlencoded';
		$auth_header = 'X-Citadel-Auth';
		$auth_value = $this->api_secret;
		if ($flags == 'json') {
			$content = json_encode($data);
			$ctype = 'application/json';
		}
		if ($flags == 'pub') {
			$auth_header = 'X-Citadel-Public';
			$auth_value = $this->api_public;
		}

		$response = wp_remote_request($url, array(
			'method' => $method,
			'body' => $content,
	//		'cookies' => array()
	/*		'timeout' => '5',
			'redirection' => '5',
			'httpversion' => '1.0',
	*/
			'blocking' => true,
			'headers' => array(
				'Content-Type' => $ctype,
				'Content-Length' => strlen($content),
				$auth_header => $auth_value,
			),
		));
		$result = wp_remote_retrieve_body( $response );

		if (!$result) throw new CMA_EX(__('CITADEL Gateway Error', 'woothemes'));
		try {
			$result = json_decode($result, true);
		} catch (Exception $e) {
			throw new CMA_EX(__('CITADEL Gateway Error', 'woothemes').": can not decode response");
		}
		return $result;
	}

	public function fullURL($url)
	{
		return rtrim($this->base_url, '/').'/'.ltrim($url,'/');
	}

	public function http_request($url, $method='GET', $data=array(), $flags='')
	{
		$url = $this->fullURL($url);
		return $this->_http_wp($url, $method, $data, $flags);
	}
	public function get_ticker($lcoin, $rcoin)
	{
		return $this->http_request('/bitshares/ticker/'.$lcoin.'/'.$rcoin, 'GET', [ ], 'pub');
	}
	public function get_invoice($invoice_id)
	{
		return $this->http_request('/invoices/'.$invoice_id, 'GET');
	}
	private function precise($amt, $prec)
	{
		return sprintf("%0.".$prec."f", $amt);
	}
	public function place_order(WC_Payment_Gateway $gw, WC_Order $order, $coin, $rate=1, $prec=8)
	{
		$base_currency = $order->get_currency();
		$base_total = $order->get_total();
		$total = $base_total;
		if ($coin != $base_currency)
		{
			$total = $this->precise($base_total * $rate, $prec);
		}
		$site_url = get_bloginfo( 'url' ) ;
		$callback_url = $site_url . '?wc-api=WC_Gateway_Citadel_Merchant';

		$positions = array();
		$count_total = 0;
		$items = $order->get_items();
		foreach ($items as $item)
		{
			$item_total = $this->precise($item->get_total() * $rate, $prec);
			$item_price = $this->precise($item_total / $item->get_quantity(), $prec);
			while ($this->precise($item_price * $item->get_quantity(), $prec)
			        < $item_total)
			{
				$item_total -= (1/pow(10,$prec));
			}
			$item_total = $this->precise($item_total, $prec);

			$positions[] = array(
				"description"=> $item->get_name(),
				"quantity"=> $item->get_quantity(),
				"coin"=> $coin,
				"total"=> $item_total,
				"price"=> $item_price,
			);
			$count_total += $item_total;
		}
		if ($count_total > 0 && $count_total < $total)
		{
			$rest = $this->precise($total - $count_total, $prec);
			$positions[] = array(
				"description"=> 'Coin difference',
				"quantity"=> 1,
				"coin"=> $coin,
				"total"=> $rest,
				"price"=> $rest,
			);
		}
		if ($gw->get_option('hide_positions') == 'yes')
		{
			$positions = array();
		}
		$data = array(
			"userdata_id" => $order->get_id(),
			"callback_url" => $callback_url,
			"callback_method" => 'GET',
			"return_url"=> $gw->get_return_url( $order ),
			"description" => "Order #" . $order->get_id(),
			"total_amount" => $total,
			"total_coin"=> $coin,
			"positions"=> $positions,
			/* "cashout"=> array(
				"method"=> "string",
				"address"=> "string"
			) */
		);
//echo "<pre>";print_r($data);exit;//debug debug
		$resp = $this->http_request('/invoices', 'PUT', $data, 'json');
		if ($resp) {
			update_post_meta( $order->get_id(), '_citadel_status', $resp['status'] );
			update_post_meta( $order->get_id(), '_citadel_invoice_id', $resp['invoice_id'] );
			$this->payment_url = $resp['payment_url'];
			$order->add_order_note( __('Created CITADEL Invoice ', 'woothemes') . $resp['invoice_id'] . ' ' . $resp['total_amount'] . ' ' . $resp['coin'] );
		}
		return $resp;
	}
}

add_action( 'plugins_loaded', 'init_citadel_gateway_class' );

function init_citadel_gateway_class() {
if (!class_exists('WC_Payment_Gateway')) {
	/* If Woocommerce hasn't been installed yet, abort */
	return;
}
class WC_Gateway_Citadel_Merchant extends WC_Payment_Gateway {

	public static $log_enabled = false;
	public static $log = null;

	public function payment_fields()
	{
		echo __('Currency', 'woocommerce');
		echo ": ";

		$coins = $this->allowed_coins;
		?>

		<select name="_cm_currency">
		<?php foreach ($coins as $c) { ?>
			<option><?php echo $c ?></option>
		<?php } ?>
		</select>

		<?php
	}

	public function validate_fields()
	{
		global $woocommerce;
		$coin = isset($_POST['_cm_currency']) ? $_POST['_cm_currency'] : null;
		$coins = $this->allowed_coins;
		if (!in_array($coin, $coins))
		{
			$error_message = "Bad coin ".$coin;
			wc_add_notice( __('Payment error:', 'woothemes') . $error_message, 'error' );
			return false;
		}
		$this->selected_coin = $coin;
		return true;
	}

	public function capture_payment($order_id, $invoice_id = null)
	{
		$order = wc_get_order( $order_id );
		if (!$invoice_id)
		{
			$invoice_id = $order->get_meta('_citadel_invoice_id', true);
		}
		if (!$invoice_id)
		{
			return 0;
		}

		$res = $this->rpc->get_invoice($invoice_id);

		update_post_meta( $order->get_id(), '_citadel_status', $res['status'] );
		$this->rpc->payment_url = $res['payment_url'];
		if ($res['status'] == 'open')
		{
			//$order->add_order_note( __('CITADEL is now processing Invoice', 'woothemes') . $invoice_id ); */
			//$order->update_status('processing', __( 'Processing payment', 'woocommerce' ));
		}
		if ($res['status'] == 'paid')
		{
			$order->payment_complete($invoice_id);
			return 2;
		}
		if ($res['status'] == 'expired')
		{
			update_post_meta( $order->get_id(), '_citadel_invoice_id', '' );
			$order->add_order_note( __('CITADEL Invoice has expired: ', 'woothemes') . $invoice_id );
			return 0;
		}

		return 1;
		//echo $order->get_transaction_id();
		//if ( 'paypal' === $order->get_payment_method() && 'open' === $order->get_meta( '_citadel_status', true ) && $order->get_transaction_id() ) {
	}

	private function thank_you($order)
	{
		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url( $order )
		);
	}

	public function process_payment($order_id)
	{
		global $woocommerce;
		$order = new WC_Order( $order_id );

		// Wait! Already have a CITADEL Invoice!
		$invoice_id = $order->get_meta('_citadel_invoice_id', true);
		if ($invoice_id)
		{
			$r = $this->capture_payment($order, $invoice_id);
			if ($r == 2)
			{
				// All done
				return $this->thank_you($order);
			}
			if ($r == 1)
			{
				// Redirect to CITADEL
				return array(
					'result' => 'success',
					'redirect' => $this->rpc->payment_url,
				);
			}
		}

		$coin = $this->selected_coin;
		$prec = 2;
		$rate = 1.0;
		$base_currency = $order->get_currency();

		if ($coin != $base_currency)
		{
			$rates = $this->rpc->get_ticker($base_currency, $coin);
			$rate = $rates['buy'];
			$prec = $rates['q_precision'];
		}

		$this->rpc->place_order($this, $order, $coin, $rate, $prec);

		// Mark as processing (we're awaiting the payment)
		//$order->update_status('processing', __( 'Awaiting payment', 'woocommerce' ));

		// Redirect to CITADEL
		return array(
			'result' => 'success',
			'redirect' => $this->rpc->payment_url,
		);
/*
		// Mark as on-hold (we're awaiting the cheque)
		$order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));

		// Reduce stock levels
		$order->reduce_order_stock();

		// Remove cart
		$woocommerce->cart->empty_cart();

		// Return thank you redirect
		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url( $order )
		);
*/
	}

	public function check_ipn_response() {
		$_DATA = $_POST;
		if (empty($_POST)) $_DATA = $_GET;
		if ( ! empty( $_DATA ) ) {//&& $this->validate_ipn() ) { // WPCS: CSRF ok.
			$posted = wp_unslash( $_DATA ); // WPCS: CSRF ok, input var ok.

			self::log("Got IPN call: ".print_r($posted, 1));

			$invoice_id = isset($posted['invoice_id']) ? $posted['invoice_id'] : null;
			$user_data = isset($posted['userdata']) ? $posted['userdata'] : null;

			if ($user_data && $invoice_id)
			{
				try {
					$order_id = (int)$user_data;
					$order = new WC_Order( $order_id );
				} catch(Exception $e) {
					self::log("Error in IPN data: ".$e->getMessage());
					$order = null;
				}
				if ($order)
				{
					$await_invoice_id = $order->get_meta('_citadel_invoice_id', true);
					if ($await_invoice_id == $invoice_id)
					{
						$this->capture_payment($order_id, $invoice_id);
					}
					else {
						self::log("Invoice ID mismatch");
					}
				}
			}
			else {
				self::log("Not enough data :(");
			}

			/* Tell CITADEL to stop calling us */
			echo "1";
			exit;
		}

		wp_die( 'Citadel IPN Request Failure', 'Citadel IPN', array( 'response' => 500 ) );
	}

	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'citadel_merchant' ) );
		}
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'hide_positions' => array(
			'title' => __( 'Hide Cart Items', 'woocommerce' ),
			'type' => 'checkbox',
			'label' => __( 'Tick this box to remove all item information from the invoice.', 'woocommerce' ),
			'default' => 'no'
		),
		'api_secret' => array(
			'title' => __( 'CITADEL Merchant APP Secret', 'woocommerce' ),
			'type' => 'password',
			'description' => __( 'Your APP Secret, as provided on CITADEL Merchant Dashboard.', 'woocommerce' ),
			//'default' => 'RASN5SoU-6aKk-zLSL-jYqL-dL3tlQSawDt6',
			'desc_tip' => false,
		),
		'api_public' => array(
			'title' => __( 'CITADEL Merchant APP Key', 'woocommerce' ),
			'type' => 'text',
			'description' => __( 'Your APP Key, as provided on CITADEL Merchant Dashboard.', 'woocommerce' ),
			//'default' => '',
			'desc_tip' => false,
		),
		'title' => array(
			'title' => __( 'Title', 'woocommerce' ),
			'type' => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
			'default' => 'CITADEL Merchant',
			'desc_tip' => true,
		),
		'allowed_coins' => array(
			'title' => __( 'Allowed Coins', 'woocommerce' ),
			'type' => 'textarea',
			'description' => __( 'This controls the coins the user can use to pay. Put one coin per line.', 'woocommerce' ),
			'default' => "BTC\nXMR"
		)
		);
	}


	public function __construct() {

		self::$log_enabled = true;

		$this->id = "citadel_merchant";
		$this->icon = false;
		$this->has_fields = false;
		$this->method_title = "CITADEL Merchant";
		$this->method_description = "CITADEL Merchant Payment Gateway";

		$this->has_fields = true;

		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		$this->title = $this->get_option( 'title' );
		$this->rpc = new CMA($this->get_option( 'api_secret' ),$this->get_option( 'api_public' ));
		$this->allowed_coins = $this->read_allowed_coins();

		add_action('woocommerce_api_wc_gateway_citadel_merchant', array($this, 'check_ipn_response'));

		add_action( 'woocommerce_order_status_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_payment' ) );
	}

	private function read_allowed_coins() {
		$cs = $this->get_option('allowed_coins');
		$s = preg_split('/\n|\s|,/', $cs, -1, PREG_SPLIT_NO_EMPTY);
		return $s;
	}
    }
}


?>