<?php

/**
 * F1 — Layout checkout 2 kolom Shopify-style (kiri: form+payment, kanan: order summary).
 *
 * @package WEC
 */

namespace WEC;

if (! defined('ABSPATH')) {
    exit;
}

class Checkout_Layout
{
    public function __construct()
    {
        add_filter('woocommerce_locate_template', array($this, 'override_template'), 10, 3);
        add_action('init', array($this, 'reposition_coupon'));

        // Ganti heading "Billing details" bawaan WooCommerce agar sesuai
        // alur Shopify-style (Kontak -> Pembayaran), bukan istilah invoicing.
        add_filter('gettext', array($this, 'translate_billing_heading'), 10, 3);
    }

    public function translate_billing_heading($translated, $text, $domain)
    {
        if ('woocommerce' !== $domain || 'Billing details' !== $text || ! function_exists('is_checkout') || ! is_checkout()) {
            return $translated;
        }

        $needs_shipping = function_exists('WC') && WC()->cart ? WC()->cart->needs_shipping() : false;

        return $needs_shipping
            ? __('Kontak & Alamat Pengiriman', 'woo-express-checkout')
            : __('Kontak', 'woo-express-checkout');
    }

    public function override_template($template, $template_name, $template_path)
    {
        if (! $this->should_override()) {
            return $template;
        }

        $map = array(
            'checkout/form-checkout.php' => WEC_PATH . 'templates/checkout/form-checkout.php',
            'checkout/review-order.php'  => WEC_PATH . 'templates/checkout/review-order.php',
        );

        if (isset($map[$template_name]) && file_exists($map[$template_name])) {
            return $map[$template_name];
        }

        return $template;
    }

    /**
     * Kupon di-render di dalam order summary (kolom kanan), bukan di posisi
     * default WooCommerce di atas form.
     */
    public function reposition_coupon()
    {
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
    }

    public function should_override()
    {
        $enabled_via_setting = 'yes' === get_option('wec_checkout_layout_enabled', 'yes');

        if (! apply_filters('wec_enable_layout', $enabled_via_setting)) {
            return false;
        }

        if ($this->is_cartflows_page()) {
            return false;
        }

        return true;
    }

    private function is_cartflows_page()
    {
        if (! defined('CARTFLOWS_VERSION')) {
            return false;
        }

        if (function_exists('wcf_is_step_post_type') && wcf_is_step_post_type(get_the_ID())) {
            return true;
        }

        return false;
    }
}
