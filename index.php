<?php
/*
Plugin Name: Gateway for PayPing on WooCommerce
Version: 4.3.0
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
}
add_action('plugins_loaded', 'load_payping_woo_gateway', 0);
