<?php
/**
 * F2 — Guest Checkout Tanpa Form Login & Field Minimal.
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
	 * Signup dinonaktifkan karena akun dibuat otomatis oleh F4 (Auto_Account).
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
	 * Sederhanakan field billing menjadi: Email, Nama Lengkap, No. HP.
	 * Field alamat disembunyikan ketika cart tidak butuh shipping (produk digital).
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
			// Sudah login → email ter-prefill dari akun, jangan biarkan diubah.
			if ( is_user_logged_in() ) {
				$fields['billing']['billing_email']['custom_attributes'] = array( 'readonly' => 'readonly' );
			}
		}

		// billing_first_name dipakai sebagai satu-satunya field nama (nama lengkap).
		if ( isset( $fields['billing']['billing_first_name'] ) ) {
			$fields['billing']['billing_first_name']['label']       = __( 'Nama Lengkap', 'woo-express-checkout' );
			$fields['billing']['billing_first_name']['placeholder'] = __( 'Nama Lengkap', 'woo-express-checkout' );
			$fields['billing']['billing_first_name']['priority']    = 20;
			$fields['billing']['billing_first_name']['class']       = array( 'form-row-wide' );
		}

		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['label']       = __( 'No. HP', 'woo-express-checkout' );
			$fields['billing']['billing_phone']['placeholder'] = __( '08xxx atau +62xxx', 'woo-express-checkout' );
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
	 * Default value untuk field alamat yang disembunyikan, supaya order tetap
	 * valid meski field-nya tidak ditampilkan (mencegah error validasi).
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
			'billing_address_1' => __( 'Produk Digital', 'woo-express-checkout' ),
			'billing_last_name' => '',
		);

		return isset( $defaults[ $input ] ) ? $defaults[ $input ] : $value;
	}

	/**
	 * Format: 08xxx atau +62xxx/62xxx, minimal 9 digit.
	 *
	 * @param array    $data   Data checkout.
	 * @param \WP_Error $errors Error handler.
	 */
	public function validate_phone( $data, $errors ) {
		$phone = isset( $_POST['billing_phone'] ) ? wc_clean( wp_unslash( $_POST['billing_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $this->is_valid_phone( $phone ) ) {
			$errors->add(
				'validation',
				__( 'Nomor HP tidak valid. Gunakan format 08xxx atau +62xxx (min. 9 digit).', 'woo-express-checkout' )
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
