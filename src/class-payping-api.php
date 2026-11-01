<?php
namespace WooPayping;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Thin HTTP client for the PayPing v3 payment API.
 *
 * The gateway class builds request bodies and applies its public filters,
 * then hands the final request here. Everything the remote API returns is
 * normalized into a Result object so callers never parse raw responses.
 * The Bearer token is only used to build the request; it never leaves
 * this class and is never part of a Result.
 */
final class Payping_Api {

    /**
     * POST /pay — create a payment, return its code and start URL fragment.
     */
    public function create_payment( $url, $args ) {
        $response = wp_safe_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return Result::from_wp_error( $response );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( (string) $body, true );

        $request_id = sanitize_text_field( (string) wp_remote_retrieve_header( $response, 'x-paypingrequest-id' ) );

        if ( 200 === $code && isset( $json['paymentCode'] ) && '' !== $json['paymentCode'] ) {
            return Result::ok( $code )
                ->set( 'payment_code', sanitize_text_field( (string) $json['paymentCode'] ) )
                ->set( 'request_id', $request_id );
        }

        $message = ( 200 !== $code )
            ? self::error_message( $body, $code )
            : __( 'تراکنش ناموفق بود', 'woo-payping-gateway' );

        return Result::fail( $code, $message )
            ->set( 'request_id', $request_id )
            ->set( 'trace_id', self::trace_id( $body ) );
    }

    /**
     * POST /pay/verify — confirm a payment.
     *
     * A 409 with metaData code 110 means the payment was already verified
     * before; the result is flagged so the caller can treat it as success.
     */
    public function verify_payment( $url, $args ) {
        $response = wp_safe_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return Result::from_wp_error( $response );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( (string) $body, true );

        $result = ( 200 === $code )
            ? Result::ok( $code )
            : Result::fail( $code, self::error_message( $body, $code ) );

        $result->set( 'body', is_array( $json ) ? $json : [] )
            ->set( 'trace_id', self::trace_id( $body ) );

        if ( 409 === $code && 110 === (int) ( $json['metaData']['code'] ?? 0 ) ) {
            $result->duplicate = true;
        }

        return $result;
    }

    /**
     * Human readable message for a failed PayPing API response.
     *
     * PayPing replies with RFC 7807 problem+json errors where the
     * description for the merchant sits in metaData.errors. Unknown
     * payloads fall back to the raw body, or a generic message when the
     * body is empty or too long to be useful.
     */
    public static function error_message( $body, $status_code ) {
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

    /**
     * PayPing puts its support trace id on every error body.
     */
    private static function trace_id( $body ) {
        $data = json_decode( (string) $body, true );
        return isset( $data['paypingTraceId'] ) ? sanitize_text_field( (string) $data['paypingTraceId'] ) : '';
    }
}

/**
 * One API call outcome. ok/duplicate speak for themselves, extras carry
 * whatever the specific call returned (payment_code, body, trace_id, ...).
 */
final class Result {

    public $ok          = false;
    public $status_code = 0;
    public $message     = '';
    public $duplicate   = false;
    public $extras      = [];

    public function __construct( $ok, $status_code, $message ) {
        $this->ok          = $ok;
        $this->status_code = (int) $status_code;
        $this->message     = (string) $message;
    }

    public static function ok( $status_code ) {
        return new self( true, $status_code, '' );
    }

    public static function fail( $status_code, $message ) {
        return new self( false, $status_code, $message );
    }

    public static function from_wp_error( $wp_error ) {
        return self::fail( 0, $wp_error->get_error_message() );
    }

    public function set( $key, $value ) {
        $this->extras[ $key ] = $value;
        return $this;
    }

    public function get( $key, $default = '' ) {
        return array_key_exists( $key, $this->extras ) ? $this->extras[ $key ] : $default;
    }
}
