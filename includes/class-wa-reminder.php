<?php

/**
 * WhatsApp Payment Reminder.
 *
 * Sends WA reminder when order status becomes pending (Midtrans pending).
 *
 * @package WEC
 */

namespace WEC;

if (! defined('ABSPATH')) {
    exit;
}

class WA_Reminder
{
    private $api;

    public function __construct()
    {
        if ('yes' !== get_option('wec_wa_reminder_enabled', 'no')) {
            return;
        }

        $this->api = new StarSender_API();

        if (! $this->api->is_configured()) {
            add_action('admin_notices', array($this, 'api_key_notice'));
            return;
        }

        $this->register_hooks();
    }

    private function register_hooks()
    {
        // Process when the order transitions to pending. The sent meta
        // prevents duplicate reminders if WooCommerce fires related hooks.
        add_action('woocommerce_order_status_changed', array($this, 'send_reminder_on_change'), 20, 3);

        // A new order may be created before Midtrans saves its payment URL.
        // Schedule a delayed check for the pending status and payment metadata.
        add_action('woocommerce_new_order', array($this, 'schedule_pending_reminder'), 20, 1);
        add_action('woocommerce_checkout_order_processed', array($this, 'schedule_pending_reminder'), 20, 1);
        add_action('wec_send_pending_reminder', array($this, 'send_scheduled_reminder'), 10, 1);
    }

    /**
     * Send WA reminder for unpaid order.
     */
    public function send_reminder($order_id, $order = null)
    {
        if (! $order) {
            $order = wc_get_order($order_id);
        }

        if (! $order) {
            $this->log('Order not found: ' . $order_id);
            return;
        }

        // Only for Midtrans payment method
        $payment_method = $order->get_payment_method();
        $this->log('Payment method: ' . $payment_method . ' for order #' . $order_id);

        if (false === strpos(strtolower((string) $payment_method), 'midtrans')) {
            $this->log('Not a Midtrans order, skipping');
            return;
        }

        // Check if already sent
        if ($order->get_meta('_wec_wa_reminder_sent')) {
            $this->log('Already sent for order #' . $order_id);
            return;
        }

        // Get payment URL
        $payment_url = $this->get_payment_url($order);
        if (empty($payment_url)) {
            $this->log('No payment URL for order #' . $order_id);
            return;
        }
        $this->log('Payment URL: ' . $payment_url);

        // Get phone number
        $phone = $order->get_billing_phone();
        if (empty($phone)) {
            $this->log('No phone for order #' . $order_id);
            return;
        }
        $this->log('Phone: ' . $phone);

        // Build message
        $message = $this->build_message($order, $payment_url);

        // Get delay setting (default 120 seconds = 2 minutes)
        $delay = intval(get_option('wec_wa_reminder_delay', 120));

        $this->log('Sending WA with delay: ' . $delay . ' seconds');

        // Send via Star Sender
        $result = $this->api->send_message($phone, $message, $delay);

        if (is_wp_error($result)) {
            $error = $result->get_error_message();
            $this->log('Error: ' . $error);
            $order->add_order_note(
                sprintf(__('WA reminder failed: %s', 'woo-express-checkout'), $error)
            );
        } else {
            $this->log('Success: ' . print_r($result, true));
            $order->update_meta_data('_wec_wa_reminder_sent', current_time('mysql'));
            $order->add_order_note(__('WA payment reminder accepted by Star Sender API.', 'woo-express-checkout'));
        }

        // Persist the note/meta even when the Midtrans webhook terminates the request.
        $order->save();
    }

    /**
     * Schedule a check after the checkout/payment process has saved Midtrans
     * metadata on the order.
     */
    public function schedule_pending_reminder($order_id)
    {
        $order_id = absint($order_id);
        if (! $order_id || wp_next_scheduled('wec_send_pending_reminder', array($order_id))) {
            return;
        }

        wp_schedule_single_event(time() + 15, 'wec_send_pending_reminder', array($order_id));
        $this->log('Scheduled pending reminder for order #' . $order_id);
    }

    /**
     * Send the scheduled reminder only if the order is pending.
     */
    public function send_scheduled_reminder($order_id)
    {
        $order = wc_get_order($order_id);
        if (! $order || 'pending' !== $order->get_status()) {
            return;
        }

        $this->send_reminder($order_id, $order);
    }

