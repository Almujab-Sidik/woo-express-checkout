<?php
/**
 * Email template (Plain text) — Buat Password.
 *
 * @package WEC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Variabel tersedia:
 *
 * @var string     $email_heading
 * @var \WP_User   $user
 * @var string     $reset_url
 * @var int        $order_id
 * @var \WC_Email  $email
 */

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf( esc_html__( 'Halo %s,', 'woo-express-checkout' ), esc_html( $user->display_name ) );
echo "\n\n";

esc_html_e( 'Terima kasih telah berbelanja di toko kami. Saat checkout, akun Anda telah dibuat secara otomatis sehingga Anda dapat melihat riwayat pesanan dan melakukan pembelian berikutnya lebih cepat.', 'woo-express-checkout' );
echo "\n\n";

esc_html_e( 'Silakan buat password untuk mengakses akun Anda melalui link berikut:', 'woo-express-checkout' );
echo "\n\n";

echo esc_url( $reset_url ) . "\n\n";

esc_html_e( 'Link di atas akan kadaluarsa dalam 24 jam.', 'woo-express-checkout' );
echo "\n\n";

esc_html_e( 'Jika Anda tidak merasa melakukan pembelian ini, abaikan email ini.', 'woo-express-checkout' );
echo "\n";

echo "\n----------------------------------------\n";
echo esc_html( wp_specialchars_decode( get_option( 'woocommerce_email_footer_text' ), ENT_QUOTES ) );
