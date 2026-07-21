<?php
/**
 * Gutenberg / WooCommerce Block Checkout Compatibility.
 *
 * Enqueues block-specific assets on any checkout page detected by WCDM_Compat.
 *
 * @package WooCouponDisplayManager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCDM_Blocks {

	/** @var WCDM_Blocks */
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$activated = get_option( 'wcdm_activated', get_option( 'wcdm_enabled', 'no' ) );
		if ( 'yes' !== $activated ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_assets' ) );
	}

	/**
	 * Enqueue block-checkout CSS + JS on any checkout page (global compat).
	 */
	public function enqueue_block_assets() {
		if ( ! WCDM_Compat::is_checkout_page() ) {
			return;
		}

		wp_enqueue_style(
			'wcdm-blocks-css',
			WCDM_PLUGIN_URL . 'assets/css/blocks.css',
			array(),
			WCDM_VERSION . '.' . filemtime( WCDM_PLUGIN_DIR . 'assets/css/blocks.css' )
		);

		wp_enqueue_script(
			'wcdm-blocks-js',
			WCDM_PLUGIN_URL . 'assets/js/blocks.js',
			array( 'jquery' ),
			WCDM_VERSION . '.' . filemtime( WCDM_PLUGIN_DIR . 'assets/js/blocks.js' ),
			true
		);

		$display_mode = get_option( 'wcdm_coupon_display_mode', 'input' );
		$show_input   = ( 'input' === $display_mode || 'both' === $display_mode ) ? 'yes' : 'no';
		$enable_list  = ( 'list' === $display_mode || 'both' === $display_mode ) ? 'yes' : 'no';

		wp_localize_script(
			'wcdm-blocks-js',
			'wcdm_block_params',
			array(
				'button_style'  => get_option( 'wcdm_button_style', 'secondary' ),
				'button_text'   => get_option( 'wcdm_button_text', __( 'Apply Coupon', 'coupon-display-manager-for-woocommerce' ) ),
				'show_hint'     => get_option( 'wcdm_show_hint', 'yes' ),
				'hint_text'     => get_option( 'wcdm_hint_text', __( 'Optional — only if you have a coupon', 'coupon-display-manager-for-woocommerce' ) ),
				'show_input'    => $show_input,
				'enable_list'   => $enable_list,
				'checkout_type' => WCDM_Compat::get_checkout_type(),
				'single_coupon' => get_option( 'wcdm_single_coupon', 'yes' ),
				'layout_position' => get_option( 'wcdm_layout_position', 'above_payment' ),
			)
		);
	}
}
