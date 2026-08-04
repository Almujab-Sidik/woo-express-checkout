<?php

/**
 * Plugin Name: WooCommerce Express Checkout
 * Plugin URI:  https://era.ai/woo-express-checkout
 * Description: Pengalaman checkout ala Shopify untuk WooCommerce — guest checkout cepat, field minimal, bundle offer, dan auto account creation.
 * Version:     0.1.2
 * Author:      Era AI
 * Text Domain: woo-express-checkout
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.8
 * License:     GPL-2.0-or-later
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WEC_VERSION', '0.1.2');
define('WEC_FILE', __FILE__);
define('WEC_PATH', plugin_dir_path(__FILE__));
define('WEC_URL', plugin_dir_url(__FILE__));
define('WEC_BASENAME', plugin_basename(__FILE__));

// Self-hosted update checker (GitHub Releases) — plugin ini tidak ada di
// wordpress.org, jadi butuh mekanisme "Update tersedia" sendiri. Konfigurasi
// (repo, branch, token) sengaja diletakkan sebagai constant di wp-config.php,
// bukan hard-code di sini, supaya token tidak ikut ter-commit ke repo.
require_once WEC_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';

if (defined('WEC_GITHUB_REPO') && WEC_GITHUB_REPO) {
    $wec_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        WEC_GITHUB_REPO,
        WEC_FILE,
        'woo-express-checkout'
    );

    $wec_update_checker->setBranch(defined('WEC_GITHUB_BRANCH') ? WEC_GITHUB_BRANCH : 'main');

    if (defined('WEC_GITHUB_TOKEN') && WEC_GITHUB_TOKEN) {
        $wec_update_checker->setAuthentication(WEC_GITHUB_TOKEN);
    }
}

// Declare HPOS (Custom Order Tables) compatibility.
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WEC_FILE);
    }
});

// Maps WEC\Guest_Checkout -> includes/class-guest-checkout.php,
// WEC\Emails\Set_Password -> includes/emails/class-set-password.php.
spl_autoload_register(function ($class) {
    if (0 !== strpos($class, 'WEC\\')) {
        return;
    }

    $relative = substr($class, 4);
    $parts    = explode('\\', $relative);
    $last     = array_pop($parts);
    $sub      = $parts ? strtolower(implode('/', $parts)) . '/' : '';
    $file     = WEC_PATH . 'includes/' . $sub . 'class-' . str_replace('_', '-', strtolower($last)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

register_activation_hook(WEC_FILE, function () {
    update_option('wec_version', WEC_VERSION);
    flush_rewrite_rules();
});

add_action('plugins_loaded', function () {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('WooCommerce Express Checkout membutuhkan plugin WooCommerce yang aktif untuk berjalan.', 'woo-express-checkout')
            );
        });
        return;
    }

    load_plugin_textdomain('woo-express-checkout', false, dirname(WEC_BASENAME) . '/languages');

    \WEC\Plugin::instance();
});
