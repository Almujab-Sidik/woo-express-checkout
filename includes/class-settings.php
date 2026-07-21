<?php

/**
 * Halaman setting terpadu — WooCommerce > Express Checkout.
 *
 * 3 toggle utama untuk modul Express Checkout/Bundle & Upsell/Coupon
 * Display Manager. Panel Bundle & Coupon Manager memanggil langsung method
 * render setting asli milik class tsb (tidak ditulis ulang) supaya field
 * yang sudah ada (color picker, grid pilih kupon, dst.) tetap identik.
 *
 * @package WEC
 */

namespace WEC;

if (! defined('ABSPATH')) {
    exit;
}

class Settings
{
    const PAGE_SLUG = 'wec-express-checkout';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Express Checkout Settings', 'woo-express-checkout'),
            __('Express Checkout', 'woo-express-checkout'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array($this, 'render_page')
        );
    }

    public function register_settings()
    {
        register_setting('wec_module_settings_group', 'wec_module_checkout_enabled');
        register_setting('wec_module_settings_group', 'wec_module_bundle_enabled');
        register_setting('wec_module_settings_group', 'wec_module_coupon_enabled');

        register_setting('wec_checkout_settings_group', 'wec_checkout_guest_enabled');
        register_setting('wec_checkout_settings_group', 'wec_checkout_layout_enabled');
        register_setting('wec_checkout_settings_group', 'wec_checkout_product_pages_enabled');

        // Master module toggles default OFF — every feature is opt-in.
        // Admin turns them on from WooCommerce > Express Checkout.
        foreach (array('wec_module_checkout_enabled', 'wec_module_bundle_enabled', 'wec_module_coupon_enabled') as $option) {
            if (false === get_option($option)) {
                update_option($option, 'no');
            }
        }

        // Sub-feature toggles default ON — they only take effect once their
        // parent module (Express Checkout) is enabled, so it's fine for them
        // to be pre-checked.
        foreach (array('wec_checkout_guest_enabled', 'wec_checkout_layout_enabled') as $option) {
            if (false === get_option($option)) {
                update_option($option, 'yes');
            }
        }

        // Checkout per Produk requires a separate SCF setup step, so it
        // defaults OFF like the master module toggles.
        if (false === get_option('wec_checkout_product_pages_enabled')) {
            update_option('wec_checkout_product_pages_enabled', 'no');
        }
    }

    public function render_page()
    {
        $checkout_on = 'yes' === get_option('wec_module_checkout_enabled', 'no');
        $bundle_on   = 'yes' === get_option('wec_module_bundle_enabled', 'no');
        $coupon_on   = 'yes' === get_option('wec_module_coupon_enabled', 'no');
        ?>
        <div class="wrap wec-settings-wrap">
            <h1><?php esc_html_e('WooCommerce Express Checkout', 'woo-express-checkout'); ?></h1>
            <p class="description">
                <?php esc_html_e('Aktifkan/nonaktifkan tiga fitur di bawah ini. Setting detail masing-masing fitur akan muncul begitu fiturnya diaktifkan.', 'woo-express-checkout'); ?>
            </p>

            <div class="card" style="max-width: 100%; margin-top: 16px;">
                <h2><?php esc_html_e('Fitur Aktif', 'woo-express-checkout'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('wec_module_settings_group'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Express Checkout', 'woo-express-checkout'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wec_module_checkout_enabled" value="yes" <?php checked($checkout_on); ?> />
                                    <?php esc_html_e('Layout checkout Shopify-style, guest checkout, auto account creation.', 'woo-express-checkout'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Bundle & Upsell', 'woo-express-checkout'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wec_module_bundle_enabled" value="yes" <?php checked($bundle_on); ?> />
                                    <?php esc_html_e('Order bump di checkout, bundle produk, dan upsell post-purchase.', 'woo-express-checkout'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Coupon Display Manager', 'woo-express-checkout'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wec_module_coupon_enabled" value="yes" <?php checked($coupon_on); ?> />
                                    <?php esc_html_e('Reposisi & styling field kupon di checkout, daftar kupon yang bisa diklik.', 'woo-express-checkout'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Simpan Fitur Aktif', 'woo-express-checkout')); ?>
                </form>
            </div>

            <?php if ($checkout_on) : ?>
                <div class="card wec-settings-subpanel" style="max-width: 100%; margin-top: 16px;">
                    <h2><?php esc_html_e('Setting — Express Checkout', 'woo-express-checkout'); ?></h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('wec_checkout_settings_group'); ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Guest Checkout', 'woo-express-checkout'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wec_checkout_guest_enabled" value="yes" <?php checked('yes' === get_option('wec_checkout_guest_enabled', 'yes')); ?> />
                                        <?php esc_html_e('Sembunyikan form login, checkout tanpa perlu akun (akun dibuat otomatis di belakang layar).', 'woo-express-checkout'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Layout 2 Kolom', 'woo-express-checkout'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wec_checkout_layout_enabled" value="yes" <?php checked('yes' === get_option('wec_checkout_layout_enabled', 'yes')); ?> />
                                        <?php esc_html_e('Layout checkout 2 kolom ala Shopify (kolom kiri form, kolom kanan ringkasan pesanan sticky).', 'woo-express-checkout'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Checkout per Produk (SCF)', 'woo-express-checkout'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wec_checkout_product_pages_enabled" value="yes" <?php checked('yes' === get_option('wec_checkout_product_pages_enabled', 'no')); ?> />
                                        <?php esc_html_e('Buat halaman checkout khusus per produk — tiap halaman punya URL sendiri untuk ditempel ke tombol CTA landing page.', 'woo-express-checkout'); ?>
                                    </label>
                                    <p class="description">
                                        <?php
                                        echo wp_kses(
                                            __('Membutuhkan plugin <strong>Secure Custom Fields</strong> (atau ACF) aktif agar field "Produk" muncul di tiap halaman Checkout Produk.', 'woo-express-checkout'),
                                            array('strong' => array())
                                        );
                                        ?>
                                    </p>
                                    <?php if ('yes' === get_option('wec_checkout_product_pages_enabled', 'no')) : ?>
                                        <p>
                                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=wec_product_checkout')); ?>" class="button">
                                                <?php esc_html_e('Kelola Halaman Checkout Produk', 'woo-express-checkout'); ?>
                                            </a>
                                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=wec_product_checkout')); ?>" class="button">
                                                <?php esc_html_e('Tambah Halaman Baru', 'woo-express-checkout'); ?>
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Simpan Setting Express Checkout', 'woo-express-checkout')); ?>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($bundle_on && class_exists('\Upsell_Bundle_Admin')) : ?>
                <div class="wec-settings-subpanel" style="margin-top: 16px;">
                    <h2 style="padding: 0 0 8px;"><?php esc_html_e('Setting — Bundle & Upsell', 'woo-express-checkout'); ?></h2>
                    <?php \Upsell_Bundle_Admin::get_instance()->render_settings_page(); ?>
                </div>
            <?php endif; ?>

            <?php if ($coupon_on && class_exists('\WCDM_Settings')) : ?>
                <div class="wec-settings-subpanel" style="margin-top: 16px;">
                    <?php \WCDM_Settings::get_instance()->render_submenu_page(); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
