<?php
if(!defined('ABSPATH'))exit;

if( class_exists('WC_Payment_Gateway') && !class_exists('WC_payping') ){
	class WC_payping extends WC_Payment_Gateway{
	    
        private $baseurl = 'https://api.payping.ir/v2';
        private $paypingToken;
        private $success_massage;
        private $failed_massage;
        private $Debug_Mode;
        
		public function __construct(){
		    
			$this->id = 'WC_payping';
			$this->method_title = __('پرداخت از طریق درگاه پی‌پینگ', 'woocommerce');
			$this->method_description = __('تنظیمات درگاه پرداخت پی‌پینگ برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
			$this->icon = apply_filters('woo_payping_logo', WOO_GPPDU.'/assets/images/logo.png');
			$this->has_fields = false;
			$this->init_form_fields();
			$this->init_settings();

			$checkserver = $this->settings['ioserver'];
			if( $checkserver == 'yes')$this->baseurl  = 'https://api.payping.io/v2';
			
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];

			$this->paypingToken = $this->settings['paypingToken'];

			$this->success_massage = $this->settings['success_massage'];
			$this->failed_massage = $this->settings['failed_massage'];

			$this->Debug_Mode = $this->settings['Debug_Mode'];

			if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			else
				add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));

			add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_payping_Gateway'));
			add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_payping_Gateway'));


		}

		public function admin_options(){
			parent::admin_options();
		}

		public function init_form_fields(){
			$this->form_fields = apply_filters('WC_payping_Config', array(
					'base_confing' => array(
						'title' => __('تنظیمات پایه ای', 'woocommerce'),
						'type' => 'title',
						'description' => '',
					),
					'enabled' => array(
						'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('فعالسازی درگاه پی‌پینگ', 'woocommerce'),
						'description' => __('برای فعالسازی درگاه پرداخت پی‌پینگ باید چک باکس را تیک بزنید', 'woocommerce'),
						'default' => 'yes',
						'desc_tip' => true,
					),
					'ioserver' => array(
						'title' => __('سرور خارج', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('اتصال به سرور خارج', 'woocommerce'),
						'description' => __('در صورت تیک خوردن، درگاه به سرور خارج از کشور متصل می‌شود.', 'woocommerce'),
						'default' => 'no',
						'desc_tip' => true,
					),
					'title' => array(
						'title' => __('عنوان درگاه', 'woocommerce'),
						'type' => 'text',
						'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
						'default' => __('پرداخت از طریق پی‌پینگ', 'woocommerce'),
						'desc_tip' => true,
					),
					'description' => array(
						'title' => __('توضیحات درگاه', 'woocommerce'),
						'type' => 'text',
						'desc_tip' => true,
						'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
						'default' => __('پرداخت به وسیله کلیه کارت های عضو شتاب از طریق درگاه پی‌پینگ', 'woocommerce')
					),
					'account_confing' => array(
						'title' => __('تنظیمات حساب پی‌پینگ', 'woocommerce'),
						'type' => 'title',
						'description' => '',
					),
					'paypingToken' => array(
						'title' => __('توکن', 'woocommerce'),
						'type' => 'text',
						'description' => __('توکن درگاه پی‌پینگ', 'woocommerce'),
						'default' => '',
						'desc_tip' => true
					),
					'payment_confing' => array(
						'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
						'type' => 'title',
						'description' => '',
					),
					'success_massage' => array(
						'title' => __('پیام پرداخت موفق', 'woocommerce'),
						'type' => 'textarea',
						'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) پی‌پینگ استفاده نمایید .', 'woocommerce'),
						'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
					),
					'failed_massage' => array(
						'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
						'type' => 'textarea',
						'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید .', 'woocommerce'),
						'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
					)
				)
			);
		}

		public function process_payment($order_id){
			$order = new WC_Order($order_id);
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url(true)
			);
		}

		function isJson($string) {
			json_decode($string);
			return (json_last_error() == JSON_ERROR_NONE);
		}

		public function Send_to_payping_Gateway($order_id){
			$paypingpayCode = get_post_meta($order_id, '_payping_payCode', true);
			if( $paypingpayCode ){
				wp_redirect( sprintf('%s/pay/gotoipg/%s', $this->baseurl, $paypingpayCode )) ;
				exit;
			}
			global $woocommerce;
			$woocommerce->session->order_id_payping = $order_id;
			
			$order = new WC_Order($order_id);
			$currency = $order->get_currency();
			$currency = apply_filters('WC_payping_Currency', $currency, $order_id);
			
			$form = '<form action="" method="POST" class="payping-checkout-form" id="payping-checkout-form">
					<input type="submit" name="payping_submit" class="button alt" id="payping-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
					<a class="button cancel" href="' . wc_get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
				 </form><br/>';

			$form = apply_filters('WC_payping_Form', $form, $order_id, $woocommerce);

			do_action('WC_payping_Gateway_Before_Form', $order_id, $woocommerce);
			echo $form;
			do_action('WC_payping_Gateway_After_Form', $order_id, $woocommerce);

			$Amount = intval( $order->get_total() );

			$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
			$Amount = $this->payping_check_currency( $Amount, $currency );

			$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
			$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
			$Amount = apply_filters('woocommerce_order_amount_total_payping_gateway', $Amount, $currency);

			$CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_payping'));

			$products = array();
			$order_items = $order->get_items();
			foreach ((array)$order_items as $product) {
				$products[] = $product['name'] . ' (' . $product['qty'] . ') ';
			}
			$products = implode(' - ', $products);

			$Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' | محصولات : ' . $products;
			$Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '-';
			$Email = $order->get_billing_email();
			$Paymenter = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$ResNumber = intval($order->get_order_number());

			//Hooks for iranian developer
			$Description = apply_filters('WC_payping_Description', $Description, $order_id);
			$Mobile = apply_filters('WC_payping_Mobile', $Mobile, $order_id);
			$Email = apply_filters('WC_payping_Email', $Email, $order_id);
			$Paymenter = apply_filters('WC_payping_Paymenter', $Paymenter, $order_id);
			$ResNumber = apply_filters('WC_payping_ResNumber', $ResNumber, $order_id);
			do_action('WC_payping_Gateway_Payment', $order_id, $Description, $Mobile);
			$Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
			$Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';
			if ( $Email == '' )
				$payerIdentity = $Mobile;
			else
				$payerIdentity = $Email;
			$data = array(
				'payerName'=>$Paymenter,
				'Amount' => $Amount,
				'payerIdentity'=> $payerIdentity ,
				'returnUrl' => $CallbackUrl,
				'Description' => $Description ,
				'clientRefId' => $order->get_order_number()
			);

			$args = array(
				'body' => json_encode($data),
				'timeout' => '45',
				'redirection' => '5',
				'httpsversion' => '1.0',
				'blocking' => true,
			   'headers' => array(
				  'Authorization' => 'Bearer '.$this->paypingToken,
				  'Content-Type'  => 'application/json',
				  'Accept' => 'application/json'
				  ),
				'cookies' => array()
			);

			$api_url  = apply_filters( 'WC_payping_Gateway_Payment_api_url', $this->baseurl . '/pay', $order_id );

			$api_args = apply_filters( 'WC_payping_Gateway_Payment_api_args', $args, $order_id );

			$response = wp_remote_post($api_url, $api_args);

			$ERR_ID = wp_remote_retrieve_header($response, 'x-paypingrequest-id');
			if( is_wp_error($response) ){
				$Message = $response->get_error_message();
			}else{	
				$code = wp_remote_retrieve_response_code( $response );
				if( $code === 200){
					if (isset($response["body"]) and $response["body"] != '') {
						$code_pay = wp_remote_retrieve_body($response);
						$code_pay =  json_decode($code_pay, true);
						update_post_meta($order_id, '_payping_payCode', $code_pay["code"] );
						$Note = 'ساخت موفق پرداخت، کد پرداخت: '.$code_pay["code"];
						$order->add_order_note($Note, 1, false);
						wp_redirect(sprintf('%s/pay/gotoipg/%s', $this->baseurl, $code_pay["code"]));
						exit;
					} else {
						$Message = ' تراکنش ناموفق بود- کد خطا : '.$ERR_ID;
						$Fault = $Message;
					}
				}else{
					$Message = wp_remote_retrieve_body( $response ).'<br /> کد خطا: '.$ERR_ID;
					$Fault = $Message;
				}
			}

			if(!empty($Message) && $Message){
				$Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
				$Fault = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Fault);
				$Note = apply_filters('woo_payping_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
				wc_add_notice($Fault, 'error');
				$order->add_order_note($Note, 0, false);
				do_action('woo_payping_Send_to_Gateway_Failed', $order_id, $Fault);
			}
		}

		public function Return_from_payping_Gateway(){
			global $woocommerce;
			
			if( isset( $_REQUEST['wc_order'] ) ){
				$order_id = sanitize_text_field( $_REQUEST['wc_order'] );
			}else{
				$order_id = $woocommerce->session->order_id_payping;
				unset( $woocommerce->session->order_id_payping );
			}

			// Get refid
			if( isset( $_REQUEST['refid'] ) ){
				$refid = sanitize_text_field( $_REQUEST['refid'] );
			}else{
				$refid = null;
			}
			
			$order_id = apply_filters('WC_payping_return_order_id', $order_id);
			
			
			if( isset( $order_id ) ){
				if( $refid != null && $refid > 1000 ){
					update_post_meta($order_id, 'woo_payping_refid', $refid );
				}

				// Get Order id
				$order = new WC_Order($order_id);
				// Get Currency Order
				$currency = $order->get_currency();
				// Add Filter For Another Developer
				$currency = apply_filters('WC_payping_Currency', $currency, $order_id);

				if( $order->get_status() != 'completed' ){
					// Get Amount
					$Amount = intval($order->get_total());
					/* add filter for other developer */
					$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
					/* check currency and set amount */
					$Amount = $this->payping_check_currency( $Amount, $currency );

					// Add Filter for ANother Developer
					$refid = apply_filters('WC_payping_return_refid', $refid);
					$Transaction_ID = $refid;
					
					//Set Data 
					$data = array('refId' => $refid, 'amount' => $Amount);
					$args = array(
						'body' => json_encode($data),
						'timeout' => '45',
						'redirection' => '5',
						'httpsversion' => '1.0',
						'blocking' => true,
						'headers' => array(
						'Authorization' => 'Bearer ' . $this->paypingToken,
						'Content-Type'  => 'application/json',
						'Accept' => 'application/json'
						),
					 'cookies' => array()
					);
					
				// Add Filter for use Another developer
				$verify_api_url = apply_filters( 'WC_payping_Gateway_Payment_verify_api_url', $this->baseurl . '/pay/verify', $order_id );
				//response
				$response = wp_remote_post($verify_api_url, $args);
				
				$body = wp_remote_retrieve_body( $response );
				$rbody = json_decode( $body, true );
				
				$ERR_ID = wp_remote_retrieve_header($response, 'x-paypingrequest-id');
				if( is_wp_error($response) ){
					$Status = 'failed';
					$Fault = $response->get_error_message();
					$Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$response->get_error_message();
				}else{
					$code = wp_remote_retrieve_response_code( $response );
					$txtmsg = $this->status_message( $code );
					
					//check cardNumber payer
					if(isset($rbody['cardNumber'])){
						$cardNumber = $rbody['cardNumber'];
						update_post_meta($order_id, 'payping_payment_card_number', $cardNumber);
					}else{
						$cardNumber = '-';
					}
					
					if( $code === 200 ){
						if( isset( $refid ) and $refid != '' ){
							$Status = 'completed';
							$Message = $txtmsg.'<br>شماره کارت: <b dir="ltr">'.$cardNumber.'</b>';
						}else{
							$Status = 'failed';
							$Message = 'متاسفانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' . $body .'<br /> شماره خطا: '.$ERR_ID;
							$Fault = 'خطای دریافت کد پیگیری!';
						}
					}elseif( $code == 400){
						if( array_key_exists('15', $rbody) ){
							$Status = 'completed';
							$Message = $txtmsg.'<br>شماره کارت: <b dir="ltr">'.$cardNumber.'</b>';
						}elseif( array_key_exists( '1', $rbody) ){
							$Status = 'failed';
							$Message = "کاربر در صفحه بانک از پرداخت انصراف داده است.<br> شماره خطا: $ERR_ID";
							$Fault = 'تراكنش توسط شما لغو شد.';
						}else{
							$Status = 'failed';
							$Message = $txtmsg."<br> شماره خطا: $ERR_ID";
							$Fault = 'خطایی رخ داده است، با مدیریت سایت تماس بگیرید.';
						}
					}else{
						$Status = 'failed';
						$Message = $txtmsg.'<br> شماره خطا: '.$ERR_ID;
						$Fault = 'خطای نامشخص در تایید پرداخت!';
					}
				}
				
					if( isset( $Transaction_ID ) && $Transaction_ID != 0 ){
						update_post_meta($order_id, '_transaction_id', $Transaction_ID );
						if( $Status == 'completed' ){
							$Note = sprintf( __('%s <br> شماره سفارش: %s <br>', 'woocommerce'), $Message, $Transaction_ID) ;
							$Note = apply_filters('WC_payping_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID );
							$Notice = wpautop(wptexturize($this->success_massage));
							$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);
							$Notice = apply_filters('WC_payping_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
							do_action('WC_payping_Return_from_Gateway_Success', $order_id, $Transaction_ID, $response);
							wc_add_notice($Message, 'error');
							$order->add_order_note( $Message, 0, false);
							$woocommerce->cart->empty_cart();
							$order->payment_complete($Transaction_ID);
							wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
							exit;
						}else{
							$tr_id = ($Transaction_ID && $Transaction_ID != 0) ? ('<br/>کد پیگیری: ' . $Transaction_ID) : '';
							$Note = sprintf(__('خطا در هنگام تایید پرداخت: %s', 'woocommerce'), $Message, $tr_id);
							$Note = apply_filters('WC_payping_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
							$Notice = wpautop(wptexturize($Note));
							$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);
							$Notice = str_replace("{fault}", $Message, $Notice);
							$Notice = apply_filters('WC_payping_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
							do_action('WC_payping_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);
							wc_add_notice($Fault, 'error');
							$order->add_order_note( $Notice, 0, false);
							wp_redirect(wc_get_checkout_url());
							exit;
						}
					}else{
						wc_add_notice($Fault, 'error');
						$order->add_order_note( $Notice, 0, false);
						update_post_meta($order_id, '_transaction_id', $Transaction_ID );
					}
				}else{
					$Transaction_ID = get_post_meta($order_id, '_transaction_id', true);
					$Notice = wpautop(wptexturize($this->success_massage.' شناسه خطای پی پینگ:'.$ERR_ID));
					$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);
					$Notice = apply_filters('WC_payping_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
					do_action('WC_payping_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);
					wc_add_notice($Fault, 'error');
					$order->add_order_note( $Notice, 0, false);
					wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
					exit;
				}
			}else{
				$Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
				$Notice = wpautop(wptexturize($this->failed_massage.' شناسه خطای پی پینگ:'.$ERR_ID));
				$Notice = str_replace("{fault}", $Fault, $Notice);
				$Notice = apply_filters('WC_payping_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
				wc_add_notice($Fault, 'error');
				$order->add_order_note( $Notice, 0, false);
				do_action('WC_payping_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);
				wp_redirect(wc_get_checkout_url());
				exit;
			}
		}

		public function payping_check_currency( $Amount, $currency ){
			if( strtolower( $currency ) == strtolower('IRT') || strtolower( $currency ) == strtolower('TOMAN') || strtolower( $currency ) == strtolower('Iran TOMAN') || strtolower( $currency ) == strtolower('Iranian TOMAN') || strtolower( $currency ) == strtolower('Iran-TOMAN') || strtolower( $currency ) == strtolower('Iranian-TOMAN') || strtolower( $currency ) == strtolower('Iran_TOMAN') || strtolower( $currency ) == strtolower('Iranian_TOMAN') || strtolower( $currency ) == strtolower('تومان') || strtolower( $currency ) == strtolower('تومان ایران') ){
				$Amount = $Amount * 1;
			}elseif(strtolower($currency) == strtolower('IRHT')){
				$Amount = $Amount * 1000;
			}elseif( strtolower( $currency ) == strtolower('IRHR') ){
				$Amount = $Amount * 100;					
			}elseif( strtolower( $currency ) == strtolower('IRR') ){
				$Amount = $Amount / 10;
			}
			return  $Amount;                      
		}

		public function status_message($code){
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
		}

	}
}
