<?php
/*
Plugin Name: افزونه پرداخت پی‌پینگ برای Easy Digital Downloads
Version: 1.0
Description:  افزونه درگاه پرداخت پی‌پینگ برای Easy Digital Downloads
Plugin URI: https://www.payping.ir/
Author: Erfan Ebrahimi
Author URI: http://erfanebrahimi.ir/

*/

/**
 * Add Toman currency for EDD
 *
 * @param 				array $currencies Currencies list
 * @return 				array
 */
if ( ! function_exists('irg_add_toman_currencyP')):
function irg_add_tomain_currencyP( $currencies ) {
	$currencies['IRT'] = 'تومان';
	return $currencies;
}
endif;
add_filter( 'edd_currencies', 'irg_add_tomain_currency' );

/**
 * Format decimals
 */
add_filter( 'edd_sanitize_amount_decimals', function( $decimals ) {
	
	$currency = function_exists('edd_get_currency') ? edd_get_currency() : '';
	
	global $edd_options;
	
	if ( $edd_options['currency'] == 'IRT' || $currency == 'IRT' || $edd_options['currency'] == 'RIAL' || $currency == 'RIAL' ) {
		return $decimals = 0;
	}

	return $decimals;
} );

add_filter( 'edd_format_amount_decimals', function( $decimals ) {
	
	$currency = function_exists('edd_get_currency') ? edd_get_currency() : '';
	
	global $edd_options;
	
	if ( $edd_options['currency'] == 'IRT' || $currency == 'IRT' || $edd_options['currency'] == 'RIAL' || $currency == 'RIAL' ) {
		return $decimals = 0;
	}
	
	return $decimals;
} );

if ( function_exists('per_number') ) {
	add_filter( 'edd_irt_currency_filter_after', 'per_number', 10, 2 );
}

add_filter( 'edd_irt_currency_filter_after', 'toman_postfixP', 10, 2 );
function toman_postfixP( $price, $did ) {
	return str_replace( 'IRT', 'تومان', $price );
}

add_filter( 'edd_rial_currency_filter_after', 'rial_postfixP', 10, 2 );
function rial_postfixP( $price, $did ) {
	return str_replace( 'RIAL', 'ریال', $price );
}
