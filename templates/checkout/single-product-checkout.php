<?php
/**
 * Template halaman "Checkout Produk" — 1 produk, 1 URL checkout.
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
