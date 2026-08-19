<?php

/**
 * Small AJAX coupon field shared by the mobile summary and desktop sidebar.
 *
 * The markup is intentionally button-only (no nested form) because it sits
 * inside the main checkout form; checkout.js proxies submission to the
 * WooCommerce apply_coupon AJAX endpoint.
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wec-coupon-field">
    <div class="wec-coupon-input-wrap">
        <input
            type="text"
            class="wec-coupon-input"
            placeholder="<?php esc_attr_e('Masukkan kode promo', 'woo-express-checkout'); ?>"
            autocomplete="off">
        <button type="button" class="wec-coupon-btn">
            <?php esc_html_e('Terapkan', 'woo-express-checkout'); ?>
        </button>
    </div>
    <div class="wec-coupon-msg"></div>
</div>