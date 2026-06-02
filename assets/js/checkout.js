/**
 * Register PayPing Regular payment method for WooCommerce Blocks (Gutenberg).
 * Wrapped in IIFE to avoid conflicts with other payment methods.
 */
(function() {
  // Destructure required utilities from global WC Blocks Registry and WordPress libraries
  const { registerPaymentMethod } = wc.wcBlocksRegistry;
  const { createElement } = wp.element;
  const { __ } = wp.i18n;

  /**
   * React component for payment method content
   * @returns {JSX.Element} Payment method UI with description
   */
  const PaypingRegularContent = () => {
    return createElement(
      'div',
      { 
        className: 'payping-regular-content',
        'data-testid': 'payping-regular-container'
      },
      // Payment Description
      createElement(
        'div',
        { className: 'payping-regular-description' },
        paypingRegularSettings.description
      )
    );
  };

  // Register payment method with WooCommerce Blocks
  registerPaymentMethod({
    name: 'WC_payping', // Unique payment method ID
    label: createElement(
      'div',
      { className: 'payping-regular-label-wrapper' },
      // Logo in payment method list
      createElement('img', {
        src: paypingRegularSettings.icon,
        alt: paypingRegularSettings.title,
        style: { // Inline styles
          display: 'inline-block',
          margin: '0 0 0 10px',
          verticalAlign: 'middle',
          maxWidth: '100px'
        },
        className: 'payping-regular-icon'
      }),
      paypingRegularSettings.title
    ),
    ariaLabel: paypingRegularSettings.ariaLabel,
    content: createElement(PaypingRegularContent),
    edit: null,
    canMakePayment: () => true,
    paymentMethodId: 'WC_payping'
  });
})();