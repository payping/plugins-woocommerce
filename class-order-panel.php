<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PayPing data box on the order admin screen.
 *
 * Payment events are stored in order meta instead of generic order notes,
 * so PayPing data stays separate from data written by other gateways and
 * the box can later host actions such as refund or reverse.
 */
class Payping_Order_Panel {

    const EVENTS_META     = '_payping_events';
    const LAST_ERROR_META = '_payping_last_error';
    const MAX_EVENTS      = 30;

    public static function init() {
        if ( is_admin() ) {
            add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
            add_action( 'admin_post_woo_payping_reverify', array( __CLASS__, 'handle_reverify' ) );
            add_action( 'admin_notices', array( __CLASS__, 'print_reverify_notice' ) );
        }
    }

    /**
     * Append a payment event to the order timeline.
     *
     * Error events also refresh the stored last-error snapshot, which
     * keeps the PayPing trace id available for support requests.
     */
    public static function log_event( $order, $type, $message, $extra = array() ) {
        if ( ! $order || ! is_a( $order, 'WC_Abstract_Order' ) ) {
            return;
        }

        $events   = $order->get_meta( self::EVENTS_META );
        $events   = is_array( $events ) ? $events : array();
        $events[] = array(
            'type'    => sanitize_key( $type ),
            'message' => wp_strip_all_tags( (string) $message ),
            'time'    => time(),
        );

        $order->update_meta_data( self::EVENTS_META, array_slice( $events, -self::MAX_EVENTS ) );

        if ( 'error' === $type || 'failed' === $type ) {
            $order->update_meta_data( self::LAST_ERROR_META, array(
                'message'  => wp_strip_all_tags( (string) $message ),
                'trace_id' => isset( $extra['trace_id'] ) ? sanitize_text_field( (string) $extra['trace_id'] ) : '',
                'time'     => time(),
            ) );
        }

        $order->save();
    }

    public static function register_meta_box() {
        $screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

        $order = self::get_current_order();
        if ( ! $order || 'WC_payping' !== $order->get_payment_method() ) {
            return;
        }

        add_meta_box( 'payping_order_panel', 'داده‌های درگاه پی‌پینگ', array( __CLASS__, 'render' ), $screen, 'side', 'high' );
    }

    public static function render( $post = null ) {
        $order = ( $post instanceof WP_Post ) ? wc_get_order( $post->ID ) : self::get_current_order();
        if ( ! $order || 'WC_payping' !== $order->get_payment_method() ) {
            return;
        }

        $events = $order->get_meta( self::EVENTS_META );
        $events = is_array( $events ) ? $events : array();

        self::print_styles();
        ?>
        <div class="payping-order-panel">
            <ul class="payping-rows">
                <?php foreach ( self::get_rows( $order, $events ) as $row ) : ?>
                    <?php
                    $classes = array( 'value' );
                    if ( ! empty( $row['class'] ) ) {
                        $classes[] = $row['class'];
                    }
                    if ( ! empty( $row['ltr'] ) ) {
                        $classes[] = 'ltr';
                    }
                    ?>
                    <li>
                        <span class="label"><?php echo esc_html( $row['label'] ); ?></span>
                        <span class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
                            <?php echo esc_html( $row['value'] ); ?>
                            <?php if ( ! empty( $row['copy'] ) ) : ?>
                                <button type="button" class="button button-small payping-copy" data-copy="<?php echo esc_attr( $row['value'] ); ?>">کپی</button>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php self::render_reverify_section( $order ); ?>
            <?php if ( $events ) : ?>
                <div class="payping-section-title">رویدادها</div>
                <ul class="payping-events">
                    <?php foreach ( array_reverse( $events ) as $event ) : ?>
                        <li class="payping-ev-<?php echo esc_attr( $event['type'] ); ?>">
                            <span class="payping-ev-time">
                                <?php echo esc_html( self::format_time( (int) $event['time'] ) ); ?>
                                <span class="payping-ev-badge"><?php echo esc_html( self::event_badge_label( $event['type'] ) ); ?></span>
                            </span>
                            <span class="payping-ev-text"><?php echo esc_html( $event['message'] ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        self::print_scripts();
    }

    /**
     * Short Persian labels for the event timeline badges.
     */
    private static function event_badge_label( $type ) {
        $labels = array(
            'created'    => 'ثبت',
            'verified'   => 'تأیید',
            'duplicate'  => 'تکراری',
            'failed'     => 'ناموفق',
            'error'      => 'خطا',
            'cancelled'  => 'انصراف',
            'invalidated' => 'ابطال',
        );

        return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
    }

    /**
     * The re-check button for unpaid orders whose return trip from the
     * gateway was lost or failed. Needs both the payment code and the
     * reference id the gateway handed back.
     */
    private static function render_reverify_section( $order ) {
        if ( $order->is_paid() || 'WC_payping' !== $order->get_payment_method() ) {
            return;
        }

        $pay_code = $order->get_meta( '_payping_payCode' );
        if ( ! $pay_code ) {
            return;
        }

        $refid = $order->get_meta( '_payping_refid' );
        if ( ! $refid ) {
            echo '<p class="payping-reverify-hint">کد پیگیری درگاه برای این پرداخت ثبت نشده و استعلام مجدد ممکن نیست.</p>';
            return;
        }

        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=woo_payping_reverify&order=' . $order->get_id() ),
            'woo_payping_reverify_' . $order->get_id()
        );

        echo '<p class="payping-reverify"><a href="' . esc_url( $url ) . '" class="button">استعلام مجدد پرداخت</a></p>';
    }

