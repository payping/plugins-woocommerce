<?php
/*
Plugin Name: Gateway for PayPing on WooCommerce
Version: 4.6.0
Description:  افزونه درگاه پرداخت پی‌پینگ برای ووکامرس
Plugin URI: https://www.payping.ir/
Author: Mahdi Sarani
Author URI: https://mahdisarani.ir
*/
if(!defined('ABSPATH')) exit;

define('WOO_GPPDIR', plugin_dir_path( __FILE__ ));
define('WOO_GPPDU', plugin_dir_url( __FILE__ ));

function load_payping_woo_gateway(){

	/* Add Payping Gateway Method */
	add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_payping_Gateway');
	function Woocommerce_Add_payping_Gateway($methods){
		$methods[] = 'WC_payping';
		return $methods;
	}
	/* Add Iranian Currencies Woocommerce */
	add_filter('woocommerce_currencies', 'add_IR_currency_For_PayPing');
	function add_IR_currency_For_PayPing($currencies){
		$currencies['IRR'] = __('ریال', 'woocommerce');
		$currencies['IRT'] = __('تومان', 'woocommerce');
		$currencies['IRHR'] = __('هزار ریال', 'woocommerce');
		$currencies['IRHT'] = __('هزار تومان', 'woocommerce');
		return $currencies;
	}
	/* Add Iranian Currencies Symbols Woocommerce */
	add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol_For_PayPing', 10, 2);
	function add_IR_currency_symbol_For_PayPing($currency_symbol, $currency){
		switch ($currency) {
			case 'IRR':
				$currency_symbol = 'ریال';
				break;
			case 'IRT':
				$currency_symbol = 'تومان';
				break;
			case 'IRHR':
				$currency_symbol = 'هزار ریال';
				break;
			case 'IRHT':
				$currency_symbol = 'هزار تومان';
				break;
		}
		return $currency_symbol;
	}
	require_once( WOO_GPPDIR . 'class-wc-gateway-payping.php' );
	//require_once( WOO_GPPDIR . 'block-support.php' );
}
add_action('plugins_loaded', 'load_payping_woo_gateway', 0);


/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
*/
function declare_payping_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_payping_cart_checkout_blocks_compatibility');


// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'payping_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type

 */
function payping_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            $payment_method_registry->register( new Payping_Gateway_Blocks );
        }
    );
}