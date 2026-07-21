<?php

/**
 * Checkout Form — Layout 2 Kolom (Shopify-style).
 *
 * Override dari WooCommerce checkout/form-checkout.php (v9.4.0).
 * Mengubah layout default menjadi 2 kolom:
 * - Kiri:  customer details + metode pembayaran + Place Order
 * - Kanan: order summary (sticky di desktop, accordion di mobile)
 *
 * Semua hook standar WooCommerce tetap dipertahankan agar plugin lain
 * (pixel tracking, CartFlows, dll.) tetap berfungsi.
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var WC_Checkout $checkout
 */

do_action('woocommerce_before_checkout_form', $checkout);

if (! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in()) {
    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('You must be logged in to checkout.', 'woocommerce')));
    return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout wec-checkout-form" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data" aria-label="<?php esc_attr_e('Checkout', 'woo-express-checkout'); ?>">

    <div class="wec-checkout-wrapper">

        <?php // Accordion ringkasan pesanan — tampil hanya di layar mobile (<768px). ?>
        <div class="wec-summary-toggle" id="wec-summary-toggle">
            <button type="button" class="wec-summary-toggle-btn" aria-expanded="false" aria-controls="wec-summary-mobile">
                <span class="wec-summary-toggle-icon" aria-hidden="true">&#9662;</span>
                <span class="wec-summary-toggle-label"><?php esc_html_e('Lihat ringkasan pesanan', 'woo-express-checkout'); ?></span>
                <span class="wec-summary-toggle-total"><?php wc_cart_totals_order_total_html(); ?></span>
            </button>
        </div>

        <div class="wec-summary-mobile" id="wec-summary-mobile" hidden>
            <?php require WEC_PATH . 'templates/checkout/order-summary-content.php'; ?>
        </div>

        <div class="wec-checkout-grid">

            <!-- ====================== KOLOM KIRI ====================== -->
            <div class="wec-checkout-col-left">

                <?php if ($checkout->get_checkout_fields()) : ?>

                    <?php do_action('woocommerce_checkout_before_customer_details'); ?>

                    <div id="customer_details" class="wec-customer-details">
                        <?php do_action('woocommerce_checkout_billing'); ?>
                        <?php do_action('woocommerce_checkout_shipping'); ?>
                    </div>

                    <?php do_action('woocommerce_checkout_after_customer_details'); ?>

                <?php endif; ?>

                <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>
                <?php do_action('woocommerce_checkout_before_order_review'); ?>

                <?php
                // ID 'order_review' dipertahankan (bukan nama yang akurat lagi
                // di layout ini) supaya AJAX fragment-update JS WooCommerce
                // bawaan tetap mengenali & mengganti .woocommerce-checkout-payment.
                ?>
                <div id="order_review" class="woocommerce-checkout-review-order wec-payment-section">
                    <h3 class="wec-section-title"><?php esc_html_e('Pembayaran', 'woo-express-checkout'); ?></h3>
                    <?php woocommerce_checkout_payment(); ?>
                </div>

                <?php do_action('woocommerce_checkout_after_order_review'); ?>

            </div>

            <!-- ====================== KOLOM KANAN ====================== -->
            <aside class="wec-checkout-col-right">
                <div class="wec-order-summary wec-order-summary--desktop">

                    <?php require WEC_PATH . 'templates/checkout/order-summary-content.php'; ?>

                </div>
            </aside>

        </div><!-- .wec-checkout-grid -->

    </div><!-- .wec-checkout-wrapper -->

</form>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>