    /**
     * admin-post handler behind the re-check button.
     */
    public static function handle_reverify() {
        $order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
        check_admin_referer( 'woo_payping_reverify_' . $order_id );

        if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'شما اجازه انجام این عملیات را ندارید.' );
        }

        $order = $order_id ? wc_get_order( $order_id ) : false;
        if ( ! $order || ! is_a( $order, 'WC_Order' ) || 'WC_payping' !== $order->get_payment_method() ) {
            wp_die( 'سفارش نامعتبر است.' );
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        if ( ! isset( $gateways['WC_payping'] ) ) {
            wp_die( 'درگاه پی‌پینگ در دسترس نیست.' );
        }

        $outcome = $gateways['WC_payping']->process_reverify( $order );

        wp_safe_redirect( add_query_arg(
            array(
                'payping_reverify' => $outcome['success'] ? 'ok' : 'fail',
                'payping_msg'      => rawurlencode( $outcome['message'] ),
            ),
            $order->get_edit_order_url()
        ) );
        exit;
    }

    /**
     * Result banner the admin lands on after the re-check.
     */
    public static function print_reverify_notice() {
        if ( empty( $_GET['payping_reverify'] ) || empty( $_GET['payping_msg'] ) ) {
            return;
        }

        $ok      = 'ok' === sanitize_text_field( wp_unslash( $_GET['payping_reverify'] ) );
        $message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['payping_msg'] ) ) );

        echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    private static function print_scripts() {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;
        ?>
        <script>
            (function () {
                var panel = document.querySelector('.payping-order-panel');
                if (!panel) {
                    return;
                }
                panel.addEventListener('click', function (event) {
                    var button = event.target.closest('.payping-copy');
                    if (!button) {
                        return;
                    }
                    var text = button.getAttribute('data-copy');
                    var done = function () {
                        var original = button.textContent;
                        button.textContent = 'کپی شد';
                        setTimeout(function () { button.textContent = original; }, 1200);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(done);
                    } else {
                        var input = document.createElement('textarea');
                        input.value = text;
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        document.body.removeChild(input);
                        done();
                    }
                });
            })();
        </script>
        <?php
    }

    /**
     * Rows shown at the top of the box. The list is filterable so other
     * code can add fields without touching the template.
     */
    private static function get_rows( $order, $events ) {
        $rows = array( self::get_status_row( $order, $events ) );

        $pay_code = $order->get_meta( '_payping_payCode' );
        if ( $pay_code ) {
            $rows[] = array( 'label' => 'کد پرداخت', 'value' => $pay_code, 'ltr' => true, 'copy' => true );
        }

        $amount = $order->get_meta( '_payping_amount' );
        if ( '' !== $amount && null !== $amount ) {
            $rows[] = array( 'label' => 'مبلغ ارسالی', 'value' => number_format_i18n( (int) $amount ) . ' تومان' );
        }

        $transaction_id = $order->get_transaction_id();
        if ( $transaction_id ) {
            $rows[] = array( 'label' => 'شماره تراکنش', 'value' => $transaction_id, 'ltr' => true, 'copy' => true );
        }

        $card_number = $order->get_meta( 'payping_payment_card_number' );
        if ( $card_number && '-' !== $card_number ) {
            $rows[] = array( 'label' => 'شماره کارت', 'value' => self::format_card_number( $card_number ), 'ltr' => true );
        }

        $last_error = $order->get_meta( self::LAST_ERROR_META );
        if ( ! empty( $last_error['message'] ) ) {
            $rows[] = array( 'label' => 'آخرین خطا', 'value' => $last_error['message'] );

            if ( ! empty( $last_error['trace_id'] ) ) {
                $rows[] = array( 'label' => 'Trace ID', 'value' => $last_error['trace_id'], 'ltr' => true, 'copy' => true );
            }
        }

        if ( $events ) {
            $last_event = end( $events );
            $rows[] = array( 'label' => 'آخرین بروزرسانی', 'value' => self::format_time( (int) $last_event['time'] ) );
        }

        return apply_filters( 'woo_payping_order_panel_rows', $rows, $order );
    }

    /**
     * Show card numbers from the gateway in readable groups. Masked values
     * (containing stars) already carry their own separators.
     */
    private static function format_card_number( $card_number ) {
        $card_number = (string) $card_number;

        if ( false === strpos( $card_number, '*' ) && preg_match( '/^\d{12,19}$/', $card_number ) ) {
            return trim( chunk_split( $card_number, 4, '-' ), '-' );
        }

        return $card_number;
    }

    /**
     * Payment status derived from the order state and the last event.
     */
    private static function get_status_row( $order, $events ) {
        if ( $order->is_paid() ) {
            return array( 'label' => 'وضعیت', 'value' => 'پرداخت شده', 'class' => 'payping-st-ok' );
        }

        $last_type = $events ? end( $events )['type'] : '';

        if ( 'error' === $last_type || 'failed' === $last_type ) {
            return array( 'label' => 'وضعیت', 'value' => 'ناموفق', 'class' => 'payping-st-fail' );
        }

        if ( 'cancelled' === $last_type ) {
            return array( 'label' => 'وضعیت', 'value' => 'انصراف از پرداخت', 'class' => 'payping-st-warn' );
        }

        if ( $order->get_meta( '_payping_payCode' ) ) {
            return array( 'label' => 'وضعیت', 'value' => 'در انتظار پرداخت', 'class' => 'payping-st-pending' );
        }

        return array( 'label' => 'وضعیت', 'value' => '—' );
    }

    private static function format_time( $timestamp ) {
        return wp_date( 'Y/m/d H:i', $timestamp );
    }

    /**
     * Order being edited on the current admin screen.
     */
    private static function get_current_order() {
        // HPOS order screens pass the order id in the query string,
        // classic order screens expose the post global.
        $order_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $order_id && ! empty( $GLOBALS['post']->ID ) ) {
            $order_id = (int) $GLOBALS['post']->ID;
        }

        return $order_id ? wc_get_order( $order_id ) : false;
    }

    private static function print_styles() {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;
        ?>
        <style>
            .payping-order-panel ul { margin: 0; padding: 0; list-style: none; }
            .payping-order-panel .payping-rows li { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px solid #f0f0f1; }
            .payping-order-panel .payping-rows li:last-child { border-bottom: 0; }
            .payping-order-panel .payping-rows .label { color: #646970; flex-shrink: 0; }
            .payping-order-panel .payping-rows .value { text-align: left; word-break: break-word; }
            .payping-order-panel .payping-rows .value.ltr { direction: ltr; unicode-bidi: embed; }
            .payping-order-panel .payping-st-ok { color: #00a32a; font-weight: 600; }
            .payping-order-panel .payping-st-fail { color: #d63638; font-weight: 600; }
            .payping-order-panel .payping-st-warn { color: #996800; font-weight: 600; }
            .payping-order-panel .payping-st-pending { color: #646970; }
            .payping-order-panel .payping-section-title { margin: 12px 0 6px; color: #1d2327; font-weight: 600; }
            .payping-order-panel .payping-events li { position: relative; margin: 0; padding: 0 16px 12px 0; border-right: 2px solid #e2e4e7; }
            .payping-order-panel .payping-events li:last-child { border-right-color: transparent; padding-bottom: 2px; }
            .payping-order-panel .payping-events li::before { content: ''; position: absolute; right: -5px; top: 4px; width: 8px; height: 8px; border-radius: 50%; background: #8c8f94; }
            .payping-order-panel .payping-events li.payping-ev-verified::before,
            .payping-order-panel .payping-events li.payping-ev-duplicate::before { background: #00a32a; }
            .payping-order-panel .payping-events li.payping-ev-error::before,
            .payping-order-panel .payping-events li.payping-ev-failed::before { background: #d63638; }
            .payping-order-panel .payping-events li.payping-ev-cancelled::before { background: #dba617; }
            .payping-order-panel .payping-ev-time { display: block; margin-bottom: 2px; font-size: 11px; color: #8c8f94; }
            .payping-order-panel .payping-ev-text { display: block; }
            .payping-order-panel .payping-ev-badge { display: inline-block; margin-right: 6px; padding: 0 6px; border-radius: 3px; font-size: 10px; line-height: 16px; background: #f0f0f1; color: #50575e; }
            .payping-order-panel li.payping-ev-verified .payping-ev-badge,
            .payping-order-panel li.payping-ev-duplicate .payping-ev-badge { background: #edfaef; color: #00a32a; }
            .payping-order-panel li.payping-ev-error .payping-ev-badge,
            .payping-order-panel li.payping-ev-failed .payping-ev-badge { background: #fcf0f1; color: #d63638; }
            .payping-order-panel li.payping-ev-cancelled .payping-ev-badge { background: #fcf9e8; color: #996800; }
            .payping-order-panel .payping-copy { margin-right: 6px; padding: 0 6px; line-height: 20px; height: 20px; font-size: 10px; vertical-align: middle; cursor: pointer; }
            .payping-order-panel .payping-reverify { margin: 10px 0 0; }
            .payping-order-panel .payping-reverify .button { width: 100%; text-align: center; justify-content: center; }
            .payping-order-panel .payping-reverify-hint { margin: 10px 0 0; font-size: 11px; color: #8c8f94; }
        </style>
        <?php
    }
}
