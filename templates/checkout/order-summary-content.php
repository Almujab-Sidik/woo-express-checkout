<?php

/**
 * Konten Order Summary — dipakai bersama oleh:
 * - Kolom kanan desktop (sticky)
 * - Accordion mobile
 *
 * Berisi: field kupon + review order table (cart items + totals).
 * Dipanggil via require dari form-checkout.php.
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<div class="wec-summary-card">

    <h3 class="wec-summary-heading"><?php esc_html_e('Ringkasan Pesanan', 'woo-express-checkout'); ?></h3>

    <?php
    /**
     * Field Kupon (bukan nested form — menggunakan div + JS AJAX).
     * WC's coupon nonce & URL tersedia via wc_checkout_params.
     */
    if (wc_coupons_enabled()) :
    ?>
        <div class="wec-coupon-field">
            <div class="wec-coupon-input-wrap">
                <input
                    type="text"
                    name="coupon_code"
                    class="input-text wec-coupon-input"
                    placeholder="<?php esc_attr_e('Kode kupon', 'woo-express-checkout'); ?>"
                    autocomplete="off" />
                <button type="button" class="button wec-coupon-btn" id="wec-apply-coupon">
                    <?php esc_html_e('Pakai', 'woo-express-checkout'); ?>
                </button>
            </div>
            <div class="wec-coupon-msg" role="alert" aria-live="polite"></div>
        </div>
    <?php
    endif;

    /**
     * Order review table — dipanggil via function standar WooCommerce.
     * Template review-order.php sudah di-override untuk menampilkan thumbnail.
     * Class .woocommerce-checkout-review-order-table dipertahankan agar
     * AJAX update_order_review fragment tetap menemukan & menggantinya.
     */
    woocommerce_order_review();
    ?>

</div><!-- .wec-summary-card -->