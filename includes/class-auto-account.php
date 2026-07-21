<?php
/**
 * F4 — Auto Account Creation + Email Set Password.
 *
 * Akun pelanggan baru (kalau email belum terdaftar) baru dibuat, dan email
 * "Buat Password" baru dikirim, setelah order berstatus "Processing"
 * (pembayaran diterima) atau "Completed" — BUKAN langsung saat checkout
 * disubmit. Ini disengaja: supaya pelanggan wajib membayar dulu sebelum
 * dapat akun/akses (link set-password), bukan sekadar checkout tanpa bayar.
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
		// Bukan woocommerce_checkout_order_processed — itu fire saat submit
		// checkout, sebelum pembayaran terkonfirmasi.
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

		// Idempotency: order bisa lewat "processing" lalu "completed" — jangan proses dua kali.
		if ( $order->get_meta( self::META_FLAG ) ) {
			return;
		}
		$order->update_meta_data( self::META_FLAG, '1' );
		$order->save();

		// Pakai get_customer_id(), bukan is_user_logged_in(): hook status ini
		// bisa jalan dari konteks lain (admin ubah status manual, webhook
		// gateway), jadi "siapa yang login sekarang" bukan lagi "siapa checkout".
		if ( $order->get_customer_id() ) {
			return;
		}

		$email = $order->get_billing_email();
		if ( ! is_email( $email ) ) {
			return;
		}

		// Email sudah terdaftar → link ke akun existing, tanpa email set-password.
		$existing_id = email_exists( $email );
		if ( $existing_id ) {
			$order->set_customer_id( $existing_id );
			$order->save();
			wc_update_new_customer_past_orders( $existing_id );
			return;
		}

		$password = wp_generate_password( 24, true, true );

		// Suppress email "new account" bawaan WC — kita kirim email custom.
		add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );

		try {
			$user_id = wc_create_new_customer( $email, $email, $password );
		} catch ( \Exception $e ) {
			remove_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );
			$this->log( sprintf( 'Order #%1$d: gagal membuat user — %2$s', $order_id, $e->getMessage() ) );
			return; // Order tetap tercatat sebagai guest.
		}

		remove_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );

		if ( is_wp_error( $user_id ) ) {
			$this->log( sprintf( 'Order #%1$d: gagal membuat user — %2$s', $order_id, $user_id->get_error_message() ) );
			return; // Order tetap tercatat sebagai guest.
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

		// Fallback kalau class email belum sempat terdaftar ke mailer WC.
		$email = new Emails\Set_Password();
		$email->trigger( $user_id, $order_id );
	}

	private function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'woo-express-checkout' ) );
		}
	}
}
