<?php

/**
 * WhatsApp Payment Reminder.
 *
 * Sends WA reminder when order status becomes on-hold (Midtrans pending).
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
        // Hook to order status change - multiple hooks for compatibility
        add_action('woocommerce_order_status_on-hold', array($this, 'send_reminder'), 20, 2);
        add_action('woocommerce_order_status_pending', array($this, 'send_reminder'), 20, 2);
        add_action('woocommerce_order_status_changed', array($this, 'send_reminder_on_change'), 20, 3);
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
     * Alternative hook for order status changed.
     */
    public function send_reminder_on_change($order_id, $old_status, $new_status)
    {
        $this->log("Status changed: {$old_status} -> {$new_status} for order #{$order_id}");

        if (in_array($new_status, array('on-hold', 'pending'))) {
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

        $message = "Halo {$order->get_billing_first_name()},

_(Mohon abaikan pesan ini jika bukan Anda yang melakukan pemesanan)_

Terimakasih untuk pemesanan Anda:

Order ID: *{$order->get_order_number()}*
Tanggal: *{$order->get_date_created()->date('F j, Y')}*
Status: *{$order->get_status()}*
Produk: *{$order_items}*
Total: *{$order->get_formatted_order_total()}*

Silakan lanjutkan pembayaran melalui link berikut:
{$payment_url}

Link pembayaran berlaku 24 jam.

Terimakasih banyak sudah berbelanja di website kami.

Salam Hangat,
{$site_name}";

        return $message;
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
