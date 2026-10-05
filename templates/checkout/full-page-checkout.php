<?php

/**
 * Theme-independent shell for the main Express Checkout page.
 *
 * WordPress and WooCommerce head/footer hooks remain available for styles,
 * scripts, analytics, and payment gateways; only the visual theme header,
 * footer, page title, and content wrapper are omitted.
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<main id="wec-checkout-canvas" class="wec-checkout-canvas">
    <?php
    if (is_singular(\WEC\Product_Checkout::POST_TYPE)) {
        echo do_shortcode('[woocommerce_checkout]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } else {
        while (have_posts()) {
            the_post();
            the_content();
        }
    }
    ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
