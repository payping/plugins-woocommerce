<?php
/*
Plugin Name: Woocommerce PayPing Gateway
Version: 1.5.1
Description:  افزونه درگاه پرداخت پی‌پینگ برای ووکامرس
Plugin URI: https://www.payping.ir/
Author: Mashhadcode
Author URI: https://mashhadcode.com
*/
if (!defined('ABSPATH'))
	exit;
define('WC_GPPDIR', plugin_dir_path( __FILE__ ));
include_once("class-wc-gateway-payping.php");