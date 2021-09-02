<?php
/*
Plugin Name: Woocommerce PayPing Gateway
Version: 4.0.0
Description:  افزونه درگاه پرداخت پی‌پینگ برای ووکامرس
Plugin URI: https://www.payping.ir/
Author: Mahdi Sarani
Author URI: https://mahdisarani.ir
*/
if(!defined('ABSPATH')) exit;

define('WC_GPPDIR', plugin_dir_path( __FILE__ ));
function Load_payping_Gateway(){
	/* Show Debug In Console */
	function WC_GPP_Debug_Log($Debug_Mode='no', $object=null, $label=null ){
		if($Debug_Mode === 'yes'){
			$object = $object; 
			$message = json_encode( $object, JSON_UNESCAPED_UNICODE);
			$label = "Debug".($label ? " ($label): " : ': '); 
			echo "<script>console.log(\"$label\", $message);</script>";

			file_put_contents(WC_GPPDIR.'/log_payping.txt', $label."\n".$message."\n\n", FILE_APPEND);
		}
	}
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
	require_once( WC_GPPDIR . 'class-wc-gateway-payping.php' );
}
add_action('plugins_loaded', 'Load_payping_Gateway', 0);