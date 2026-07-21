<?php

/**
 * Review Order Table — versi enhanced dengan thumbnail.
 *
 * Override dari WooCommerce checkout/review-order.php (v5.2.0).
 * Tetap mempertahankan class 'woocommerce-checkout-review-order-table'
 * pada wrapper agar AJAX fragment update tetap berfungsi.
 *
 * Semua hook standar (woocommerce_review_order_*) dipertahankan.
 *
 * @package WEC
 */

defined('ABSPATH') || exit;
?>
<div class="shop_table woocommerce-checkout-review-order-table wec-review-table">

    <!-- Daftar item -->
    <div class="wec-review-items">
        <?php do_action('woocommerce_review_order_before_cart_contents'); ?>

        <?php foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) : ?>
            <?php
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

            if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key)) :
                $thumbnail = $_product->get_image(array(56, 56));
            ?>
                <div class="wec-review-item <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">
                    <div class="wec-review-item-thumb">
                        <?php echo wp_kses_post($thumbnail); ?>
                        <span class="wec-review-item-qty"><?php echo esc_html($cart_item['quantity']); ?></span>
                    </div>
                    <div class="wec-review-item-info">
                        <div class="wec-review-item-name">
                            <?php echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key)); ?>
                        </div>
                        <?php echo wc_get_formatted_cart_item_data($cart_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                    </div>
                    <div class="wec-review-item-total">
                        <?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                    </div>
                </div>
        <?php
            endif;
        endforeach;

        do_action('woocommerce_review_order_after_cart_contents');
        ?>
    </div><!-- .wec-review-items -->

    <!-- Totals -->
    <div class="wec-review-totals">

        <div class="wec-review-row wec-review-subtotal">
            <span><?php esc_html_e('Subtotal', 'woo-express-checkout'); ?></span>
            <span><?php wc_cart_totals_subtotal_html(); ?></span>
        </div>

        <?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
            <div class="wec-review-row wec-review-discount coupon-<?php echo esc_attr(sanitize_title($code)); ?>">
                <span><?php wc_cart_totals_coupon_label($coupon); ?></span>
                <span><?php wc_cart_totals_coupon_html($coupon); ?></span>
            </div>
        <?php endforeach; ?>

        <?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>
            <?php do_action('woocommerce_review_order_before_shipping'); ?>
            <?php wc_cart_totals_shipping_html(); ?>
            <?php do_action('woocommerce_review_order_after_shipping'); ?>
        <?php endif; ?>

        <?php foreach (WC()->cart->get_fees() as $fee) : ?>
            <div class="wec-review-row wec-review-fee">
                <span><?php echo esc_html($fee->name); ?></span>
                <span><?php wc_cart_totals_fee_html($fee); ?></span>
            </div>
        <?php endforeach; ?>

        <?php if (wc_tax_enabled() && ! WC()->cart->display_prices_including_tax()) : ?>
            <?php if ('itemized' === get_option('woocommerce_tax_total_display')) : ?>
                <?php foreach (WC()->cart->get_tax_totals() as $code => $tax) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited 
                ?>
                    <div class="wec-review-row wec-review-tax tax-rate-<?php echo esc_attr(sanitize_title($code)); ?>">
                        <span><?php echo esc_html($tax->label); ?></span>
                        <span><?php echo wp_kses_post($tax->formatted_amount); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="wec-review-row wec-review-tax">
                    <span><?php echo esc_html(WC()->countries->tax_or_vat()); ?></span>
                    <span><?php wc_cart_totals_taxes_total_html(); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php do_action('woocommerce_review_order_before_order_total'); ?>

        <div class="wec-review-row wec-review-total">
            <span><?php esc_html_e('Total', 'woo-express-checkout'); ?></span>
            <span><?php wc_cart_totals_order_total_html(); ?></span>
        </div>

        <?php do_action('woocommerce_review_order_after_order_total'); ?>

    </div><!-- .wec-review-totals -->

</div><!-- .woocommerce-checkout-review-order-table -->