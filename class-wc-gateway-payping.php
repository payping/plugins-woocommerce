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
            $order   = wc_get_order( $order_id );
            $pay_url = $this->get_payment_start_url( $order );

            if ( is_wp_error( $pay_url ) ) {
                $this->record_send_failure( $order, $pay_url );
                wc_add_notice( $this->get_failed_notice( $pay_url->get_error_message() ), 'error' );

                return array( 'result' => 'failure' );
            }

            return array(
                'result'   => 'success',
                'redirect' => $pay_url
            );
        }

        // Legacy receipt page: order-pay URL with the order key only.
        public function payment_receipt_page( $order_id ) {
            $order = wc_get_order( $order_id );

            if ( ! $order || $order->get_payment_method() !== $this->id ) {
                return;
            }

            // Do not re-enter the gateway for a paid order.
            if ( $order->is_paid() ) {
                wp_redirect( $this->get_return_url( $order ) );
                exit;
            }

            $this->Send_to_payping_Gateway( $order_id );
        }

        // Legacy entry point, also used by third-party code.
        public function Send_to_payping_Gateway( $order_id ) {
            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                wc_add_notice( __( 'سفارش یافت نشد!', 'woo-payping-gateway' ), 'error' );
                wp_safe_redirect( wc_get_checkout_url() );
                exit;
            }

            $pay_url = $this->get_payment_start_url( $order );

            if ( is_wp_error( $pay_url ) ) {
                $this->record_send_failure( $order, $pay_url );
                wc_add_notice( $this->get_failed_notice( $pay_url->get_error_message() ), 'error' );

                wp_safe_redirect( $order->get_checkout_payment_url() );
                exit;
            }

            wp_redirect( $pay_url );
            exit;
        }

        /**
         * Get the pay/start URL for the order, creating the payment first when needed.
         *
         * @return string|WP_Error
         */
        private function get_payment_start_url( $order ) {
            global $woocommerce;
            $order_id = $order->get_id();

            // Session fallback for the return URL.
            if ( isset( $woocommerce->session ) ) {
                $woocommerce->session->order_id_payping = $order_id;
            }

            // Reuse the stored payment code, unless the order total has changed
            // since the payment was created.
            $paypingpayCode = $order->get_meta( '_payping_payCode' );
            if ( '' !== $paypingpayCode ) {
                $stored_amount = $this->get_stored_amount( $order );
                if ( null === $stored_amount || $stored_amount === $this->get_order_amount_in_toman( $order ) ) {
                    return sprintf( '%s/pay/start/%s', $this->baseurl, $paypingpayCode );
                }

                $this->invalidate_payment_code( $order, __( 'مبلغ سفارش تغییر کرده است', 'woo-payping-gateway' ) );
            }

            // PayPing requires the amount as a JSON number.
            $Amount = $this->get_order_amount_in_toman( $order );
        
            $CallbackUrl = add_query_arg( 'wc_order', $order_id, WC()->api_request_url( 'WC_payping' ) );
            
            $Description = sprintf(
                'خرید به شماره سفارش: %s | توسط: %s %s | خرید از %s',
                $order->get_order_number(),
                $order->get_billing_first_name(),
                $order->get_billing_last_name(),
                get_bloginfo( 'name' )
            );
            
            $Mobile = $order->get_billing_phone() ?: '-';
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
                'ClientRefId'   => $this->get_client_ref_id( $order ),
                'NationalCode'  => ''
            ];
        
            $args = [
                'body'    => wp_json_encode( $data ),
                'timeout' => 45,
                'headers' => [
                    'X-Platform'         => 'woocommerce',
                    'X-Platform-Version' => WOO_PAYPING_VERSION,
                    'Authorization'      => 'Bearer ' . $this->paypingToken,
                    'Content-Type'       => 'application/json',
                    'Accept'             => 'application/json'
                ]
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
                        $order->update_meta_data( '_payping_amount', $Amount );
                        $order->save();

                        Payping_Order_Panel::log_event( $order, 'created', 'ساخت موفق پرداخت، کد پرداخت: ' . $code_pay['paymentCode'] );

                        return sprintf( '%s/pay/start/%s', $this->baseurl, $code_pay['paymentCode'] );
                    }
                }
                
                $Message = ( 200 !== $code )
                    ? $this->get_api_error_message( $body, $code )
                    : __( 'تراکنش ناموفق بود', 'woo-payping-gateway' );
            }

            if ( $ERR_ID ) {
                $Message .= ' | ' . __( 'شناسه درخواست', 'woo-payping-gateway' ) . ': ' . sanitize_text_field( $ERR_ID );
            }

            // Keep the trace id from the error body for the order panel.
            $response_data = isset( $body ) ? json_decode( (string) $body, true ) : [];
            $error_details = [];
            if ( isset( $response_data['paypingTraceId'] ) ) {
                $error_details['trace_id'] = sanitize_text_field( (string) $response_data['paypingTraceId'] );
            }

            return new WP_Error( 'payping_send_to_gateway', $Message, $error_details );
        }

        /**
         * Human readable message for a failed PayPing API response.
         *
         * PayPing replies with RFC 7807 problem+json errors where the
         * description for the merchant sits in metaData.errors. Unknown
         * payloads fall back to the raw body, or a generic message when
         * the body is empty or too long to be useful.
         */
        private function get_api_error_message( $body, $status_code ) {
            $data     = json_decode( (string) $body, true );
            $messages = [];

            if ( is_array( $data ) ) {
                foreach ( $data['metaData']['errors'] ?? [] as $error ) {
                    if ( ! empty( $error['message'] ) ) {
                        $messages[] = sanitize_text_field( $error['message'] );
                    }
                }

                if ( ! $messages && ! empty( $data['detail'] ) ) {
                    $messages[] = sanitize_text_field( $data['detail'] );
                }
            }

            if ( ! $messages ) {
                $raw = trim( wp_strip_all_tags( (string) $body ) );
                $messages[] = ( '' !== $raw && mb_strlen( $raw ) <= 200 )
                    ? $raw
                    : sprintf( __( 'خطای نامشخص در ارتباط با درگاه پی‌پینگ (کد %s)', 'woo-payping-gateway' ), $status_code );
            }

            $message = implode( ' | ', $messages );

            $error_code = (int) ( $data['metaData']['code'] ?? 0 );
            if ( $error_code ) {
                $message .= ' | ' . __( 'کد خطا', 'woo-payping-gateway' ) . ': ' . $error_code;
            }

            return $message;
        }

        private function record_send_failure( $order, $error ) {
            $Message = is_wp_error( $error ) ? $error->get_error_message() : $error;
            $details = is_wp_error( $error ) ? (array) $error->get_error_data() : [];

            Payping_Order_Panel::log_event( $order, 'error', __( 'خطا در هنگام ارسال به بانک: ', 'woo-payping-gateway' ) . $Message, $details );
            do_action( 'woo_payping_Send_to_Gateway_Failed', $order->get_id(), $Message );
        }

        /**
         * Amount the stored payment code was created with, or null for older payments.
         */
        private function get_stored_amount( $order ) {
            $amount = $order->get_meta( '_payping_amount' );

            return '' === $amount ? null : (int) $amount;
        }

        /**
         * Amount a payment response must match: the stored amount, or the
         * current order amount for older payments.
         */
        private function get_expected_amount( $order ) {
            return $this->get_stored_amount( $order ) ?? $this->get_order_amount_in_toman( $order );
        }

        /**
         * Remove the stored payment code and amount, so the next attempt
         * creates a new payment.
         */
        private function invalidate_payment_code( $order, $reason = '' ) {
            if ( '' === $order->get_meta( '_payping_payCode' ) ) {
                return;
            }

            $order->delete_meta_data( '_payping_payCode' );
            $order->delete_meta_data( '_payping_amount' );
            $order->save();

            $message = 'کد پرداخت این سفارش در ووکامرس باطل شد';
            if ( '' !== $reason ) {
                $message .= ' | ' . $reason;
            }
            Payping_Order_Panel::log_event( $order, 'invalidated', $message );
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

            // Nothing left to do for a paid order.
            if ( $order->is_paid() ) {
                wp_redirect( $this->get_return_url( $order ) );
                exit;
            }

            // No active PayPing payment on this order.
            if ( $order->get_payment_method() !== $this->id || '' === $order->get_meta( '_payping_payCode' ) ) {
                wp_redirect( wc_get_checkout_url() );
                exit;
            }

            $clientRefId   = isset( $responseData['clientRefId'] ) ? sanitize_text_field( $responseData['clientRefId'] ) : '';
            $expectedRefId = $this->get_client_ref_id( $order );
            $refid         = isset( $responseData['paymentRefId'] ) ? sanitize_text_field( $responseData['paymentRefId'] ) : null;
            $Transaction_ID = apply_filters( 'WC_payping_return_refid', $refid );

            if ( '' === $clientRefId || $clientRefId !== $expectedRefId ) {
                $error_message = sprintf( 'شناسه سفارش برگشتی (%s) با شناسه سفارش اصلی (%s) مطابقت ندارد', $clientRefId ?: 'ندارد', $expectedRefId );
                $this->handle_verification_error( $order, $Transaction_ID, $error_message );
                return;
            }

            $status = isset( $_REQUEST['status'] ) ? absint( $_REQUEST['status'] ) : null;
            
            // User cancelled at the bank: back to the order payment page
            if ( 0 === $status ) {
                Payping_Order_Panel::log_event( $order, 'cancelled', 'کاربر در صفحه بانک از پرداخت انصراف داده است.' );
                wc_add_notice( 'تراكنش توسط شما لغو شد. لطفاً برای تکمیل خرید مجدداً اقدام نمایید.', 'error' );

                do_action( 'woo_payping_Payment_Cancelled', $order_id );

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
            $stored_payment_code = $order->get_meta( '_payping_payCode' );

            if ( empty( $stored_payment_code ) ) {
                return 'کد پرداخت ذخیره شده یافت نشد';
            }
            if ( ! isset( $responseData['paymentCode'] ) ) {
                return 'پارامتر کد پرداخت در پاسخ درگاه وجود ندارد';
            }

            $returned_payment_code = sanitize_text_field( $responseData['paymentCode'] );
            if ( $returned_payment_code !== $stored_payment_code ) {
                return sprintf( 'کد پرداخت برگشتی (%s) با کد ذخیره شده (%s) مطابقت ندارد', $returned_payment_code, $stored_payment_code );
            }

            // Amount must match what was sent to PayPing
            $expected_amount = $this->get_expected_amount( $order );

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

            // Amount must match what was sent to PayPing
            $expected_amount = $this->get_expected_amount( $order );

            $data = [
                'PaymentRefId' => $Transaction_ID,
                'PaymentCode'  => $stored_payment_code,
                'Amount'       => $expected_amount
            ];

            $args = [
                'body'    => wp_json_encode( $data ),
                'timeout' => 45,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paypingToken,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json'
                ]
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

            if ( 200 !== $code && isset( $rbody['status'], $rbody['metaData']['code'] ) ) {
                $error_code = (int) $rbody['metaData']['code'];
                $trace_id   = isset( $rbody['paypingTraceId'] ) ? sanitize_text_field( (string) $rbody['paypingTraceId'] ) : '';

                // 409/110 means this payment was already verified before.
                if ( 409 === (int) $rbody['status'] && 110 === $error_code ) {
                    $this->handle_duplicate_payment( $order, $Transaction_ID );
                    return;
                }

                $this->handle_payment_failure( $order, $Transaction_ID, $this->get_api_error_message( $body, $code ), $trace_id );
                return;
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

        // ClientRefId sent to PayPing for this order.
        private function get_client_ref_id( $order ) {
            return (string) $order->get_order_number();
        }

        private function handle_payment_success( $order, $Transaction_ID, $cardNumber, $message ) {
            global $woocommerce;
            $order_id = $order->get_id();

            $order->update_meta_data( '_transaction_id', $Transaction_ID );
            $order->save();

            $woocommerce->cart->empty_cart();
            $order->payment_complete( $Transaction_ID );

            $event = sprintf( '%s | شماره کارت: %s | شماره پیگیری پرداخت: %s', $message, $cardNumber, $Transaction_ID );
            Payping_Order_Panel::log_event( $order, 'verified', $event );

            // The payment code is used up.
            $this->invalidate_payment_code( $order );

            do_action( 'woo_payping_Payment_Success', $order_id, $Transaction_ID );

            $notice = wpautop( wptexturize( $this->success_massage ) );
            $notice = str_replace( '{transaction_id}', $Transaction_ID, $notice );
            $notice = apply_filters( 'woocommerce_thankyou_order_received_text', $notice, $order_id, $Transaction_ID );
            wc_add_notice( $notice, 'success' );

            wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
            exit;
        }

        private function handle_verification_error( $order, $Transaction_ID, $error_message, $trace_id = '' ) {
            $tr_id  = ( $Transaction_ID && $Transaction_ID != 0 ) ? ' | کد پیگیری: ' . $Transaction_ID : '';
            $message = sprintf( __( 'خطا در تأیید پرداخت: %s%s', 'woo-payping-gateway' ), $error_message, $tr_id );

            Payping_Order_Panel::log_event( $order, 'error', $message, array( 'trace_id' => $trace_id ) );
            wc_add_notice( $this->get_failed_notice( $error_message, $Transaction_ID ), 'error' );
            
            // Back to the order payment page for another attempt
            wp_redirect( $order->get_checkout_payment_url() );
            exit;
        }

        private function handle_duplicate_payment( $order, $Transaction_ID ) {
            global $woocommerce;
            $message = 'این سفارش قبلا تایید شده است.';

            if ( ! $order->is_paid() ) {
                $order->update_meta_data( '_transaction_id', $Transaction_ID );
                $order->save();

                $woocommerce->cart->empty_cart();
                $order->payment_complete( $Transaction_ID );
                Payping_Order_Panel::log_event( $order, 'duplicate', $message );
            }

            // The code was already used by the first verification.
            $this->invalidate_payment_code( $order, $message );

            do_action( 'woo_payping_Payment_Duplicate', $order->get_id(), $Transaction_ID );

            wc_add_notice( $message, 'success' );

            wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
            exit;
        }

        private function handle_payment_failure( $order, $Transaction_ID, $message, $trace_id = '' ) {
            $tr_id  = ( $Transaction_ID && $Transaction_ID != 0 ) ? ' | کد پیگیری: ' . $Transaction_ID : '';
            $event = sprintf( __( 'خطا در هنگام تایید پرداخت: %s%s', 'woo-payping-gateway' ), $message, $tr_id );

            Payping_Order_Panel::log_event( $order, 'failed', $event, array( 'trace_id' => $trace_id ) );

            // Force a new payment on the next attempt.
            $this->invalidate_payment_code( $order, $message );

            do_action( 'woo_payping_Payment_Failed', $order->get_id(), $Transaction_ID, $message );

            wc_add_notice( $this->get_failed_notice( $message, $Transaction_ID ), 'error' );
            
            // Back to the order payment page for another attempt
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

        /**
         * Order total converted to Toman. The filter chain is kept for
         * third-party code that adjusts the amount. Returns an integer.
         */
        private function get_order_amount_in_toman( $order ) {
            $order_id = $order->get_id();
            $currency = apply_filters( 'WC_payping_Currency', $order->get_currency(), $order_id );
            $Amount   = (float) $order->get_total();
            $Amount   = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency );
            $Amount   = $this->payping_check_currency( $Amount, $currency );
            $Amount   = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency );
            $Amount   = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency );
            $Amount   = apply_filters( 'woocommerce_order_amount_total_payping_gateway', $Amount, $currency );

            return (int) round( $Amount );
        }

        /**
         * Failure notice based on failed_massage ({transaction_id}, {fault}).
         */
        private function get_failed_notice( $fault, $Transaction_ID = '' ) {
            $message = $this->failed_massage;
            if ( '' === $message ) {
                $message = __( 'پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woo-payping-gateway' );
            }

            $notice = wpautop( wptexturize( $message ) );
            $notice = str_replace( '{transaction_id}', wp_strip_all_tags( (string) $Transaction_ID ), $notice );
            $notice = str_replace( '{fault}', wp_strip_all_tags( $fault ), $notice );

            return $notice;
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