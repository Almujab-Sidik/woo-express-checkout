<?php
/**
 * Custom WooCommerce email containing a password setup link.
 *
	 * Sent to new customers who checked out without logging in so they can set
	 * their account password through the native WordPress reset flow.
 *
 * @package WEC\Emails
 */

namespace WEC\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Set_Password extends \WC_Email {

	public $user;
	public $reset_url;
	public $order_id;

	public function __construct() {
		$this->id             = 'wec_set_password';
		$this->title          = __( 'Express Checkout: Set Password', 'woo-express-checkout' );
		$this->description    = __( 'Sent to new customers who checked out without logging in and need to set an account password.', 'woo-express-checkout' );
		$this->customer_email = true;

		$this->subject = __( 'Set your account password at {site_title}', 'woo-express-checkout' );
		$this->heading = __( 'Set Your Account Password', 'woo-express-checkout' );

		$this->template_html  = 'emails/wec-set-password.php';
		$this->template_plain = 'emails/plain/wec-set-password.php';
		$this->template_base  = WEC_PATH . 'templates/';

		add_action( 'login_init', array( $this, 'redirect_native_reset_screen' ) );
		add_filter( 'template_include', array( $this, 'load_reset_template' ), 99 );
		add_action( 'template_redirect', array( $this, 'process_reset_request' ), 1 );

		parent::__construct();
	}

	public function trigger( $user_id, $order_id = 0 ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->restore_locale();
			return;
		}

		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			$this->restore_locale();
			return;
		}

		// Keep WordPress's secure reset key while presenting the form on My Account.
		$account_url = function_exists( 'wc_get_page_permalink' )
			? wc_get_page_permalink( 'myaccount' )
			: home_url( '/' );
		$reset_url = add_query_arg(
			array(
				'action' => 'rp',
				'key'    => $key,
				'login'  => rawurlencode( $user->user_login ),
			),
			$account_url
		);

		$this->object    = $user;
		$this->reset_url = $reset_url;
		$this->order_id  = $order_id;
		$this->recipient = $user->user_email;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function redirect_native_reset_screen() {
		if ( empty( $_GET['action'] ) || 'rp' !== sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			return;
		}

		$account_url = function_exists( 'wc_get_page_permalink' )
			? wc_get_page_permalink( 'myaccount' )
			: home_url( '/' );
		$args = array(
			'action' => 'rp',
			'key'    => isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '',
			'login'  => isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '',
		);

		wp_safe_redirect( add_query_arg( $args, $account_url ) );
		exit;
	}

	public function load_reset_template( $template ) {
		if ( ! $this->is_account_reset_request() ) {
			return $template;
		}

		$custom_template = WEC_PATH . 'templates/account/reset-password.php';
		return file_exists( $custom_template ) ? $custom_template : $template;
	}

	public function process_reset_request() {
		if ( ! $this->is_account_reset_request() || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( ! isset( $_POST['wec_reset_password_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wec_reset_password_nonce'] ) ), 'wec_reset_password' ) ) {
			wp_die( esc_html__( 'The password reset request could not be verified.', 'woo-express-checkout' ) );
		}

		$key      = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$user     = check_password_reset_key( $key, $login );

		if ( is_wp_error( $user ) ) {
			$this->redirect_with_reset_error( $key, $login, 'invalid' );
		}

		if ( strlen( $password ) < 8 ) {
			$this->redirect_with_reset_error( $key, $login, 'length' );
		}

		reset_password( $user, $password );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		$account_url = function_exists( 'wc_get_page_permalink' )
			? wc_get_page_permalink( 'myaccount' )
			: home_url( '/' );
		wp_safe_redirect( $account_url );
		exit;
	}

	private function is_account_reset_request() {
		if ( empty( $_GET['action'] ) || 'rp' !== sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			return false;
		}

		$account_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'myaccount' ) : 0;
		return $account_page_id > 0 && is_page( $account_page_id );
	}

	private function redirect_with_reset_error( $key, $login, $error ) {
		$account_url = function_exists( 'wc_get_page_permalink' )
			? wc_get_page_permalink( 'myaccount' )
			: home_url( '/' );
		$url = add_query_arg(
			array(
				'action' => 'rp',
				'key'    => $key,
				'login'  => $login,
				'error'  => $error,
			),
			$account_url
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'email_heading' => $this->get_heading(),
				'user'          => $this->object,
				'reset_url'     => $this->reset_url,
				'order_id'      => $this->order_id,
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'woo-express-checkout',
			$this->template_base
		);
	}

	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'email_heading' => $this->get_heading(),
				'user'          => $this->object,
				'reset_url'     => $this->reset_url,
				'order_id'      => $this->order_id,
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'woo-express-checkout',
			$this->template_base
		);
	}
}
