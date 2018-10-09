<?php
/*
Plugin Name: افزونه پرداخت پی‌پینگ برای Easy Digital Downloads
Version: 1.0
Description:  افزونه درگاه پرداخت پی‌پینگ برای Easy Digital Downloads
Plugin URI: https://www.payping.ir/
Author: Erfan Ebrahimi
Author URI: http://erfanebrahimi.ir/

*/
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_payping_Gateway' ) ) :

	class EDD_payping_Gateway {

		public function __construct() {

			add_filter( 'edd_payment_gateways', array( $this, 'add' ) );
			add_action( 'edd_payping_cc_form' , array( $this, 'cc_form' ) );
			add_action( 'edd_gateway_payping' , array( $this, 'process' ) );
			add_action( 'edd_verify_payping' , array( $this, 'verify' ) );
			add_filter( 'edd_settings_gateways', array( $this, 'settings' ) );

			add_action( 'edd_payment_receipt_after', array( $this, 'receipt' ) );

			add_action( 'init', array( $this, 'listen' ) );
		}


		public function add( $gateways ) {
			global $edd_options;

			$gateways[ 'payping' ] = array(
				'checkout_label' 		=>	isset( $edd_options['payping_label'] ) ? $edd_options['payping_label'] : 'درگاه پرداخت آنلاین پی‌پینگ',
				'admin_label' 			=>	'پی‌پینگ'
			);

			return $gateways;
		}


		public function cc_form() {
			return;
		}

		public function process( $purchase_data ) {
			global $edd_options;
			@ session_start();
			$payment = $this->insert_payment( $purchase_data );

			if ( $payment ) {

				$tokenCode = ( isset( $edd_options[ 'payping_tokenCode' ] ) ? $edd_options[ 'payping_tokenCode' ] : '' );
				$desc = 'پرداخت شماره #' . $payment;
				$callback = add_query_arg( 'verify_payping', '1', get_permalink( $edd_options['success_page'] ) );

				$amount = intval( $purchase_data['price'] ) ;
				if ( $edd_options['payping_currency'] == 'IRR' )
					$amount = $amount / 10; // Return back to original one.

				$data = array(
					'clientRefId' 			=>	$payment,
					'payerIdentity'         =>  $purchase_data['user_email'],
					'Amount' 				=>	$amount,
					'Description' 			=>	$desc,
					'returnUrl' 			=>	$callback
				) ;

				try {
					$curl = curl_init();

					curl_setopt_array($curl, array(
							CURLOPT_URL => "https://api.payping.ir/v1/pay",
							CURLOPT_RETURNTRANSFER => true,
							CURLOPT_ENCODING => "",
							CURLOPT_MAXREDIRS => 10,
							CURLOPT_TIMEOUT => 30,
							CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
							CURLOPT_CUSTOMREQUEST => "POST",
							CURLOPT_POSTFIELDS => json_encode($data),
							CURLOPT_HTTPHEADER => array(
								"accept: application/json",
								"authorization: Bearer " . $tokenCode,
								"cache-control: no-cache",
								"content-type: application/json"
							),
						)
					);

					$response = curl_exec($curl);
					$header = curl_getinfo($curl);
					$err = curl_error($curl);
					curl_close($curl);

					if ($err) {
						edd_insert_payment_note( $payment, 'کد خطا: CURL#' . $err );
						edd_update_payment_status( $payment, 'failed' );
						edd_set_error( 'payping_connect_error', 'در اتصال به درگاه مشکلی پیش آمد.' );
						edd_send_back_to_checkout();
						return false;
					} else {
						if ($header['http_code'] == 200) {
							$response = json_decode($response, true);
							if (isset($response["code"]) and $response["code"] != '') {
								edd_insert_payment_note( $payment, 'کد تراکنش پی‌پینگ: ' . $response["code"] );
								edd_update_payment_meta( $payment, 'payping_code', $response["code"] );
								$_SESSION['pp_payment'] = $payment;
								wp_redirect( sprintf( 'https://api.payping.ir/v1/pay/gotoipg/%s', $response["code"] ) );
							} else {
								$Message = ' تراکنش ناموفق بود- شرح خطا : عدم وجود کد ارجاع ';
								edd_insert_payment_note( $payment, $Message  );
								edd_update_payment_status( $payment, 'failed' );
								edd_set_error( 'payping_connect_error', $Message );
								edd_send_back_to_checkout();
							}
						} elseif ($header['http_code'] == 400) {
							$Message = ' تراکنش ناموفق بود- شرح خطا : ' . implode('. ',array_values (json_decode($response,true)));
							edd_insert_payment_note( $payment, $Message  );
							edd_update_payment_status( $payment, 'failed' );
							edd_set_error( 'payping_connect_error', $Message );
							edd_send_back_to_checkout();
						} else {
							$Message = ' تراکنش ناموفق بود- شرح خطا : ' . $this->error_reason($header['http_code']) . '(' . $header['http_code'] . ')';
							edd_insert_payment_note( $payment, $Message  );
							edd_update_payment_status( $payment, 'failed' );
							edd_set_error( 'payping_connect_error', $Message );
							edd_send_back_to_checkout();
						}
					}
				} catch (Exception $e){
					$Message = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
					edd_insert_payment_note( $payment, $Message  );
					edd_update_payment_status( $payment, 'failed' );
					edd_set_error( 'payping_connect_error', $Message );
					edd_send_back_to_checkout();
				}


			} else {
				edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
			}
		}


		public function verify() {
			global $edd_options;

			if ( isset( $_GET['refid'] ) ) {
				$refid = sanitize_text_field( $_GET['refid'] );
				$payment = edd_get_payment( $_SESSION['pp_payment'] );
				unset( $_SESSION['pp_payment'] );
				if ( ! $payment ) {
					wp_die( 'رکورد پرداخت موردنظر وجود ندارد!' );
				}
				if ( $payment->status == 'complete' ) return false;

				$amount = intval( edd_get_payment_amount( $payment->ID ) ) ;
				if ( edd_get_currency() == 'IRR' )
					$amount = $amount / 10; // Return back to original one.

				$tokenCode = ( isset( $edd_options[ 'payping_tokenCode' ] ) ? $edd_options[ 'payping_tokenCode' ] : '' );

				$data = json_encode( array(
					'amount' 				=>	$amount,
					'refId'				=>	$refid
				) );

				try {
					$curl = curl_init();
					curl_setopt_array($curl, array(
						CURLOPT_URL => "https://api.payping.ir/v1/pay/verify",
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING => "",
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_TIMEOUT => 30,
						CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST => "POST",
						CURLOPT_POSTFIELDS => json_encode($data),
						CURLOPT_HTTPHEADER => array(
							"accept: application/json",
							"authorization: Bearer ".$tokenCode,
							"cache-control: no-cache",
							"content-type: application/json",
						),
					));
					$response = curl_exec($curl);
					$err = curl_error($curl);
					$header = curl_getinfo($curl);
					curl_close($curl);


					edd_empty_cart();

					if ( version_compare( EDD_VERSION, '2.1', '>=' ) )
						edd_set_payment_transaction_id( $payment->ID, $refid );

					if ($err) {
						$Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$err;
						edd_insert_payment_note( $payment->ID, $Message );
						edd_update_payment_meta( $payment->ID, 'payping_refid', $refid );
						edd_update_payment_status( $payment->ID, 'failed' );
						wp_redirect( get_permalink( $edd_options['failure_page'] ) );
					} else {
						if ($header['http_code'] == 200) {
							$response = json_decode($response, true);
							if (isset($refid) and $refid != '') {
								edd_insert_payment_note( $payment->ID, 'شماره تراکنش بانکی: ' . $refid );
								edd_update_payment_meta( $payment->ID, 'payping_refid', $refid );
								edd_update_payment_status( $payment->ID, 'publish' );
								edd_send_to_success_page();
							} else {
								$Message = 'متافسانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' . $this->error_reason($header['http_code']) . '(' . $header['http_code'] . ')' ;
								edd_insert_payment_note( $payment->ID, $Message );
								edd_update_payment_meta( $payment->ID, 'payping_refid', $refid );
								edd_update_payment_status( $payment->ID, 'failed' );
								wp_redirect( get_permalink( $edd_options['failure_page'] ) );
							}
						} elseif ($header['http_code'] == 400) {
							$Message = ' تراکنش ناموفق بود- شرح خطا : ' . implode('. ',array_values (json_decode($response,true)));
							edd_insert_payment_note( $payment->ID, $Message );
							edd_update_payment_meta( $payment->ID, 'payping_refid', $refid );
							edd_update_payment_status( $payment->ID, 'failed' );
							wp_redirect( get_permalink( $edd_options['failure_page'] ) );
						} else {
							$Message = ' تراکنش ناموفق بود- شرح خطا : ' . $this->error_reason($header['http_code']) . '(' . $header['http_code'] . ')';
							edd_insert_payment_note( $payment->ID, $Message );
							edd_update_payment_meta( $payment->ID, 'payping_refid', $refid );
							edd_update_payment_status( $payment->ID, 'failed' );
							wp_redirect( get_permalink( $edd_options['failure_page'] ) );
						}

					}
				} catch (Exception $e){
					$Message = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
					edd_insert_payment_note( $payment->ID, $Message );
					edd_update_payment_meta( $payment->ID, 'payping_refid', $refid );
					edd_update_payment_status( $payment->ID, 'failed' );
					wp_redirect( get_permalink( $edd_options['failure_page'] ) );
				}

			}
		}

		/**
		 * Receipt field for payment
		 *
		 * @param 				object $payment
		 * @return 				void
		 */
		public function receipt( $payment ) {
			$refid = edd_get_payment_meta( $payment->ID, 'payping_refid' );
			if ( $refid ) {
				echo '<tr class="payping-ref-id-row ezp-field ehsaan-me"><td><strong>شماره تراکنش بانکی:</strong></td><td>' . $refid . '</td></tr>';
			}
		}

		/**
		 * Gateway settings
		 *
		 * @param 				array $settings
		 * @return 				array
		 */
		public function settings( $settings ) {
			return array_merge( $settings, array(
				'payping_header' 		=>	array(
					'id' 			=>	'payping_header',
					'type' 			=>	'header',
					'name' 			=>	'افزونه درگاه پرداخت <strong>پی‌پینگ</strong><br> توسعه دهنده : <a href="http://erfanebrahimi.com">ابراهیمی</a>'
				),
				'payping_tokenCode' 		=>	array(
					'id' 			=>	'payping_tokenCode',
					'name' 			=>	'کد توکن اختصاصی',
					'type' 			=>	'text',
					'size' 			=>	'regular'
				),
				'payping_currency' 		=>	array(
					'id' 			=>	'payping_currency',
					'name' 			=>	'واحد پولی وبسایت شما',
					'type' 			=>	'radio',
					'options' 		=>	array( 'IRT' => 'تومان', 'IRR' => 'ریال' ),
					'std' 			=>	edd_get_currency()
				),
				'payping_label' 	=>	array(
					'id' 			=>	'payping_label',
					'name' 			=>	'نام درگاه در صفحه پرداخت',
					'type' 			=>	'text',
					'size' 			=>	'regular',
					'std' 			=>	'پرداخت از طریق پی‌پینگ'
				)
			) );
		}


		/**
		 * Inserts a payment into database
		 *
		 * @param 			array $purchase_data
		 * @return 			int $payment_id
		 */
		private function insert_payment( $purchase_data ) {
			global $edd_options;

			$payment_data = array(
				'price' => $purchase_data['price'],
				'date' => $purchase_data['date'],
				'user_email' => $purchase_data['user_email'],
				'purchase_key' => $purchase_data['purchase_key'],
				'currency' => $edd_options['currency'],
				'downloads' => $purchase_data['downloads'],
				'user_info' => $purchase_data['user_info'],
				'cart_details' => $purchase_data['cart_details'],
				'status' => 'pending'
			);

			// record the pending payment
			$payment = edd_insert_payment( $payment_data );

			return $payment;
		}

		/**
		 * Listen to incoming queries
		 *
		 * @return 			void
		 */
		public function listen() {
			if ( isset( $_GET[ 'verify_payping' ] ) && $_GET[ 'verify_payping' ] ) {
				do_action( 'edd_verify_payping' );
			}
		}


		public function error_reason( $code ) {
			switch ($code){
				case 200 :
					return 'عملیات با موفقیت انجام شد';
					break ;
				case 400 :
					return 'مشکلی در ارسال درخواست وجود دارد';
					break ;
				case 500 :
					return 'مشکلی در سرور رخ داده است';
					break;
				case 503 :
					return 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد';
					break;
				case 401 :
					return 'عدم دسترسی';
					break;
				case 403 :
					return 'دسترسی غیر مجاز';
					break;
				case 404 :
					return 'آیتم درخواستی مورد نظر موجود نمی‌باشد';
					break;
			}
			return '';
		}
	}

endif;

new EDD_payping_Gateway;
