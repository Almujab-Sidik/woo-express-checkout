<?php

/**
 * Checkout layout with the customer form and payment on the left and the
 * order summary on the right.
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
        add_filter('template_include', array($this, 'use_checkout_canvas'), 99);
        add_filter('body_class', array($this, 'add_checkout_body_class'));
        add_action('init', array($this, 'reposition_coupon'));

        // Replace WooCommerce's default billing heading with a customer-facing
        // heading that matches the streamlined checkout flow.
        add_filter('gettext', array($this, 'translate_billing_heading'), 10, 3);
        add_filter('woocommerce_order_button_text', array($this, 'format_order_button_text'), 20);
        add_filter('woocommerce_order_button_html', array($this, 'format_order_button_html'), 20);
    }

    public function translate_billing_heading($translated, $text, $domain)
    {
        if ('woocommerce' !== $domain || 'Billing details' !== $text || ! function_exists('is_checkout') || ! is_checkout()) {
            return $translated;
        }

        $needs_shipping = function_exists('WC') && WC()->cart ? WC()->cart->needs_shipping() : false;

        return $needs_shipping
            ? __('Contact & Shipping Address', 'woo-express-checkout')
            : __('Contact', 'woo-express-checkout');
    }

    public function format_order_button_text($default_text)
    {
        $text = get_option('wec_checkout_order_button_text', $default_text);
        $format = get_option('wec_checkout_order_button_format', '{text}');

        if (! $text || ! $format || ! function_exists('WC') || ! WC()->cart) {
            return $default_text;
        }

        $price = wp_strip_all_tags(html_entity_decode(WC()->cart->get_total(), ENT_QUOTES, get_bloginfo('charset')));
        return str_replace(
            array('{text}', '{price}', '$Price', '$price'),
            array($text, $price, $price, $price),
            $format
        );
    }

    public function format_order_button_html($html)
    {
        $label = $this->format_order_button_text('Place order');

        if ('Place order' === $label || false === stripos($html, 'id="place_order"')) {
            return $html;
        }

        return preg_replace_callback(
            '/(<button\b[^>]*\bid=["\']place_order["\'][^>]*>)(.*?)(<\/button>)/is',
            static function ($matches) use ($label) {
                return $matches[1] . esc_html($label) . $matches[3];
            },
            $html
        );
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
     * Render the main checkout in a theme-independent canvas.
     *
     * Theme page templates add their own header, footer, title, content-width,
     * and global padding around the checkout shortcode. Those wrappers can
     * collapse the two-column checkout after a theme update, so the checkout
     * page deliberately keeps wp_head()/wp_footer() while omitting theme
     * chrome.
     */
    public function use_checkout_canvas($template)
    {
        if (! $this->is_express_checkout_page()) {
            return $template;
        }

        $canvas_template = WEC_PATH . 'templates/checkout/full-page-checkout.php';

        return file_exists($canvas_template) ? $canvas_template : $template;
    }

    public function add_checkout_body_class($classes)
    {
        if ($this->is_express_checkout_page()) {
            $classes[] = 'wec-express-checkout';
        }

        return $classes;
    }

    /**
     * Remove WooCommerce's native coupon form from its default position.
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

    private function is_express_checkout_page()
    {
        if (! $this->should_override() || ! function_exists('is_checkout') || ! is_checkout()) {
            return false;
        }

        if (is_singular(Product_Checkout::POST_TYPE)) {
            // Elementor-built checkout pages intentionally own their canvas.
            return 'builder' !== get_post_meta(get_queried_object_id(), '_elementor_edit_mode', true);
        }

        // Payment and confirmation endpoints have their own WooCommerce
        // templates and must keep their normal page chrome.
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
            return false;
        }

        $checkout_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('checkout') : 0;

        return $checkout_page_id > 0 && is_page($checkout_page_id);
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
