<?php

/**
 * Shared order summary content for the desktop sidebar and mobile accordion.
 *
 * Berisi: review order table (cart items + totals).
 * Loaded by form-checkout.php.
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<div class="wec-summary-card">

    <h3 class="wec-summary-heading"><?php esc_html_e('Order Summary', 'woo-express-checkout'); ?></h3>

    <?php
    /**
     * Render the standard WooCommerce order review table. The overridden
     * review-order.php template retains the expected class for AJAX updates.
     */
    woocommerce_order_review();
    ?>

</div><!-- .wec-summary-card -->
