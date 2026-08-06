<?php

namespace WEC;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Product-specific checkout pages using a custom post type and an SCF product field.
 *
 * Each post provides a dedicated checkout URL that adds one configured
 * product to the cart for use with landing-page calls to action.
 */
class Product_Checkout
{
    const POST_TYPE     = 'wec_product_checkout';
    const FIELD_PRODUCT = 'wec_checkout_product';

    public function __construct()
    {
        // Always register the post type so existing content and permalinks are
        // preserved when the feature is temporarily disabled.
        add_action('init', array($this, 'register_post_type'));

        if ('yes' !== get_option('wec_checkout_product_pages_enabled', 'no')) {
            return;
        }

        add_action('acf/init', array($this, 'register_fields'));
        add_filter('woocommerce_is_checkout', array($this, 'force_is_checkout'));
        add_filter('single_template', array($this, 'load_template'));
        add_action('template_redirect', array($this, 'prepare_cart'));
        add_action('admin_notices', array($this, 'maybe_scf_missing_notice'));
    }

    public function register_post_type()
    {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name'          => __('Checkout', 'woo-express-checkout'),
                'singular_name' => __('Checkout', 'woo-express-checkout'),
                'add_new_item'  => __('Add Checkout Page', 'woo-express-checkout'),
                'edit_item'     => __('Edit Checkout Page', 'woo-express-checkout'),
                'all_items'     => __('Checkout', 'woo-express-checkout'),
            ),
            'public'       => true,
            'has_archive'  => false,
            'show_in_menu' => 'woocommerce',
            'supports'     => array('title', 'editor', 'thumbnail'),
            'rewrite'      => array('slug' => 'checkout'),
        ));

        // Resolve product checkout pages before the WooCommerce checkout page
        // when both routes use the same /checkout/ URL base.
        add_rewrite_rule(
            '^checkout/(?!order-received(?:/|$)|order-pay(?:/|$)|order-cancel(?:/|$)|view-order(?:/|$)|pay(?:/|$)|add-payment-method(?:/|$)|delete-payment-method(?:/|$)|set-default-payment-method(?:/|$))([^/]+)/?$',
            'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]',
            'top'
        );

        $this->maybe_enable_elementor_support();
        $this->maybe_flush_rewrite_rules();
    }

    /**
     * Enable Elementor support when Elementor is available. Elementor Pro's
     * ACF Dynamic Tags support is detected through the ACF API.
     */
    private function maybe_enable_elementor_support()
    {
        if (! did_action('elementor/loaded') && ! class_exists('\Elementor\Plugin')) {
            return;
        }

        add_post_type_support(self::POST_TYPE, 'elementor');

        // Preserve Elementor's default supported post types when the option
        // has not been saved yet.
        $default   = defined('\Elementor\Plugin::ELEMENTOR_DEFAULT_POST_TYPES')
            ? \Elementor\Plugin::ELEMENTOR_DEFAULT_POST_TYPES
            : array('page', 'post');
        $supported = get_option('elementor_cpt_support', $default);
        if (! is_array($supported)) {
            $supported = $default;
        }
        if (! in_array(self::POST_TYPE, $supported, true)) {
            $supported[] = self::POST_TYPE;
            update_option('elementor_cpt_support', $supported);
        }
    }

    /**
     * The required product field is registered automatically when SCF or ACF
     * is available. Additional custom fields can be created independently for
     * page content and exposed through Elementor Dynamic Tags.
     */
    public function register_fields()
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group(array(
            'key'    => 'group_wec_checkout_produk',
            'title'  => __('Product Checkout Settings', 'woo-express-checkout'),
            'fields' => array(
                array(
                    'key'           => 'field_wec_checkout_product',
                    'label'         => __('Product', 'woo-express-checkout'),
                    'name'          => self::FIELD_PRODUCT,
                    'type'          => 'post_object',
                    'instructions'  => __('This product is added to the cart when the page loads. Existing cart contents are cleared first when they contain a different product.', 'woo-express-checkout'),
                    'required'      => 1,
                    'post_type'     => array('product'),
                    'return_format' => 'id',
                    'ui'            => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => self::POST_TYPE,
                    ),
                ),
            ),
        ));
    }

    public function force_is_checkout($is_checkout)
    {
        if (is_singular(self::POST_TYPE)) {
            return true;
        }
        return $is_checkout;
    }

    public function load_template($template)
    {
        if (! is_singular(self::POST_TYPE)) {
            return $template;
        }

        // Let Elementor render pages built with Elementor so the checkout
        // shortcode can be placed wherever the page design requires.
        if ('builder' === get_post_meta(get_the_ID(), '_elementor_edit_mode', true)) {
            return $template;
        }

        $custom = WEC_PATH . 'templates/checkout/single-product-checkout.php';
        return file_exists($custom) ? $custom : $template;
    }

    public function prepare_cart()
    {
        if (! is_singular(self::POST_TYPE) || ! WC()->cart) {
            return;
        }

        $post_id    = get_the_ID();
        $product_id = function_exists('get_field')
            ? (int) get_field(self::FIELD_PRODUCT, $post_id)
            : (int) get_post_meta($post_id, self::FIELD_PRODUCT, true);

        if (! $product_id) {
            return;
        }

        $product = wc_get_product($product_id);
        if (! $product || 'publish' !== $product->get_status() || ! $product->is_purchasable()) {
            return;
        }

        $cart = WC()->cart;

        // Preserve the cart when it already contains only this main product,
        // so refreshing the page does not remove selected order bumps.
        $needs_reset = true;
        if (! $cart->is_empty()) {
            $main_product_ids = array();
            foreach ($cart->get_cart() as $item) {
                if (empty($item['is_order_bump'])) {
                    $main_product_ids[] = (int) $item['product_id'];
                }
            }
            $main_product_ids = array_values(array_unique($main_product_ids));
            if (array($product_id) === $main_product_ids) {
                $needs_reset = false;
            }
        }

        if (! $needs_reset) {
            return;
        }

        $cart->empty_cart();

        if ($product->is_type('variable')) {
            $default_attributes = $product->get_default_attributes();
            $variation_id        = 0;
            if (! empty($default_attributes)) {
                $data_store   = \WC_Data_Store::load('product');
                $variation_id = $data_store->find_matching_product_variation($product, $default_attributes);
            }
            if ($variation_id) {
                $cart->add_to_cart($product_id, 1, $variation_id, $default_attributes);
            }
            // Leave variable products without a default variation unchanged;
            // selecting a variation automatically would be unsafe.
            return;
        }

        $cart->add_to_cart($product_id, 1);
    }

    public function maybe_scf_missing_notice()
    {
        if (function_exists('acf_add_local_field_group') || ! current_user_can('manage_woocommerce')) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            wp_kses(
                __('<strong>Product-Specific Checkout</strong> is enabled, but <strong>Secure Custom Fields</strong> (or ACF) is not active. The "Product" field will not appear on Product Checkout pages.', 'woo-express-checkout'),
                array('strong' => array())
            )
        );
    }

    /**
     * Store the active rewrite slug so rewrite rules are refreshed automatically
     * when the slug changes.
     */
    private function maybe_flush_rewrite_rules()
    {
        $slug = 'checkout';
        $version = defined('WEC_VERSION') ? WEC_VERSION : '1.0.0';
        if (
            $slug !== get_option('wec_product_checkout_rewrite_slug')
            || $version !== get_option('wec_product_checkout_rewrite_version')
        ) {
            flush_rewrite_rules();
            update_option('wec_product_checkout_rewrite_slug', $slug);
            update_option('wec_product_checkout_rewrite_version', $version);
        }
    }
}
