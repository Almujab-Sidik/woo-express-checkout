<?php

/**
 * Checkout form with a two-column layout.
 *
 * Override of WooCommerce checkout/form-checkout.php (v9.4.0).
 * The layout places customer details, payment, and order submission on the
 * left, with a sticky desktop summary and mobile accordion on the right.
 *
 * Standard WooCommerce hooks are preserved so integrations such as
 * pixel tracking and CartFlows continue to function.
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

        <?php // Mobile-only order summary accordion. ?>
        <div class="wec-summary-toggle" id="wec-summary-toggle">
            <button type="button" class="wec-summary-toggle-btn" aria-expanded="false" aria-controls="wec-summary-mobile">
                <span class="wec-summary-toggle-icon" aria-hidden="true">&#9662;</span>
                <span class="wec-summary-toggle-label"><?php esc_html_e('View order summary', 'woo-express-checkout'); ?></span>
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
                // Preserve the standard ID so WooCommerce can update checkout
                // fragments and replace the payment section during AJAX calls.
                ?>
                <div id="order_review" class="woocommerce-checkout-review-order wec-payment-section">
                    <h3 class="wec-section-title"><?php esc_html_e('Payment', 'woo-express-checkout'); ?></h3>
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
