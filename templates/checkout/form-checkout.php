<?php

/**
 * Checkout form with a single-column mobile layout and two-column desktop
 * layout modeled on the BizSchool checkout reference.
 *
 * Overrides WooCommerce checkout/form-checkout.php (v9.4.0).
 *
 * Customer details (form + payment) sit on the left; the order summary,
 * coupon, and payment methods sit in a sticky right sidebar on desktop. On
 * mobile the summary becomes an accordion and the payment methods are shown
 * inline. The CTA is WooCommerce's place_order button, which renders inside
 * the payment section.
 *
 * Standard WooCommerce hooks are preserved so integrations such as pixel
 * tracking and CartFlows keep working.
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

    <div class="wec-checkout-wrapper checkout-page">

        <div class="checkout-container">
            <div class="checkout-main">
                <header class="checkout-header">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="brand">
                        <?php \WEC\Settings::render_brand(); ?>
                    </a>
                </header>

                <div class="trust-indicator">
                    <span class="trust-star" aria-hidden="true">★</span>
                    <span><?php esc_html_e('Dipercaya 40.000+ pebisnis', 'woo-express-checkout'); ?><br><?php esc_html_e('di seluruh Indonesia', 'woo-express-checkout'); ?></span>
                </div>

                <?php
                $checkout_product_name = __('Produk pilihan Anda', 'woo-express-checkout');
                if (function_exists('WC') && WC()->cart && ! WC()->cart->is_empty()) {
                    $checkout_items = WC()->cart->get_cart();
                    $checkout_first_item = reset($checkout_items);
                    if (! empty($checkout_first_item['data']) && is_object($checkout_first_item['data'])) {
                        $checkout_product_name = $checkout_first_item['data']->get_name();
                    }
                }
                ?>
                <div class="product-heading">
                    <h1><?php echo esc_html($checkout_product_name); ?></h1>
                </div>

                <div class="product-benefits">
                    <div class="benefit-item"><span class="check-icon">✓</span><span><?php esc_html_e('Sekali bayar, akses selamanya', 'woo-express-checkout'); ?></span></div>
                    <div class="benefit-item"><span class="check-icon">✓</span><span><?php esc_html_e('Tinggal input data, hasil otomatis', 'woo-express-checkout'); ?></span></div>
                    <div class="benefit-item"><span class="check-icon">✓</span><span><?php esc_html_e('Ada panduan langkah demi langkah', 'woo-express-checkout'); ?></span></div>
                </div>

                <div class="access-notice">
                    <div class="notice-icon" aria-hidden="true">i</div>
                    <p><?php esc_html_e('Akses produk otomatis dikirim ke', 'woo-express-checkout'); ?> <strong><?php esc_html_e('WhatsApp & email setelah pembayaran.', 'woo-express-checkout'); ?></strong></p>
                </div>

                <?php // Mobile-only order summary accordion.
                ?>
                <div class="mobile-order-summary">
                    <details class="order-summary">
                        <summary>
                            <div class="summary-product">
                                <span class="cart-icon" aria-hidden="true">&#128722;</span>
                                <span><?php echo esc_html($checkout_product_name); ?></span>
                            </div>
                            <div class="summary-right">
                                <strong><?php wc_cart_totals_order_total_html(); ?></strong>
                                <span class="summary-arrow">&#8964;</span>
                            </div>
                        </summary>

                        <div class="summary-content">
                            <?php require WEC_PATH . 'templates/checkout/order-summary-content.php'; ?>
                        </div>
                    </details>
                </div>

                <?php if ($checkout->get_checkout_fields()) : ?>

                    <?php do_action('woocommerce_checkout_before_customer_details'); ?>

                    <div id="customer_details" class="wec-customer-details customer-section">
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
                    <?php woocommerce_checkout_payment(); ?>
                </div>

                <?php do_action('woocommerce_checkout_after_order_review'); ?>

                <?php // Security reassurance + accepted payment methods (mobile).
                ?>
                <div class="mobile-payment">
                    <div class="security-text">
                        <span aria-hidden="true">&#128274;</span>
                        <?php esc_html_e('Pembayaran aman & terenkripsi', 'woo-express-checkout'); ?>
                    </div>

                    <?php \WEC\Settings::render_payment_methods(); ?>
                </div>

            </div><!-- .checkout-main -->

            <?php require WEC_PATH . 'templates/checkout/checkout-sidebar.php'; ?>

        </div><!-- .checkout-container -->
    </div><!-- .wec-checkout-wrapper -->

</form>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>
