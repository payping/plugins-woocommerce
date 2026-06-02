<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Payping_Gateway_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'WC_payping';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_payping_gateway_settings', [] );
        $this->gateway = new WC_payping();
    }

    public function is_active() {
        return $this->gateway ? $this->gateway->is_available() : false;
    }

    public function get_payment_method_script_handles() {
        
        wp_register_script(
            'payping-gateway-blocks',
            WOO_GPPDU . '/assets/js/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            '1.0.1',
            true
        );
        
        // ⬇️ استفاده از wp_localize_script (دقیقاً مثل الگوی اقساطی)
        wp_localize_script(
            'payping-gateway-blocks',
            'paypingRegularSettings',
            [
                'icon'        => WOO_GPPDU . '/assets/images/logo.png',
                'title'       => $this->gateway->title ?? '',
                'description' => $this->gateway->description ?? '',
                'ariaLabel'   => $this->gateway->title ?? ''
            ]
        );
        
        if ( function_exists( 'wp_set_script_translations' ) ) {            
            wp_set_script_translations( 'payping-gateway-blocks' );
        }
        
        return [ 'payping-gateway-blocks' ];
    }

    public function get_payment_method_data() {
        if ( is_null( $this->gateway ) ) {
            return [];
        }
        
        return [
            'title'       => $this->gateway->title ?? '',
            'description' => $this->gateway->description ?? '',
            'icon'        => WOO_GPPDU . '/assets/images/logo.png',
            'supports'    => $this->gateway->supports ?? [],
        ];
    }
}