<?php
/**
 * F4 — Auto Account Creation + Email Set Password.
 *
 * New customer accounts are created and the password setup email is sent only
 * after the order reaches Processing or Completed. Accounts are not created
 * when the checkout form is submitted.
 *
 * @package WEC
 */

namespace WEC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Auto_Account {

	const META_FLAG = '_wec_account_processed';

	public function __construct() {
		// Run after payment processing begins or the order is completed.
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_process_order' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_process_order' ) );
	}

	public function maybe_process_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$this->process_order( $order );
	}

	private function process_order( $order ) {
		$order_id = $order->get_id();

		// An order may transition from Processing to Completed; process it once.
		if ( $order->get_meta( self::META_FLAG ) ) {
			return;
		}
		$order->update_meta_data( self::META_FLAG, '1' );
		$order->save();

		// Use the order customer ID because status hooks may run from an admin
		// action or gateway webhook rather than the original checkout session.
		if ( $order->get_customer_id() ) {
			return;
		}

		$email = $order->get_billing_email();
		if ( ! is_email( $email ) ) {
			return;
		}

		// Link the order to an existing account without sending a password email.
		$existing_id = email_exists( $email );
		if ( $existing_id ) {
			$order->set_customer_id( $existing_id );
			$order->save();
			wc_update_new_customer_past_orders( $existing_id );
			return;
		}

		$password = wp_generate_password( 24, true, true );

		// Suppress WooCommerce's default new-account email; the custom email is sent below.
		add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );

		try {
			$user_id = wc_create_new_customer( $email, $email, $password );
		} catch ( \Exception $e ) {
			remove_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );
			$this->log( sprintf( 'Order #%1$d: unable to create customer — %2$s', $order_id, $e->getMessage() ) );
			return;
		}

		remove_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );

		if ( is_wp_error( $user_id ) ) {
			$this->log( sprintf( 'Order #%1$d: unable to create customer — %2$s', $order_id, $user_id->get_error_message() ) );
			return;
		}

		$order->set_customer_id( $user_id );
		$order->save();

		$this->send_set_password_email( $user_id, $order_id );
	}

	private function send_set_password_email( $user_id, $order_id ) {
		$emails = WC()->mailer()->get_emails();

		foreach ( $emails as $email ) {
			if ( $email instanceof Emails\Set_Password ) {
				$email->trigger( $user_id, $order_id );
				return;
			}
		}

		// Fallback in case the email class is not registered with the mailer yet.
		$email = new Emails\Set_Password();
		$email->trigger( $user_id, $order_id );
	}

	private function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'woo-express-checkout' ) );
		}
	}
}
