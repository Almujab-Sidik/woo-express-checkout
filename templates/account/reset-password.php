<?php
/**
 * Custom password reset screen displayed on the WooCommerce My Account page.
 *
 * @package WEC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
$error = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
$user  = $key && $login ? check_password_reset_key( $key, $login ) : new WP_Error( 'missing_key' );

get_header( 'shop' );
?>
<main class="wec-reset-password-page">
	<div class="wec-reset-password-card">
		<p class="wec-reset-password-brand"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		<h1><?php esc_html_e( 'Set Your Account Password', 'woo-express-checkout' ); ?></h1>

		<?php if ( is_wp_error( $user ) ) : ?>
			<div class="wec-reset-password-notice wec-reset-password-error">
				<?php esc_html_e( 'This password reset link is invalid or has expired. Please request a new one.', 'woo-express-checkout' ); ?>
			</div>
		<?php else : ?>
			<p class="wec-reset-password-intro">
				<?php esc_html_e( 'Create a password to access your account and manage your orders.', 'woo-express-checkout' ); ?>
			</p>

			<?php if ( 'length' === $error ) : ?>
				<div class="wec-reset-password-notice wec-reset-password-error">
					<?php esc_html_e( 'Please use a password with at least 8 characters.', 'woo-express-checkout' ); ?>
				</div>
			<?php elseif ( 'invalid' === $error ) : ?>
				<div class="wec-reset-password-notice wec-reset-password-error">
					<?php esc_html_e( 'This password reset link is invalid or has expired.', 'woo-express-checkout' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" class="wec-reset-password-form">
				<label for="wec-reset-password"><?php esc_html_e( 'New password', 'woo-express-checkout' ); ?></label>
				<input type="password" id="wec-reset-password" name="password" minlength="8" autocomplete="new-password" required>
				<p class="wec-reset-password-help"><?php esc_html_e( 'Use at least 8 characters. A stronger password is recommended.', 'woo-express-checkout' ); ?></p>
				<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
				<input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>">
				<?php wp_nonce_field( 'wec_reset_password', 'wec_reset_password_nonce' ); ?>
				<button type="submit"><?php esc_html_e( 'Save Password', 'woo-express-checkout' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer( 'shop' );
