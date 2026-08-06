<?php
/**
 * Uninstall — remove plugin options.
 *
 * Existing users and orders are intentionally preserved.
 *
 * @package WEC
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wec_version' );
delete_option( 'wec_block_notice_dismissed' );
