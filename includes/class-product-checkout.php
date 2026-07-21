<?php

namespace WEC;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Halaman checkout khusus per produk — CPT + field SCF "Produk".
 *
 * Tiap post = 1 URL checkout yang otomatis mengisi cart dengan 1 produk
 * tertentu, menggantikan pola "1 funnel = 1 checkout" milik CartFlows.
 * URL post ini yang ditempel ke tombol CTA di landing page.
 */
class Product_Checkout
{
    const POST_TYPE     = 'wec_product_checkout';
    const FIELD_PRODUCT = 'wec_checkout_product';

    public function __construct()
    {
        // Selalu daftarkan CPT supaya data/permalink lama tidak hilang saat
        // toggle sempat dimatikan, tapi hook cart/template hanya aktif
        // jika fitur diaktifkan.
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
                'add_new_item'  => __('Tambah Halaman Checkout', 'woo-express-checkout'),
                'edit_item'     => __('Edit Halaman Checkout', 'woo-express-checkout'),
                'all_items'     => __('Checkout', 'woo-express-checkout'),
            ),
            'public'       => true,
            'has_archive'  => false,
            'show_in_menu' => 'woocommerce',
            'supports'     => array('title', 'editor', 'thumbnail'),
            'rewrite'      => array('slug' => 'checkout'),
        ));

        $this->maybe_enable_elementor_support();
        $this->maybe_flush_rewrite_rules();
    }

    /**
     * Supaya klien bisa desain halamannya sendiri pakai Elementor (Elementor
     * Pro sudah punya modul Dynamic Tags ACF yang otomatis kompatibel dengan
     * SCF — deteksinya berbasis fungsi acf_get_field_groups(), bukan nama
     * plugin). Dipanggil langsung (bukan lewat option) supaya tidak
     * tergantung urutan hook 'init' antara plugin ini dan Elementor.
     */
    private function maybe_enable_elementor_support()
    {
        if (! did_action('elementor/loaded') && ! class_exists('\Elementor\Plugin')) {
            return;
        }

        add_post_type_support(self::POST_TYPE, 'elementor');

        // Default harus sama persis dengan default Elementor sendiri
        // (Plugin::ELEMENTOR_DEFAULT_POST_TYPES = ['page','post']) — kalau
        // option ini belum pernah disimpan (situs yang belum pernah buka
        // Elementor > Settings > Post Types), pakai array kosong di sini
        // akan diam-diam MENGHAPUS dukungan Elementor dari Page & Post.
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
     * Field "Produk" ini yang jadi satu-satunya ketergantungan kode (nama
     * field harus persis `wec_checkout_product`, tipe Post Object dibatasi
     * ke post type `product`) — didaftarkan lewat kode supaya selalu ada
     * begitu SCF/ACF aktif, tanpa setup manual. Field lain di luar ini bebas
     * dibuat manual oleh klien lewat menu Custom Fields seperti biasa untuk
     * kebutuhan konten (judul promo, badge, testimoni, dst.), lalu ditarik
     * ke desain Elementor via Dynamic Tags.
     */
    public function register_fields()
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group(array(
            'key'    => 'group_wec_checkout_produk',
            'title'  => __('Pengaturan Checkout Produk', 'woo-express-checkout'),
            'fields' => array(
                array(
                    'key'           => 'field_wec_checkout_product',
                    'label'         => __('Produk', 'woo-express-checkout'),
                    'name'          => self::FIELD_PRODUCT,
                    'type'          => 'post_object',
                    'instructions'  => __('Produk ini otomatis masuk cart saat halaman dibuka. Cart pelanggan akan dikosongkan dulu jika sebelumnya berisi produk lain.', 'woo-express-checkout'),
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

        // Halaman yang sudah didesain pakai Elementor dibiarkan dirender oleh
        // Elementor apa adanya — klien bebas custom, tinggal taruh widget
        // Shortcode berisi [woocommerce_checkout] di posisi yang diinginkan.
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

        // Jangan reset cart kalau produk utamanya sudah persis produk ini
        // (mis. customer sekadar refresh halaman) — supaya order bump yang
        // sudah dicentang tidak ikut hilang. Item order bump diabaikan dari
        // perbandingan karena itu bukan "produk utama".
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
            // Produk variable tanpa varian default: sengaja dibiarkan, tidak
            // ada cara aman menebak varian yang benar.
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
                __('Fitur <strong>Checkout per Produk</strong> aktif, tapi plugin <strong>Secure Custom Fields</strong> (atau ACF) belum terpasang/aktif — field "Produk" tidak akan muncul di halaman Checkout Produk.', 'woo-express-checkout'),
                array('strong' => array())
            )
        );
    }

    /**
     * Simpan slug yang sedang dipakai (bukan cuma flag boolean) — supaya
     * kalau slug-nya diganti lagi di kode nanti, flush otomatis jalan lagi
     * dengan sendirinya tanpa perlu reset manual.
     */
    private function maybe_flush_rewrite_rules()
    {
        $slug = 'checkout';
        if ($slug !== get_option('wec_product_checkout_rewrite_slug')) {
            flush_rewrite_rules();
            update_option('wec_product_checkout_rewrite_slug', $slug);
        }
    }
}
