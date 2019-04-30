<?php
if (!defined('ABSPATH'))
	exit;

function Load_payping_Gateway()
{

	if (class_exists('WC_Payment_Gateway') && !class_exists('WC_PPal') && !function_exists('Woocommerce_Add_payping_Gateway')) {
        
        /* Show Debug In Console */
        function WC_GPP_Debug_Log($Debug_Mode='no', $object=null, $label=null )
        {
            if($Debug_Mode === 'yes'){
                $object = $object; 
                $message = json_encode( $object, JSON_UNESCAPED_UNICODE);
                $label = "Debug".($label ? " ($label): " : ': '); 
                echo "<script>console.log(\"$label\", $message);</script>";

                file_put_contents(WC_GPPDIR.'/log_payping.txt', $label."\n".$message."\n\n", FILE_APPEND);
            }
        }

		add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_payping_Gateway');

		function Woocommerce_Add_payping_Gateway($methods)
		{
			$methods[] = 'WC_PPal';
			return $methods;
		}

		add_filter('woocommerce_currencies', 'add_IR_currency_For_PayPing');

		function add_IR_currency_For_PayPing($currencies)
		{
			$currencies['IRR'] = __('ریال', 'woocommerce');
			$currencies['IRT'] = __('تومان', 'woocommerce');

			return $currencies;
		}

		add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol_For_PayPing', 10, 2);

		function add_IR_currency_symbol_For_PayPing($currency_symbol, $currency)
		{
			switch ($currency) {
				case 'IRR':
					$currency_symbol = 'ریال';
					break;
				case 'IRT':
					$currency_symbol = 'تومان';
					break;
			}
			return $currency_symbol;
		}

		class WC_PPal extends WC_Payment_Gateway
		{

			public function __construct()
			{

				$this->id = 'WC_PPal';
				$this->method_title = __('پرداخت از طریق درگاه پی‌پینگ', 'woocommerce');
				$this->method_description = __('تنظیمات درگاه پرداخت پی‌پینگ برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
				$this->icon = apply_filters('WC_PPal_logo', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/logo.png');
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

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


			public function admin_options()
			{
				parent::admin_options();
			}

			public function init_form_fields()
			{
				$this->form_fields = apply_filters('WC_PPal_Config', array(
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
						),
                        'Debug_Confing' => array(
							'title' => __('تنظیمات اشکال زدایی <span style="font-size:12px;color:red;">این بخش برای توسعه دهندگان است.(در صورت نداشتن اطلاعات کافی آن را رها کنید).</span>', 'woocommerce'),
							'type' => 'title',
							'description' => '',
						),
                        'Debug_Mode' => array(
							'title' => __('فعالسازی حالت اشکال زدایی<br/><span style="font-size:11px;color:red;">از فعالسازی این گزینه در حالت عادی خودداری نمایید.</span>', 'woocommerce'),
							'type' => 'checkbox',
                            'label' => __('حالت اشکال زدایی', 'woocommerce'),
							'description' => __('برای فعالسازی حالت اشکال زدایی تیک بزنید.', 'woocommerce'),
							'default' => 'no',
							'desc_tip' => true
						)
					)
				);
			}

			public function process_payment($order_id)
			{
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
			function status_message($code) {
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

			public function Send_to_payping_Gateway($order_id)
			{
				global $woocommerce;
				$woocommerce->session->order_id_payping = $order_id;
				$order = new WC_Order($order_id);
				$currency = $order->get_currency();
				$currency = apply_filters('WC_PPal_Currency', $currency, $order_id);

				$form = '<form action="" method="POST" class="payping-checkout-form" id="payping-checkout-form">
						<input type="submit" name="payping_submit" class="button alt" id="payping-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
				$form = apply_filters('WC_PPal_Form', $form, $order_id, $woocommerce);

				do_action('WC_PPal_Gateway_Before_Form', $order_id, $woocommerce);
				echo $form;
				do_action('WC_PPal_Gateway_After_Form', $order_id, $woocommerce);


				$Amount = intval($order->order_total);
				$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
				if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
				)
					$Amount = $Amount * 1;
				else if (strtolower($currency) == strtolower('IRR'))
					$Amount = $Amount / 10;

				$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
				$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
				$Amount = apply_filters('woocommerce_order_amount_total_payping_gateway', $Amount, $currency);

				$CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_PPal'));

				$products = array();
				$order_items = $order->get_items();
				foreach ((array)$order_items as $product) {
					$products[] = $product['name'] . ' (' . $product['qty'] . ') ';
				}
				$products = implode(' - ', $products);

				$Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
				$Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '-';
				$Email = $order->billing_email;
				$Paymenter = $order->billing_first_name . ' ' . $order->billing_last_name;
				$ResNumber = intval($order->get_order_number());

				//Hooks for iranian developer
				$Description = apply_filters('WC_PPal_Description', $Description, $order_id);
				$Mobile = apply_filters('WC_PPal_Mobile', $Mobile, $order_id);
				$Email = apply_filters('WC_PPal_Email', $Email, $order_id);
				$Paymenter = apply_filters('WC_PPal_Paymenter', $Paymenter, $order_id);
				$ResNumber = apply_filters('WC_PPal_ResNumber', $ResNumber, $order_id);
				do_action('WC_PPal_Gateway_Payment', $order_id, $Description, $Mobile);
				$Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
				$Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';
				if ( $Email == '' )
					$payerIdentity = $Mobile ;
				else
					$payerIdentity = $Email ;
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

                $response = wp_remote_post('https://api.payping.ir/v1/pay', $args);
                
                /* Call Function Show Debug In Console */
                WC_GPP_Debug_Log($this->Debug_Mode, $response, "Pay"); 
                
				$XPP_ID = $response["headers"]["x-paypingrequest-id"];
					if ( is_wp_error($response) ) {
						$Message = $response->get_error_message();
					} else {	
						$code = wp_remote_retrieve_response_code( $response );
						if ( $code === 200) {
							if (isset($response["body"]) and $response["body"] != '') {
								$code_pay = wp_remote_retrieve_body($response);
								$code_pay =  json_decode($code_pay, true);
								wp_redirect(sprintf('https://api.payping.ir/v1/pay/gotoipg/%s', $code_pay["code"]));
								exit;
							} else {
								$Message = ' تراکنش ناموفق بود- کد خطا : '.$XPP_ID;
								$Fault = $Message;
							}
						} elseif ( $code == 400) {
							$Message = wp_remote_retrieve_body( $response ).'<br /> کد خطا: '.$XPP_ID;
							$Fault = '';
						} else {
							$Message = wp_remote_retrieve_body( $response ).'<br /> کد خطا: '.$XPP_ID;
						}
					}

				if (!empty($Message) && $Message) {

					$Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
					$Note = apply_filters('WC_PPal_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
					$order->add_order_note($Note);

					$Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
					$Notice = apply_filters('WC_PPal_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
					if ($Notice)
						wc_add_notice($Notice, 'error');

					do_action('WC_PPal_Send_to_Gateway_Failed', $order_id, $Fault);
				}
			}


			public function Return_from_payping_Gateway()
			{


				global $woocommerce;


				if (isset($_GET['wc_order']))
					$order_id = $_GET['wc_order'];
				else if (isset($_GET['clientrefid']))
					$order_id = $_GET['clientrefid'];
				else {
					$order_id = $woocommerce->session->order_id_payping;
					unset($woocommerce->session->order_id_payping);
				}

				if ($order_id) {

					$order = new WC_Order($order_id);
					$currency = $order->get_currency();
					$currency = apply_filters('WC_PPal_Currency', $currency, $order_id);

					if ($order->status != 'completed') {


						$Amount = intval($order->order_total);
						$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
						if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
						)
							$Amount = $Amount * 1;
						else if (strtolower($currency) == strtolower('IRR'))
							$Amount = $Amount / 10;
                        
						$data = array('refId' => $_GET['refid'], 'amount' => $Amount);
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

                    $response = wp_remote_post('https://api.payping.ir/v1/pay/verify', $args);
                        
                    /* Call Function Show Debug In Console */
                    WC_GPP_Debug_Log($this->Debug_Mode, $response, "Verify");
                        
                    $XPP_ID = $response["headers"]["x-paypingrequest-id"];
                    if ( is_wp_error($response) ) {
                        $Status = 'failed';
				        $Fault = $response->get_error_message();
						$Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$response->get_error_message();
					} else {	
						$code = wp_remote_retrieve_response_code( $response );
						if ( $code === 200 ) {
							if (isset($_GET["refid"]) and $_GET["refid"] != '') {
								$Status = 'completed';
								$Transaction_ID = $_GET["refid"];
								$Fault = '';
								$Message = '';
							} else {
                                $Status = 'failed';
								$Transaction_ID = $_GET['refid'];
								$Message = 'متافسانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' .wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$XPP_ID;
								$Fault = $code;
							}
						} elseif ( $code == 400) {
                            $Status = 'failed';
				            $Transaction_ID = $_GET['refid'];
							$Message = wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$XPP_ID;
							$Fault = $code;
						} else {
                            $Status = 'failed';
				            $Transaction_ID = $_GET['refid'];
							$Message = wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$XPP_ID;
                            $Fault = $code;
						}
					}

						if ($Status == 'completed' && isset($Transaction_ID) && $Transaction_ID != 0) {
							update_post_meta($order_id, '_transaction_id', $Transaction_ID);

							$order->payment_complete($Transaction_ID);
							$woocommerce->cart->empty_cart();

							$Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
							$Note = apply_filters('WC_PPal_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
							if ($Note)
								$order->add_order_note($Note, 1);

							$Notice = wpautop(wptexturize($this->success_massage));

							$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

							$Notice = apply_filters('WC_PPal_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
							if ($Notice)
								wc_add_notice($Notice, 'success');

							do_action('WC_PPal_Return_from_Gateway_Success', $order_id, $Transaction_ID);

							wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
							exit;
						}else{

							$tr_id = ($Transaction_ID && $Transaction_ID != 0) ? ('<br/>کد پیگیری : ' . $Transaction_ID) : '';

							$Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s', 'woocommerce'), $Message, $tr_id);

							$Note = apply_filters('WC_PPal_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
							if ($Note)
								$order->add_order_note($Note, 1);

							$Notice = wpautop(wptexturize($Note));

							$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

							$Notice = str_replace("{fault}", $Message, $Notice);
							$Notice = apply_filters('WC_PPal_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
							if ($Notice)
								wc_add_notice($Notice, 'error');

							do_action('WC_PPal_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

							wp_redirect($woocommerce->cart->get_checkout_url());
							exit;
						}
					}else{


						$Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

						$Notice = wpautop(wptexturize($this->success_massage.' شناسه خطای پی پینگ:'.$XPP_ID));

						$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

						$Notice = apply_filters('WC_PPal_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
						if ($Notice)
							wc_add_notice($Notice, 'success');

						do_action('WC_PPal_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

						wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
						exit;
					}
				}else{

					$Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
					$Notice = wpautop(wptexturize($this->failed_massage.' شناسه خطای پی پینگ:'.$XPP_ID));
					$Notice = str_replace("{fault}", $Fault, $Notice);
					$Notice = apply_filters('WC_PPal_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
					if ($Notice)
						wc_add_notice($Notice, 'error');

					do_action('WC_PPal_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);

					wp_redirect($woocommerce->cart->get_checkout_url());
					exit;
				}
			}

		}

	}
}
add_action('plugins_loaded', 'Load_payping_Gateway', 0);
?>