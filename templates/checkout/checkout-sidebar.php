<?php

/**
 * Desktop order summary sidebar.
 *
 * Renders the product line, price rows, coupon field, and payment methods in
 * the right column of the two-column checkout layout. The standard review
 * order table is preserved inside the summary card for AJAX fragment updates.
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<aside class="checkout-sidebar">
    <div class="sidebar-sticky">

        <!-- Order summary -->
        <div class="sidebar-card">
            <h3><?php esc_html_e('Ringkasan Pesanan', 'woo-express-checkout'); ?></h3>

            <?php require WEC_PATH . 'templates/checkout/order-summary-content.php'; ?>
        </div>

        <?php
        /**
         * Coupon Display Manager uses this location when its layout is set to
         * "WooCommerce Default / Sidebar".
         */
        do_action('wec_checkout_sidebar_coupon');
        ?>

    </div>
</aside>
