<?php

namespace WEC;

if (! defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private static $instance = null;

    private $modules = array();

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->register_hooks();
        $this->init_modules();
    }

    private function register_hooks()
    {
        add_action('init', array($this, 'load_textdomain'));
        add_action('woocommerce_loaded', array($this, 'register_emails'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_bundle_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_notices', array($this, 'maybe_block_checkout_notice'));
        add_action('wp_ajax_wec_dismiss_notice', array($this, 'handle_dismiss_notice'));
    }

    /**
     * Modul bundle & coupon-manager sengaja tetap pakai nama class asli
     * (global, tanpa namespace WEC\) — kodenya dipindah verbatim dari
     * plugin asal, nol perubahan logika.
     */
    private function init_modules()
    {
        if ('yes' === get_option('wec_module_checkout_enabled', 'no')) {
            $this->modules['guest_checkout']  = new Guest_Checkout();
            $this->modules['auto_account']    = new Auto_Account();
            $this->modules['checkout_layout'] = new Checkout_Layout();
        }

        if ('yes' === get_option('wec_module_bundle_enabled', 'no')) {
            $this->define_bundle_constants();
            require_once WEC_PATH . 'modules/bundle/includes/class-upsell-bundle-admin.php';
            require_once WEC_PATH . 'modules/bundle/includes/class-upsell-bundle-order-bump.php';
            require_once WEC_PATH . 'modules/bundle/includes/class-upsell-bundle-product-bundle.php';
            require_once WEC_PATH . 'modules/bundle/includes/class-upsell-bundle-post-purchase.php';

            \Upsell_Bundle_Admin::get_instance();
            \Upsell_Bundle_Order_Bump::get_instance();
            \Upsell_Bundle_Product_Bundle::get_instance();
            \Upsell_Bundle_Post_Purchase::get_instance();

            // Settingnya sekarang inline di halaman Express Checkout terpadu,
            // jadi submenu WooCommerce aslinya disembunyikan.
            remove_action('admin_menu', array(\Upsell_Bundle_Admin::get_instance(), 'add_admin_menu'));
        }

        if ('yes' === get_option('wec_module_coupon_enabled', 'no')) {
            $this->define_coupon_constants();
            require_once WEC_PATH . 'modules/coupon-manager/includes/class-wcdm-compat.php';
            require_once WEC_PATH . 'modules/coupon-manager/includes/class-wcdm-settings.php';
            require_once WEC_PATH . 'modules/coupon-manager/includes/class-wcdm-frontend.php';
            require_once WEC_PATH . 'modules/coupon-manager/includes/class-wcdm-blocks.php';

            if (is_admin()) {
                \WCDM_Settings::get_instance();

                // Same reason as the bundle module above.
                remove_action('admin_menu', array(\WCDM_Settings::get_instance(), 'add_admin_menu'));
                remove_filter('woocommerce_settings_tabs_array', array(\WCDM_Settings::get_instance(), 'add_settings_tab'), 50);
            }
            \WCDM_Frontend::get_instance();
            \WCDM_Blocks::get_instance();
        }

        $this->modules['product_checkout'] = new Product_Checkout();

        $this->modules['settings'] = new Settings();
    }

    /**
     * Nama constant dipertahankan sama seperti plugin asal (upsell-bundle-woocommerce)
     * supaya kode di modules/bundle tidak perlu diubah.
     */
    private function define_bundle_constants()
    {
        if (! defined('UPSELL_BUNDLE_VERSION')) {
            define('UPSELL_BUNDLE_VERSION', WEC_VERSION);
            define('UPSELL_BUNDLE_PATH', WEC_PATH . 'modules/bundle/');
            define('UPSELL_BUNDLE_URL', WEC_URL . 'modules/bundle/');
        }
    }

    /**
     * Nama constant dipertahankan sama seperti plugin asal (woo-coupon-manager-main)
     * supaya kode di modules/coupon-manager tidak perlu diubah.
     */
    private function define_coupon_constants()
    {
        if (! defined('WCDM_VERSION')) {
            define('WCDM_VERSION', '1.1.1');
            define('WCDM_PLUGIN_DIR', WEC_PATH . 'modules/coupon-manager/');
            define('WCDM_PLUGIN_URL', WEC_URL . 'modules/coupon-manager/');
            define('WCDM_PLUGIN_BASENAME', WEC_BASENAME);
        }
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('woo-express-checkout', false, dirname(WEC_BASENAME) . '/languages');
    }

    public function register_emails()
    {
        add_filter('woocommerce_email_classes', array($this, 'add_email_classes'));
    }

    public function add_email_classes($emails)
    {
        $emails['WEC_Emails_Set_Password'] = new Emails\Set_Password();
        return $emails;
    }

    public function enqueue_assets()
    {
        if (function_exists('is_checkout') && is_checkout() && ! is_wc_endpoint_url('order-received')) {
            wp_enqueue_style(
                'wec-font-inter',
                'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
                array(),
                null
            );
            wp_enqueue_style('wec-checkout', WEC_URL . 'assets/css/checkout.css', array('wec-font-inter'), WEC_VERSION);
            wp_enqueue_script('wec-checkout', WEC_URL . 'assets/js/checkout.js', array(), WEC_VERSION, true);
        }
    }

    /**
     * Di plugin asalnya, frontend.css milik modul bundle di-load lewat fungsi
     * global terpisah (bukan lewat salah satu class yang dipindahkan), jadi
     * perlu di-enqueue eksplisit di sini — sitewide, sama seperti aslinya.
     */
    public function enqueue_bundle_frontend_assets()
    {
        if ('yes' === get_option('wec_module_bundle_enabled', 'yes')) {
            wp_enqueue_style('upsell-bundle-frontend', WEC_URL . 'modules/bundle/assets/css/frontend.css', array(), WEC_VERSION);
        }
    }

    public function enqueue_admin_assets($hook)
    {
        wp_enqueue_script('wec-admin', WEC_URL . 'assets/js/admin.js', array('jquery'), WEC_VERSION, true);
        wp_localize_script(
            'wec-admin',
            'wecAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('wec_dismiss_notice'),
            )
        );
    }

    public function maybe_block_checkout_notice()
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        if (get_option('wec_block_notice_dismissed')) {
            return;
        }

        $page_id = wc_get_page_id('checkout');
        if (! $page_id || $page_id < 1) {
            return;
        }

        $post = get_post($page_id);
        if (! $post || false === strpos((string) $post->post_content, 'wp:woocommerce/checkout')) {
            return;
        }

?>
        <div class="notice notice-warning is-dismissible" data-wec-dismiss="block">
            <p>
                <strong><?php esc_html_e('WooCommerce Express Checkout', 'woo-express-checkout'); ?></strong> &mdash;
                <?php
                echo wp_kses(
                    __('Plugin ini bekerja optimal dengan shortcode checkout klasik. Halaman checkout Anda saat ini menggunakan <strong>Checkout Block</strong>. Untuk pengalaman penuh, ganti blok tersebut dengan shortcode <code>[woocommerce_checkout]</code>.', 'woo-express-checkout'),
                    array('strong' => array(), 'code' => array())
                );
                ?>
            </p>
        </div>
<?php
    }

    public function handle_dismiss_notice()
    {
        check_ajax_referer('wec_dismiss_notice', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error();
        }
        update_option('wec_block_notice_dismissed', '1');
        wp_send_json_success();
    }
}
