# WooCommerce Express Checkout

WooCommerce plugin that combines streamlined guest checkout, bundle and upsell tools, coupon display management, and product-specific checkout pages in one modular package.

## Fitur

- **Express Checkout** — two-column checkout layout, guest checkout, and automatic customer account creation after successful payment.
- **Bundle & Upsell** — checkout order bumps, product bundles, and post-purchase upsell offers.
- **Coupon Display Manager** — configurable coupon placement, styling, and clickable coupon lists.
- **Product-Specific Checkout** — dedicated checkout pages per product, supported by [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/), with one URL per page for landing-page calls to action.

All features above are **disabled by default** and can be enabled from **WooCommerce → Express Checkout**.

## Requirement

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.1+
- (Optional) [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/) or ACF — required only for Product-Specific Checkout.

## Installation

This plugin is not distributed through wordpress.org. Install it using one of the following methods:

1. **Manual** — download the ZIP from [Releases](../../releases), then upload it through **Plugins → Add New → Upload Plugin**.
2. **Automatic updates** — after the initial installation, the plugin checks this repository's [Releases](../../releases) and displays an update notice in wp-admin when a new version is available.

## Updates

The plugin includes [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker), configured to use this repository. To publish a new version:

1. Increase the `Version:` value in the `woo-express-checkout.php` header.
2. Commit and push to the `main` branch.
3. Create a **GitHub Release** with a matching tag, such as `v0.2.0`.
4. Existing installations will detect the new release automatically.

## License

GPL-2.0-or-later
