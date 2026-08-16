<?php

/**
 * Plugin Name: WooCommerce Express Checkout
 * Plugin URI:  https://era.ai/woo-express-checkout
 * Description: Streamlined WooCommerce checkout with guest checkout, minimal fields, bundle offers, and automatic account creation.
 * Version:     0.1.22
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

define('WEC_VERSION', '0.1.22');
define('WEC_FILE', __FILE__);
define('WEC_PATH', plugin_dir_path(__FILE__));
define('WEC_URL', plugin_dir_url(__FILE__));
define('WEC_BASENAME', plugin_basename(__FILE__));

// Self-hosted update checker using GitHub Releases. Repository settings can be
// overridden through wp-config.php so private repository credentials remain
// outside the plugin source.
require_once WEC_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';

$wec_github_repo = defined('WEC_GITHUB_REPO') && WEC_GITHUB_REPO
    ? WEC_GITHUB_REPO
    : 'https://github.com/Almujab-Sidik/woo-express-checkout/';

if ($wec_github_repo) {
    $wec_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        $wec_github_repo,
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

// Maps WEC\Guest_Checkout to includes/class-guest-checkout.php and
// WEC\Emails\Set_Password to includes/emails/class-set-password.php.
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
                esc_html__('WooCommerce Express Checkout requires the WooCommerce plugin to be active.', 'woo-express-checkout')
            );
        });
        return;
    }

    load_plugin_textdomain('woo-express-checkout', false, dirname(WEC_BASENAME) . '/languages');

    \WEC\Plugin::instance();
});