    /**
     * Alternative hook for order status changed.
     */
    public function send_reminder_on_change($order_id, $old_status, $new_status)
    {
        $this->log("Status changed: {$old_status} -> {$new_status} for order #{$order_id}");

        if ('pending' === $new_status) {
            $this->send_reminder($order_id);
        }
    }

    /**
     * Debug log.
     */
    private function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WEC WA Reminder] ' . $message);
        }
    }

    /**
     * Get Midtrans payment URL.
     */
    private function get_payment_url($order)
    {
        // Try redirect URL first
        $payment_url = $order->get_meta('_mt_payment_url');

        if (! empty($payment_url)) {
            return $payment_url;
        }

        // Fallback: construct from snap token
        $snap_token = $order->get_meta('_mt_payment_snap_token');
        if (! empty($snap_token)) {
            return $order->get_checkout_payment_url(true) . '&snap_token=' . $snap_token;
        }

        // Final fallback
        return $order->get_checkout_order_received_url();
    }

    /**
     * Build WhatsApp message.
     */
    private function build_message($order, $payment_url)
    {
        $site_name = get_bloginfo('name');

        // Format order items
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name() . ' x' . $item->get_quantity();
        }
        $order_items = implode(', ', $items);
        $formatted_total = html_entity_decode(
            wp_strip_all_tags($order->get_formatted_order_total()),
            ENT_QUOTES,
            get_bloginfo('charset') ?: 'UTF-8'
        );
        $formatted_total = trim(preg_replace('/\\s+/', ' ', $formatted_total));

        $billing_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $shipping_name = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
        $billing_address = $this->format_address($order, 'billing');
        $shipping_address = $this->format_address($order, 'shipping');
        $date_created = $order->get_date_created();

        $placeholders = array(
            '%site_name%'        => get_bloginfo('name'),
            '%order_id%'         => $order->get_order_number(),
            '%order_date%'       => $date_created ? $date_created->date_i18n('F j, Y') : '',
            '%order_status%'     => wc_get_order_status_name($order->get_status()),
            '%order_items%'      => $order_items,
            '%order_total%'      => $formatted_total,
            '%payment_url%'      => $payment_url,
            '%billing_name%'     => $billing_name,
            '%billing_email%'    => $order->get_billing_email(),
            '%billing_phone%'    => $order->get_billing_phone(),
            '%billing_address%'  => $billing_address,
            '%shipping_name%'    => $shipping_name,
            '%shipping_address%' => $shipping_address,
        );

        $template = get_option('wec_wa_reminder_template', '');
        if ('' === trim($template)) {
            $template = $this->get_default_template();
        }

        return strtr($template, $placeholders);
    }

    /**
     * Format billing or shipping address for a plain-text message.
     */
    private function format_address($order, $type)
    {
        $get = 'billing' === $type ? 'get_billing_' : 'get_shipping_';
        $parts = array(
            $order->{$get . 'address_1'}(),
            $order->{$get . 'address_2'}(),
            $order->{$get . 'city'}(),
            $order->{$get . 'state'}(),
            $order->{$get . 'postcode'}(),
            $order->{$get . 'country'}(),
        );

        return implode(', ', array_filter(array_map('trim', $parts)));
    }

    /**
     * Default template used when the setting is empty.
     */
    private function get_default_template()
    {
        return "Halo %billing_name%,\n\n_(Mohon abaikan pesan ini jika bukan Anda yang melakukan pemesanan)_\n\nTerimakasih untuk pemesanan Anda:\n\nOrder ID: *%order_id%*\nTanggal: *%order_date%*\nStatus: *%order_status%*\nProduk: *%order_items%*\nTotal: *%order_total%*\n\nSilakan lanjutkan pembayaran melalui link berikut:\n%payment_url%\n\nLink pembayaran berlaku 24 jam.\n\nTerimakasih banyak sudah berbelanja di website kami.\n\nSalam Hangat,\n%site_name%";
    }

    /**
     * Admin notice for missing API key.
     */
    public function api_key_notice()
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }
?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('WooCommerce Express Checkout', 'woo-express-checkout'); ?></strong> &mdash;
                <?php esc_html_e('Star Sender API key is required for WA Payment Reminder.', 'woo-express-checkout'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wec-express-checkout')); ?>">
                    <?php esc_html_e('Configure now', 'woo-express-checkout'); ?>
                </a>
            </p>
        </div>
<?php
    }
}
