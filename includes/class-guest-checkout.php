<?php
/**
 * Guest checkout with a minimal customer information form.
 *
 * @package WEC
 */

namespace WEC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Guest_Checkout {

	public function __construct() {
		if ( 'yes' !== get_option( 'wec_checkout_guest_enabled', 'yes' ) ) {
			return;
		}

		add_filter( 'pre_option_woocommerce_enable_guest_checkout', array( $this, 'force_guest_checkout' ) );
		add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', array( $this, 'disable_signup' ) );
		add_filter( 'pre_option_woocommerce_enable_checkout_login_reminder', array( $this, 'disable_login_reminder' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'simplify_fields' ), 20 );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'default_hidden_values' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_phone' ), 10, 2 );
	}

	/**
	 * @return string
	 */
	public function force_guest_checkout() {
		return apply_filters( 'wec_force_guest_checkout', 'yes' );
	}

	/**
	 * Account registration is handled automatically after successful payment.
	 *
	 * @return string
	 */
	public function disable_signup() {
		return apply_filters( 'wec_disable_signup_on_checkout', 'no' );
	}

	/**
	 * @return string
	 */
	public function disable_login_reminder() {
		return 'no';
	}

	/**
	 * Reduce billing fields to email, full name, and phone number.
	 * Address fields are hidden when the cart does not require shipping.
	 *
	 * @param array $fields Field checkout.
	 * @return array
	 */
	public function simplify_fields( $fields ) {
		if ( ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
			return $fields;
		}

		$cart           = function_exists( 'WC' ) ? WC()->cart : null;
		$needs_shipping = $cart ? $cart->needs_shipping() : false;

		unset( $fields['billing']['billing_company'], $fields['billing']['billing_address_2'] );

		if ( ! $needs_shipping ) {
			unset(
				$fields['billing']['billing_country'],
				$fields['billing']['billing_address_1'],
				$fields['billing']['billing_city'],
				$fields['billing']['billing_state'],
				$fields['billing']['billing_postcode'],
				$fields['billing']['billing_last_name']
			);
		}

		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['priority'] = 10;
			$fields['billing']['billing_email']['class']    = array( 'form-row-wide' );
			// Keep the account email read-only for logged-in customers.
			if ( is_user_logged_in() ) {
				$fields['billing']['billing_email']['custom_attributes'] = array( 'readonly' => 'readonly' );
			}
		}

		// Use billing_first_name as the full-name field.
		if ( isset( $fields['billing']['billing_first_name'] ) ) {
			$fields['billing']['billing_first_name']['label']       = __( 'Full Name', 'woo-express-checkout' );
			$fields['billing']['billing_first_name']['placeholder'] = __( 'Full Name', 'woo-express-checkout' );
			$fields['billing']['billing_first_name']['priority']    = 20;
			$fields['billing']['billing_first_name']['class']       = array( 'form-row-wide' );
		}

		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['label']       = __( 'Phone Number', 'woo-express-checkout' );
			$fields['billing']['billing_phone']['placeholder'] = __( '08xxx or +62xxx', 'woo-express-checkout' );
			$fields['billing']['billing_phone']['priority']    = 30;
			$fields['billing']['billing_phone']['class']       = array( 'form-row-wide' );
			$fields['billing']['billing_phone']['required']    = true;
		}

		if ( isset( $fields['order']['order_comments'] ) ) {
			unset( $fields['order']['order_comments'] );
		}

		return $fields;
	}

	/**
	 * Provide defaults for hidden address fields so orders remain valid without
	 * triggering validation errors.
	 *
	 * @param mixed  $value Nilai saat ini.
	 * @param string $input Nama field.
	 * @return mixed
	 */
	public function default_hidden_values( $value, $input ) {
		if ( null !== $value ) {
			return $value;
		}

		$defaults = array(
			'billing_country'   => function_exists( 'WC' ) ? WC()->countries->get_base_country() : 'ID',
			'billing_state'     => '',
			'billing_city'      => '',
			'billing_postcode'  => '',
			'billing_address_1' => __( 'Digital Product', 'woo-express-checkout' ),
			'billing_last_name' => '',
		);

		return isset( $defaults[ $input ] ) ? $defaults[ $input ] : $value;
	}

	/**
	 * Accepted formats: 08xxx, +62xxx, or 62xxx with at least 9 digits.
	 *
	 * @param array    $data   Data checkout.
	 * @param \WP_Error $errors Error handler.
	 */
	public function validate_phone( $data, $errors ) {
		$phone = isset( $_POST['billing_phone'] ) ? wc_clean( wp_unslash( $_POST['billing_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $this->is_valid_phone( $phone ) ) {
			$errors->add(
				'validation',
				__( 'Please enter a valid phone number using the format 08xxx or +62xxx (at least 9 digits).', 'woo-express-checkout' )
			);
		}
	}

	public function is_valid_phone( $phone ) {
		$phone = preg_replace( '/[\s\-\(\)]+/', '', $phone );
		if ( '' === $phone ) {
			return false;
		}
		return (bool) preg_match( '/^(\+62|62|0)[0-9]{8,14}$/', $phone );
	}
}
