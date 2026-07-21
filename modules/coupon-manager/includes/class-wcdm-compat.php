<?php
/**
 * Compatibility Layer — Global Checkout Detection & Single Coupon Enforcement.
 *
 * @package WooCouponDisplayManager
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCDM_Compat {

	/** @var bool|null */
	private static $is_checkout_cache = null;

	// ------------------------------------------------------------------
	// Checkout Page Detection
	// ------------------------------------------------------------------

	/**
	 * Check if the current page is any checkout page.
	 */
	public static function is_checkout_page() {
		if ( null !== self::$is_checkout_cache ) {
			return self::$is_checkout_cache;
		}

		$result = false;

		// 1. Standard WooCommerce (classic shortcode + block checkout).
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$result = true;
		}

		// 2. CartFlows free & pro.
		if ( ! $result ) {
			$result = self::is_cartflows_checkout();
		}

		// 3. FunnelKit / WooFunnels.
		if ( ! $result ) {
			$result = self::is_funnelkit_checkout();
		}

		// 4. Generic fallback — page contains [woocommerce_checkout] shortcode or block.
		if ( ! $result ) {
			$result = self::page_has_checkout_content();
		}

		self::$is_checkout_cache = $result;
		return $result;
	}

	/** Reset cached result. */
	public static function reset_cache() {
		self::$is_checkout_cache = null;
	}

	/** Detect CartFlows checkout steps. */
	private static function is_cartflows_checkout() {
		if ( function_exists( 'wcf_is_checkout_page' ) && wcf_is_checkout_page() ) {
			return true;
		}
		if ( function_exists( 'wfacp_is_checkout_page' ) && wfacp_is_checkout_page() ) {
			return true;
		}
		if ( is_singular( 'cartflows_step' ) ) {
			$step_type = get_post_meta( get_the_ID(), 'wcf-step-type', true );
			if ( 'checkout' === $step_type ) {
				return true;
			}
		}
		return false;
	}

	/** Detect FunnelKit checkout pages. */
	private static function is_funnelkit_checkout() {
		if ( function_exists( 'wffn_is_checkout' ) && wffn_is_checkout() ) {
			return true;
		}
		if ( class_exists( 'WFFN_Core' ) ) {
			$core = WFFN_Core::get_instance();
			if ( $core && method_exists( $core, 'get_checkout' ) ) {
				$checkout = $core->get_checkout();
				if ( $checkout && method_exists( $checkout, 'is_checkout_page' ) && $checkout->is_checkout_page() ) {
					return true;
				}
			}
		}
		if ( function_exists( 'wfocu_is_checkout_page' ) && wfocu_is_checkout_page() ) {
			return true;
		}
		return false;
	}

	/** Generic fallback: check if the page has a WooCommerce checkout shortcode or block. */
	private static function page_has_checkout_content() {
		global $post;
		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}
		if ( has_shortcode( $post->post_content, 'woocommerce_checkout' ) ) {
			return true;
		}
		if ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $post ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Return a string identifier for the active checkout context.
	 *
	 * @return string 'cartflows' | 'funnelkit' | 'block' | 'classic' | 'unknown'
	 */
	public static function get_checkout_type() {
		if ( function_exists( 'wcf_is_checkout_page' ) && wcf_is_checkout_page() ) {
			return 'cartflows';
		}
		if ( function_exists( 'wfacp_is_checkout_page' ) && wfacp_is_checkout_page() ) {
			return 'cartflows';
		}
		if ( is_singular( 'cartflows_step' ) && 'checkout' === get_post_meta( get_the_ID(), 'wcf-step-type', true ) ) {
			return 'cartflows';
		}
		if ( function_exists( 'wffn_is_checkout' ) && wffn_is_checkout() ) {
			return 'funnelkit';
		}

		// Check current post for WC checkout block.
		global $post;
		if ( $post && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $post ) ) {
			return 'block';
		}

		// WC 8.5+ utility — handles WC 9.x "compatibility mode" where the checkout
		// page still has the shortcode but WC renders the block checkout by default.
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' )
			&& method_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils', 'is_checkout_block_default' )
			&& \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default() ) {
			return 'block';
		}

		// Directly inspect the WC checkout page (handles cases where $post is
		// not the checkout page itself, e.g. embedded via shortcode on another page).
		if ( function_exists( 'wc_get_page_id' ) && function_exists( 'has_block' ) ) {
			$checkout_page_id = wc_get_page_id( 'checkout' );
			if ( $checkout_page_id > 0 ) {
				$checkout_page = get_post( $checkout_page_id );
				if ( $checkout_page && has_block( 'woocommerce/checkout', $checkout_page ) ) {
					return 'block';
				}
			}
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return 'classic';
		}
		return 'unknown';
	}

	// ------------------------------------------------------------------
	// Single Coupon Enforcement (server-side)
	// ------------------------------------------------------------------

	/**
	 * Boot the single-coupon limiter if enabled.
	 */
	public static function maybe_boot_single_coupon() {
		if ( 'yes' !== get_option( 'wcdm_single_coupon', 'yes' ) ) {
			return;
		}
		// Before a coupon is applied, remove all existing ones so only the new one stays.
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'remove_existing_before_apply' ), 10, 2 );
	}

	/**
	 * When a new coupon is being validated, clear previously applied coupons first.
	 *
	 * @param  bool       $valid  Whether the coupon is valid.
	 * @param  WC_Coupon  $coupon The coupon being applied.
	 * @return bool
	 */
	public static function remove_existing_before_apply( $valid, $coupon ) {
		static $is_removing = false;
		if ( $is_removing ) {
			return $valid;
		}

		if ( ! $valid ) {
			return $valid;
		}

		if ( ! WC()->cart ) {
			return $valid;
		}

		// Ensure we are actually applying a coupon (not just listing, validating page load, or checking available coupons).
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$trace       = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 );
		$is_applying = false;
		foreach ( $trace as $step ) {
			if ( isset( $step['function'] ) && 'apply_coupon' === $step['function'] ) {
				$is_applying = true;
				break;
			}
		}

		if ( ! $is_applying ) {
			return $valid;
		}

		$applied = WC()->cart->get_applied_coupons();
		if ( empty( $applied ) ) {
			return $valid;
		}

		$new_code = strtolower( $coupon->get_code() );

		$is_removing = true;
		foreach ( $applied as $code ) {
			if ( strtolower( $code ) !== $new_code ) {
				// Remove silently — suppress notices so UX isn't confusing.
				remove_action( 'woocommerce_removed_coupon', array( 'WC_Checkout', 'refresh_checkout' ) );
				WC()->cart->remove_coupon( $code );
			}
		}
		$is_removing = false;

		return $valid;
	}
}

// Boot single-coupon limiter as early as possible.
add_action( 'woocommerce_init', array( 'WCDM_Compat', 'maybe_boot_single_coupon' ) );
