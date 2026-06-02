<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'WC_payping' ) ) {
    
    class WC_payping extends WC_Payment_Gateway {
        
        private $baseurl = 'https://api.payping.ir/v3';
        private $paypingToken;
        private $success_massage;
        private $failed_massage;
        
        // Prevent duplicate form rendering
        private static $form_rendered = false;
        
        public function __construct() {
            $this->id                 = 'WC_payping';
            $this->method_title       = __( 'پرداخت از طریق درگاه پی‌پینگ', 'woo-payping-gateway' );
            $this->method_description = __( 'تنظیمات درگاه پرداخت پی‌پینگ برای افزونه فروشگاه ساز ووکامرس', 'woo-payping-gateway' );
            $this->icon               = apply_filters( 'woo_payping_logo', WOO_GPPDU . '/assets/images/logo.png' );
            $this->has_fields         = false;
            
            $this->init_form_fields();
            $this->init_settings();

            $checkserver = $this->settings['ioserver'] ?? 'no';
            if ( $checkserver == 'yes' ) {
                $this->baseurl = 'https://api.payping.io/v3';
            }
            
            $this->title          = $this->settings['title'] ?? __( 'پرداخت از طریق پی‌پینگ', 'woo-payping-gateway' );
            $this->description    = $this->settings['description'] ?? '';
            $this->paypingToken   = $this->settings['paypingToken'] ?? '';
            $this->success_massage= $this->settings['success_massage'] ?? '';
            $this->failed_massage = $this->settings['failed_massage'] ?? '';

            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            }

            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'payment_receipt_page' ) );
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'Return_from_payping_Gateway' ) );
        }

        public function admin_options() {
            parent::admin_options();
        }

        public function init_form_fields() {
            $this->form_fields = apply_filters( 'WC_payping_Config', array(
                'base_confing' => array(
                    'title'       => __( 'تنظیمات پایه ای', 'woo-payping-gateway' ),
                    'type'        => 'title',
                    'description' => '',
                ),
                'enabled' => array(
                    'title'       => __( 'فعالسازی/غیرفعالسازی', 'woo-payping-gateway' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'فعالسازی درگاه پی‌پینگ', 'woo-payping-gateway' ),
                    'description' => __( 'برای فعالسازی درگاه پرداخت پی‌پینگ باید چک باکس را تیک بزنید', 'woo-payping-gateway' ),
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'ioserver' => array(
                    'title'       => __( 'سرور خارج', 'woo-payping-gateway' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'اتصال به سرور خارج', 'woo-payping-gateway' ),
                    'description' => __( 'در صورت تیک خوردن، درگاه به سرور خارج از کشور متصل می‌شود.', 'woo-payping-gateway' ),
                    'default'     => 'no',
                    'desc_tip'    => true,
                ),
                'title' => array(
                    'title'       => __( 'عنوان درگاه', 'woo-payping-gateway' ),
                    'type'        => 'text',
                    'description' => __( 'عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woo-payping-gateway' ),
                    'default'     => __( 'پرداخت از طریق پی‌پینگ', 'woo-payping-gateway' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'توضیحات درگاه', 'woo-payping-gateway' ),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => __( 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woo-payping-gateway' ),
                    'default'     => __( 'پرداخت به وسیله کلیه کارت های عضو شتاب از طریق درگاه پی‌پینگ', 'woo-payping-gateway' )
                ),
                'account_confing' => array(
                    'title'       => __( 'تنظیمات حساب پی‌پینگ', 'woo-payping-gateway' ),
                    'type'        => 'title',
                    'description' => '',
                ),
                'paypingToken' => array(
                    'title'       => __( 'توکن', 'woo-payping-gateway' ),
                    'type'        => 'text',
                    'description' => __( 'توکن درگاه پی‌پینگ', 'woo-payping-gateway' ),
                    'default'     => '',
                    'desc_tip'    => true
                ),
                'payment_confing' => array(
                    'title'       => __( 'تنظیمات عملیات پرداخت', 'woo-payping-gateway' ),
                    'type'        => 'title',
                    'description' => '',
                ),
                'success_massage' => array(
                    'title'       => __( 'پیام پرداخت موفق', 'woo-payping-gateway' ),
                    'type'        => 'textarea',
                    'description' => __( 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) پی‌پینگ استفاده نمایید .', 'woo-payping-gateway' ),
                    'default'     => __( 'با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woo-payping-gateway' ),
                ),
                'failed_massage' => array(
                    'title'       => __( 'پیام پرداخت ناموفق', 'woo-payping-gateway' ),
                    'type'        => 'textarea',
                    'description' => __( 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید .', 'woo-payping-gateway' ),
                    'default'     => __( 'پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woo-payping-gateway' ),
                )
            ) );
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url( true )
            );
        }

        public function isJson( $string ) {
            json_decode( $string );
            return ( json_last_error() == JSON_ERROR_NONE );
        }

        /**
         * Display payment receipt page with payment form or redirect to existing payCode
         */
        public function payment_receipt_page( $order_id ) {
            $order = wc_get_order( $order_id );
            
            if ( ! $order || $order->get_payment_method() !== $this->id ) {
                return;
            }
            
            // ✅ بهینه‌سازی: اگر سفارش قبلاً لغو یا ناموفق شده، برای تلاش مجدد به حالت pending برگردانده شود
            if ( $order->has_status( array( 'failed', 'cancelled' ) ) ) {
                $order->update_status( 'pending', __( 'آماده‌سازی برای تلاش مجدد پرداخت', 'woo-payping-gateway' ) );
            }
            
            // ✅ استفاده مجدد از کد پرداخت قبلی (جلوگیری از ساخت تراکنش تکراری در پی‌پینگ)
            $paypingpayCode = $order->get_meta( '_payping_payCode' );
            if ( ! empty( $paypingpayCode ) ) {
                wp_redirect( sprintf( '%s/pay/start/%s', $this->baseurl, $paypingpayCode ) );
                exit;
            }
            
            wc_print_notices();
            echo $this->generate_payment_form( $order_id );
        }

        private function generate_payment_form( $order_id ) {
            if ( self::$form_rendered ) {
                return '';
            }
            self::$form_rendered = true;
            
            $order = wc_get_order( $order_id );
            $checkout_url = wc_get_checkout_url();
            
            $form = sprintf(
                '<form method="POST" class="payping-checkout-form" id="payping-checkout-form-%d" action="%s">
                    <input type="hidden" name="payping_order_id" value="%d" />
                    %s
                    <div class="payping-button-group">
                        <button type="submit" name="payping_submit" class="button alt payping-submit-button" id="payping-payment-button-%d">%s</button>
                        <a class="button cancel payping-cancel-button" href="%s">%s</a>
                    </div>
                </form>',
                esc_attr( $order_id ),
                esc_url( add_query_arg( 'process_payment', '1', wc_get_checkout_url() ) ),
                esc_attr( $order_id ),
                wp_nonce_field( 'payping_payment_action_' . $order_id, 'payping_nonce_' . $order_id, true, false ),
                esc_attr( $order_id ),
                esc_html__( 'پرداخت', 'woo-payping-gateway' ),
                esc_url( $checkout_url ),
                esc_html__( 'بازگشت', 'woo-payping-gateway' )
            );
            
            return apply_filters( 'WC_payping_Form', $form, $order_id, $order );
        }

        public function process_payment_submission() {
            if ( ! isset( $_POST['payping_submit'] ) || ! isset( $_POST['payping_order_id'] ) ) {
                return;
            }
            
            $order_id = absint( $_POST['payping_order_id'] );
            
            if ( ! isset( $_POST['payping_nonce_' . $order_id] ) || ! wp_verify_nonce( $_POST['payping_nonce_' . $order_id], 'payping_payment_action_' . $order_id ) ) {
                wc_add_notice( __( 'درخواست نامعتبر شناسایی شد.', 'woo-payping-gateway' ), 'error' );
                wp_safe_redirect( wc_get_checkout_url() );
                exit;
            }
            
            $this->Send_to_payping_Gateway( $order_id );
        }

        public function Send_to_payping_Gateway( $order_id ) {
            global $woocommerce;
            $woocommerce->session->order_id_payping = $order_id;
            $order = wc_get_order( $order_id );
            
            if ( ! $order ) {
                wc_add_notice( __( 'سفارش یافت نشد!', 'woo-payping-gateway' ), 'error' );
                wp_safe_redirect( wc_get_checkout_url() );
                exit;
            }
        
            $paypingpayCode = $order->get_meta( '_payping_payCode' );
            if ( ! empty( $paypingpayCode ) ) {
                wp_redirect( sprintf( '%s/pay/start/%s', $this->baseurl, $paypingpayCode ) );
                exit;
            }
            
            $currency = apply_filters( 'WC_payping_Currency', $order->get_currency(), $order_id );
            $Amount = intval( $order->get_total() );
            $Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency );
            $Amount = $this->payping_check_currency( $Amount, $currency );
            $Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency );
            $Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency );
            $Amount = apply_filters( 'woocommerce_order_amount_total_payping_gateway', $Amount, $currency );
        
            $CallbackUrl = add_query_arg( 'wc_order', $order_id, WC()->api_request_url( 'WC_payping' ) );
            
            $Description = sprintf(
                'خرید به شماره سفارش: %s | توسط: %s %s | خرید از %s',
                $order->get_order_number(),
                $order->get_billing_first_name(),
                $order->get_billing_last_name(),
                get_bloginfo( 'name' )
            );
            
            $Mobile = $order->get_meta( '_billing_phone' ) ?: '-';
            $Email = $order->get_billing_email();
            $Paymenter = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        
            $Description = apply_filters( 'WC_payping_Description', $Description, $order_id );
            $Mobile = apply_filters( 'WC_payping_Mobile', $Mobile, $order_id );
            $Email = apply_filters( 'WC_payping_Email', $Email, $order_id );
            $Paymenter = apply_filters( 'WC_payping_Paymenter', $Paymenter, $order_id );
            
            $payerIdentity = '';
            if ( filter_var( $Email, FILTER_VALIDATE_EMAIL ) ) {
                $payerIdentity = $Email;
            } elseif ( preg_match( '/^09[0-9]{9}$/', $Mobile ) ) {
                $payerIdentity = $Mobile;
            }
        
            $data = [
                'PayerName'     => $Paymenter,
                'Amount'        => $Amount,
                'PayerIdentity' => $payerIdentity,
                'ReturnUrl'     => $CallbackUrl,
                'Description'   => $Description,
                'ClientRefId'   => $order->get_order_number(),
                'NationalCode'  => ''
            ];
        
            $args = [
                'body'        => wp_json_encode( $data ),
                'timeout'     => 45,
                'redirection' => 5,
                'blocking'    => true,
                'headers'     => [
                    'X-Platform'         => 'woocommerce',
                    'X-Platform-Version' => '4.6.1',
                    'Authorization'      => 'Bearer ' . $this->paypingToken,
                    'Content-Type'       => 'application/json',
                    'Accept'             => 'application/json'
                ],
                'httpversion' => '1.0',
                'data_format' => 'body'
            ];
        
            $api_url  = apply_filters( 'WC_payping_Gateway_Payment_api_url', $this->baseurl . '/pay', $order_id );
            $api_args = apply_filters( 'WC_payping_Gateway_Payment_api_args', $args, $order_id );
            $response = wp_safe_remote_post( $api_url, $api_args );
        
            $ERR_ID  = wp_remote_retrieve_header( $response, 'x-paypingrequest-id' );
            $Message = '';
        
            if ( is_wp_error( $response ) ) {
                $Message = $response->get_error_message();
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );
        
                if ( 200 === $code && ! empty( $body ) ) {
                    $code_pay = json_decode( $body, true );
                    if ( isset( $code_pay['paymentCode'] ) ) {
                        $order->update_meta_data( '_payping_payCode', $code_pay['paymentCode'] );
                        $order->save();
                        
                        // ✅ اصلاح شده: پارامتر دوم از 1 به 0 تغییر کرد تا ایمیل ارسال نشود و سایت کرش نکند
                        $order->add_order_note( 'ساخت موفق پرداخت، کد پرداخت: ' . $code_pay['paymentCode'], 0 );
                        
                        wp_redirect( sprintf( '%s/pay/start/%s', $this->baseurl, $code_pay['paymentCode'] ) );
                        exit;
                    }
                }
                
                $Message = ( 200 !== $code ) 
                    ? wp_remote_retrieve_body( $response ) . ' | کد خطا: ' . $ERR_ID
                    : 'تراکنش ناموفق بود- کد خطا: ' . $ERR_ID;
            }
        
            if ( ! empty( $Message ) ) {
                $note = sprintf( __( 'خطا در هنگام ارسال به بانک: %s', 'woo-payping-gateway' ), $Message );
                $order->add_order_note( $note, 0 );
                wc_add_notice( $note, 'error' );
                do_action( 'woo_payping_Send_to_Gateway_Failed', $order_id, $Message );
                
                wp_safe_redirect( $order->get_checkout_payment_url() );
                exit;
            }
        }

        public function Return_from_payping_Gateway() {
            global $woocommerce;

            $paypingResponse = isset( $_REQUEST['data'] ) ? wp_unslash( $_REQUEST['data'] ) : '';
            $responseData    = json_decode( $paypingResponse, true ) ?: [];

            if ( isset( $_REQUEST['wc_order'] ) ) {
                $order_id = absint( $_REQUEST['wc_order'] );
            } elseif ( ! empty( $woocommerce->session->order_id_payping ) ) {
                $order_id = absint( $woocommerce->session->order_id_payping );
                unset( $woocommerce->session->order_id_payping );
            } else {
                wp_redirect( wc_get_checkout_url() );
                exit;
            }

            $order = wc_get_order( $order_id );
            if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                wp_redirect( wc_get_checkout_url() );
                exit;
            }

            $clientRefId   = isset( $responseData['clientRefId'] ) ? sanitize_text_field( $responseData['clientRefId'] ) : null;
            $expectedRefId = $this->get_expected_client_ref_id( $order );
            $refid         = isset( $responseData['paymentRefId'] ) ? sanitize_text_field( $responseData['paymentRefId'] ) : null;
            $Transaction_ID = apply_filters( 'WC_payping_return_refid', $refid );

            if ( ! $clientRefId || $clientRefId != $expectedRefId ) {
                $error_message = sprintf( 'شناسه سفارش برگشتی (%s) با شناسه سفارش اصلی (%s) مطابقت ندارد', $clientRefId ?: 'ندارد', $expectedRefId );
                $this->handle_verification_error( $order, $Transaction_ID, $error_message );
                return;
            }

            $status = isset( $_REQUEST['status'] ) ? absint( $_REQUEST['status'] ) : null;
            
            // ✅ مدیریت انصراف کاربر: هدایت به صفحه پرداخت سفارش (نه صفحه اصلی چک‌اوت)
            if ( 0 === $status ) {
                $order->add_order_note( 'کاربر در صفحه بانک از پرداخت انصراف داده است.', 0, false );
                wc_add_notice( 'تراكنش توسط شما لغو شد. لطفاً برای تکمیل خرید مجدداً اقدام نمایید.', 'error' );
                
                wp_redirect( $order->get_checkout_payment_url() );
                exit;
            }

            $validation_error = $this->validate_return_data( $order, $responseData );
            if ( $validation_error ) {
                $this->handle_verification_error( $order, $Transaction_ID, $validation_error );
                return;
            }

            $this->verify_payment( $order, $Transaction_ID, $responseData );
        }

        private function validate_return_data( $order, $responseData ) {
            $order_id = $order->get_id();
            $stored_payment_code = $order->get_meta( '_payping_payCode' );
            
            if ( empty( $stored_payment_code ) ) {
                return 'کد پرداخت ذخیره شده یافت نشد';
                }
            if ( ! isset( $responseData['paymentCode'] ) ) {
                return 'پارامتر کد پرداخت در پاسخ درگاه وجود ندارد';
            }
            if ( $responseData['paymentCode'] !== $stored_payment_code ) {
                return sprintf( 'کد پرداخت برگشتی (%s) با کد ذخیره شده (%s) مطابقت ندارد', $responseData['paymentCode'], $stored_payment_code );
            }
            
            $currency = apply_filters( 'WC_payping_Currency', $order->get_currency(), $order_id );
            $expected_amount = apply_filters(
                'woocommerce_order_amount_total_IRANIAN_gateways_irt',
                $this->payping_check_currency(
                    apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', intval( $order->get_total() ), $currency ),
                    $currency
                ),
                $currency
            );
            
            if ( ! isset( $responseData['amount'] ) ) {
                return 'پارامتر مبلغ در پاسخ درگاه وجود ندارد';
            }
            
            $returned_amount = intval( $responseData['amount'] );
            if ( $returned_amount !== $expected_amount ) {
                return sprintf( 'مبلغ پرداختی (%s) با مبلغ سفارش (%s) مطابقت ندارد', number_format( $returned_amount ), number_format( $expected_amount ) );
            }
            
            return false;
        }

        private function verify_payment( $order, $Transaction_ID, $responseData ) {
            $order_id = $order->get_id();
            $stored_payment_code = $order->get_meta( '_payping_payCode' );

            $currency = apply_filters( 'WC_payping_Currency', $order->get_currency(), $order_id );
            $expected_amount = apply_filters(
                'woocommerce_order_amount_total_IRANIAN_gateways_irt',
                $this->payping_check_currency(
                    apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', intval( $order->get_total() ), $currency ),
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
                'body'        => wp_json_encode( $data ),
                'timeout'     => 45,
                'redirection' => 5,
                'blocking'    => true,
                'headers'     => [
                    'Authorization' => 'Bearer ' . $this->paypingToken,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json'
                ],
                'httpversion' => '1.0',
                'data_format' => 'body'
            ];

            $verify_api_url = apply_filters( 'WC_payping_Gateway_Payment_verify_api_url', $this->baseurl . '/pay/verify', $order_id );
            $response = wp_safe_remote_post( $verify_api_url, $args );
            $body     = wp_remote_retrieve_body( $response );
            $rbody    = json_decode( $body, true ) ?: [];

            if ( is_wp_error( $response ) ) {
                $this->handle_verification_error( $order, $Transaction_ID, 'خطا در ارتباط به پی‌پینگ: ' . $response->get_error_message() );
                return;
            }

            $code = wp_remote_retrieve_response_code( $response );
            
            if ( isset( $rbody['status'], $rbody['metaData']['code'] ) && $rbody['status'] == 409 ) {
                $error_code = (int) $rbody['metaData']['code'];
                if ( 110 === $error_code ) {
                    $this->handle_duplicate_payment( $order, $Transaction_ID );
                    return;
                } else {
                    $error_message = $rbody['metaData']['errors'][0]['message'] ?? 'خطایی رخ داده است.';
                    $this->handle_payment_failure( $order, $Transaction_ID, $error_message, 'اطلاعات پرداخت نامعتبر است، لطفاً مجدد تلاش کنید.' );
                    return;
                }
            }

            if ( ! isset( $rbody['code'] ) || $rbody['code'] !== $stored_payment_code ) {
                $this->handle_verification_error( $order, $Transaction_ID, 'کد پرداخت برگشتی با کد ذخیره شده مطابقت ندارد' );
                return;
            }

            $response_amount = isset( $rbody['amount'] ) ? intval( $rbody['amount'] ) : 0;
            if ( $response_amount !== $expected_amount ) {
                $this->handle_verification_error( $order, $Transaction_ID, sprintf( 'مبلغ پرداختی (%s) با مبلغ سفارش (%s) مطابقت ندارد', number_format( $response_amount ), number_format( $expected_amount ) ) );
                return;
            }

            $cardNumber  = isset( $rbody['cardNumber'] ) ? sanitize_text_field( $rbody['cardNumber'] ) : '-';
            $CardHashPan = isset( $rbody['cardHashPan'] ) ? sanitize_text_field( $rbody['cardHashPan'] ) : '-';
            
            $order->update_meta_data( 'payping_payment_card_number', $cardNumber );
            $order->update_meta_data( 'payping_payment_card_hashpan', $CardHashPan );
            $order->save();

            if ( 200 === $code ) {
                $this->handle_payment_success( $order, $Transaction_ID, $cardNumber, $this->status_message( $code ) );
            } else {
                $this->handle_verification_error( $order, $Transaction_ID, $this->status_message( $code ) ?: 'خطای نامشخص در تایید پرداخت!' );
            }
        }

        private function get_expected_client_ref_id( $order ) {
            $parent_order_id = $order->get_parent_id();
            if ( $parent_order_id > 0 ) {
                $parent_order = wc_get_order( $parent_order_id );
                if ( $parent_order ) {
                    return (string) $parent_order->get_id();
                }
            }
            return (string) $order->get_id();
        }

        private function handle_payment_success( $order, $Transaction_ID, $cardNumber, $message ) {
            global $woocommerce;
            $order_id = $order->get_id();
            $full_message = sprintf( '%s<br>شماره کارت: <b dir="ltr">%s</b>', $message, $cardNumber );
            
            $order->update_meta_data( '_transaction_id', $Transaction_ID );
            $order->save();

            $woocommerce->cart->empty_cart();
            $order->payment_complete( $Transaction_ID );
            
            $note = sprintf( __( '%s <br>شماره پیگیری پرداخت: %s', 'woo-payping-gateway' ), $full_message, $Transaction_ID );
            $order->add_order_note( $note );

            $notice = wpautop( wptexturize( $this->success_massage ) );
            $notice = str_replace( '{transaction_id}', $Transaction_ID, $notice );
            $notice = apply_filters( 'woocommerce_thankyou_order_received_text', $notice, $order_id, $Transaction_ID );
            wc_add_notice( $notice, 'success' );

            wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
            exit;
        }

        private function handle_verification_error( $order, $Transaction_ID, $error_message ) {
            $order_id = $order->get_id();
            $user_friendly_message = 'خطایی در تأیید پرداخت رخ داده است. لطفاً برای تکمیل خرید مجدداً اقدام نمایید یا با مدیریت سایت تماس بگیرید.';
            
            $tr_id = ( $Transaction_ID && $Transaction_ID != 0 ) ? '<br/>کد پیگیری: ' . $Transaction_ID : '';
            $note = sprintf( __( 'خطا در تأیید پرداخت: %s %s', 'woo-payping-gateway' ), $error_message, $tr_id );
            
            $notice = wpautop( wptexturize( $note ) );
            $notice = str_replace( '{transaction_id}', $Transaction_ID, $notice );
            $notice = str_replace( '{fault}', $error_message, $notice );
            
            $order->add_order_note( $note, 0, false );
            wc_add_notice( $user_friendly_message, 'error' );
            
            // ✅ هدایت به صفحه پرداخت سفارش برای امکان تلاش مجدد
            wp_redirect( $order->get_checkout_payment_url() );
            exit;
        }

        private function handle_duplicate_payment( $order, $Transaction_ID ) {
            $order_id = $order->get_id();
            $message = 'این سفارش قبلا تایید شده است.';
            
            $order->update_meta_data( '_transaction_id', $Transaction_ID );
            $order->save();

            if ( ! $order->is_paid() ) {
                $order->payment_complete( $Transaction_ID );
            }
            
            $order->add_order_note( $message );
            wc_add_notice( $message, 'success' );

            wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
            exit;
        }

        private function handle_payment_failure( $order, $Transaction_ID, $message, $fault ) {
            $order_id = $order->get_id();
            
            $tr_id = ( $Transaction_ID && $Transaction_ID != 0 ) ? '<br/>کد پیگیری: ' . $Transaction_ID : '';
            $note = sprintf( __( 'خطا در هنگام تایید پرداخت: %s %s', 'woo-payping-gateway' ), $message, $tr_id );
            
            $notice = wpautop( wptexturize( $note ) );
            $notice = str_replace( "{transaction_id}", $Transaction_ID, $notice );
            $notice = str_replace( "{fault}", $message, $notice );
            
            $order->add_order_note( $notice, 0, false );
            wc_add_notice( $fault, 'error' );
            
            // ✅ هدایت به صفحه پرداخت سفارش برای امکان تلاش مجدد
            wp_redirect( $order->get_checkout_payment_url() );
            exit;
        }

        public function payping_check_currency( $Amount, $currency ) {
            $currency_lower = strtolower( $currency );
            if ( in_array( $currency_lower, array( 'irt', 'toman', 'iran toman', 'iranian toman', 'iran-toman', 'iranian-toman', 'iran_toman', 'iranian_toman', 'تومان', 'تومان ایران' ) ) ) {
                $Amount = $Amount * 1;
            } elseif ( $currency_lower == 'irht' ) {
                $Amount = $Amount * 1000;
            } elseif ( $currency_lower == 'irhr' ) {
                $Amount = $Amount * 100;					
            } elseif ( $currency_lower == 'irr' ) {
                $Amount = $Amount / 10;
            }
            return $Amount;                      
        }

        public function status_message( $code ) {
            switch ( $code ) {
                case 200: return 'عملیات با موفقیت انجام شد';
                case 400: return 'مشکلی در ارسال درخواست وجود دارد';
                case 500: return 'مشکلی در سرور رخ داده است';
                case 503: return 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد';
                case 401: return 'عدم دسترسی';
                case 403: return 'دسترسی غیر مجاز';
                case 404: return 'آیتم درخواستی مورد نظر موجود نمی‌باشد';
                default:  return 'خطای نامشخص';
            }
        }
    }
}

// Hook for processing payment form submission
add_action( 'init', function() {
    if ( class_exists( 'WC_payping' ) ) {
        $gateway = new WC_payping();
        $gateway->process_payment_submission();
    }
});