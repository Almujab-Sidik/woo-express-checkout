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

		// Use WordPress's native password reset flow instead of a custom token.
		$reset_url = add_query_arg(
			array(
				'action' => 'rp',
				'key'    => $key,
				'login'  => rawurlencode( $user->user_login ),
			),
			wp_login_url()
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
