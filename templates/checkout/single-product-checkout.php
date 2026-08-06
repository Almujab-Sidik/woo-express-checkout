<?php
/**
 * Product-specific checkout template for one product and one checkout URL.
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="wec-product-checkout-page">
	<?php
	$wec_title = get_the_title();
	if ($wec_title) :
		?>
		<h1 class="wec-product-checkout-title"><?php echo esc_html($wec_title); ?></h1>
	<?php endif; ?>

	<?php echo do_shortcode('[woocommerce_checkout]'); ?>
</div>

<?php
get_footer();
