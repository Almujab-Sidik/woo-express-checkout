<?php
/**
 * Plain-text email template for setting an account password.
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

printf( esc_html__( 'Hello %s,', 'woo-express-checkout' ), esc_html( $user->display_name ) );
echo "\n\n";

esc_html_e( 'Thank you for shopping with us. An account was created automatically during checkout so you can view your order history and complete future purchases faster.', 'woo-express-checkout' );
echo "\n\n";

esc_html_e( 'Please set a password to access your account using the following link:', 'woo-express-checkout' );
echo "\n\n";

echo esc_url( $reset_url ) . "\n\n";

esc_html_e( 'The link above expires in 24 hours.', 'woo-express-checkout' );
echo "\n\n";

esc_html_e( 'If you did not place this order, you can safely ignore this email.', 'woo-express-checkout' );
echo "\n";

echo "\n----------------------------------------\n";
echo esc_html( wp_specialchars_decode( get_option( 'woocommerce_email_footer_text' ), ENT_QUOTES ) );
