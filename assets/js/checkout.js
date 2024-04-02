const payping_settings = window.wc.wcSettings.getSetting( 'payping_gateway_data', {} );
const payping_label = window.wp.htmlEntities.decodeEntities( payping_settings.title ) || window.wp.i18n.__( 'پرداخت از طریق پی‌پینگ', 'woocommerce' );
const payping_Content = () => {
    return window.wp.htmlEntities.decodeEntities( payping_settings.description || '' );
};
const Payping_Block_Gateway = {
    name: 'WC_payping',
    label: payping_label,
    content: Object( window.wp.element.createElement )( payping_Content, null ),
    edit: Object( window.wp.element.createElement )( payping_Content, null ),
    canMakePayment: () => true,
    ariaLabel: payping_label,
    supports: {
        features: payping_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Payping_Block_Gateway );