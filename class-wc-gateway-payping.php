<?php
if(!defined('ABSPATH'))exit;

if( class_exists('WC_Payment_Gateway') && !class_exists('WC_payping') ){
	class WC_payping extends WC_Payment_Gateway{
	    
        private $baseurl = 'https://api.payping.ir/v3';
        private $paypingToken;
        private $success_massage;
        private $failed_massage;
        
		public function __construct(){
		    
			$this->id = 'WC_payping';
			$this->method_title = __('پرداخت از طریق درگاه پی‌پینگ', 'woocommerce');
			$this->method_description = __('تنظیمات درگاه پرداخت پی‌پینگ برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
			$this->icon = apply_filters('woo_payping_logo', WOO_GPPDU.'/assets/images/logo.png');
			$this->has_fields = false;
			$this->init_form_fields();
			$this->init_settings();

			$checkserver = $this->settings['ioserver'];
			if( $checkserver == 'yes')$this->baseurl  = 'https://api.payping.ir/v3';
			
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];

			$this->paypingToken = $this->settings['paypingToken'];

			$this->success_massage = $this->settings['success_massage'];
			$this->failed_massage = $this->settings['failed_massage'];

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
			$order = wc_get_order($order_id);
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url(true)
			);
		}

		function isJson($string) {
			json_decode($string);
			return (json_last_error() == JSON_ERROR_NONE);
		}

		/**
		 * Processes payment request and redirects to PayPing gateway.
		 *
		 * Handles payment initiation, API communication, error handling, 
		 * and redirection for PayPing payment gateway integration.
		 *
		 * @param int $order_id WooCommerce order ID
		 * @return void
		 */
		public function Send_to_payping_Gateway($order_id) {
			global $woocommerce;
			$woocommerce->session->order_id_payping = $order_id;
			$order = wc_get_order($order_id);

			// Retrieve payment code from order metadata
			$paypingpayCode = '';
			if ($order) {
				$paypingpayCode = $order->get_meta('_payping_payCode');
				
				// Fallback to post meta if not found
				if (empty($paypingpayCode)) {
					$paypingpayCode = get_post_meta($order_id, '_payping_payCode', true);
				}
			}

			// Redirect if payment code exists
			if (!empty($paypingpayCode)) {
				wp_redirect(sprintf('%s/pay/start/%s', $this->baseurl, $paypingpayCode));
				exit;
			}
			
			// Prepare payment form
			$currency = apply_filters('WC_payping_Currency', $order->get_currency(), $order_id);
			$form = sprintf(
				'<form method="POST" class="payping-checkout-form" id="payping-checkout-form">
					<input type="submit" name="payping_submit" class="button alt" id="payping-payment-button" value="%s"/>
					<a class="button cancel" href="%s">%s</a>
				</form><br/>',
				__('پرداخت', 'woocommerce'),
				esc_url(wc_get_checkout_url()),
				__('بازگشت', 'woocommerce')
			);
			$form = apply_filters('WC_payping_Form', $form, $order_id, $woocommerce);

			// Display payment form
			do_action('WC_payping_Gateway_Before_Form', $order_id, $woocommerce);
			echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			do_action('WC_payping_Gateway_After_Form', $order_id, $woocommerce);

			// Calculate amount with currency conversion
			$Amount = intval($order->get_total());
			$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
			$Amount = $this->payping_check_currency($Amount, $currency);
			$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
			$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
			$Amount = apply_filters('woocommerce_order_amount_total_payping_gateway', $Amount, $currency);

			// Prepare API request data
			$CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_payping'));
			$products = [];
			foreach ($order->get_items() as $item) {
				$products[] = $item->get_name() . ' (' . $item->get_quantity() . ')';
			}

			$Description = sprintf(
				'خرید به شماره سفارش: %s | توسط: %s %s | خرید از %s',
				$order->get_order_number(),
				$order->get_billing_first_name(),
				$order->get_billing_last_name(),
				get_bloginfo('name')
			);
			
			$Mobile = $order->get_meta('_billing_phone') ?: '-';
			$Email = $order->get_billing_email();
			$Paymenter = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$ResNumber = intval($order->get_order_number());

			// Apply filters
			$Description = apply_filters('WC_payping_Description', $Description, $order_id);
			$Mobile = apply_filters('WC_payping_Mobile', $Mobile, $order_id);
			$Email = apply_filters('WC_payping_Email', $Email, $order_id);
			$Paymenter = apply_filters('WC_payping_Paymenter', $Paymenter, $order_id);
			$ResNumber = apply_filters('WC_payping_ResNumber', $ResNumber, $order_id);
			
			do_action('WC_payping_Gateway_Payment', $order_id, $Description, $Mobile);

			// Validate payer identity
			$payerIdentity = '';
			if (filter_var($Email, FILTER_VALIDATE_EMAIL)) {
				$payerIdentity = $Email;
			} elseif (preg_match('/^09[0-9]{9}$/', $Mobile)) {
				$payerIdentity = $Mobile;
			}

			// Build API request payload
			$data = [
				'PayerName'      => $Paymenter,
				'Amount'         => $Amount,
				'PayerIdentity'  => $payerIdentity,
				'ReturnUrl'      => $CallbackUrl,
				'Description'    => $Description,
				'ClientRefId'    => $order->get_order_number(),
				'NationalCode'   => ''
			];

			$args = [
				'body'        => wp_json_encode($data),
				'timeout'     => 45,
				'redirection' => 5,
				'blocking'    => true,
				'headers'     => [
					'X-Platform'           => 'woocommerce',
					'X-Platform-Version'   => '4.6.0',
					'Authorization'        => 'Bearer ' . $this->paypingToken,
					'Content-Type'         => 'application/json',
					'Accept'               => 'application/json'
				],
				'httpversion' => '1.0',
				'data_format' => 'body'
			];

			// Execute API request
			$api_url = apply_filters('WC_payping_Gateway_Payment_api_url', $this->baseurl . '/pay', $order_id);
			$api_args = apply_filters('WC_payping_Gateway_Payment_api_args', $args, $order_id);
			$response = wp_safe_remote_post($api_url, $api_args);

			// Handle API response
			$ERR_ID = wp_remote_retrieve_header($response, 'x-paypingrequest-id');
			$Fault = $Message = '';

			if (is_wp_error($response)) {
				$Message = $response->get_error_message();
			} else {
				$code = wp_remote_retrieve_response_code($response);
				$body = wp_remote_retrieve_body($response);

				if (200 === $code && !empty($body)) {
					$code_pay = json_decode($body, true);
					
					if (isset($code_pay['paymentCode'])) {
						$order->update_meta_data('_payping_payCode', $code_pay['paymentCode']);
						$order->save();
						update_post_meta($order->get_id(), '_payping_payCode', $code_pay['paymentCode']);

						$order->add_order_note('ساخت موفق پرداخت، کد پرداخت: ' . $code_pay['paymentCode'], 1);
						wp_redirect(sprintf('%s/pay/start/%s', $this->baseurl, $code_pay['paymentCode']));
						exit;
					}
				}
				$Message = (200 !== $code) 
					? wp_remote_retrieve_body($response) . ' | کد خطا: ' . $ERR_ID
					: 'تراکنش ناموفق بود- کد خطا: ' . $ERR_ID;
			}

			// Handle errors
			if (!empty($Message)) {
				$note = sprintf(__('خطا در هنگام ارسال به بانک: %s', 'woocommerce'), $Message);
				$order->add_order_note($note, 0);
				wc_add_notice($note, 'error');
				do_action('woo_payping_Send_to_Gateway_Failed', $order_id, $Message);
			}
		}

		public function Return_from_payping_Gateway() {
			global $woocommerce;

			// Sanitize and validate input data
			$paypingResponse = isset($_REQUEST['data']) ? wp_unslash($_REQUEST['data']) : '';
			$responseData = json_decode($paypingResponse, true) ?: [];

			// Retrieve order ID
			if (isset($_REQUEST['wc_order'])) {
				$order_id = absint($_REQUEST['wc_order']);
			} elseif (!empty($woocommerce->session->order_id_payping)) {
				$order_id = absint($woocommerce->session->order_id_payping);
				unset($woocommerce->session->order_id_payping);
			} else {
				wp_redirect(wc_get_checkout_url());
				exit;
			}

			// Load order and validate
			$order = wc_get_order($order_id);
			if (!$order || !is_a($order, 'WC_Order')) {
				wp_redirect(wc_get_checkout_url());
				exit;
			}

			// Retrieve and validate clientRefId
			$clientRefId = isset($responseData['clientRefId']) ? sanitize_text_field($responseData['clientRefId']) : null;

			// Get expected reference ID (handling sub-orders)
			$expectedRefId = $this->get_expected_client_ref_id($order);

			// Retrieve reference ID
			$refid = isset($responseData['paymentRefId']) ? sanitize_text_field($responseData['paymentRefId']) : null;
			$refid = apply_filters('WC_payping_return_refid', $refid);
			$Transaction_ID = $refid;

			// Validate clientRefId against order reference
			if (!$clientRefId || $clientRefId != $expectedRefId) {
				$error_message = sprintf(
					'شناسه سفارش برگشتی (%s) با شناسه سفارش اصلی (%s) مطابقت ندارد',
					$clientRefId ?: 'ندارد',
					$expectedRefId
				);
				
				$this->handle_verification_error(
					$order,
					$Transaction_ID,
					$error_message
				);
				return;
			}

			// Process payment status
			$status = isset($_REQUEST['status']) ? absint($_REQUEST['status']) : null;
			
			if (0 === $status) {
				// Handle payment cancellation
				$this->handle_payment_failure(
					$order,
					$Transaction_ID,
					'کاربر در صفحه بانک از پرداخت انصراف داده است.',
					'تراكنش توسط شما لغو شد.'
				);
				return;
			}

			// Validate return data before proceeding
			$validation_error = $this->validate_return_data($order, $responseData);
			if ($validation_error) {
				$this->handle_verification_error(
					$order,
					$Transaction_ID,
					$validation_error
				);
				return;
			}

			// Proceed with payment verification
			$this->verify_payment($order, $Transaction_ID, $responseData);
		}

		/**
		 * Validates return data against order details
		 * 
		 * @param WC_Order $order WooCommerce order object
		 * @param array $responseData Response data from gateway
		 * @return string|bool Error message if invalid, false if valid
		 */
		private function validate_return_data($order, $responseData) {
			$order_id = $order->get_id();
			
			// Retrieve stored payment code
			$stored_payment_code = $order->get_meta('_payping_payCode');
			if (empty($stored_payment_code)) {
				$stored_payment_code = get_post_meta($order_id, '_payping_payCode', true);
			}
			
			// Validate payment code exists
			if (empty($stored_payment_code)) {
				return 'کد پرداخت ذخیره شده یافت نشد';
			}
			
			// Validate response has payment code
			if (!isset($responseData['paymentCode'])) {
				return 'پارامتر کد پرداخت در پاسخ درگاه وجود ندارد';
			}
			
			// Compare payment codes
			if ($responseData['paymentCode'] !== $stored_payment_code) {
				return sprintf(
					'کد پرداخت برگشتی (%s) با کد ذخیره شده (%s) مطابقت ندارد',
					$responseData['paymentCode'],
					$stored_payment_code
				);
			}
			
			// Calculate expected amount
			$currency = apply_filters('WC_payping_Currency', $order->get_currency(), $order_id);
			$expected_amount = apply_filters(
				'woocommerce_order_amount_total_IRANIAN_gateways_irt',
				$this->payping_check_currency(
					apply_filters(
						'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency',
						intval($order->get_total()),
						$currency
					),
					$currency
				),
				$currency
			);
			
			// Validate response has amount
			if (!isset($responseData['amount'])) {
				return 'پارامتر مبلغ در پاسخ درگاه وجود ندارد';
			}
			
			// Compare amounts
			$returned_amount = intval($responseData['amount']);
			if ($returned_amount !== $expected_amount) {
				return sprintf(
					'مبلغ پرداختی (%s) با مبلغ سفارش (%s) مطابقت ندارد',
					number_format($returned_amount),
					number_format($expected_amount)
				);
			}
			
			return false; // No error
		}

		/**
		 * Verifies payment with PayPing API after successful local validation
		 * 
		 * @param WC_Order $order WooCommerce order object
		 * @param string|null $Transaction_ID Transaction ID
		 * @param array $responseData Response data from gateway
		 * @return void
		 */
		private function verify_payment($order, $Transaction_ID, $responseData) {
			$order_id = $order->get_id();
			
			// Retrieve stored payment code
			$stored_payment_code = $order->get_meta('_payping_payCode');
			if (empty($stored_payment_code)) {
				$stored_payment_code = get_post_meta($order_id, '_payping_payCode', true);
			}

			// Calculate expected amount
			$currency = apply_filters('WC_payping_Currency', $order->get_currency(), $order_id);
			$expected_amount = apply_filters(
				'woocommerce_order_amount_total_IRANIAN_gateways_irt',
				$this->payping_check_currency(
					apply_filters(
						'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency',
						intval($order->get_total()),
						$currency
					),
					$currency
				),
				$currency
			);

			$data = [
				'PaymentRefId' => $Transaction_ID,
				'PaymentCode'  => $stored_payment_code,
				'Amount'       => $expected_amount
			];

			$args = [
				'body'        => wp_json_encode($data),
				'timeout'     => 45,
				'redirection' => 5,
				'blocking'    => true,
				'headers'     => [
					'Authorization' => 'Bearer ' . $this->paypingToken,
					'Content-Type'  => 'application/json',
					'Accept'       => 'application/json'
				],
				'httpversion' => '1.0',
				'data_format' => 'body'
			];

			// Send verification request
			$verify_api_url = apply_filters('WC_payping_Gateway_Payment_verify_api_url', $this->baseurl . '/pay/verify', $order_id);
			$response = wp_safe_remote_post($verify_api_url, $args);
			$body = wp_remote_retrieve_body($response);
			$rbody = json_decode($body, true) ?: [];

			// Handle verification response
			if (is_wp_error($response)) {
				$this->handle_verification_error(
					$order,
					$Transaction_ID,
					'خطا در ارتباط به پی‌پینگ: ' . $response->get_error_message()
				);
				return;
			}

			$code = wp_remote_retrieve_response_code($response);
			
			// Handle response for verify API
			if (isset($rbody['status'], $rbody['metaData']['code']) && $rbody['status'] == 409) {
				$error_code = (int) $rbody['metaData']['code'];

				switch ($error_code) {
					case 110:
						// Duplicate payment detected
						$this->handle_duplicate_payment($order, $Transaction_ID);
						return;

					case 133:
					default:
						// Invalid payment or unknown error
						$error_message = $rbody['metaData']['errors'][0]['message'] ?? 'خطایی رخ داده است.';
						$this->handle_payment_failure(
							$order,
							$Transaction_ID,
							$error_message,
							'اطلاعات پرداخت نامعتبر است، لطفاً مجدد تلاش کنید.'
						);
						return;
				}
			}

			// Validate response code matches stored payment code
			if (!isset($rbody['code']) || $rbody['code'] !== $stored_payment_code) {
				$this->handle_verification_error(
					$order,
					$Transaction_ID,
					'کد پرداخت برگشتی با کد ذخیره شده مطابقت ندارد'
				);
				return;
			}

			// Validate response amount matches expected amount
			$response_amount = isset($rbody['amount']) ? intval($rbody['amount']) : 0;
			if ($response_amount !== $expected_amount) {
				$this->handle_verification_error(
					$order,
					$Transaction_ID,
					sprintf(
						'مبلغ پرداختی (%s) با مبلغ سفارش (%s) مطابقت ندارد',
						number_format($response_amount),
						number_format($expected_amount)
					)
				);
				return;
			}

			// Handle card details
			$cardNumber = isset($rbody['cardNumber']) ? sanitize_text_field($rbody['cardNumber']) : '-';
			$CardHashPan = isset($rbody['cardHashPan']) ? sanitize_text_field($rbody['cardHashPan']) : '-';
			$order->update_meta_data('payping_payment_card_number', $cardNumber);
			$order->update_meta_data('payping_payment_card_hashpan', $CardHashPan);

			// Determine payment status
			if (200 === $code) {
				$this->handle_payment_success(
					$order,
					$Transaction_ID,
					$cardNumber,
					$this->status_message($code)
				);
			} else {
				$this->handle_verification_error(
					$order,
					$Transaction_ID,
					$this->status_message($code) ?: 'خطای نامشخص در تایید پرداخت!'
				);
			}
		}

		/**
		 * Gets expected client reference ID handling sub-orders
		 * 
		 * @param WC_Order $order
		 * @return string Expected reference ID
		 */
		private function get_expected_client_ref_id($order) {
			// Check if this is a sub-order
			$parent_order_id = $order->get_parent_id();
			
			if ($parent_order_id > 0) {
				// This is a sub-order - get parent order
				$parent_order = wc_get_order($parent_order_id);
				
				if ($parent_order) {
					// Use parent order ID for sub-orders
					return (string) $parent_order->get_id();
				}
			}
			
			// For main orders, use their own ID
			return (string) $order->get_id();
		}

		/**
		 * Handles successful payment verification after strict validation.
		 * 
		 * @param WC_Order $order WooCommerce order object
		 * @param string $Transaction_ID Transaction ID
		 * @param string $cardNumber Card number
		 * @param string $message Success message
		 * @return void
		 */
		private function handle_payment_success($order, $Transaction_ID, $cardNumber, $message) {
			global $woocommerce;
			
			$order_id = $order->get_id();
			$full_message = sprintf('%s<br>شماره کارت: <b dir="ltr">%s</b>', $message, $cardNumber);
			
			// Update transaction ID
			$order->update_meta_data('_transaction_id', $Transaction_ID);
			$order->save();

			// Empty cart and complete payment
			$woocommerce->cart->empty_cart();
			$order->payment_complete($Transaction_ID);
			
			// Add order note
			$note = sprintf(
				__('%s <br>شماره پیگیری پرداخت: %s', 'woocommerce'),
				$full_message,
				$Transaction_ID
			);
			$order->add_order_note($note);

			// Prepare success notice
			$notice = wpautop(wptexturize($this->success_massage));
			$notice = str_replace('{transaction_id}', $Transaction_ID, $notice);
			$notice = apply_filters('woocommerce_thankyou_order_received_text', $notice, $order_id, $Transaction_ID);
			wc_add_notice($notice, 'success');

			// Redirect to thank you page
			wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
			exit;
		}

		/**
		 * Handles verification error with detailed message.
		 * 
		 * @param WC_Order $order WooCommerce order object
		 * @param string|null $Transaction_ID Transaction ID
		 * @param string $error_message Detailed error message
		 * @return void
		 */
		private function handle_verification_error($order, $Transaction_ID, $error_message) {
			$order_id = $order->get_id();
			$user_friendly_message = 'خطایی در تأیید پرداخت رخ داده است. لطفاً با مدیریت سایت تماس بگیرید.';
			
			// Prepare error notice
			$tr_id = ($Transaction_ID && $Transaction_ID != 0) ? '<br/>کد پیگیری: ' . $Transaction_ID : '';
			$note = sprintf(
				__('خطا در تأیید پرداخت: %s %s', 'woocommerce'),
				$error_message,
				$tr_id
			);
			
			$notice = wpautop(wptexturize($note));
			$notice = str_replace('{transaction_id}', $Transaction_ID, $notice);
			$notice = str_replace('{fault}', $error_message, $notice);
			
			// Add order note and notice
			$order->add_order_note($note, 0, false);
			wc_add_notice($user_friendly_message, 'error');
			
			// Redirect to checkout
			wp_redirect(wc_get_checkout_url());
			exit;
		}

		/**
		 * Handles duplicate payment verification (status 409).
		 * 
		 * @param WC_Order $order WooCommerce order object
		 * @param string $Transaction_ID Transaction ID
		 * @return void
		 */
		private function handle_duplicate_payment($order, $Transaction_ID) {
			
			$order_id = $order->get_id();
			$message = 'این سفارش قبلا تایید شده است.';
			
			// Update transaction ID
			$order->update_meta_data('_transaction_id', $Transaction_ID);
			$order->save();

			// Complete payment if not already completed
			if (!$order->is_paid()) {
				$order->payment_complete($Transaction_ID);
			}
			
			// Add order note
			$order->add_order_note($message);
			wc_add_notice($message, 'success');

			// Redirect to thank you page
			wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
			exit;
		}

		/**
		 * Handles payment failure scenarios.
		 * 
		 * @param WC_Order $order WooCommerce order object
		 * @param string|null $Transaction_ID Transaction ID
		 * @param string $message Error message
		 * @param string $fault User-friendly fault message
		 * @return void
		 */
		private function handle_payment_failure($order, $Transaction_ID, $message, $fault) {
			$order_id = $order->get_id();
			
			// Prepare error notice
			$tr_id = ($Transaction_ID && $Transaction_ID != 0) ? '<br/>کد پیگیری: ' . $Transaction_ID : '';
			$note = sprintf(
				__('خطا در هنگام تایید پرداخت: %s %s', 'woocommerce'),
				$message,
				$tr_id
			);
			
			$notice = wpautop(wptexturize($note));
			$notice = str_replace("{transaction_id}", $Transaction_ID, $notice);
			$notice = str_replace("{fault}", $message, $notice);
			
			// Add order note and notice
			$order->add_order_note($notice, 0, false);
			wc_add_notice($fault, 'error');
			
			// Redirect to checkout
			wp_redirect(wc_get_checkout_url());
			exit;
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